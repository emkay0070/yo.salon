# Model/Schema Relationship Mismatch Audit

## Scope
Read-only audit of 5 models identified in previous reports for Salon vs Provider terminology mismatches.

## Findings

### 1. PaymentAccount
**File**: `app/Models/PaymentAccount.php`

**Issues**:
- Line 31: `'salon_id'` in `$fillable` array
- Line 32: `'specialist_id'` in `$fillable` array
- Line 33: `'provider'` in `$fillable` (appears to be a string field, not FK)
- Line 81: `provider()` relationship uses `belongsTo(Provider::class)` without explicit foreign key (defaults to `provider_id`)

**Mismatch**: Fillable uses `salon_id` but relationship expects `provider_id` by Laravel convention.

**Impact**: High - PaymentAccount is financial-critical. Relationship may fail or query wrong column.

---

### 2. ServiceCategory
**File**: `app/Models/ServiceCategory.php`

**Issues**:
- Line 15: `'salon_id'` in `$fillable` array
- Line 57: Static method `getOrdered(string $salonId)` uses `where('salon_id', $salonId)`
- No `provider()` relationship method defined

**Mismatch**: Model uses `salon_id` throughout. No Provider-native relationship defined.

**Impact**: Medium - ServiceCategory is used for service organization. Queries will fail if schema uses `provider_id`.

---

### 3. CustomerTimeline
**File**: `app/Models/CustomerTimeline.php`

**Issues**:
- Line 15: `'salon_id'` in `$fillable` array
- Line 22: `'provider_id'` in `$fillable` array (both columns present!)
- Line 81: `provider()` relationship uses `belongsTo(Provider::class, 'salon_id')`

**Mismatch**: Model has both `salon_id` and `provider_id` columns, but relationship explicitly uses `salon_id`.

**Impact**: High - Duplicate columns suggest incomplete migration. Relationship points to wrong FK.

---

### 4. BrandExperience
**File**: `app/Models/BrandExperience.php`

**Issues**:
- Line 14: `'salon_id'` in `$fillable` array
- Line 38: `provider()` relationship uses `belongsTo(Provider::class, 'salon_id')`

**Mismatch**: Model uses `salon_id` instead of `provider_id`.

**Impact**: Medium - BrandExperience is theming-related. Relationship may fail.

---

### 5. CustomerActivity
**File**: `app/Models/CustomerActivity.php`

**Issues**:
- Line 15: `'salon_id'` in `$fillable` array
- Line 37: `provider()` relationship uses `belongsTo(Provider::class, 'salon_id')`

**Mismatch**: Model uses `salon_id` instead of `provider_id`.

**Impact**: Medium - CustomerActivity is notification/activity tracking. Relationship may fail.

---

## Summary

| Model | salon_id in fillable | provider_id in fillable | Relationship FK | Severity |
|-------|---------------------|------------------------|-----------------|----------|
| PaymentAccount | Yes | No | provider_id (implicit) | High |
| ServiceCategory | Yes | No | None defined | Medium |
| CustomerTimeline | Yes | Yes | salon_id (explicit) | High |
| BrandExperience | Yes | No | salon_id (explicit) | Medium |
| CustomerActivity | Yes | No | salon_id (explicit) | Medium |

## Blockers

1. **Schema verification required**: Cannot determine if database schema uses `salon_id` or `provider_id` without checking migrations.
2. **CustomerTimeline duplicate columns**: Has both `salon_id` and `provider_id` - suggests incomplete migration.
3. **PaymentAccount ambiguous**: Has `provider` field (string?) and `salon_id` FK - unclear intended design.

## Recommendations

**Do not fix without schema verification**. Each model requires:
1. Check corresponding migration file to confirm actual column names
2. Verify if data migration has already occurred
3. Determine if `salon_id` columns should be renamed to `provider_id` or if relationships should use `salon_id`
4. For CustomerTimeline specifically: resolve duplicate column situation

## Next Steps

1. Audit migration files for these 5 models
2. Check database schema to confirm actual column names
3. Determine migration strategy (rename columns vs update relationships)
4. Create separate fix plan for each model based on findings
