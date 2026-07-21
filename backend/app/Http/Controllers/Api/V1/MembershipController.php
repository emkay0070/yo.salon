<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PlanService;
use App\Services\SubscriptionService;
use App\Services\BillingService;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    protected PlanService $planService;
    protected SubscriptionService $subscriptionService;
    protected BillingService $billingService;
    protected UsageService $usageService;

    public function __construct(
        PlanService $planService,
        SubscriptionService $subscriptionService,
        BillingService $billingService,
        UsageService $usageService
    ) {
        $this->planService = $planService;
        $this->subscriptionService = $subscriptionService;
        $this->billingService = $billingService;
        $this->usageService = $usageService;
    }

    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'has_subscription' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        $usage = $this->usageService->getUsageBySubscription($subscription->id);
        $status = $this->subscriptionService->checkSubscriptionStatus($salonId);

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'usage' => $usage,
            'status' => $status,
        ]);
    }

    public function plans(): JsonResponse
    {
        $plans = $this->planService->getAllActivePlans();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    public function plan(string $id): JsonResponse
    {
        $plan = $this->planService->getPlanById($id);

        if (!$plan) {
            return response()->json([
                'message' => 'Plan not found',
            ], 404);
        }

        return response()->json([
            'plan' => $plan,
            'features' => $this->planService->getPlanFeatures($plan->id),
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $usage = $this->usageService->syncUsageWithActualData($subscription->id);
        $summary = $this->usageService->getUsageSummary($subscription->id);
        $limits = $this->usageService->checkUsageLimits($subscription->id);

        return response()->json([
            'usage' => $usage,
            'summary' => $summary,
            'limits' => $limits,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $invoices = $this->billingService->getInvoicesBySubscription($subscription->id);

        return response()->json([
            'invoices' => $invoices->load('payments'),
        ]);
    }

    public function invoice(string $id): JsonResponse
    {
        $invoice = $this->billingService->getInvoiceById($id);

        if (!$invoice) {
            return response()->json([
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'invoice' => $invoice->load(['subscription.plan', 'payments']),
        ]);
    }

    public function timeline(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $timeline = $this->billingService->getBillingTimeline($subscription->id);

        return response()->json([
            'timeline' => $timeline,
        ]);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $comparison = $this->planService->comparePlans(
            $subscription->plan_id,
            $request->plan_id
        );

        $subscription = $this->subscriptionService->changePlan(
            $subscription->id,
            $request->plan_id
        );

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'comparison' => $comparison,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $subscription = $this->subscriptionService->cancelSubscription(
            $subscription->id,
            $request->reason
        );

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'message' => 'Subscription cancelled successfully',
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        $subscription = $this->subscriptionService->getSubscriptionBySalon($salonId);

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        $subscription = $this->subscriptionService->resumeSubscription($subscription->id);

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'message' => 'Subscription resumed successfully',
        ]);
    }

    public function startTrial(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
        ]);

        $salonId = $request->attributes->get('salon_id');

        $existingSubscription = $this->subscriptionService->getSubscriptionBySalon($salonId);
        if ($existingSubscription) {
            return response()->json([
                'message' => 'Subscription already exists',
            ], 400);
        }

        $subscription = $this->subscriptionService->startTrial(
            $salonId,
            $request->plan_id,
            14
        );

        return response()->json([
            'subscription' => $subscription->load('plan'),
            'message' => 'Trial started successfully',
        ]);
    }
}
