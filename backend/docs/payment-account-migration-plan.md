# PaymentAccount Migration Plan

## Objective
Migrate from PaymentMethod-only system to PaymentAccount + PaymentMethod dual-abstraction system.

## Current State
- `PaymentMethod` conflates "how customer pays" with "where money goes"
- No abstraction for payment account ownership
- Cannot distinguish between salon-owned vs Yo.Salon-facilitated accounts

## Target State
- `PaymentMethod` = HOW customer pays (MTN MoMo, Visa, Cash)
- `PaymentAccount` = WHOSE account receives money (Salon's Flutterwave, Yo.Salon routing)
- Clear separation of payment routing from payment method

## Migration Strategy: Phased Dual-Write

### Phase 1: Infrastructure (Current)
- ✅ Create PaymentAccount model
- ✅ Create payment_accounts table migration
- ⏳ Run migration (pending)

### Phase 2: Backward Compatibility Layer
Add `payment_account_id` to PaymentMethod table (nullable):
```php
$table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
```

This allows existing PaymentMethods to exist without a PaymentAccount initially.

### Phase 3: Data Migration
Create migration to backfill PaymentAccounts from existing PaymentMethods:

```php
// For each salon's PaymentMethods, create a corresponding PaymentAccount
$paymentMethods = PaymentMethod::all();
foreach ($paymentMethods as $pm) {
    PaymentAccount::firstOrCreate([
        'salon_id' => $pm->salon_id,
        'provider' => $pm->provider,
        'account_type' => 'merchant', // Default to merchant for existing
        'external_account_id' => $pm->account_id,
        'account_name' => $pm->display_name,
        'is_active' => $pm->is_active,
        'verification_status' => 'verified', // Assume verified for existing
        'is_default' => true, // First account becomes default
    ]);
}
```

### Phase 4: Link PaymentMethods to PaymentAccounts
Update PaymentMethod records to reference their corresponding PaymentAccount:
```php
foreach ($paymentMethods as $pm) {
    $account = PaymentAccount::where('salon_id', $pm->salon_id)
        ->where('provider', $pm->provider)
        ->first();
    if ($account) {
        $pm->update(['payment_account_id' => $account->id]);
    }
}
```

### Phase 5: Update Transaction Model
Add `payment_account_id` to Transaction table (nullable):
```php
$table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
```

### Phase 6: Update Payment Services
Modify payment services to:
1. Determine PaymentAccount from PaymentMethod
2. Pass PaymentAccount to payment providers
3. Record PaymentAccount on Transaction

### Phase 7: Make payment_account_id Required
Once all flows use PaymentAccount:
- Remove nullable from payment_account_id in PaymentMethod
- Remove nullable from payment_account_id in Transaction
- Add validation to ensure PaymentMethod has PaymentAccount

### Phase 8: Cleanup (Future)
- Consider deprecating PaymentMethod fields that duplicate PaymentAccount
- Add onboarding flow for new PaymentAccount creation
- Add UI for managing PaymentAccounts

## Key Considerations

### 1. Zero Downtime
- All changes must be backward compatible
- Existing PaymentMethods continue to work during migration
- PaymentAccount is optional initially

### 2. Data Integrity
- Ensure foreign key relationships are maintained
- Handle cases where PaymentMethod has no corresponding PaymentAccount
- Default to merchant account type for existing data

### 3. Business Logic
- Payment routing logic should be centralized
- Fee calculation remains in FeeEngine (unchanged)
- Settlement logic will eventually use PaymentAccount

### 4. Testing
- Test migration on staging environment first
- Verify existing payment flows still work
- Test new PaymentAccount creation flows

## Rollback Plan
If migration fails:
1. Drop payment_account_id columns (with cascade)
2. Drop payment_accounts table
3. Revert service changes
4. No data loss (PaymentMethod remains intact)

## Success Criteria
- ✅ PaymentAccount model and table exist
- ✅ Existing PaymentMethods can operate without PaymentAccount
- ✅ New PaymentMethods can be created with PaymentAccount
- ✅ Payment routing can be determined from PaymentAccount
- ✅ Transaction records can reference PaymentAccount
- ✅ All existing payment flows continue to work
