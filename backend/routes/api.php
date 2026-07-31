<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SalonController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PaymentRequestController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\PortalAccountController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\FeatureFlagController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\CustomerBookingController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\ServicePackageController;
use App\Http\Controllers\Api\V1\GiftCardController;
use App\Http\Controllers\Api\V1\PulseController;
use App\Http\Controllers\Api\V1\CopilotController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\BrandExperienceController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\PlatformPaymentController;
use App\Http\Controllers\Api\V1\SalonPaymentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\BookingActivityController;
use App\Http\Controllers\Api\V1\DashboardController;

Route::prefix('v1')->group(function () {
    // Public webhook routes (no authentication required)
    Route::post('/webhooks/flutterwave', [WebhookController::class, 'handleFlutterwave']);
    Route::post('/webhooks/platform', [WebhookController::class, 'handlePlatformWebhook']);
    Route::post('/webhooks/salon', [WebhookController::class, 'handleSalonWebhook']);
    Route::post('/webhooks/mtn', [WebhookController::class, 'handleMTN']);

    // Public routes
    Route::get('/salons', [SalonController::class, 'index']);
    Route::get('/salons/{slug}', [SalonController::class, 'showBySlug']);
    Route::get('/salons/{slug}/services', [SalonController::class, 'services']);
    Route::get('/salons/{slug}/staff', [SalonController::class, 'staff']);
    Route::get('/salons/{slug}/brand-experience', [BrandExperienceController::class, 'showBySlug']);
    
    // Public booking route (Journey 4: Booking + Create Account)
    Route::post('/bookings/with-account', [BookingController::class, 'storeWithAccount']);
    
    // Public customer lookup
    Route::post('/customers/lookup', [CustomerController::class, 'lookup']);
    
    // Public payment methods (no auth required - for booking flow)
    Route::get('/salons/{slug}/payment-methods', [PaymentMethodController::class, 'getPublicMethods']);
    
    // Authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Portal account routes (public)
    Route::post('/portal/create', [PortalAccountController::class, 'store']);
    Route::post('/portal/login', [PortalAccountController::class, 'login']);
    Route::post('/portal/accept-invitation', [PortalAccountController::class, 'acceptInvitation']);
    // Invitations (public validation and acceptance)
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept']);

    // Portal protected routes (require portal authentication)
    Route::middleware(['auth:portal'])->group(function () {
        // Portal context middleware resolves salon through customer relationship
        Route::middleware(['portal.context'])->group(function () {

            // Portal home - returns everything needed for initial app load
            Route::get('/portal/context', [PortalAccountController::class, 'context']);
            Route::get('/portal/me', [PortalAccountController::class, 'me']);
            Route::get('/portal/home', [PortalAccountController::class, 'home']);
            Route::post('/portal/logout', [PortalAccountController::class, 'logout']);
            
            // Portal recent visits and last booking
            Route::get('/portal/recent-visits', [PortalAccountController::class, 'recentVisits']);
            Route::get('/portal/last-booking', [PortalAccountController::class, 'lastBooking']);
            
            // Portal offers
            Route::get('/portal/offers', [OfferController::class, 'customerOffers']);
            
            // Portal loyalty
            Route::get('/portal/loyalty', [LoyaltyController::class, 'summary']);
            Route::get('/portal/loyalty/history', [LoyaltyController::class, 'history']);
            Route::post('/portal/loyalty/redeem', [LoyaltyController::class, 'redeem']);
            
            // Portal booking
            Route::get('/portal/services', [ServiceController::class, 'indexForPortal']);
            Route::get('/portal/availability', [CustomerBookingController::class, 'availability']);
            Route::get('/portal/availability/dates', [CustomerBookingController::class, 'availableDates']);
            Route::get('/portal/availability/staff', [CustomerBookingController::class, 'availableStaff']);
            
            // Portal notifications
            Route::get('/portal/notifications', [NotificationController::class, 'index']);
            Route::post('/portal/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/portal/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
            
            // Portal booking activities
            Route::get('/portal/bookings/{id}/activities', [BookingActivityController::class, 'show']);
            Route::get('/portal/bookings', [CustomerBookingController::class, 'index']);
            Route::post('/portal/bookings', [CustomerBookingController::class, 'store']);
            Route::post('/portal/bookings/rebook/{id}', [CustomerBookingController::class, 'rebook']);
            Route::patch('/portal/bookings/{id}/cancel', [CustomerBookingController::class, 'cancel']);
            Route::post('/portal/bookings/{id}/reschedule', [CustomerBookingController::class, 'reschedule']);
            Route::get('/portal/bookings/upcoming', [CustomerBookingController::class, 'upcoming']);
            Route::get('/portal/bookings/history', [CustomerBookingController::class, 'history']);
            
            // Portal wallet
            Route::get('/portal/wallet', [WalletController::class, 'index']);
            Route::post('/portal/wallet/add-funds', [WalletController::class, 'addFunds']);
            Route::get('/portal/wallet/transactions', [WalletController::class, 'transactions']);
            
            // Portal packages
            Route::get('/portal/packages', [ServicePackageController::class, 'index']);
            Route::post('/portal/packages/purchase', [ServicePackageController::class, 'purchase']);
            Route::get('/portal/packages/my', [ServicePackageController::class, 'customerPackages']);
            
            // Portal gift cards
            Route::post('/portal/gift-cards/validate', [GiftCardController::class, 'validate']);
            Route::post('/portal/gift-cards/redeem', [GiftCardController::class, 'redeem']);
            Route::post('/portal/gift-cards/purchase', [GiftCardController::class, 'purchase']);
            Route::get('/portal/gift-cards/purchased', [GiftCardController::class, 'purchased']);
            Route::get('/portal/gift-cards/redeemed', [GiftCardController::class, 'redeemed']);
        });
    });

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Onboarding routes (for users in onboarding status)
        Route::prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'show']);
            Route::post('/draft', [OnboardingController::class, 'updateDraft']);
            Route::post('/complete', [OnboardingController::class, 'complete']);
        });

        // Auth routes (no salon context required)
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);

        // Protected routes requiring salon context
        Route::middleware(['salon.context'])->group(function () {
        // Pulse Operational Center
        Route::get('/pulse', [PulseController::class, 'index']);
        Route::get('/salons/check-slug', [SalonController::class, 'checkSlug']);
        Route::apiResource('salons', SalonController::class)->except(['index']);
        
        // Customers
        Route::apiResource('customers', CustomerController::class);
        
        // Bookings
        Route::apiResource('bookings', BookingController::class);
        Route::get('/salons/{salon}/bookings', [BookingController::class, 'bySalon']);
        Route::get('/customers/{customer}/bookings', [BookingController::class, 'byCustomer']);
        
        // Services
        Route::apiResource('services', ServiceController::class);
        Route::get('/salons/{salon}/services', [ServiceController::class, 'bySalon']);
        
        // Staff
        Route::apiResource('staff', StaffController::class);
        Route::get('/salons/{salon}/staff', [StaffController::class, 'bySalon']);
        
        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        
        // Booking activities
        Route::get('/bookings/{id}/activities', [BookingActivityController::class, 'show']);
        
        // Dashboard
        Route::get('/dashboard/live-stats', [DashboardController::class, 'liveStats']);
        
        // Profiles
        Route::apiResource('profiles', ProfileController::class);
        Route::get('/salons/{salon}/profiles', [ProfileController::class, 'bySalon']);

        // Payments — Methods
        Route::apiResource('payment-methods', PaymentMethodController::class);
        Route::post('/payment-methods/test-connection', [PaymentMethodController::class, 'testConnection']);
        Route::post('/payment-methods/{paymentMethod}/verify-credentials', [PaymentMethodController::class, 'verifyCredentials']);

        // Payments — Requests
        Route::apiResource('payment-requests', PaymentRequestController::class)->except(['update']);
        Route::patch('/payment-requests/{paymentRequest}/status', [PaymentRequestController::class, 'updateStatus']);
        Route::post('/payment-requests/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel']);
        Route::get('/payment-requests/{paymentRequest}/check-status', [PaymentRequestController::class, 'checkStatus']);

        // Payments — Platform (B2B - Subscription Payments)
        Route::prefix('payments/platform')->group(function () {
            Route::post('/initialize', [PlatformPaymentController::class, 'initializeSubscriptionPayment']);
            Route::post('/verify', [PlatformPaymentController::class, 'verifySubscriptionPayment']);
            Route::post('/refund', [PlatformPaymentController::class, 'refundSubscriptionPayment']);
            Route::get('/invoices/{invoiceId}/status', [PlatformPaymentController::class, 'getInvoicePaymentStatus']);
        });

        // Payments — Salon (B2C - Customer Booking Payments)
        Route::prefix('payments/salon')->group(function () {
            Route::post('/initialize', [SalonPaymentController::class, 'initializeBookingPayment']);
            Route::post('/verify', [SalonPaymentController::class, 'verifySalonPayment']);
            Route::post('/manual', [SalonPaymentController::class, 'recordManualPayment']);
            Route::post('/refund', [SalonPaymentController::class, 'refundSalonPayment']);
            Route::get('/bookings/{bookingId}/status', [SalonPaymentController::class, 'getBookingPaymentStatus']);
        });

        // Payments — Transactions
        Route::get('/transactions/summary', [TransactionController::class, 'summary']);
        Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);

        // Payments — Settlements
        Route::apiResource('settlements', SettlementController::class)->except(['destroy']);

        // Membership
        Route::get('/membership', [MembershipController::class, 'index']);
        Route::get('/membership/plans', [MembershipController::class, 'plans']);
        Route::get('/membership/plans/{id}', [MembershipController::class, 'plan']);
        Route::get('/membership/usage', [MembershipController::class, 'usage']);
        Route::get('/membership/invoices', [MembershipController::class, 'invoices']);
        Route::get('/membership/invoices/{id}', [MembershipController::class, 'invoice']);
        Route::get('/membership/timeline', [MembershipController::class, 'timeline']);
        Route::post('/membership/change-plan', [MembershipController::class, 'changePlan']);
        Route::post('/membership/cancel', [MembershipController::class, 'cancel']);
        Route::post('/membership/resume', [MembershipController::class, 'resume']);
        Route::post('/membership/start-trial', [MembershipController::class, 'startTrial']);

        // Analytics
        Route::get('/analytics', [AnalyticsController::class, 'index']);
        Route::get('/analytics/intelligence', [AnalyticsController::class, 'intelligence']);
        
        // AI Copilot
        Route::post('/copilot/chat', [CopilotController::class, 'chat']);
        
        // Portal account routes (legacy - deprecated in favor of explicit invites)
        Route::post('/portal/send-invitation', [PortalAccountController::class, 'sendInvitation']);
        
        // Invitations (admin creates them)
        Route::post('/invitations', [InvitationController::class, 'store']);
        
        // Feature Flags (admin)
        Route::get('/features', [FeatureFlagController::class, 'index']);
        Route::post('/features/enable', [FeatureFlagController::class, 'enable']);
        Route::post('/features/disable', [FeatureFlagController::class, 'disable']);
        Route::get('/features/policies', [FeatureFlagController::class, 'policies']);
        Route::post('/features/seed-policies', [FeatureFlagController::class, 'seedPolicies']);
        Route::get('/features/suggestions', [FeatureFlagController::class, 'suggestions']);
        
        // Offers (admin)
        Route::apiResource('offers', OfferController::class);
        
        // Loyalty (admin/internal)
        Route::post('/loyalty/award', [LoyaltyController::class, 'award']);
        
        // Brand Experience
        Route::get('/brand-experience', [BrandExperienceController::class, 'show']);
        Route::put('/brand-experience', [BrandExperienceController::class, 'update']);
        });
    });
});
