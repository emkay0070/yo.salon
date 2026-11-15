# Yo.Salon Stabilization Register

Living record of controller/service audits, confirmed defects, verdicts, fixes,
regression coverage, commits and suite results. Findings are only marked fixed
after the behavior is reproduced or verified in code and covered by a test.

- Last updated: 2026-11-15
- Branch: `main`
- HEAD at last full-suite run: `e138adb`
- Test database: `yo_salon_test` only. Production `yo_salon` is never touched.

## Suite progression

| Checkpoint | Tests | Passed | Skipped | Failures/Errors | Exit |
|---|---:|---:|---:|---:|---:|
| `d61a70e` (stabilization baseline) | 764 | 702 | 1 | 50 failures / 11 errors | 1 |
| `0632920` (capabilities clean) | 774 | 773 | 1 | 0 | 0 |
| `518e452` (payments clean) | 783 | 782 | 1 | 0 | 0 |
| `e138adb` (settlement/payout/ledger clean, current) | 794 | 793 | 1 | 0 | 0 |

The single skip is an intentionally skipped test, not a dormant/undiscovered
test. PHPUnit is 12.5.33; `/** @test */` metadata is not honored, so new tests
use the `#[Test]` attribute or `test_` method names.

## Method and guardrails

- Audit implementation first: controllers, middleware, services, models,
  migrations and route targets. Test failures alone are not treated as proof of
  a product defect.
- Fix only confirmed defects with the smallest appropriate correction.
- Add regression coverage that fails without the fix.
- Run focused tests, then the full suite, before committing.
- Commit by domain with conventional messages.
- Do not modify tests merely to raise the pass count; test-fixture defects are
  recorded separately from product defects.
- No production deployment, no new provider types, no broad architecture
  migration.
- Policy-dependent or architecture-level questions are isolated rather than
  guessed.

## Defect register

### Schema and finance foundations

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-01 | `financial_transactions.reference_id`, `journal_entries.reference_id` and `ledger_entries.reference_id` were `uuid`, but payment/refund journal references use bigint transaction IDs. PostgreSQL raised `uuid = integer`. | Confirmed | Widened reference columns to `VARCHAR(36)` with a guarded irreversible down migration; cast transaction IDs to string in `JournalPostingService` and `::text` in analytics joins. Regression tests cover bigint refs, multiple payments/refunds per booking, UUID refs, uniqueness and analytics attribution. | `738da5b` |
| F-02 | Resource capability queries used `groupBy('resource_id')` with `SELECT *`; PostgreSQL rejected the query (`SQLSTATE[42803]`). | Confirmed | Selected only `resource_id` in the affected `whereHas` subqueries in `ResourceAvailabilityService` and `ResourceAllocator`; preserved query semantics. | `bded96e` |
| F-03 | `provider_capacities` was queried by `ProviderCapacity`, `CapabilityResolver`, `InvoiceService` and `AddOnPurchaseController`, but no migration created it. | Confirmed | Added the table migration for purchased add-on capacity. | `70a88b9` |

### Staff and specialist domain

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-04 | `StaffController::index()` performed a `staff_id` backfill write during a GET request. | Confirmed | Removed the mutation; preserved two-pass staff-to-specialist resolution without side effects. Added read-integrity tests. | `3ea0f52` |
| F-05 | `StaffController::show()` constrained assignment lookup by `location_id`, which could hide a valid assignment when staff and assignment locations differ. | Confirmed | Lookup now uses `staff_id` + `provider_id`; regression test covers mismatched location fields. | `02cff52` |
| F-06 | `StaffController::index()` had been narrowed to staff with active specialist assignments, hiding newly created staff, receptionists and managers. | Confirmed (regression) | Management listing returns all staff; specialist resolution remains unchanged. | `36a9156` |
| F-07 | `BookingResource` exposed `location_id`/`staff_id` only inside optional loaded relations, while consumers filter by flat fields. | Confirmed (contract) | Added always-present flat `location_id` and `staff_id` as additive fields. | `c13837a` |
| F-08 | Newer assignment-creation flows do not populate `staff_id`. | Confirmed, open | Isolated; requires a decision on whether assignment creation must resolve a staff record. See Open Items. | — |

`staff_id` global uniqueness was evaluated and rejected as a fix: `(specialist_id,
location_id)` correctly permits multi-branch assignments.

### Booking, availability and sessions

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-09 | `transitionStatus()` allowed `confirmed → completed`, bypassing the documented state machine and enabling sessionless completion. | Confirmed | Removed `completed` from allowed `confirmed` transitions; `in_progress` retained for walk-ins. | `3ea0f52` |
| F-10 | `resolveBooking()` mapped `COMPLETED_LATE` to a direct `confirmed → completed` transition, which then failed after F-09. | Confirmed | Added `resolveAsCompletedLate()` performing `confirmed → in_progress → completed`, resolution record and activity, atomically. | `b7cfc04` |
| F-11 | `CustomerBookingService::createBooking()` referenced `$locationId` inside a closure without capturing it, breaking provider-level conflict checks. | Confirmed | Captured the variable in the transaction closure. | `36fef8d` |
| F-12 | `SessionService::startSession()` counted the session being started in its concurrency check, reporting a false "already serving another customer" error. | Confirmed | Excluded the current session from the active-session query. | `eea3f15` |
| F-13 | Session tests used a raw `Specialist` actor; canonical actors are `SpecialistAccount` or `User`. | Test defect | Aligned fixtures to canonical actors; no product change. | `eea3f15` |
| F-14 | Availability tests bypassed documented preconditions (session fields, independent-specialist assignment, conflict messaging). | Test defect | Aligned fixtures with domain preconditions; assertions now describe the intended contract. | `22af675` |
| F-15 | Finance fixtures used bigint user IDs or placeholder strings for UUID polymorphic recipients. | Test defect | Switched fixtures to Specialist UUIDs and generated booking UUIDs. | `fd6a06d` |
| F-16 | Multi-session start: a parent booking becomes `in_progress` after the first session, but `startSession()` accepts only `confirmed/arrived/waiting`. | Unresolved contract | Isolated; business decision needed on whether subsequent sessions may start from `in_progress`. | — |

### Authentication, identity and test harness

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-17 | C5/C6 failures were attributed to authorization defects. | False positive | Root causes were fixtures: missing provider-wide `authority_scope`, inactive users, and Sanctum token/guard usage. Tests corrected; no authorization change. | `d61a70e` |
| F-18 | Capability tests used `/** @test */` metadata, silently skipped by PHPUnit 12. | Harness defect | Converted eight methods to `#[Test]`; resolver behavior now actually executes. | `237944c` |

### Capability domain

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-19 | `Cache::forgetMatchingPattern()` is not implemented by any Laravel cache store; plan/subscription/add-on changes would throw. | Confirmed | Both resolvers forget exact per-resource keys for all three key families (feature, quota, credit). | `cc6bccf` |
| F-20 | Specialist subscription lookup had an OR-precedence bug that could return another specialist's trialing row. | Confirmed | Corrected the scoped lookup and invalidated the resolved provider ID after add-on purchase. | `cc6bccf` |
| F-21 | Capability cache invalidation and specialist scoping lacked regression coverage. | Gap | Added cache eviction, trial isolation and all-key-family tests. | `0632920` |

### Routes and portal

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-22 | Routes pointed at missing controller methods: `/payments/salon/verify`, `/refund`, `/webhooks/salon`, `/specialist-portal/invitations`. Resource routes advertised unimplemented actions. | Confirmed | Pointed routes at implemented methods; restricted resources; removed three never-implemented portal routes with no consumer. | `5d92b10` |
| F-23 | `CustomerBookingController::cancel()` referenced an undefined service property, so every portal cancellation failed. | Confirmed | Used the injected `CustomerBookingService` property. | `f4b4a4e` |
| F-24 | No guard prevented routes from referencing nonexistent controller actions. | Gap | Added `RouteIntegrityTest`; 425 routes, 0 broken targets. | `a4a6637` |

### Payments, refunds and settlement verification

Read-only audit completed first; every finding below was verified in code before
change. Partial-refund fee policy and platform-admin authorization remain
isolated (see Open Items).

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| F-25 | `payment_requests.idempotency_key` was read/written by the model and controller but the column did not exist, so every create failed. | Confirmed | Added nullable column with per-provider unique index. | `ef915af` |
| F-26 | `PaymentRequestController` used `Log::` without importing it. | Confirmed | Added the facade import. | `81011e7` |
| F-27 | `store()` dereferenced `booking_id` even when absent, crashing non-booking requests. | Confirmed | Guarded optional booking access. | `81011e7` |
| F-28 | `updateStatus()` accepted `paid`, letting any tenant mint a completed transaction without payment; it also accepted `approved`, which violates the status constraint. | Confirmed | Endpoint now accepts only `failed/cancelled/expired`; settlement occurs only through provider verification or the manual-payment path. | `81011e7` |
| F-29 | Payment request lookups had no tenant scoping (read/cancel/status IDOR). | Confirmed | All actions require the request to belong to the authenticated provider; unknown methods and customers are rejected on create. | `81011e7` |
| F-30 | Payment method reads/updates/credential checks had no ownership check; updates used the query builder and bypassed encrypted casts, storing secrets in plaintext. | Confirmed | Added provider ownership guards; updates go through the model and encrypted casts. | `ce686c6` |
| F-31 | Booking payment initialize/verify/manual/refund/status endpoints trusted caller-supplied IDs across tenants. | Confirmed | Every booking, method, location and transaction must belong to the authenticated provider. | `d429116` |
| F-32 | A successful provider API response was treated as a completed payment even when MTN returned `PENDING`/`FAILED`. | Confirmed | Settlement requires a terminal successful status. | `f2f74be` |
| F-33 | Concurrent verification/webhook calls could both settle the same payment request. | Confirmed | Payment request row is locked before settlement; terminal-status check precedes transaction creation. | `f2f74be` |
| F-34 | Refunds could exceed the captured amount, be repeated after a full refund, or race each other. | Confirmed | Refunds are capped at captured net amount, accumulate `metadata.refunded_amount`, and lock the original transaction. Non-payment transactions are rejected. | `f2f74be` |
| F-35 | MTN did not implement `refundPayment`; the provider interface did not require it. | Confirmed | Added MTN refund support and made the method part of the provider contract. | `f2f74be` |
| F-36 | MTN used the global configured environment rather than the payment method's environment. | Confirmed | All MTN calls use the per-method environment. | `f2f74be` |
| F-37 | MTN webhooks are unsigned; the handler treated the callback payload as proof of payment. | Confirmed | The handler re-verifies the reference with the MTN API before recording anything. | `f2f74be` |
| F-38 | Payment hardening lacked regression coverage. | Gap | Added provider isolation, idempotency, encryption-at-rest, refund cap/accumulation, MTN pending-status and webhook re-verification tests. | `518e452` |

Flutterwave webhook routing was reported as broken in the audit but was not
confirmed: booking and platform payments carry distinct `metadata.type` values.

## Settlements, payouts and ledger (verified)

The second deep audit findings were reproduced in code and fixed. No production
deployment was involved; every fix is covered by regression tests and the full
suite remains clean.

| ID | Finding | Verdict | Resolution | Commit |
|---|---|---|---|---|
| P-01 | `ExecutePayoutJob` passed `'automated'` as the fourth positional argument into `PayoutService::initiatePayout()`, which expects six named arguments; `recipientType`/`recipientId` were never sent. | Confirmed | Both call sites (`ExecutePayoutJob`, `CompensationPayoutController`) now pass `recipientType`/`recipientId` explicitly from the settlement. Regression: `ManualPayoutTest` and `ScheduledPayoutsTest` assert the payout's recipient and execution mode. | `29691a2` |
| P-02 | MTN disbursement callbacks are unsigned; the webhook handler treated the callback payload as proof of completion, so a forged callback could complete a payout that never ran. | Confirmed | The handler re-queries MTN via the provider's configured credentials; only a provider-confirmed `completed`/`failed` mutates the payout. Unverifiable callbacks leave the payout untouched. Regression: forged/pending/failed verification tests in `DisbursementExecutionTest`. | `1957027` |
| P-03 | `SettlementController` eager-loaded nonexistent `period`/`earnings` relations, returning 500; `Settlement` was missing `payable`/`reference`/`earnings` relations. | Confirmed | Added `reference()` morphTo and `earnings()` HasMany relations, retained `reference_type`/`reference_id` fillable, and updated the controller to load `payable`, `earnings` and `payouts`. Regression: `SettlementApiTest`. | `ef6e0cb` |
| P-04 | Negative payables could be closed into settlements that can never be paid. | Confirmed | Period closing now rejects negative payables with a `LogicException` and keeps the period open. Regression: `test_negative_payable_is_rejected...`. | `b4c3c34` |
| P-05 | Concurrent period closes could both observe `open` and create duplicate settlements/payables. | Confirmed | Close now locks the period row with `lockForUpdate()` before checking status and creating the settlement. Regression: covered by period close tests. | `b4c3c34` |
| P-06 | A cancelled settlement with no payouts still had its full remaining amount, so a payout could be initiated against a cancelled obligation. | Confirmed | `PayoutService::initiatePayout()` guards the locked settlement status and throws for cancelled settlements. Regression: `test_it_rejects_payout_for_cancelled_settlement`. | `c575e97` |
| P-07 | `FinancialTransaction::createWithJournals()` and `JournalEntry::createWithEntries()` wrote wrapper/journals/ledger movements without a transaction, so a later failure could leave a half-posted financial transaction; the posted event fired before commit. | Confirmed | Both creation paths now run inside `DB::transaction()`, roll back entirely on failure, and dispatch `FinancialTransactionPosted` only after commit. Regression: `test_financial_transaction_rolls_back_all_journals_when_one_fails` and ledger-entry rollback test. | `e138adb` |

Immediate settlements are now complete: payable/recipient/reference are populated when an immediate earning creates a settlement (`RecordEarning`), and `SettlementController` exposes the real relations. Zero-value periods are voided while still open without creating a settlement. Carry-forward/write-off policy for closed-negative periods remains isolated (see Open Items).

## Open items requiring a business/architecture decision

1. **Platform-admin authorization.** There is no platform-admin concept in the
   schema or middleware: `users` has no `is_admin`, `provider_users.role` is
   provider-scoped, and `profiles.role` is provider-scoped. `Admin\PlatformPaymentController`
   and `ScheduleMigrationController` therefore have no valid platform-admin
   gate. Introducing an admin model is an architecture change and is isolated.
2. **Partial-refund fee policy.** Refunds are capped at captured net amount and
   accumulate correctly. Whether gateway/platform fees should be returned in
   part or in full is a product policy decision.
3. **Zero/negative payables.** Code now rejects negative payables at period close and voids zero-value periods without creating settlements. Whether a zero/negative payable may still be *displayed* to users (and how) is a product display-policy decision.
4. **Multi-session start.** See F-16.
5. **`staff_id` population in newer assignment flows.** See F-08.
6. **Refund initiation vs. provider confirmation.** MTN refunds are
   asynchronous (202). The refund transaction is currently recorded when the
   provider accepts the request. Whether the ledger should wait for a final
   provider callback is a settlement-semantics decision.

## Controller/area coverage

| Area | Audited | Notes |
|---|---|---|
| Staff/specialist | `StaffController`, assignment services, specialist resolvers | Fixed read side effects, listing regression, assignment lookup, resource contract |
| Booking/availability | `CustomerBookingController`, `BookingService`, `CustomerBookingService`, availability/allocator services | State machine, closure capture, capability query, resource fields |
| Sessions | `SessionService` and session endpoints | Concurrency check, canonical actors |
| Capabilities | provider/specialist capability resolvers, add-on purchase, invoice service | Cache invalidation, schema, subscription precedence |
| Payments | `PaymentRequestController`, `PaymentMethodController`, `BookingPaymentController`, `WebhookController`, `BookingPaymentService`, `MTN/Flutterwave/Airtel` providers | Full read-only audit; verified defects fixed |
| Routes | `routes/api.php` and every controller action target | 425 routes, 0 broken targets |
| Settlements/payouts/ledger | `PayoutService`, `ExecutePayoutJob`, settlement/ledger services | Verified: fixed payout initiation args, cancelled-settlement guard, period close locks, MTN disbursement re-verification, atomic journal posting, settlement relations; regression tests added |
| Platform admin | `Admin\PlatformPaymentController`, `ScheduleMigrationController` | Blocked on missing platform-admin concept |
