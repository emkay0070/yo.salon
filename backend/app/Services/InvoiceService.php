<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\PlatformPaymentMethod;
use App\Models\SalonCapacity;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * InvoiceService - Invoice generation and payment management
 * 
 * This service handles invoice generation, payment recording, and cash confirmation.
 * Invoices are the source of truth for subscription billing.
 */
class InvoiceService
{
    public function generateInvoice(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            $invoice = Invoice::create([
                'provider_id' => $data['provider_id'] ?? null,
                'subscription_id' => $data['subscription_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'status' => 'pending',
                'currency' => $data['currency'] ?? 'UGX',
                'subtotal' => $data['subtotal'] ?? 0,
                'tax' => $data['tax'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'total' => $data['total'] ?? 0,
                'due_date' => $data['due_date'] ?? Carbon::now()->addDays(7),
                'line_items' => $data['line_items'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $invoice;
        });
    }

    public function generateSubscriptionInvoice(Subscription $subscription): Invoice
    {
        return DB::transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            $provider = $subscription->provider;

            // Calculate amount based on billing cycle
            $amount = $subscription->billing_cycle === 'yearly'
                ? $plan->yearly_price
                : $plan->monthly_price;

            // Generate line items
            $lineItems = [
                [
                    'description' => "{$plan->name} Plan ({$subscription->billing_cycle})",
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            ];

            return $this->generateInvoice([
                'provider_id' => $provider->id,
                'subscription_id' => $subscription->id,
                'subtotal' => $amount,
                'tax' => 0,
                'discount' => 0,
                'total' => $amount,
                'due_date' => Carbon::now()->addDays(7),
                'line_items' => $lineItems,
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $subscription->billing_cycle,
                ],
            ]);
        });
    }

    public function markAsPaid(string $invoiceId, string $paymentMethod, array $paymentData = []): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $paymentMethod, $paymentData) {
            $invoice = Invoice::findOrFail($invoiceId);

            // Create payment record
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'status' => 'completed',
                'currency' => $invoice->currency,
                'amount' => $invoice->total,
                'payment_method' => $paymentMethod,
                'payment_gateway' => $paymentData['payment_gateway'] ?? null,
                'transaction_id' => $paymentData['transaction_id'] ?? null,
                'processed_at' => Carbon::now(),
                'gateway_response' => $paymentData['gateway_response'] ?? null,
                'metadata' => $paymentData['metadata'] ?? [],
            ]);

            // Update invoice status
            $invoice->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'payment_method' => $paymentMethod,
            ]);

            // Activate subscription if exists
            if ($invoice->subscription_id) {
                $subscription = $invoice->subscription;
                if ($subscription->status === 'trialing' || $subscription->status === 'past_due') {
                    app(SubscriptionService::class)->activateSubscription($subscription->id);
                }
            }

            return $invoice->fresh();
        });
    }

    public function markAsPending(string $invoiceId, string $paymentMethod): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $paymentMethod) {
            $invoice = Invoice::findOrFail($invoiceId);

            // Create pending payment record
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'status' => 'pending',
                'currency' => $invoice->currency,
                'amount' => $invoice->total,
                'payment_method' => $paymentMethod,
                'metadata' => [
                    'awaiting_confirmation' => true,
                ],
            ]);

            // Update invoice to pending (already pending by default)
            $invoice->update([
                'payment_method' => $paymentMethod,
            ]);

            return $invoice->fresh();
        });
    }

    public function confirmCashPayment(string $invoiceId, array $confirmationData): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $confirmationData) {
            $invoice = Invoice::findOrFail($invoiceId);

            if ($invoice->status === 'paid') {
                throw new \Exception('Invoice is already paid');
            }

            // Find or create pending payment
            $payment = InvoicePayment::where('invoice_id', $invoiceId)
                ->where('payment_method', 'cash')
                ->first();

            if (!$payment) {
                // Create new payment record for cash
                $payment = InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'status' => 'pending',
                    'currency' => $invoice->currency,
                    'amount' => $invoice->total,
                    'payment_method' => 'cash',
                    'metadata' => [
                        'awaiting_confirmation' => true,
                    ],
                ]);
            }

            // Update payment to completed
            $payment->update([
                'status' => 'completed',
                'processed_at' => Carbon::now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'confirmed_by' => $confirmationData['confirmed_by'] ?? null,
                    'confirmation_date' => Carbon::now()->toIso8601String(),
                    'reference' => $confirmationData['reference'] ?? null,
                    'notes' => $confirmationData['notes'] ?? null,
                ]),
            ]);

            // Mark invoice as paid
            $invoice->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'payment_method' => 'cash',
            ]);

            // Activate subscription if exists
            if ($invoice->subscription_id) {
                $subscription = $invoice->subscription;
                if ($subscription->status === 'trialing' || $subscription->status === 'past_due') {
                    app(SubscriptionService::class)->activateSubscription($subscription->id);
                }
            }

            return $invoice->fresh();
        });
    }

    public function markAsFailed(string $invoiceId, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $reason) {
            $invoice = Invoice::findOrFail($invoiceId);

            // Update pending payment to failed
            $payment = InvoicePayment::where('invoice_id', $invoiceId)
                ->where('status', 'pending')
                ->first();

            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'failure_reason' => $reason,
                        'failed_at' => Carbon::now()->toIso8601String(),
                    ]),
                ]);
            }

            // Update invoice status
            $invoice->update([
                'status' => 'failed',
            ]);

            // Deactivate associated capacity records for add-on purchases
            $this->deactivateCapacityForInvoice($invoiceId);

            // Suspend subscription if exists
            if ($invoice->subscription_id) {
                $subscription = $invoice->subscription;
                if ($subscription->isActive()) {
                    app(SubscriptionService::class)->suspendSubscription($subscription->id, $reason);
                }
            }

            return $invoice->fresh();
        });
    }

    public function getInvoiceByNumber(string $invoiceNumber): ?Invoice
    {
        return Invoice::where('invoice_number', $invoiceNumber)->first();
    }

    public function getInvoicesByProvider(string $providerId): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('provider_id', $providerId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPendingInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        return Invoice::where('status', 'pending')
            ->where('due_date', '<=', Carbon::now())
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get active platform payment methods
     */
    public function getActivePaymentMethods(): \Illuminate\Database\Eloquent\Collection
    {
        return PlatformPaymentMethod::active()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get platform payment method by type
     */
    public function getPaymentMethodByType(string $type): ?PlatformPaymentMethod
    {
        return PlatformPaymentMethod::byType($type)->first();
    }

    /**
     * Initialize payment for invoice based on payment method type
     */
    public function initializePayment(string $invoiceId, string $paymentMethodType): array
    {
        $paymentMethod = $this->getPaymentMethodByType($paymentMethodType);

        if (!$paymentMethod || !$paymentMethod->is_active) {
            throw new \Exception('Payment method not available');
        }

        $invoice = Invoice::findOrFail($invoiceId);

        // Check verification mode and route accordingly
        switch ($paymentMethod->verification_mode) {
            case 'manual':
                // Mark as pending, awaiting admin verification
                $updatedInvoice = $this->markAsPending($invoiceId, $paymentMethod->type);
                return [
                    'status' => 'pending_verification',
                    'message' => 'Payment pending manual verification',
                    'invoice' => $updatedInvoice,
                    'payment_method' => $paymentMethod,
                    'details' => $paymentMethod->details,
                ];

            case 'automatic':
                // Would integrate with payment gateway API
                return [
                    'status' => 'requires_gateway',
                    'message' => 'Payment gateway integration required',
                    'invoice' => $invoice,
                    'payment_method' => $paymentMethod,
                ];

            case 'webhook':
                // Would integrate with webhook-based payment
                return [
                    'status' => 'requires_webhook',
                    'message' => 'Webhook-based payment integration required',
                    'invoice' => $invoice,
                    'payment_method' => $paymentMethod,
                ];

            default:
                throw new \Exception('Unknown verification mode');
        }
    }

    /**
     * Verify manual payment (admin action)
     */
    public function verifyManualPayment(string $invoiceId, array $verificationData): Invoice
    {
        return DB::transaction(function () use ($invoiceId, $verificationData) {
            $invoice = Invoice::findOrFail($invoiceId);

            if ($invoice->status === 'paid') {
                throw new \Exception('Invoice is already paid');
            }

            // Find pending payment
            $payment = InvoicePayment::where('invoice_id', $invoiceId)
                ->where('status', 'pending')
                ->first();

            if (!$payment) {
                // Create payment record
                $payment = InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'status' => 'pending',
                    'currency' => $invoice->currency,
                    'amount' => $invoice->total,
                    'payment_method' => $invoice->payment_method,
                    'metadata' => [
                        'awaiting_confirmation' => true,
                    ],
                ]);
            }

            // Update payment to completed
            $payment->update([
                'status' => 'completed',
                'processed_at' => Carbon::now(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'verified_by' => $verificationData['verified_by'] ?? null,
                    'verification_date' => Carbon::now()->toIso8601String(),
                    'reference' => $verificationData['reference'] ?? null,
                    'notes' => $verificationData['notes'] ?? null,
                ]),
            ]);

            // Mark invoice as paid
            $invoice->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
            ]);

            // Activate subscription if exists
            if ($invoice->subscription_id) {
                $subscription = $invoice->subscription;
                if ($subscription->status === 'trialing' || $subscription->status === 'past_due') {
                    app(SubscriptionService::class)->activateSubscription($subscription->id);
                }
            }

            return $invoice->fresh();
        });
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = Carbon::now()->format('Ymd');
        $sequence = Invoice::whereDate('created_at', Carbon::today())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    /**
     * Deactivate capacity records associated with a failed invoice.
     * This ensures that failed add-on purchases don't contribute to capacity.
     */
    private function deactivateCapacityForInvoice(string $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice || !$invoice->provider_id) {
            return;
        }

        // Find capacity records linked to this invoice
        $capacities = SalonCapacity::forProvider($invoice->provider_id)
            ->whereJsonContains('metadata', ['first_purchase_invoice_id' => $invoiceId])
            ->orWhereJsonContains('metadata', ['last_purchase_invoice_id' => $invoiceId])
            ->get();

        foreach ($capacities as $capacity) {
            // If this was the first purchase, deactivate the entire record
            if (($capacity->metadata['first_purchase_invoice_id'] ?? null) === $invoiceId) {
                $capacity->update(['is_active' => false]);
            } else {
                // If this was a subsequent purchase, decrement the capacity
                $capacity->decrement('additional_capacity', $capacity->metadata['last_purchase_units'] ?? 0);
            }
        }
    }
}
