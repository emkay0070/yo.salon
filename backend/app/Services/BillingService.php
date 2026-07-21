<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\BillingEvent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BillingService
{
    public function createInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = Invoice::create($data);

            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($invoice->id);
            $invoice->update(['invoice_number' => $invoiceNumber]);

            // Record billing event
            $this->recordBillingEvent($invoice->subscription_id, 'invoice_created', 'Invoice created', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $invoice->total,
            ]);

            return $invoice->fresh();
        });
    }

    public function getInvoiceById(string $id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function getInvoicesBySubscription(string $subscriptionId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function markInvoiceAsPaid(string $invoiceId, array $paymentData = []): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $paymentData) {
            $invoice = Invoice::findOrFail($invoiceId);

            $invoice->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'payment_method' => $paymentData['payment_method'] ?? null,
            ]);

            // Create payment record
            if (!empty($paymentData)) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'status' => 'completed',
                    'currency' => $invoice->currency,
                    'amount' => $invoice->total,
                    'payment_method' => $paymentData['payment_method'] ?? null,
                    'payment_gateway' => $paymentData['payment_gateway'] ?? null,
                    'transaction_id' => $paymentData['transaction_id'] ?? null,
                    'processed_at' => Carbon::now(),
                    'gateway_response' => $paymentData['gateway_response'] ?? null,
                ]);
            }

            // Record billing event
            $this->recordBillingEvent($invoice->subscription_id, 'payment_succeeded', 'Payment succeeded', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
            ]);

            return $invoice->fresh();
        });
    }

    public function markInvoiceAsFailed(string $invoiceId, string $reason = null): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $reason) {
            $invoice = Invoice::findOrFail($invoiceId);

            $invoice->update([
                'status' => 'failed',
            ]);

            // Create failed payment record
            Payment::create([
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'currency' => $invoice->currency,
                'amount' => $invoice->total,
                'processed_at' => Carbon::now(),
            ]);

            // Record billing event
            $this->recordBillingEvent($invoice->subscription_id, 'payment_failed', 'Payment failed', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->total,
                'reason' => $reason,
            ]);

            return $invoice->fresh();
        });
    }

    public function generateInvoiceForSubscription(string $subscriptionId): Invoice
    {
        $subscription = Subscription::findOrFail($subscriptionId);
        $plan = $subscription->plan;

        $price = $subscription->billing_cycle === 'yearly'
            ? $plan->yearly_price
            : $plan->monthly_price;

        return $this->createInvoice([
            'subscription_id' => $subscriptionId,
            'status' => 'pending',
            'currency' => 'UGX',
            'subtotal' => $price,
            'tax' => 0,
            'discount' => 0,
            'total' => $price,
            'due_date' => Carbon::now()->addDays(7),
            'line_items' => [
                [
                    'description' => "{$plan->name} Plan ({$subscription->billing_cycle})",
                    'quantity' => 1,
                    'unit_price' => $price,
                    'total' => $price,
                ],
            ],
        ]);
    }

    public function getBillingTimeline(string $subscriptionId): array
    {
        $events = BillingEvent::where('subscription_id', $subscriptionId)
            ->recent()
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

    private function generateInvoiceNumber(string $invoiceId): string
    {
        return 'INV-' . strtoupper(substr($invoiceId, 0, 8)) . '-' . date('Ymd');
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
