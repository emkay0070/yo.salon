<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\PlatformProduct;
use App\Models\UsageEvent;
use App\Models\SalonCapacity;
use App\Services\InvoiceService;
use App\Services\Payments\PaymentManager;
use App\Services\CapabilityResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddOnPurchaseController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly PaymentManager $paymentManager,
        private readonly CapabilityResolver $capabilityResolver
    ) {}

    /**
     * List all available add-on products the salon can purchase.
     * Prices are resolved from the default price book (UGX for Uganda).
     *
     * GET /api/v1/billing/add-ons
     */
    public function index(Request $request): JsonResponse
    {
        $products = PlatformProduct::active()
            ->where(function ($query) {
                $query->where('type', 'prepaid')
                      ->orWhere('type', 'add_on');
            })
            ->ordered()
            ->with(['prices' => function ($q) {
                $q->active()
                  ->whereHas('priceBook', fn ($pb) => $pb->where('is_default', true)->active());
            }])
            ->get()
            ->map(function ($product) {
                $price = $product->prices->first();
                return [
                    'code'          => $product->code,
                    'name'          => $product->name,
                    'description'   => $product->description,
                    'resource_code' => $product->resource_code,
                    'units'         => $product->units,
                    'type'          => $product->type,
                    'billing_model' => $product->billing_model,
                    'price'         => $price ? [
                        'amount'   => (float) $price->amount,
                        'currency' => $price->currency,
                        'formatted' => $price->formatted(),
                    ] : null,
                ];
            });

        return response()->json(['data' => $products]);
    }

    /**
     * Purchase an add-on product.
     * Flow: lookup product → validate → generate invoice → attempt payment → unlock quota
     *
     * POST /api/v1/billing/add-ons/purchase
     */
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_code'      => 'required|string|exists:platform_products,code',
            'payment_method_id' => 'required|uuid|exists:payment_methods,id',
        ]);

        $provider = auth()->user();

        $product = PlatformProduct::where('code', $validated['product_code'])
            ->where('is_active', true)
            ->firstOrFail();

        $paymentMethod = PaymentMethod::where('id', $validated['payment_method_id'])
            ->where('provider_id', $provider->provider_id ?? $provider->id)
            ->where('is_active', true)
            ->firstOrFail();

        // Resolve price from default price book
        $price = $product->priceFor('uganda_default');

        if (!$price) {
            return response()->json([
                'success' => false,
                'message' => 'No pricing available for this product in your region.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // ── Step 1: Draft invoice ─────────────────────────────────
            $invoice = $this->invoiceService->draftAddOnInvoice(
                providerId: $provider->provider_id ?? $provider->id,
                product: $product,
                price: $price
            );

            // ── Step 2: Freeze invoice ────────────────────────────────
            $invoice = $this->invoiceService->finalizeInvoice($invoice->id);

            // ── Step 3: Attempt payment ───────────────────────────────
            $gateway = $this->paymentManager->getProvider($paymentMethod->provider);

            if (!$gateway) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Payment provider '{$paymentMethod->provider}' is not available.",
                ], 422);
            }

            $result = $gateway->attemptInvoiceCollection($invoice, $paymentMethod);

            if (!$result->success) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $result->message ?? 'Payment failed. Please try again.',
                    'reference' => $result->providerReference,
                ], 402);
            }

            // ── Step 4: Mark invoice paid ─────────────────────────────
            $this->invoiceService->markAsPaid($invoice->id, $result->transactionId, $paymentMethod->provider);

            // ── Step 5: Create capacity record for purchased add-on ──
            $subscription = $provider->subscription ?? null;

            if ($subscription) {
                $providerId = $provider->provider_id ?? $provider->id;
                $salon = $provider->salon;
                
                // Check if capacity record exists for this resource
                $existingCapacity = SalonCapacity::forProvider($providerId)
                    ->forResource($product->resource_code)
                    ->first();

                if ($existingCapacity) {
                    // Increment existing capacity
                    $existingCapacity->increment('additional_capacity', $product->units);
                    $existingCapacity->update([
                        'is_active' => true,
                        'metadata' => array_merge(
                            $existingCapacity->metadata ?? [],
                            [
                                'last_purchase_invoice_id' => $invoice->id,
                                'last_purchase_date' => now()->toIso8601String(),
                                'last_purchase_units' => $product->units,
                            ]
                        )
                    ]);
                } else {
                    // Create new capacity record
                    SalonCapacity::create([
                        'provider_id' => $providerId,
                        'resource_code' => $product->resource_code,
                        'additional_capacity' => $product->units,
                        'is_active' => true,
                        'metadata' => [
                            'first_purchase_invoice_id' => $invoice->id,
                            'first_purchase_date' => now()->toIso8601String(),
                            'product_code' => $product->code,
                        ],
                    ]);
                }

                // Clear capability cache so new capacity is reflected immediately
                if ($salon) {
                    $this->capabilityResolver->clearCache($salon->id);
                }
            }

            // ── Step 6: Log the purchase as a usage event ─────────────
            UsageEvent::record(
                providerId:   $provider->provider_id ?? $provider->id,
                resourceCode: 'PURCHASE_' . $product->resource_code,
                units:        $product->units,
                metadata:     [
                    'product_code' => $product->code,
                    'invoice_id'   => $invoice->id,
                    'amount_paid'  => $invoice->total,
                    'currency'     => $invoice->currency,
                ],
                subscriptionId: $subscription?->id
            );

            DB::commit();

            Log::info('AddOnPurchase: completed', [
                'provider_id'  => $provider->provider_id ?? $provider->id,
                'product_code' => $product->code,
                'units'        => $product->units,
                'invoice_id'   => $invoice->id,
            ]);

            return response()->json([
                'success'    => true,
                'message'    => "{$product->name} purchased successfully. {$product->units} " . (isset($product->resource->unit_label) ? $product->resource->unit_label : 'units') . " have been added to your account.",
                'invoice_id' => $invoice->id,
                'units_added' => $product->units,
                'resource'   => $product->resource_code,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('AddOnPurchase: failed', [
                'provider_id'  => $provider->provider_id ?? $provider->id,
                'product_code' => $validated['product_code'],
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Purchase failed. Please try again.',
            ], 500);
        }
    }
}
