<?php

namespace App\Jobs;

use App\Models\BillingEvent;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Services\Payments\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of retry attempts before giving up.
     */
    public int $tries = 3;

    /**
     * Execute the job.
     *
     * Renewal flow (strict order — do not reorder):
     *   1. Find subscriptions due for renewal
     *   2. Draft the invoice (calculate: base fee + overage)
     *   3. Finalize (freeze) the invoice — amount is now immutable
     *   4. Attempt automatic collection via PaymentManager
     *       a. Success  → mark invoice paid, activate subscription, log BillingEvent
     *       b. Failure  → leave invoice pending, notify salon to pay manually
     *
     * The invoice is ALWAYS created and frozen before any payment attempt.
     * This ensures accounting stays clean even if a gateway is offline.
     */
    public function handle(InvoiceService $invoiceService, PaymentManager $paymentManager): void
    {
        $now = now();

        $dueSubscriptions = Subscription::with(['provider', 'plan', 'invoices'])
            ->where('status', 'active')
            ->where('renews_at', '<=', $now)
            ->get();

        Log::info('ProcessSubscriptionRenewals: found due subscriptions', [
            'count' => $dueSubscriptions->count(),
            'run_at' => $now->toIso8601String(),
        ]);

        foreach ($dueSubscriptions as $subscription) {
            try {
                $this->processRenewal($subscription, $invoiceService, $paymentManager);
            } catch (\Throwable $e) {
                Log::error('ProcessSubscriptionRenewals: failed for subscription', [
                    'subscription_id' => $subscription->id,
                    'provider_id'     => $subscription->provider_id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process a single subscription renewal.
     */
    private function processRenewal(
        Subscription $subscription,
        InvoiceService $invoiceService,
        PaymentManager $paymentManager
    ): void {
        DB::transaction(function () use ($subscription, $invoiceService, $paymentManager) {

            // ── Step 1: Draft the invoice ─────────────────────────────
            $invoice = $invoiceService->draftRenewalInvoice($subscription);

            Log::info('ProcessSubscriptionRenewals: invoice drafted', [
                'subscription_id' => $subscription->id,
                'invoice_id'      => $invoice->id,
                'total'           => $invoice->total,
            ]);

            // ── Step 2: Freeze the invoice — amount is now immutable ──
            $invoice = $invoiceService->finalizeInvoice($invoice->id);

            Log::info('ProcessSubscriptionRenewals: invoice finalized', [
                'invoice_id' => $invoice->id,
                'status'     => $invoice->status,
            ]);

            // ── Step 3: Attempt automatic collection ──────────────────
            $paymentMethod = PaymentMethod::where('provider_id', $subscription->provider_id)
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();

            if (!$paymentMethod) {
                // No saved payment method — notify salon to pay manually
                $this->notifySalonToPayManually($subscription, $invoice);
                return;
            }

            $provider = $paymentManager->getProvider($paymentMethod->provider);

            if (!$provider) {
                $this->notifySalonToPayManually($subscription, $invoice);
                return;
            }

            // Check capability before attempting
            $capabilities = $provider->getCapabilities();
            $canAutoCharge = ($capabilities['tokenized_charging'] ?? false)
                          || ($capabilities['momo_push'] ?? false);

            if (!$canAutoCharge) {
                $this->notifySalonToPayManually($subscription, $invoice);
                return;
            }

            $result = $provider->attemptInvoiceCollection($invoice, $paymentMethod);

            // ── Step 4a: Success ──────────────────────────────────────
            if ($result->success) {
                $invoiceService->markAsPaid($invoice->id, $result->transactionId, $paymentMethod->provider);

                $subscription->update([
                    'renews_at' => $this->nextRenewalDate($subscription),
                ]);

                BillingEvent::create([
                    'subscription_id' => $subscription->id,
                    'type'            => 'payment_succeeded',
                    'description'     => "Auto-collected renewal payment. Invoice #{$invoice->invoice_number}.",
                    'payload'         => [
                        'invoice_id'       => $invoice->id,
                        'transaction_id'   => $result->transactionId,
                        'provider'         => $result->transactionId,
                        'amount'           => $invoice->total,
                    ],
                ]);

                Log::info('ProcessSubscriptionRenewals: auto-collection succeeded', [
                    'subscription_id' => $subscription->id,
                    'invoice_id'      => $invoice->id,
                ]);

                return;
            }

            // ── Step 4b: Failure — notify salon ──────────────────────
            BillingEvent::create([
                'subscription_id' => $subscription->id,
                'type'            => 'payment_failed',
                'description'     => "Auto-collection failed. Invoice #{$invoice->invoice_number} is pending.",
                'payload'         => [
                    'invoice_id' => $invoice->id,
                    'message'    => $result->message,
                ],
            ]);

            $this->notifySalonToPayManually($subscription, $invoice);
        });
    }

    /**
     * Send an in-app notification to the salon to pay their invoice manually.
     */
    private function notifySalonToPayManually(Subscription $subscription, Invoice $invoice): void
    {
        Notification::create([
            'notifiable_type' => 'provider',
            'notifiable_id'   => $subscription->provider_id,
            'type'            => 'invoice_payment_required',
            'title'           => 'Subscription Renewal — Payment Required',
            'body'            => "Your subscription has renewed. Invoice #{$invoice->invoice_number} for {$invoice->currency} " . number_format($invoice->total) . " is due. Please pay to keep your account active.",
            'data'            => [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount'         => $invoice->total,
                'currency'       => $invoice->currency,
                'due_date'       => $invoice->due_date?->toDateString(),
            ],
            'read_at'         => null,
        ]);

        Log::info('ProcessSubscriptionRenewals: salon notified for manual payment', [
            'subscription_id' => $subscription->id,
            'invoice_id'      => $invoice->id,
        ]);
    }

    /**
     * Calculate the next renewal date based on billing cycle.
     */
    private function nextRenewalDate(Subscription $subscription): \Carbon\Carbon
    {
        return match ($subscription->billing_cycle) {
            'yearly'  => now()->addYear(),
            default   => now()->addMonth(),
        };
    }
}
