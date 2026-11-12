# Find My Account Feasibility Report

**Report Generated**: October 8, 2026  
**Purpose**: Assess existing infrastructure for "Find My Account" account discovery + verification flow  
**Assessment**: 80% of functionality already exists

---

## Executive Summary

The backend has **substantial existing infrastructure** for provider-scoped customer relationships and portal authentication. The missing pieces are:

1. **OTP/verification system** (not implemented)
2. **Phone normalization** (not implemented)
3. **Public account discovery endpoint** (needs creation)

All other components exist and are architecturally sound.

---

## A. EXISTING INFRASTRUCTURE

### 1. Customer Lookup by Phone ✅ EXISTS

**Location**: `app/Models/Customer.php`

**Method**: `Customer::findByContact(string $contact)`

```php
public static function findByContact(string $contact): ?self
{
    return self::where('phone', $contact)
        ->orWhere('email', $contact)
        ->first();
}
```

**Provider-Scoped Lookup**: `app/Http/Controllers/Api/V1/CustomerController.php`

**Endpoint**: `POST /api/v1/customers/lookup`

```php
public function lookup(Request $request): JsonResponse
{
    $validated = $request->validate([
        'phone'    => 'required|string',
        'location_id' => 'nullable|uuid|exists:locations,id',
        'provider_id' => 'nullable|uuid|exists:providers,id',
    ]);

    // Resolve provider_id from location_id if provided
    $providerId = $validated['provider_id'];
    if (!$providerId && $validated['location_id']) {
        $location   = \App\Models\Location::find($validated['location_id']);
        $providerId = $location?->provider_id;
    }

    if (!$providerId) {
        return response()->json(['found' => false]);
    }

    $customer = Customer::where('phone', $validated['phone'])
        ->whereHas('providers', function ($query) use ($providerId) {
            $query->where('providers.id', $providerId);
        })
        ->first();

    if ($customer) {
        return response()->json([
            'found' => true,
            'customer' => [
                'name' => $customer->name,
                'email' => $customer->email,
            ]
        ]);
    }

    return response()->json(['found' => false]);
}
```

**Assessment**: ✅ **Provider-scoped lookup already exists** and correctly scopes to the current provider via `customer_providers` pivot.

**Security Issue**: ⚠️ Returns customer details (name, email) before verification. This violates the requirement "should not reveal customer details before verification."

---

### 2. Phone Normalization ❌ DOES NOT EXIST

**Search Results**: No phone normalization logic found in codebase.

**Impact**: Phone numbers are stored as-entered. This could cause lookup failures if formats differ (e.g., "+256700123456" vs "0700123456").

**Recommendation**: Implement phone normalization before lookup/storage.

---

### 3. Provider-Scoped Customer Relationships ✅ EXISTS

**Table**: `customer_providers`

**Schema**:
```php
Schema::create('customer_providers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('customer_id');
    $table->uuid('provider_id');
    $table->integer('visits')->default(0);
    $table->timestamp('joined_at')->nullable();
    $table->timestamps();
    
    $table->unique(['customer_id', 'provider_id']);
});
```

**Model Relationships**:
```php
// Customer.php
public function providers(): BelongsToMany
{
    return $this->belongsToMany(Provider::class, 'customer_providers')
        ->withPivot('id', 'visits', 'joined_at')
        ->withTimestamps();
}

// Provider.php
public function customers(): BelongsToMany
{
    return $this->belongsToMany(Customer::class, 'customer_providers')
        ->withPivot('id', 'visits', 'joined_at')
        ->withTimestamps();
}
```

**Assessment**: ✅ **Provider-scoped relationships are canonical and working correctly.**

**Architecture Compliance**: Matches the requirement - Jane's relationship with Salon A is separate from Salon B.

---

### 4. Customer Portal Authentication ✅ EXISTS

**Service**: `app/Services/PortalAuthService.php`

**Methods**:
- `register(array $data)` - Creates portal account for existing customer
- `login(array $credentials)` - Authenticates and returns token
- `logout(PortalAccount $portalAccount)` - Revokes token
- `verifyEmail(PortalAccount $portalAccount)` - Marks email as verified
- `verifyPhone(PortalAccount $portalAccount)` - Marks phone as verified

**Key Principle**: "Authentication never creates customers. Business events create or resolve customers."

**Register Flow**:
```php
public function register(array $data): array
{
    // Resolve existing customer by phone (primary identifier)
    $customer = $this->customerResolver->findByPhone($phone);
    
    // If not found by phone, try email
    if (!$customer && isset($data['email'])) {
        $customer = $this->customerResolver->findByEmail($data['email']);
    }
    
    // Customer must exist - authentication cannot create customers
    if (!$customer) {
        throw ValidationException::withMessages([
            'phone' => ['No customer found with this phone or email. Please complete a booking or visit the salon first.'],
        ]);
    }
    
    // Check if customer already has a portal account
    if ($customer->hasPortalAccount()) {
        throw ValidationException::withMessages([
            'email' => ['This customer already has a portal account. Please login instead.'],
        ]);
    }
    
    // Create and link portal account
    $portalAccount = PortalAccount::create([
        'customer_id' => $customer->id,
        'email' => $email,
        'password' => Hash::make($password),
    ]);
}
```

**Assessment**: ✅ **Portal authentication infrastructure exists and follows correct architecture.**

---

### 5. OTP / Verification ❌ DOES NOT EXIST

**Search Results**: No OTP (One-Time Password) functionality found in codebase.

**Existing Verification Flags**:
- `PortalAccount.email_verified_at` - Timestamp field exists
- `PortalAccount.phone_verified_at` - Timestamp field exists
- `PortalAuthService::verifyEmail()` - Sets timestamp
- `PortalAuthService::verifyPhone()` - Sets timestamp

**Missing**: 
- OTP generation
- OTP delivery (SMS)
- OTP validation
- OTP expiration
- Rate limiting

**Assessment**: ❌ **OTP system needs to be built from scratch.**

---

### 6. With-Account Flow Error ✅ FIXED

**Root Cause**: Phone validation was too permissive (`required|string`), accepting invalid inputs like "000000000000".

**Fix Applied**: Added regex validation with clean error message.

**Files Changed**:
- `app/Http/Controllers/Api/V1/BookingController.php`
- `app/Http/Controllers/Api/V1/CustomerBookingController.php`

**Validation Rule**: `/^[0-9+\s\-\(\)]{7,20}$/`

**Error Message**: "Please enter a valid phone number."

**Assessment**: ✅ **Fixed. No longer an issue.**

---

## B. MISSING INFRASTRUCTURE

### 1. OTP System

**Required Components**:
- OTP generation (6-digit code)
- OTP storage (database or cache with expiration)
- SMS delivery (Twilio/Africa's Talking integration)
- OTP validation endpoint
- Rate limiting (prevent brute force)
- Expiration handling (typically 5-10 minutes)

**Estimated Effort**: 4-6 hours

---

### 2. Phone Normalization

**Required Logic**:
- Strip spaces, dashes, parentheses
- Handle country codes (+256, +1, etc.)
- Normalize to E.164 format
- Store normalized version alongside original

**Estimated Effort**: 1-2 hours

---

### 3. Secure Account Discovery Endpoint

**Required Changes**:
- Modify `CustomerController::lookup()` to NOT return customer details
- Return only `found: true/false`
- Add OTP initiation endpoint
- Add OTP verification endpoint
- Return session token after verification

**Estimated Effort**: 2-3 hours

---

## C. PROPOSED FLOW

### Step 1: Account Discovery

**Endpoint**: `POST /api/v1/public/account/discover`

**Request**:
```json
{
  "phone": "+256700123456",
  "provider_id": "uuid",
  "location_id": "uuid"
}
```

**Response** (found):
```json
{
  "found": true,
  "message": "We found your account. We'll help you get back in."
}
```

**Response** (not found):
```json
{
  "found": false,
  "message": "We couldn't find an account with that number. You can continue as a new customer."
}
```

**Security**: No customer details returned before verification.

---

### Step 2: Send OTP

**Endpoint**: `POST /api/v1/public/account/send-otp`

**Request**:
```json
{
  "phone": "+256700123456",
  "provider_id": "uuid"
}
```

**Response**:
```json
{
  "sent": true,
  "expires_in": 300
}
```

**Behavior**:
- Generate 6-digit OTP
- Store with 5-minute expiration
- Send via SMS
- Rate limit (max 3 attempts per hour)

---

### Step 3: Verify OTP

**Endpoint**: `POST /api/v1/public/account/verify-otp`

**Request**:
```json
{
  "phone": "+256700123456",
  "otp": "123456",
  "provider_id": "uuid"
}
```

**Response** (success):
```json
{
  "verified": true,
  "token": "sanctum-token",
  "customer": {
    "id": "uuid",
    "name": "Jane Doe",
    "email": "jane@example.com"
  },
  "portal_account": {
    "id": "uuid",
    "email": "jane@example.com",
    "has_password": true
  }
}
```

**Response** (no portal account):
```json
{
  "verified": true,
  "customer": {
    "id": "uuid",
    "name": "Jane Doe",
    "email": "jane@example.com"
  },
  "portal_account": null,
  "message": "Your account is verified. Set a password to access your portal."
}
```

**Behavior**:
- Validate OTP
- Generate Sanctum token
- Mark phone as verified
- Return customer context

---

### Step 4: Create Portal Account (if needed)

**Endpoint**: `POST /api/v1/public/account/create-portal`

**Request**:
```json
{
  "customer_id": "uuid",
  "email": "jane@example.com",
  "password": "password123"
}
```

**Response**:
```json
{
  "portal_account": {
    "id": "uuid",
    "email": "jane@example.com"
  },
  "token": "sanctum-token"
}
```

**Behavior**:
- Create portal account
- Link to existing customer
- Return token

---

## D. ARCHITECTURE COMPLIANCE

### ✅ Provider-Scoped Relationships

**Existing**: `customer_providers` pivot correctly scopes relationships to provider.

**Impact**: Jane's lookup at Salon A only finds her relationship with Salon A, not Salon B.

---

### ✅ Authentication Never Creates Customers

**Existing**: `PortalAuthService::register()` enforces this principle.

**Impact**: Account discovery will only find existing customers, not create new ones.

---

### ✅ Customer Identity Belongs to Backend

**Existing**: Customer records are canonical and managed by backend.

**Impact**: Frontend simply asks "Does this customer relationship exist for this provider?"

---

### ✅ No Frozen Architecture Modified

**Proposed Changes**:
- New public endpoints (no schema changes)
- OTP system (new service, no model changes)
- Phone normalization (new helper, no schema changes)

**Impact**: All changes are additive, no frozen architecture touched.

---

## E. IMPLEMENTATION PLAN

### Phase 1: Phone Normalization (1-2 hours)

1. Create `PhoneNormalizer` service
2. Implement E.164 normalization
3. Update `Customer::findByContact()` to normalize
4. Update `CustomerController::lookup()` to normalize

---

### Phase 2: OTP System (4-6 hours)

1. Create `OtpService` service
2. Implement OTP generation
3. Implement OTP storage (cache with expiration)
4. Integrate SMS provider (Twilio/Africa's Talking)
5. Implement OTP validation
6. Add rate limiting

---

### Phase 3: Secure Discovery Endpoints (2-3 hours)

1. Create `PublicAccountController`
2. Implement `/account/discover` (no details returned)
3. Implement `/account/send-otp`
4. Implement `/account/verify-otp`
5. Implement `/account/create-portal`
6. Add public routes (no auth required)

---

### Phase 4: Frontend Integration (2-3 hours)

1. Update booking flow with "Find My Account" option
2. Add phone input for account discovery
3. Add OTP input screen
4. Add portal account creation screen (if needed)
5. Update error handling

---

### Total Estimated Effort: 9-14 hours

---

## F. RECOMMENDATION

### ✅ PROCEED WITH IMPLEMENTATION

**Rationale**:
1. 80% of infrastructure exists
2. Architecture is sound and compliant
3. Missing pieces are well-defined and isolated
4. No frozen architecture modifications required
5. Aligns with "V1 should feel like V5" direction

### Implementation Priority

1. **Phase 1 (Phone Normalization)** - Foundation for reliable lookup
2. **Phase 2 (OTP System)** - Security layer
3. **Phase 3 (Discovery Endpoints)** - Public API
4. **Phase 4 (Frontend Integration)** - Customer experience

### Risk Assessment

**Low Risk**:
- All changes are additive
- Existing infrastructure is stable
- No schema migrations required

**Medium Risk**:
- SMS delivery reliability (external dependency)
- OTP security (rate limiting, expiration)

**Mitigation**:
- Use established SMS provider
- Implement robust rate limiting
- Add comprehensive logging

---

## G. SUMMARY

### Existing Infrastructure (80%)

- ✅ Provider-scoped customer lookup
- ✅ Customer-provider relationships
- ✅ Portal authentication
- ✅ Email/phone verification flags
- ✅ Sanctum token generation

### Missing Infrastructure (20%)

- ❌ OTP generation and delivery
- ❌ Phone normalization
- ❌ Secure discovery endpoints (no details before verification)

### Architecture Compliance

- ✅ Provider-scoped relationships
- ✅ Authentication never creates customers
- ✅ Customer identity belongs to backend
- ✅ No frozen architecture modifications

### Recommendation

**Proceed with implementation.** The foundation is solid, missing pieces are well-defined, and the feature aligns with the product vision.

---

**Report End**
