<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\BillingEvent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * BillingService - Charge calculation and renewal management
 * 
 * This service handles billing calculations, subscription renewals, and invoice generation.
 * It works with InvoiceService for invoice creation and SubscriptionService for subscription management.
 */
class BillingService
{
    public function calculateCharge(Subscription $subscription): array
    {
        $plan = $subscription->plan;

        $amount = $subscription->billing_cycle === 'yearly'
            ? $plan->yearly_price
            : $plan->monthly_price;

        return [
            'subtotal' => $amount,
            'tax' => 0,
            'discount' => 0,
            'total' => $amount,
            'currency' => 'UGX',
        ];
    }

    public function processRenewal(string $subscriptionId): array
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = Subscription::findOrFail($subscriptionId);

            if (!$subscription->isActive()) {
                throw new \Exception('Cannot renew inactive subscription');
            }

            // Generate invoice for renewal
            $invoice = app(InvoiceService::class)->generateSubscriptionInvoice($subscription);

            // Renew subscription
            $renewedSubscription = app(SubscriptionService::class)->renewSubscription($subscriptionId);

            // Record billing event
            $this->recordBillingEvent($subscriptionId, 'renewal_processed', 'Renewal processed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'renews_at' => $renewedSubscription->renews_at?->toIso8601String(),
            ]);

            return [
                'subscription' => $renewedSubscription,
                'invoice' => $invoice,
            ];
        });
    }

    public function checkAndProcessDueRenewals(): array
    {
        $subscriptions = Subscription::where('status', 'active')
            ->where('renews_at', '<=', Carbon::now())
            ->get();

        $processed = [];
        $failed = [];

        foreach ($subscriptions as $subscription) {
            try {
                $result = $this->processRenewal($subscription->id);
                $processed[] = [
                    'subscription_id' => $subscription->id,
                    'invoice_id' => $result['invoice']->id,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $subscriptions->count(),
        ];
    }

    public function getBillingTimeline(string $subscriptionId): array
    {
        $events = BillingEvent::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $events->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->type,
                'description' => $event->description,
                'payload' => $event->payload,
                'created_at' => $event->created_at->toIso8601String(),
            ];
        })->toArray();
    }

    public function getUpcomingRenewals(int $days = 7): \Illuminate\Database\Eloquent\Collection
    {
        return Subscription::where('status', 'active')
            ->where('renews_at', '>', Carbon::now())
            ->where('renews_at', '<=', Carbon::now()->addDays($days))
            ->with('plan', 'provider')
            ->orderBy('renews_at')
            ->get();
    }

    public function getOverdueInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->with('subscription', 'provider')
            ->orderBy('due_date')
            ->get();
    }

    public function getInvoicesBySubscription(string $subscriptionId): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getInvoiceById(string $invoiceId): ?Invoice
    {
        return Invoice::find($invoiceId);
    }

    private function recordBillingEvent(string $subscriptionId, string $type, string $description, array $payload = []): void
    {
        BillingEvent::create([
            'subscription_id' => $subscriptionId,
            'type' => $type,
            'description' => $description,
            'payload' => $payload,
        ]);
    }
}
