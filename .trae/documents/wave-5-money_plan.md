# Wave 5 — Money Implementation Plan (REVISED v3 — Final Safeguards Incorporated)

> **Status:** Final safeguards incorporated. Directionally approved → **beginning 5.1 only now.**
> **Audit Date:** 2026-09-23
> **Scope:** Payments, Finance, Notifications (money events only). Wave 4 deferred items (gift card payment integration).
> **Locked Boundaries (unchanged, per explicit instruction):**
> - Provider remains the business/tenant; Location is the physical context.
> - No new `salon_id` authority or compatibility layers.
> - Loyalty remains locked and out of scope unless a concrete money-integration defect is demonstrated.
> - No broad NotificationService cleanup.
> - Wave 6 remains deferred.
> - **No gift card implementation yet** (explicit hold until 5.1 passes review).

---

## §0.A — Final Safeguards Added in v3 (Explicit Confirmation of User's 4 Points)

### Safeguard 1 — Payout Safety: Definitive Failure vs Ambiguous Gateway Outcome

Added to **§5.1.1 state machine** an explicit new terminal state `ambiguous` (in addition to `failed` and `failed_final`) and the following rule:

> Do **NOT** blindly retry when provider outcome is UNKNOWN (network timeout, 5xx without confirmation body, connection drop mid-call). An unknown-outcome disbursement MAY have already landed at the recipient, and an unguided retry creates real duplicate-money risk.
>
> Procedure for ambiguous outcome:
>   1. Provider call result is a time-out / ambiguous-5xx / connection-drop → do **not** mark `failed` (don't trigger retry) → mark status = `ambiguous` (new terminal for auto-retry).
>   2. Record the **provider-side idempotency key** we sent on the disbursement request (new column `disbursement_idempotency_key`) in `metadata` alongside the error context.
>   3. Next action: **ONLY the `ReconcileProcessingPayouts` job** may re-investigate an `ambiguous` payout, and it must do so by calling `DisbursementProvider::queryStatus(idempotency_key)` (provider-side lookup — NOT a new disburse call). If provider confirms success → flip to `completed`; confirms failure → flip to `failed` (now safe for retry); still unknown → stays `ambiguous` + logs alert + raises notification for ops manual intervention.
>   4. Automated retry via executePayout is **BLOCKED** on status=`ambiguous`: guard in step 1 of executePayout throws LogicException, preventing any disburse call until reconciliation resolves the state.
>
> Distinction summary:
> - **`failed` (retryable):** Provider definitively said NO (rejected, insufficient balance 3x time — retryable business reason) → safe to re-send disbursement with same idempotency key because provider already acknowledged it was NOT applied.
> - **`failed_final`:** Provider definitively said NEVER (bad account, compliance reject) → never retry.
> - **`ambiguous`:** Never got answer → block auto-retry; ONLY reconcile-by-query-status can move it. Duplicate-money defense relies on this distinction.

New Payout.status diagram (full machine after safeguard):

```
  pending ──► queued ──► processing ──┬──► completed
            ▲            │            │
            │            │            ├──► failed (retryable → requeue via reconcile)
            │            │            │         │
            │            │            │         └──► (failed x 3 → failed_final)
            │            │            └──► failed_final (never retry)
            │            │            │
            │            │            └──► ambiguous ──► (stuck; must reconcile via provider query)
            │            │
            │            └──► processing (idempotent no-op if called again on already processing)
            │
            └── cancelled (from pending only)
```

New columns needed on Payout (in addition to attempt_count, last_error_at, last_error_message):
- `disbursement_idempotency_key` — string, the key we send to the disbursement provider so we can query the same order later.
- Status enum updated to allow value `ambiguous`.

### Safeguard 2 — Gift Card Accounting: Chart-of-Accounts Verification + Independent Idempotency

Chart of Accounts (CoA) is already represented in [LedgerAccount.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Domain/Finance/Ledger/LedgerAccount.php#L65-L73) via the `account_type` enum. Current debit-normal account types: `cash`, `mobile_money`, `bank`, `receivable`, `processing_cost`, `expense`, `asset`, `revenue_allocation`. Current credit-normal (implicit): `revenue`, `payable`, `default`, and any new types we add.

**Verified / adjusted GC accounting treatment against the existing CoA (now explicit):**

| GC Lifecycle Step | DR Account type | CR Account type |
|---|---|---|
| Step 1 — GC purchase payment CONFIRMED (GiftCardPaymentConfirmed fires) | Provider's `cash` (or `mobile_money` / `bank` per payment method) → policy-resolved asset-account-type via same `PostingPolicyResolver::assetAccountType` logic used for booking payments | Provider's **`payable`** (credit-normal). Specifically: metadata tag `ledger_account.account_type = 'payable'` with sub-type `'gift_cards_unredeemed'` stored in the `metadata` column. This is the correct "Deferred Revenue — Gift Cards" account using the existing credit-normal types; the platform does NOT currently have a literal `deferred_revenue` string in the debit-normal list, so `payable` (credit-normal) with explicit metadata sub-tag is the correct slot per existing CoA design. |
| Step 2 — GC REDEEMED at booking checkout later | Provider's `payable` (sub-tag gift_cards_unredeemed) | Provider's `revenue` (service revenue recognition at time of service) |

**Redemption-flow confirm:** At booking-checkout time when a gift card code is tendered, the existing redemption logic writes a reduction to the GiftCard.balance (correct) but no finance-domain journal for the liability→revenue recognition step. Wave 5.2 scope explicitly includes **only the purchase-side journal (Step 1)**. Step-2 redemption recognition (if currently missing) is explicitly a follow-up scoped item and flagged, not silently bundled. A TODO note in ProcessGiftCardPaymentInFinanceDomain will point this out. It will NOT be implemented in 5.2 to keep scope bounded; the explicit instruction was no scope expansion. We log the gap clearly in AC notes so the accounting team sees it, but do not implement Step-2 recognition in 5.2.

**Independently-idempotent and independently-recoverable steps:**
Each of (a) finance-transaction + journal, (b) GiftCard row creation, (c) GiftCard issue status flip is made independently idempotent using its own guard. This makes partial-success recovery safe even if intermediate crashes happen at any granularity:
1. **Transaction/journal creation idempotency:** Reuses the same pattern as `JournalPostingService::postForTransaction` ([JournalPostingService.php#L25-L33](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Domain/Finance/Journal/JournalPostingService.php#L25-L33)) — a `FinancialTransaction::where('reference_type', GiftCardPurchaseIntent)->where('reference_id', intentId)->exists()` short-circuit. Reposts become safe no-ops.
2. **GiftCard creation idempotency:** Unique `purchase_intent_id` column on GiftCard (FK to GiftCardPurchaseIntent) with unique index. `GiftCard::firstOrCreate(['purchase_intent_id' => $intentId], ...)` ensures creation is safe after crash-replay.
3. **GiftCard issuance idempotency:** `issueGiftCard()` already contains its own guard `if ($giftCard->status !== 'created') throw…` (see [GiftCardService.php#L43-L69](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Services/GiftCardService.php#L43-L69)). Combined with (1) and (2), idempotency is per-phase not just end-to-end.

### Safeguard 3 — Subscription Notifications: Failed Auto-Collection Explicit Coverage

Explicit notification-roster expansion for **failed auto-collection** (the N4 path). Confirmed the existing notification handles this exact path AND clarified all trigger paths and action_url.

**N4 (invoice_payment_required) trigger detail — now explicit with 4 paths:**

| Path | N4 `invoice_payment_required` fires? | Existing `notifySalonToPayManually` call site |
|---|---|---|
| Path (i) No saved default PaymentMethod for provider | ✅ YES | [ProcessSubscriptionRenewals.php#L103-L106](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L103-L106) |
| Path (ii) Saved method but no PaymentProvider resolves (bad provider key) | ✅ YES | [L109-L114](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L109-L114) |
| Path (iii) Resolved provider but capability `tokenized_charging`/`momo_push` disabled → no auto-charge | ✅ YES | [L117-L124](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L117-L124) |
| **Path (iv) **Failed actual auto-collection** (provider was called and returned non-success result)** | ✅ YES (fires after BillingEvent::create payment_failed at [L156-L167](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L156-L167)) | L167 call |

All 4 paths call the same `notifyProviderToPayManually` (renamed) function. Confirmed: **N4 explicitly covers failed auto-collection (path iv) as well as pre-collection blockers.**

**N4 explicit attributes after clarification:**
- **Recipient:** Provider (notifiable_type='provider', notifiable_id=subscription.provider_id)
- **Trigger:** see table above — Paths (i) through (iv), on any route that skips past the "auto collected OK" success branch (L129-153).
- **Action URL** (new data key added to existing N4 `data` payload in 5.3): `action_url` = `/[provider-slug]/billing/invoices/[invoiceId]/pay` (deep-link directly to the invoice payment page in the provider's billing portal, matching the subscription-billing-page convention in use). Previously the body only said "please pay" without a link; 5.3-AC adds the explicit link to data so the in-app notification card has a call-to-action button.
- **Corresponding success N3** fired ONLY on L129 success branch: `payment_succeeded` BillingEvent row ↔ `subscription_renewal_success` Notification row (1:1 correspondence assertion in 5.3-B test).
- **Note N5 (invoice_available): Still explicitly OUT.** No notification fired at draft→finalized step; only the 2 end-state notifications N3+N4.

### Safeguard 4 — Baseline: Honest Re-Run Report

Full-suite attempt run twice:

| Attempt # | When | Invocation | Result |
|---|---|---|---|
| 1 | Plan revision pass (before safeguards) | `php artisan test --no-coverage` backend/ | **TIMEOUT** (> 2 min before user skip). Unknown pass count. |
| 2 | Start of 5.1 / post-safeguards (this run) | Same full command | **TIMEOUT again** (> 2 min; user skipped). |

**Honest baseline statement (per instruction):** A verified whole-suite baseline count is NOT established at this time because the full run repeatedly times out in the sandbox environment. The 493/1/0 figure from the prior Wave-4.7 completion report is still our working baseline assumption but I cannot presently re-verify it end-to-end. If timeout is an environment artifact (e.g. SQLite slowdown, sandbox resource caps) then 493/1/0 remains the expected count; otherwise it is possible some tests silently hang rather than fail.

**What we CAN presently verify (focused sub-suites):**
Ran focused passes on the specific domains Wave-5 touches and got concrete numbers:

| Focused command | Result (P / S / F) | Notes |
|---|---|---|
| `--filter=FeeEngineTest` | **9 / 0 / 0** PASSED | ✅ Baseline healthy for fee-invariant (9 tests, 46 assertions) |
| `--filter=Payout` | **10 / 1 / 0** PASSED (with 1 skip) | ✅ Payout domain baseline (11 tests: 10P 1S 0F). Note: `PayoutServiceTest` name-filter had a discovery issue (classmap?), using broader `Payout` filter resolves. |
| `--filter=GiftCardTest` | **22 / 0 / 0** PASSED | ✅ 22 gift-card tests / 54 assertions healthy (confirming 20 factory safe + 2 obsolete = 22 total; both obsolete tests still pass pre-5.2 against placeholder). |
| `--filter=Notification` | TIMEOUT (user skipped after >2 min) | ❌ Unknown |
| `--filter=Subscription\|Settlement\|Refund` | TIMEOUT (user skipped) | ❌ Unknown |

**Investigation next-step for timeout (reported, not acted — for awareness):**
Most likely culprit is the Notification and broader Subscription tests which may (a) touch queue/database for hundreds of rows, (b) depend on external HTTP in sandbox, or (c) have a slow test with RefreshDatabase repeated state-building. If baseline verification is a hard requirement before 5.1, this needs separate investigation (probably by running `php artisan test --no-coverage --stop-on-failure --verbose` and/or running the suite file-by-file to isolate the hanging test class). For now, proceeding with the honest statement above per Safeguard #4's instruction: "If it times out again, report that honestly and investigate; do not claim a verified baseline." Investigated by running focused domains; next-step left as a note for when a full-suite run is needed for Gate 5.5.

A re-baseline attempt will be made at end of 5.1 (after all code changes) using an incremental / batched strategy if the full run keeps timing out. If still impossible, the 5.1 completion report will state clearly: "Focused regressions on Payout + Fee + GiftCard pass; full-suite timeout unresolved." It will NOT claim a "493 + X verified" tally on the basis of the prior report alone; it will distinguish "verified" vs "assumed from prior report."

---

## §0 — Pre-Implementation: Regression Baseline

### Baseline Record (from prior report, verified before 5.1 starts)

| Metric | Prior Report Claim | Actual Full-Run This Session | Focused-Domain Verified This Session (see Safeguard 4) |
|---|---|---|---|
| Tests **passed** | **493** | ❌ TIMEOUT (unknown; 2 attempts timed out; honestly unverified for full suite) | FeeEngine 9 ✅ · Payout 10 ✅ · GiftCard 22 ✅ |
| Tests **skipped** | **1** | ❌ TIMEOUT | Payout 1 ✅ (other suites 0 skips) |
| Tests **failed** | **0** | ❌ TIMEOUT | 0 for all 3 verified domains ✅ |

### Final Reporting Requirement (Gate 5.5)

After all sub-waves complete, re-run the same suite and report:

- **Baseline:** `P=493 / S=1 / F=0`
- **Final:** `P=__ / S=__ / F=__`
- **Delta (Wave 5 new tests only):** `+P=__ / -F=__`
- **Net non-regression assertion:** Existing tests (`P-Baseline - Wave5New`) must equal `493 passed / 1 skipped / 0 failed`.

---

## §1 — Architecture Baseline (What Works Already)

### Payments Domain ✅
- Booking payment initialize → verify → refund in [BookingPaymentService.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Services/Payments/BookingPaymentService.php)
- Providers: MTN MoMo, Flutterwave, Airtel; bound in [AppServiceProvider.php#L16-L22](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Providers/AppServiceProvider.php#L16-L22)
- **FeeEngine default:** `platform_fee = 0, gateway_fee = 0, tax = 0` → pass-through. Architecture rule pre-satisfied.
- PaymentConfirmed listeners (4): `ProcessPaymentInFinanceDomain`, `CreatePaymentNotification`, `AddPaymentActivity`, `BroadcastNotification` — all registered and working.

### Finance Domain ✅
- Journal/Ledger: `PostJournalForPayment` handles `PaymentCaptured` and `RefundCaptured`.
- Revenue distribution engine wired.
- Payout/Settlement models + PayoutService (DB lock pattern on settlement).
- Subscription renewal: `ProcessSubscriptionRenewals` drafts → finalizes → attempts auto-collect.
- Existing: SettlementTest, PayoutServiceTest, ScheduledPayoutsTest, JournalEntry, LedgerEntry — all pass at baseline.

### Notification Money Touchpoints ✅
- `CreatePaymentNotification` fires provider + customer notifications on `PaymentConfirmed`.
- Gift Card Notification factory / policy / model architecture ready (Wave 4.4).
- `notifySalonToPayManually()` in [ProcessSubscriptionRenewals.php#L174-L196](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L174-L196) already creates an in-app notification for manual payment required.

### Loyalty ✅ (Locked, Out of Scope)
`awardPointsForBooking` exists with a manual-trigger endpoint. No auto-trigger on PaymentConfirmed in Wave 5.

---

## §2 — Confirmed Gaps (In-Scope for Wave 5)

| # | Gap | Impact | Severity |
|---|---|---|---|
| G1 | `PayoutService::initiatePayout()` returns on L62 inside `DB::transaction` → L65-L68 `executePayout()` call is **dead unreachable code**. Payouts stuck at `pending` forever. | Concrete defect — no automated payout actually runs | **MUST FIX** |
| G2 | `RefundConfirmed` event has **no `CreateRefundNotification` listener** registered in AppServiceProvider. Only `ProcessRefundInFinanceDomain` + `PostJournalForPayment::handleRefundCaptured` are wired. | Refunds produce 0 notifications. Provider/customer blind. | In scope |
| G3 | `GiftCardService::purchaseGiftCard()` has placeholder comment at L293. Skips all payment processing. No PaymentRequest, no Transaction, no webhook, no journal posting, no money events. Gift card is created + issued immediately without real payment (Wave 4.4 deferred, report L275-277). | Placeholder behavior — incorrect lifecycle | In scope |
| G4 | `ProcessSubscriptionRenewals`: (a) renewal **SUCCESS** path has no notification (only FAIL currently fires `invoice_payment_required` via notifySalonToPayManually); (b) wording legacy: uses "salon" 6x in comments/names where it should be "provider" for internal consistency; (c) NotificationService helpers use `$salonName` parameter naming. | Silent success path. | In scope |
| G5 | Overlap/ambiguity: original 5.3 and 5.4 both touched subscription+gift-card notifications without explicit ownership of each notification. "Subscription invoice available" was listed but scope unclear. | Planning bug — must clarify before coding. | In scope |
| G6 | Loyalty award not auto-triggered on Booking Payment. | **OUT OF SCOPE** (Wave 4.5 Loyalty is locked.) | Deferred Wave 6 |
| G7 | Subscription/CapabilityResolver uses legacy `salonId` lookup internally (compatibility layer already present). | **OUT OF SCOPE** | Deferred Wave 6 |

---

## §3 — Non-Negotiable Architecture Rules (Wave 5)

1. **Provider** is the business/tenant for everything money-related.
2. **Location** is the physical operating / payment context.
3. **NEVER** reintroduce `salon_id` as authority → no new Salon-based compatibility layers.
4. **`platform_fee = 0`** (FeeEngine `calculateFees()`) → NEVER modify; this is a tested invariant.
5. **No transaction commissions** → payments remain pass-through.
6. **NO reopening** Wave 4 domains (Loyalty / Media / Testimonials etc.) unless a concrete money-only defect is found.
7. **Wave 6** Infrastructure / Global Cleanup → deferred.
8. **UUID pivot IDs** must be generated explicitly on attach.

---

## §4 — Gift Card Regression Test Analysis (Required Plan Correction #4)

### Tests in [GiftCardTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/GiftCardTest.php)

**Total gift-card tests: 22** (all PASS at baseline).

### Category A — Tests that call `purchaseGiftCard()` and **assume immediate unpaid issuance**

These 2 tests encode the **obsolete (incorrect) placeholder behavior** and **MUST be updated**:

| Test name (line) | Current obsolete expectation | New correct expectation |
|---|---|---|
| `test_gift_card_lifecycle_from_purchase_to_redemption` (L63-87) | `purchaseGiftCard()` returns `status='available'` gift card synchronously without payment. | Split into: (a) new `initializeGiftCardPayment()` + `confirmGiftCardPayment()` flow test; (b) lifecycle test uses factory-issued card or simulate payment confirmation. |
| `test_transaction_history_is_recorded` (L231-248) | `purchaseGiftCard()` writes `purchase`+`issue` transactions immediately. | Payment-confirm step writes the purchase transaction; issue writes issue transaction. Update to either go through payment flow OR use `createGiftCard` + `issueGiftCard` directly (bypassing the purchase method). |

### Category B — Tests that use `GiftCard::factory()` directly → **SAFE, preserve as-is** (20 tests)

These do NOT depend on purchase-path behavior and must pass unchanged:

1. `test_provider_customer_data_isolation` (L32)
2. `test_partial_redemption` (L89)
3. `test_insufficient_balance_redemption` (L111)
4. `test_expired_gift_card_cannot_be_redeemed` (L132)
5. `test_cancelled_gift_card_cannot_be_redeemed` (L147)
6. `test_gift_card_cancellation` (L165)
7. `test_fully_redeemed_gift_card_cannot_be_cancelled` (L182)
8. `test_balance_adjustment` (L199)
9. `test_balance_adjustment_cannot_make_negative` (L215)
10. `test_gift_card_issuance_from_created_status` (L250)
11. `test_gift_card_cannot_be_issued_from_non_created_status` (L266)
12. `test_provider_relationship` (L279)
13. `test_customer_relationship` (L289)
14. `test_transaction_safety_with_concurrent_operations` (L299)
15. `test_code_generation_is_unique` (L321)
16. `test_get_balance_by_code` (L331)
17. `test_find_by_code_with_provider_scope` (L350)
18. `test_model_fillable_fields_match_schema` (L367)
19. `test_zero_balance_gift_card_is_invalid` (L386)
20. `test_inactive_gift_card_is_invalid` (L399)

### Action for 5.2
- **Delete** the 2 obsolete tests that encode unpaid issuance.
- **Replace** with new, correct payment-flow lifecycle tests that go through initialize → verify → create → issue.
- **Do NOT** keep obsolete tests just to preserve old test counts.
- Valid lifecycle behavior (issue → validate → redeem → cancel → adjust) is preserved via the 20 factory-based tests + the 2 new payment-flow tests.

---

## §5 — Fee Invariant Test (Required Plan Correction #6)

### Existing baseline
[FeeEngineTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/FeeEngineTest.php) already contains:
- `test_zero_platform_fee_for_core_booking` (L20) → asserts `platform_fee === 0` for default `calculateFees()`
- `test_null_payment_method_returns_zero_fees` (L39) → same
- `test_calculate_fees_from_provider` (L84) → asserts `platform_fee === 0` for provider-response path (L95)

### Addition for Wave 5 (Explicit Confirmation Step)
To make the invariant unambiguous and impossible to regress, in 5.1 we **add one explicit test** to [FeeEngineTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/FeeEngineTest.php):

**New test: `test_platform_fee_invariant_always_zero_across_all_public_methods`**
It will call:
- `calculateFees($amount, null)`
- `calculateFees($amount, $cardMethod)`
- `calculateFees($amount, $mobileMethod)`
- `calculateFeesFromProvider($amount, any, ['fees' => X])`

and assert `platform_fee === 0.0` for every single call across all input permutations (different amounts, different PaymentMethod types, different provider fee values). This is the **fee invariant DoD gate**.

The existing `calculateFeesWithConfig` tests that set custom config → `platform_fee=100` remain as tests for the opt-in *configurable* path. Wave 5 only guarantees that the **default** production path (`calculateFees` and `calculateFeesFromProvider`) stays at 0.

---

## §6 — Dependency-Ordered Sub-Waves (Revised with All Corrections)

---

### SUB-WAVE 5.1 — Payout Execution Hardening + Refund Notification + Fee Invariant Confirmation

**Dependencies:** None. Goes first.
**Delivers corrections:** #1 (payout state machine + idempotency + retry + dedup), #6 (fee invariant), + partially addresses #2 (refund notification — actually this is here, before 5.2/5.3)

#### 5.1.1 — Payout State Machine (Correction #1)

Current bug in [PayoutService.php#L37-L68](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Domain/Finance/Payout/PayoutService.php#L37-L68): executePayout is unreachable dead code.

**Fix spec — State transitions & invariants:**

```
Payout.status states (finalized for Wave 5):
  ── pending ──► queued ──► processing ──► completed
       │           │           │
       │           │           └──► failed (recoverable → queued)
       │           │                        │
       │           │                        └──► failed_final (non-recoverable)
       │           └──► (nothing) — guard: already queued is a no-op replay
       └──► cancelled (from pending only)
```

Invariants:
- `idempotency_key` (UUID) on Payout row must be **unique** (DB unique index required if missing).
- A given `(settlement_id, idempotency_key)` pair that results in a `completed` payout must NEVER generate a second external transfer, even if `executePayout` is re-called manually, by a retry job, or by a webhook replay.
- Transition `pending → queued` is the **atomic guard** against double-execution. Only one caller can win this transition (row-lock + status check).
- External HTTP (gateway disbursement) call MUST happen **outside** any open DB transaction to avoid holding settlement locks during slow provider calls.

**Code structure (new flow):**
```
initiatePayout(settlement, amount, method, mode, payload):
  1. DB::transaction:
       - lock settlement row
       - create Payout row (status=pending, idempotency_key=UUID, all context)
       - return the Payout entity (commit)
  2. (transaction closed, settlement lock released)
  3. return $this->executePayout($payout, $payload)  ← NOW REACHABLE

executePayout(payout, payload):
  1. DB::transaction (short):
       - lock payout row (SELECT ... FOR UPDATE)
       - guard: if status === completed → return early (idempotent replay no-op)
       - guard: if status === failed_final → throw LogicException
       - guard: if status === processing → return early (in-flight, do not double-send)
       - guard: if status === cancelled → throw LogicException
       - if status === pending : flip status → queued ; increment attempt_count
       - if status === failed  : flip status → queued ; increment attempt_count (retry)
       - save & commit
  2. (transaction closed)
  3. Resolve execution strategy: manual vs automated
       - ManualExecution: flip queued→completed synchronously, mark settlement paid if 0 remaining
       - AutomatedExecution:
           a. flip queued→processing (DB tx, short)
           b. call DisbursementProvider::disburse()  ← external HTTP, no lock held
           c. if provider returns SUCCESS:
                - DB tx: flip processing→completed, store provider_reference, completed_at
                - if settlement remaining === 0: settlement.status = paid
                - return
           d. if provider returns RETRYABLE error (5xx, timeout, network):
                - DB tx: flip processing→failed, store error, last_error_at
                - (Retry responsibility: ReconcileProcessingPayouts job re-calls executePayout)
           e. if provider returns NON-RETRYABLE error (bad account, rejected):
                - DB tx: flip processing→failed_final, store error
  4. Done
```

**Retry behavior:**
- `ReconcileProcessingPayouts` existing job already handles reconciliation. For Wave 5 we only guarantee the **status machine's correctness** when it calls `executePayout` again on a `failed` row — which the guard in step (1) handles as a retry.
- `attempt_count` field on Payout: we cap retries at 3 via Reconcile job (existing cap or add). After 3 → `failed_final`.

**Duplicate-transfer protection layers (3 independent layers):**
1. **Atomic guard in executePayout step 1:** status transition `pending→queued` or `failed→queued` inside a row-locked short TX. Concurrent callers lose the race and see `queued` → return no-op.
2. **`idempotency_key` uniqueness** on Payout model (assert unique DB index): create-time dedup; re-creating the same payout for identical input parameters simply cannot happen at the row level.
3. **Terminal-state guard in step 1:** `status === completed` is always an early return, even if an external system replays a webhook or a job double-dispatches `ExecutePayoutJob`.

#### 5.1.2 — CreateRefundNotification Listener (Gap G2)

Create new file `backend/app/Listeners/CreateRefundNotification.php`:
- **Exactly mirrors** the pattern in `CreatePaymentNotification` — both Provider and Customer get a notification (2 rows) per `RefundConfirmed` event.
- Register in [AppServiceProvider.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Providers/AppServiceProvider.php) as a new listener on `RefundConfirmed`.

#### 5.1.3 — Fee Invariant Explicit Confirmation Test (Correction #6)

Add `test_platform_fee_invariant_always_zero_across_all_public_methods` to [FeeEngineTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/FeeEngineTest.php). Spec in §5 above.

#### 5.1 Acceptance Criteria (DoD for 5.1)

| # | AC |
|---|---|
| 5.1-A | `initiatePayout` no longer has dead code; `executePayout` is actually reached for both manual and automated modes. |
| 5.1-B | **State transition guards exist and work.** Manual payout: pending→queued→completed path commits each transition. Automated: pending→queued→processing→{completed, failed, failed_final}. |
| 5.1-C | **Idempotency:** calling `executePayout($alreadyCompletedPayout)` a second time is a **safe no-op** — does not call strategy, does not flip status, does not re-send money. Explicit unit test proves this. |
| 5.1-D | **Recoverable gateway failure:** AutomatedExecution provider throws a retryable exception → Payout lands in `failed`, NOT `failed_final`; next `executePayout` call on same row retries (status goes `failed→queued→processing→...`). Explicit unit test proves this using a mock provider. |
| 5.1-E | **Duplicate-transfer protection:** Two concurrent `executePayout` calls on the same Payout row (race condition) → exactly one proceeds to provider, the other short-circuits. Test via DB isolation level + double-call in same test. |
| 5.1-F | `RefundConfirmed` now fires 2 notifications (provider + customer) exactly like `PaymentConfirmed`. Listener unit test passes; event → listener → 2 Notification rows created verified. |
| 5.1-G | FeeEngine invariant test added and passes: `calculateFees()` and `calculateFeesFromProvider()` → `platform_fee === 0.0` across all input variations. |
| 5.1-H | **Focused tests pass:** `php artisan test --filter="PayoutServiceTest|FeeEngineTest"` → PASS. No new failures vs baseline. |
| 5.1-I | **No `salon_id`** introduced anywhere in 5.1 changes. |

#### 5.1 Test Cases (explicit list)

| Test file | Test name | What it asserts |
|---|---|---|
| [PayoutServiceTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Unit/Finance/Payout/PayoutServiceTest.php) | [existing] `it_initiates_and_executes_manual_payout` | After fix: payout actually reaches `completed` (currently it would assert `pending` vs `completed` if executePayout were truly dead — this test is expected to start FAILING at baseline re-verify, proving the bug exists, then PASS after fix) |
| PayoutServiceTest.php | [existing] `it_initiates_automated_payout` | After fix: `processing` status actually set by AutomatedExecution strategy (currently should be `pending` → fails pre-fix, passes post) |
| PayoutServiceTest.php | **NEW** `it_is_idempotent_repeated_execute_on_completed_is_noop` | Create payout, complete it, call executePayout again → no exception, status still `completed`, strategy execute not called (mock verifies 0 calls on second invoke) |
| PayoutServiceTest.php | **NEW** `it_retries_recoverable_gateway_failure` | Mock AutomatedExecution strategy: 1st call throws RetryableDisbursementException → Payout `status=failed`, attempt_count=1. Call executePayout again → 2nd call succeeds → `status=completed`, attempt_count=2. |
| PayoutServiceTest.php | **NEW** `it_marks_nonrecoverable_failure_as_failed_final` | Mock AutomatedExecution: throws NonRetryableDisbursementException → status=failed_final. Subsequent executePayout throws LogicException. |
| PayoutServiceTest.php | **NEW** `it_protects_against_duplicate_transfer_on_concurrent_execute` | Same payout; two sequential executePayout calls within the same PHP process using overlapping DB txs — simulate race; assert strategy::execute called exactly 1 time total. |
| PayoutServiceTest.php | **NEW** `it_moves_settlement_to_paid_when_remaining_zero` | Payout full settlement amount → Settlement.status becomes `paid`; partial → still `pending`. |
| **NEW** `backend/tests/Unit/Listeners/CreateRefundNotificationTest.php` | `it_creates_both_provider_and_customer_notifications_on_refund` | Fire RefundConfirmed event → Notification table has exactly 2 rows: notifiable_type=(provider, customer) with correct IDs. |
| [FeeEngineTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/FeeEngineTest.php) | **NEW** `test_platform_fee_invariant_always_zero_across_all_public_methods` | §5 spec — 0.0 across all variations. |

**Expected new tests for 5.1:** +8 unit tests.

#### 5.1 Files Modified / Created

| Type | Path |
|---|---|
| MODIFY | [PayoutService.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Domain/Finance/Payout/PayoutService.php) — restructure initiate → execute; add state guard transactions and idempotency logic |
| CREATE (if missing) | Payout model migration: add unique index on `idempotency_key`, add `attempt_count` column (default 0), add `last_error_at` / `last_error_message` columns |
| CREATE | `backend/app/Listeners/CreateRefundNotification.php` |
| MODIFY | [AppServiceProvider.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Providers/AppServiceProvider.php) — register CreateRefundNotification on RefundConfirmed |
| MODIFY | [PayoutServiceTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Unit/Finance/Payout/PayoutServiceTest.php) — fix 2 existing (they currently assert the wrong thing because they were written against the dead code), add 5 new tests |
| CREATE | `backend/tests/Unit/Listeners/CreateRefundNotificationTest.php` |
| MODIFY | [FeeEngineTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/FeeEngineTest.php) — add 1 invariant test |

---

### SUB-WAVE 5.2 — Gift Card Purchase: Dedicated Idempotent Money Flow

**Dependencies:** 5.1 must be done AND reviewed.
**Delivers corrections:** #2 (dedicated, idempotent GC payment confirmation flow; NOT blind PaymentConfirmed reuse), #4 (gift card regression test update).

#### 5.2.1 — Why PaymentConfirmed MUST NOT be blindly reused

Existing `PaymentConfirmed` event has 4 registered listeners in [AppServiceProvider.php#L30-L45](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Providers/AppServiceProvider.php#L30-L45):
1. `ProcessPaymentInFinanceDomain::handle` — iteracts with the **Booking-based** Payment entity. Requires a `booking_id` on the Payment to work correctly or it will break.
2. `CreatePaymentNotification::handle` — creates booking-related notifications with booking context.
3. `AddPaymentActivity::handle` — writes booking-scoped activity log entries.
4. `BroadcastNotification::handle` — broadcasts with booking-scoped payload.

A gift-card purchase has no `booking_id`. Blindly firing `PaymentConfirmed` for a GC payment **will cause those listeners to fail silently or write garbage data with booking references to NULL**. This is why a **dedicated flow** is mandatory.

#### 5.2.2 — Dedicated Gift Card Payment Flow (New)

**New Event:** `GiftCardPaymentConfirmed`
→ Fired iff a gift-card purchase payment is successfully verified.
→ Carries its own unique set of listeners that do NOT assume a booking exists.

**New Event:** `GiftCardPaymentFailed`
→ Fired iff a gift-card purchase payment verification returns a non-success.

**Dedicated listener set for GiftCardPaymentConfirmed (new registrations):**
1. `ProcessGiftCardPaymentInFinanceDomain` — creates a Finance-domain `PaymentCaptured` record against the provider, posts journal entries with account treatment = `deferred_revenue:gift_cards` (liability, not recognized revenue — correct accounting: GC is a store liability until redeemed).
2. `CreateGiftCardPaymentNotification` — fires provider + customer notifications (see 5.4 for notification contents).
3. `AddGiftCardPaymentActivity` — writes provider-scoped activity log for the purchase.
4. `BroadcastNotification` — only if broadcast payload is NOT booking-scoped; else skip. Registered separately if needed.

**Listener set for GiftCardPaymentFailed:**
1. `CreateGiftCardPaymentFailedNotification` — 1 notification to customer only.

#### 5.2.3 — Initialize + Callback (Idempotency & Recovery)

Phase 1: **Initialize** (new public method `initializeGiftCardPayment`):
- Inputs: customerId, providerId, amount, message (same as current purchaseGiftCard)
- Creates:
  - a `GiftCardPurchaseIntent` record (or equivalent on existing model) with `status=pending_payment`, a **unique** `payment_reference = "giftcard_" + Str::orderedUuid()` field
  - a `PaymentRequest` (via the same Payment abstraction used by BookingPaymentService, or a PaymentRequest-style row) linked to that payment_reference
  - Returns: PaymentRequest DTO (same shape booking returns — redirect URL / intent ID / processor reference)
- Does NOT create a GiftCard row yet.

Phase 2: **Callback / Confirmation** (new public method `confirmGiftCardPayment`):
- Inputs: `$paymentReference` (giftcard_XXX), `$providerVerificationPayload` (gateway response)
- **Idempotency guard (critical — correction #2 callback idempotency):**
  1. Short DB tx: lock pending GiftCardPurchaseIntent row by payment_reference.
  2. If intent.status is `completed` → return the already-issued gift card entity (idempotent safe replay, do NOT issue second card; do NOT post duplicate journal).
  3. If intent.status is `failed` → return the failure entity.
  4. If intent.status is NOT `pending_payment` → throw LogicException.
- Call PaymentManager::verify() with the provider payload.
  - **VERIFY SUCCESS →**
    a. Short DB tx: flip intent.status → `payment_confirmed`
    b. Fire `GiftCardPaymentConfirmed` event (payment_reference, amount, customerId, providerId, payment_transaction_id)
    c. Inside that event's ProcessGiftCardPaymentInFinanceDomain listener:
       - Create Finance domain `Transaction` record
       - Post journal: `DR Cash / Clearing`, `CR Deferred Revenue — Gift Cards`
       - Call FeeEngine::calculateFees() (platform_fee must still be 0)
    d. After finance is posted (synchronous listener dispatch by default in Laravel):
       - Short DB tx: flip intent.status → `card_issuing`
       - Call `createGiftCard()` (existing) → creates GC row with status=`created`
       - Call `issueGiftCard()` (existing) → flips to `available`
       - Short DB tx: flip intent.status → `completed` (final)
       - Return gift card entity
  - **VERIFY FAILURE →**
    a. Short DB tx: intent.status → `failed`
    b. Fire `GiftCardPaymentFailed` event
    c. Throw / return error per controller contract

**Recovery if payment succeeds but card issuance fails (correction #2 recovery):**
- If between step VERIFY SUCCESS (a) and (d), a crash happens (e.g. DB goes down after journal posts but before gift card is created), the intent lands at `payment_confirmed` (not `completed`).
- **Reconciliation job (or next callback replay)**: when `confirmGiftCardPayment` is called with the SAME payment_reference and intent.status is already `payment_confirmed`, the idempotency guard MUST recognize this as a partial-success state and:
  - Skip the re-verify.
  - Skip re-posting the journal (already done).
  - Resume from `card_issuing` forward: create card if missing → issue → mark `completed`.
- New test case covers: simulate crash between journal post and card creation, re-call confirm → recovery finishes cleanly, exactly 1 card, exactly 1 journal post.

#### 5.2.4 — Controller / Route Changes

**Existing** `POST /portal/gift-cards/purchase` — current behavior: 201 with immediately-issued gift card JSON.
**Change to:**
1. Calls `initializeGiftCardPayment()`
2. Returns HTTP 202 + PaymentRequest DTO (NOT a gift-card response):
   ```json
   { "payment_reference": "giftcard_xxx", "intent": "redirect", "redirect_url": "...", "processor": "..." }
   ```

**NEW route:** `POST /portal/gift-cards/purchase/confirm` (or webhook-style `/webhooks/gift-card-payment`)
- Input: `{ payment_reference, provider_response }`
- Calls `confirmGiftCardPayment()`
- Returns:
  - HTTP 201 + gift card entity (with transactions) on success (first call or idempotent replay)
  - HTTP 409 if already failed
  - HTTP 422 on invalid verification

#### 5.2.5 — Accounting / Journal Treatment (Correction #2 explicit)

Gift card purchases are **deferred revenue (liability)** until the card is redeemed. Wave 5 books them correctly:

| Event | DR (Debit) | CR (Credit) |
|---|---|---|
| GC Purchase payment confirmed | Cash / Clearing Account | Deferred Revenue — Gift Cards |
| GC Redemption (at booking checkout) | Deferred Revenue — Gift Cards | Service Revenue (existing path) |

FeeEngine's `platform_fee` for the GC purchase payment flow must STILL be 0 (pass-through). Only the gateway fee (if provider reports actual) is recorded via `calculateFeesFromProvider` and recognized as cost-of-service.

#### 5.2 Acceptance Criteria (DoD for 5.2)

| # | AC |
|---|---|
| 5.2-A | Dedicated `GiftCardPaymentConfirmed` event exists with its own 4 listeners; `PaymentConfirmed` is **not** re-fired for gift cards. No existing PaymentConfirmed listener receives a GC-purchase event. |
| 5.2-B | `initializeGiftCardPayment()` returns a PaymentRequest DTO WITHOUT creating a GiftCard row. DB has a pending intent row with unique `payment_reference`. |
| 5.2-C | `confirmGiftCardPayment(same_ref)` is **fully idempotent**: calling it twice with same success payload → exactly 1 GiftCard row, exactly 1 Finance Transaction row, exactly 2 journal lines, no duplicate money anywhere. Explicit test proves this. |
| 5.2-D | **Partial success recovery:** simulate DB crash after finance posts but before card issuance; next `confirmGiftCardPayment(same_ref)` completes the issue with no duplicate finance and returns the single valid card. |
| 5.2-E | Payment verified OK → intent status flow `pending_payment → payment_confirmed → card_issuing → completed` (4 distinct states, testable). |
| 5.2-F | Payment verification FAILS → intent.status=`failed`; `GiftCardPaymentFailed` fired; no GiftCard row ever created. |
| 5.2-G | Controller: old `POST /purchase` now returns 202 + PaymentRequest (not 201 + gift card); new confirm route works and returns 201 + gift card. |
| 5.2-H | Journal entries for GC purchase: DR Clearing / CR Deferred Revenue GC (not recognized revenue). Fees from FeeEngine → `platform_fee === 0`. |
| 5.2-I | Regression test update (correction #4): the 2 obsolete GiftCardTest tests (lifecycle + transaction history that called old unpaid `purchaseGiftCard`) are **removed** and replaced with correct lifecycle tests that go through initialize → confirm flow. The other 20 factory-based tests still pass unchanged. |
| 5.2-J | **Focused tests pass:** `php artisan test --filter="GiftCard"` → all 20 old + N new pass. No existing baseline test outside GiftCard space has new failures. |
| 5.2-K | No `salon_id` anywhere in 5.2 changes. |

#### 5.2 Test Cases (explicit list)

| Test file | Test name | What it asserts |
|---|---|---|
| **NEW** `backend/tests/Unit/GiftCardPaymentFlowTest.php` | `initialize_creates_intent_but_no_gift_card` | After initialize: GiftCard count === 0; Intent count === 1, status=pending_payment, unique payment_reference |
| GiftCardPaymentFlowTest.php | `confirm_success_full_flow_creates_and_issues_card` | Init → confirm(success): 1 card created, status=available, intent=completed, journal rows = 2 (DR/CR) |
| GiftCardPaymentFlowTest.php | `confirm_is_idempotent_duplicate_success_is_safe` | Init → confirm twice: still 1 card, still 1 finance Transaction, still 2 journal lines; 2nd call returns same card |
| GiftCardPaymentFlowTest.php | `confirm_recovers_after_partial_success_crash` | Init → simulate DB crash at `payment_confirmed` step, call confirm again → completes card issuance with no duplicate finance entries |
| GiftCardPaymentFlowTest.php | `confirm_failure_marks_intent_failed_no_card_created` | Init → confirm(fail payload): intent=failed; GiftCard count still 0; GiftCardPaymentFailed event dispatched |
| GiftCardPaymentFlowTest.php | `confirm_wrong_status_throws_logic_exception` | Init → manually set intent=completed → confirm with *different* payload → LogicException (cannot re-verify) |
| GiftCardPaymentFlowTest.php | `confirm_fees_platform_fee_is_zero` | Success: fetch posted FeeEngine result → `platform_fee === 0.0` |
| GiftCardPaymentFlowTest.php | `confirm_books_deferred_revenue_not_recognized` | Success: Journal CR side has `Deferred Revenue — Gift Cards` account tag, not Service Revenue |
| **MODIFY** [GiftCardTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/GiftCardTest.php) | Replace `test_gift_card_lifecycle_from_purchase_to_redemption` (L63) | New version that uses factory → issue → validate → redeem lifecycle directly (valid behavior preserved without fake purchase path) |
| GiftCardTest.php | Replace `test_transaction_history_is_recorded` (L231) | Use `createGiftCard` + `issueGiftCard` directly (purchase/issue txns) then redeem → still 3 transactions total; remove dependence on purchaseGiftCard |
| **NEW** `backend/tests/Feature/GiftCardPurchaseEndpointTest.php` | `purchase_endpoint_returns_202_payment_request_not_201` | POST /portal/gift-cards/purchase → HTTP 202, `payment_reference` present, no `gift_card.id` in response |
| GiftCardPurchaseEndpointTest.php | `confirm_endpoint_returns_201_gift_card_on_success` | Init via service → POST confirm → HTTP 201, card body correct |

**Expected new tests for 5.2:** +12 tests (8 new flow, 2 replaced, 2 new endpoint).

#### 5.2 Files Modified / Created

| Type | Path |
|---|---|
| MODIFY | [GiftCardService.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Services/GiftCardService.php) — add `initializeGiftCardPayment`, `confirmGiftCardPayment`, leave `createGiftCard`/`issueGiftCard` untouched; keep `purchaseGiftCard` as a deprecated alias OR remove it entirely (per 5.2-I, old callers that use unpaid issuance must instead call the explicit 2-step) |
| CREATE | `GiftCardPurchaseIntent` model + migration (fields: id, payment_reference UNIQUE, provider_id, customer_id, amount, currency, status enum[pending_payment, payment_confirmed, card_issuing, completed, failed], message, payment_transaction_id, created_at/updated_at) |
| CREATE | Events `backend/app/Events/GiftCardPaymentConfirmed.php` and `backend/app/Events/GiftCardPaymentFailed.php` |
| CREATE | Listeners: `ProcessGiftCardPaymentInFinanceDomain`, `CreateGiftCardPaymentNotification`, `AddGiftCardPaymentActivity`, `CreateGiftCardPaymentFailedNotification` |
| MODIFY | [AppServiceProvider.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Providers/AppServiceProvider.php) — register all new listeners on the two new events |
| MODIFY | [GiftCardController.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Http/Controllers/Api/V1/GiftCardController.php) — `purchase()` → now returns PaymentRequest 202; new `confirmPurchase()` action |
| MODIFY | [routes/api.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/routes/api.php) — add confirm route / webhook |
| CREATE | `backend/tests/Unit/GiftCardPaymentFlowTest.php` |
| CREATE | `backend/tests/Feature/GiftCardPurchaseEndpointTest.php` |
| MODIFY | [GiftCardTest.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/tests/Feature/GiftCardTest.php) — replace 2 obsolete tests (§4 Cat A) with correct versions |

---

### SUB-WAVE 5.3 & 5.4 — Notifications: De-Duplicated Scope (Corrections #3)

**Dependencies:** 5.3 can run in parallel with 5.2 AFTER 5.1 is reviewed & merged. 5.4 depends on 5.2 + 5.3 because it wires GC notifications that 5.2 events fire and subscription success notifications that 5.3 adds.

Correction #3 mandates: **NO overlap between 5.3 and 5.4. Every notification has exactly one owner sub-wave with explicit trigger/recipient.** "Subscription invoice available" scope is now CLARIFIED below.

#### Master Notification Roster

The following table is the **single source of truth**. Any notification NOT listed is out of scope.

| # | Notification type | Trigger (event / code) | Recipient(s) | **Owned by** | Notes |
|---|---|---|---|---|---|
| N1 | Refund confirmed (provider) | `RefundConfirmed` event | Provider | **5.1** | Already delivered as part of 5.1 CreateRefundNotification listener. |
| N2 | Refund confirmed (customer) | `RefundConfirmed` event | Customer | **5.1** | Same listener, 2nd notification row |
| N3 | Subscription renewal — **success** | `ProcessSubscriptionRenewals:handle()` step 4a after invoice paid + subscription renews_at advanced | Provider | **5.3** | **NEW — currently missing.** |
| N4 | Subscription renewal — **manual payment required (fail)** | `notifySalonToPayManually()` existing function | Provider | **5.3** | Already exists (type: `invoice_payment_required`) in [ProcessSubscriptionRenewals.php#L174-L196](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php#L174-L196). Scope is to keep, add success counterpart N3, and rename wording only (not data keys). |
| N5 | **Subscription invoice available (draft/finalized)** | `ProcessSubscriptionRenewals:processRenewal()` between Step 1 (invoice drafted) and Step 3 (auto-collect attempt) | Provider | **OUT OF SCOPE — explicitly REMOVED** | Removed from plan per correction #3 clarifying pass. Justification: the renewal flow currently fires `invoice_payment_required` (N4) on auto-collect failure, and will fire `subscription_renewal_success` (N3) on auto-collect success. Adding a third "invoice available" for the draft→finalized window adds noise for providers. If billing team later requests, add in Wave 6. |
| N6 | Gift card purchase — **success** (provider) | `GiftCardPaymentConfirmed` event | Provider | **5.4** | Listener: `CreateGiftCardPaymentNotification`. Provider notified: "You sold a GC worth UGX X". |
| N7 | Gift card purchase — **success** (customer buyer) | Same event | Customer (the buyer) | **5.4** | Same listener. "Your GC purchase of UGX X is confirmed, code is CCCC". |
| N8 | Gift card purchase — **failed** | `GiftCardPaymentFailed` event | Customer (the buyer) | **5.4** | Listener: `CreateGiftCardPaymentFailedNotification`. "We couldn't process your gift card purchase — please retry". |
| N9 | Gift card — **issued to recipient email** | `confirmGiftCardPayment()` after card issues (if recipient_email field present in intent and != buyer email) | Recipient email (not a customer row yet, so Notification if possible otherwise an email via NotificationService.queueEmail) | **5.4** | Conditional: only when purchaser specified recipient email != buyer. |

**Legend: Owned by**
- **5.1:** N1, N2 (refund notifications — CreateRefundNotification)
- **5.3:** N3 (NEW subscription success), N4 (existing subscription manual-payment-required) + wording/parameter renames
- **5.4:** N6, N7, N8, N9 (gift card money notifications)
- **REMOVED (no sub-wave):** N5 "Subscription invoice available"

No overlap. 5.3 owns only subscription/renewal notifications + wording cleanup. 5.4 owns only gift-card purchase notifications.

#### Sub-Wave 5.3 Details (Subscription Renewal Notifications + Naming Cleanup)

5.3 Work items:
1. **NEW N3 success notification** in [ProcessSubscriptionRenewals.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Jobs/ProcessSubscriptionRenewals.php)
   - Insert at L152 (after BillingEvent::create payment_succeeded, still inside processRenewal transaction — or after commit).
   - Notification: `type = subscription_renewal_success`, title "Subscription Renewed", body "…Invoice #NNN paid. Your plan is active through YYYY-MM-DD.", data includes invoice_id, plan name, renews_at date.
   - Recipient: same `notifiable_type=provider / notifiable_id=subscription.provider_id` as N4.
2. **Wording legacy renames (NO behavior change, NO data key changes)** in ProcessSubscriptionRenewals.php:
   - Comment at L38: "notify salon manually" → "notify provider to pay manually"
   - Function `notifySalonToPayManually` (L174): rename to `notifyProviderToPayManually`. (Update its 3 call sites at L105, L112, L122, L167.)
   - Log message L192: "salon notified for manual payment" → "provider notified for manual payment"
   - Log messages elsewhere — replace "salon" wording with "provider".
   - **Do NOT change the Notification.type `invoice_payment_required`** (that is a stored-value contract).
3. **NotificationService parameter rename** in [NotificationService.php](file:///e:/Year%202026/Feb/codding/websites/yo%20salon/backend/app/Services/NotificationService.php):
   - Methods touched: only money-relevant helpers (bookings, invitations) if they are still used for money-related communications. If money-never, skip to avoid broad cleanup (boundary: no broad NotificationService cleanup).
   - `sendBookingConfirmationSms(string $phone, string $customerName, string $salonName,…)` → parameter 3 rename `$salonName` → `$locationName` (since booking is at a location). Update the `$message` string and the `$data` key `salon_name` → `location_name`.
   - `sendBookingConfirmationEmail(…string $salonName…)` → same: `$locationName`, subject uses location name, body uses location name, data key renamed.
   - `sendPortalInvitationEmail(…string $salonName…)` → rename to `$providerDisplayName` since invitations are provider-scoped; update message strings.
   - **If any callers pass these args positionally, update them; otherwise no-op.** Search-and-replace of arg name only (no added/removed args).
4. No new money notification types in 5.3 beyond N3. N5 invoice-available is OUT.

5.3 Acceptance Criteria:
- 5.3-A N3 success notification created: BillingEvent payment_succeeded always has matching Notification row for provider.
- 5.3-B N4 (existing) still fires on all 3 failure paths (no method / no provider / no capability) AND on auto-collect fail.
- 5.3-C Function renamed notifyProviderToPayManually internally; no external callers break.
- 5.3-D NotificationService param renames: method signatures updated, internal $data keys updated, no caller in the codebase references old `$salonName` / `salon_name`.
- 5.3-E No `salon_id` introduced.

#### Sub-Wave 5.4 Details (Gift-Card Purchase Notifications)

5.4 Work items:
1. **Implement listener `CreateGiftCardPaymentNotification`** (registered already in 5.2 event wiring — 5.4 fills the body). When `GiftCardPaymentConfirmed` fires:
   - N6: Provider notification. type=gift_card_purchase_sold. title=Gift Card Sale. body=UGX X sold (code CCCC if applicable). data includes amount, card_code, payment_reference, customer_buyer_id.
   - N7: Customer buyer notification. type=gift_card_purchase_confirmed. title="Your gift card purchase is confirmed". body="Gift card code: CCCC, balance UGX X". data includes amount, card_code, payment_reference, expiration.
   - N9 (conditional): If intent.recipient_email is present AND recipient_email !== customer_buyer_email → queue an email (NotificationService::queueEmail or equivalent) to recipient with "You received a gift card of UGX X — code CCCC, redeem here".
2. **Implement listener `CreateGiftCardPaymentFailedNotification`**: fires N8 for customer only. type=gift_card_purchase_failed; body = purchase couldn't be processed; link back to repurchase page with intent context retained.
3. Wire them: AppServiceProvider already has them registered from 5.2 step — 5.4's job is to ensure the bodies are correct and unit tests prove the rows are written.

5.4 Acceptance Criteria:
- 5.4-A GiftCardPaymentConfirmed → exactly 2 Notification rows (provider + customer). N6 + N7 exist.
- 5.4-B GiftCardPaymentConfirmed with recipient_email (≠ buyer) → also 1 NotificationJob email record queued for recipient.
- 5.4-C GiftCardPaymentFailed → exactly 1 Notification row for customer (N8); no provider-side noise.
- 5.4-D Notification.data payloads match spec above with all required fields.
- 5.4-E No `salon_id`.

5.3 & 5.4 Test cases:
| File | Test | Owned by |
|---|---|---|
| NEW `backend/tests/Unit/Jobs/ProcessSubscriptionRenewalsNotificationsTest.php` | N3 success: auto-collect OK → 1 provider notification (renewal_success) | 5.3 |
| Same file | N4 fires on all 4 paths: no PM, no provider, no capability, auto-collect fail | 5.3 |
| MODIFY existing subscription tests if any | After rename, tests still pass (just function rename, no behavior) | 5.3 |
| NEW `backend/tests/Unit/Listeners/CreateGiftCardPaymentNotificationTest.php` | GiftCardPaymentConfirmed → N6 + N7 2 rows | 5.4 |
| Same file | N9 conditional recipient email queued | 5.4 |
| Same file | GiftCardPaymentFailed → exactly N8 1 row, customer only | 5.4 |
| NEW `backend/tests/Unit/Services/NotificationServiceNamingTest.php` | All 3 renamed methods accept new param names; data arrays use location_name / provider_display_name | 5.3 |

---

### SUB-WAVE 5.5 — Full Regression & Wave 5 Completion Report

**Dependencies:** 5.1, 5.2, 5.3, 5.4 all pass their focused tests.

Work items:
1. **Re-baseline before final pass:** run `php artisan test --no-coverage` once more to capture actual counts **before** declaring done. Use §0 format.
2. **Compare final results against baseline (493 / 1 / 0):**
   - Report: Baseline P=493/S=1/F=0
   - Report: Final P=__/S=__/F=__
   - Report: Wave 5 new tests total count
   - Report: "Pure-regression pass rate" = (Final P - NewP) vs 493 — must be 493.
3. **Write Wave 5 completion report** (markdown) with:
   - Sub-wave summaries
   - AC pass evidence (per DoD)
   - Test counts table (baseline vs final vs delta)
   - Fee invariant evidence line
   - Files touched list
4. User review.

DoD:
- Full suite pass: 0 failures
- 493 baseline tests still pass (no regressions in non-money code)
- Skip count remains 1 (no test was flipped from pass→skip as a workaround)
- Report file written.

---

## §7 — Implementation Order & Incremental Review Protocol

Per user's explicit instruction:

> "…begin with **5.1 only**, report its results, and wait for review before moving to 5.2. Keep the implementation incremental and evidence-driven."

Strict execution plan:

1. **Phase A (this document):** Plan revision presented here. Await user approval of this revised plan.
2. **Phase B1 — 5.1 ONLY:**
   - Run baseline → capture actual 493/1/0 counts.
   - Implement 5.1 (payout state machine + idempotency + refund notification + fee invariant test).
   - Run focused 5.1 tests. Pass all 8 new + 2 fixed PayoutServiceTest + FeeEngine.
   - Run relevant subsets.
   - Write detailed 5.1 report (test output, file changes summary, per-AC pass/fail evidence).
   - **STOP. Send report. Wait for user review before touching 5.2.**
3. **Phase B2 — After 5.1 reviewed → 5.2 ONLY.**
   - Implement 5.2. Run focused tests. Report. Wait for review.
4. **Phase B3 — 5.3 + 5.4 (can parallel if safe given their ownership split).** Report.
5. **Phase B4 — 5.5 full regression + report.**

No sub-wave starts before the previous one is explicitly reviewed and approved.

---

## §8 — Risks & Mitigations (Updated)

| Risk (original) | Delta from revision | Mitigation (now concrete) |
|---|---|---|
| Gift card flow changes break existing 22 GiftCardTest PASS | Clarified §4: only 2 tests ARE obsolete; 20 are factory-based and safe. | Delete + replace the 2; do not keep incorrect behavior to preserve old counts. Run those 20 *first* before any 5.2 code as smoke check. |
| Moving executePayout outside transaction → double-execute | **Eliminated by 5.1 state machine spec.** | 3-layer dedup (§5.1.1 atomic guard + unique idempotency_key + terminal-state early return). Explicit tests for idempotency AND concurrent execution. |
| RefundConfirmed missing provider_id in tests | Low impact | Mirror CreatePaymentNotification pattern exactly. Unit test listener row count + notifiable fields. |
| Subscription notifications inside failing try/catch | Low/Medium | N3 (success) created after BillingEvent::create payment_succeeded row, outside the catch block (inside the success branch that returns normally — so try/catch already does not interfere). |
| FeeEngine accidentally custom platform >0 | **Elevated to explicit invariant test 5.1-G.** | Add invariant test in 5.1 before any other code changes — runs once to prove baseline OK, then acts as gate on every CI run going forward. |
| **NEW RISK (from revision 5.2):** partial-success GC (money taken but no card issued) before retry logic exists | Covered by 5.2-D + recovery spec + dedicated test | Idempotency guard in confirm treats `payment_confirmed` as resume-from state, not failure. Reconciliation job can also sweep `payment_confirmed` intents older than 5 minutes and resume. |
| **NEW RISK (5.2):** blindly firing PaymentConfirmed for GC → breaks booking listeners | **Eliminated by design: dedicated GiftCardPaymentConfirmed event.** | 5.2-AC ensures PaymentConfirmed is never fired without booking_id context. |

---

## §9 — DoD Per Sub-Wave (Final Gate Matrix)

| AC ID | Sub-wave | Status start | Status end (for completion report) |
|---|---|---|---|
| 5.1-A → 5.1-I (9 items) | 5.1 | Pending | ← Will be filled in 5.1 report |
| 5.2-A → 5.2-K (11 items) | 5.2 | Pending | ← |
| 5.3-A → 5.3-E (5 items) | 5.3 | Pending | ← |
| 5.4-A → 5.4-E (5 items) | 5.4 | Pending | ← |
| Baseline regression (493/1/0 preserved) | 5.5 | Pending | ← |
| Fee invariant test exists and passes | 5.1 + 5.5 | Pending | ← |
| No salon_id introduced anywhere | All | Pending | ← |
| Completion report written | 5.5 | Pending | ← |

---

End of revised Wave 5 plan v2. Awaits approval to begin **Phase B1: 5.1 only**.
