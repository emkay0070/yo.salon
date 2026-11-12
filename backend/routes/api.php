<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\V1\ScheduleExceptionController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PaymentRequestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PortalAccountController;
use App\Http\Controllers\SpecialistController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SpecialistFollowController;
use App\Http\Controllers\Api\V1\WebsiteConfigurationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\SpecialistPortal\OnboardingController as SpecialistOnboardingController;
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
use App\Http\Controllers\Api\V1\CustomerSpecialistController;
use App\Http\Controllers\SpecialistAssessmentController;
use App\Http\Controllers\Api\V1\SpecialistDiscoveryController;
use App\Http\Controllers\ServiceRecommendationController;
use App\Http\Controllers\SpecialistPortalController;
use App\Http\Controllers\Api\V1\BookingConfigurationController;
use App\Http\Controllers\Api\V1\BookingRulesController;
use App\Http\Controllers\Api\V1\BookingOrchestratorController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\PlatformPaymentController;
use App\Http\Controllers\Api\V1\BookingPaymentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\BookingActivityController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ScheduleMigrationController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\TimelineController;
use App\Http\Controllers\Api\V1\DiscoverController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\Api\V1\LocationServiceController;
use App\Http\Controllers\Api\V1\AddOnPurchaseController;
use App\Http\Controllers\Admin\PlatformPaymentController as AdminPlatformPaymentController;
use App\Http\Controllers\Admin\SeederController;
use App\Http\Controllers\Api\V1\BookingContextController;
use App\Http\Controllers\Api\V1\TransactionController;
// use App\Http\Controllers\Api\V1\SalonScheduleController; // Replaced with ProviderScheduleController
use App\Http\Controllers\Api\V1\ProviderScheduleController;
use App\Http\Controllers\Api\V1\AssignmentScheduleController;
use App\Http\Controllers\Api\V1\OperationalHealthController;
use App\Http\Controllers\Api\V1\PublicSpecialistController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\TestimonialController;
// use App\Http\Controllers\Api\V1\PublicSalonWebsiteController; // TODO: Controller does not exist
// use App\Http\Controllers\Api\V1\SalonCapabilityController; // TODO: Controller does not exist
use App\Http\Controllers\Api\V1\LedgerController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\BusinessCategoryController;
use App\Http\Controllers\Api\V1\ProfessionController;


// Public availability routes (no version prefix for frontend compatibility)
Route::get('/providers/{providerId}/availability', [AvailabilityController::class, 'index']);
Route::get('/providers/{providerId}/availability/batch', [AvailabilityController::class, 'batch']);
Route::get('/providers/{providerId}/availability/first-available', [AvailabilityController::class, 'firstAvailable']);
Route::post('/providers/{providerId}/slots/lock', [AvailabilityController::class, 'lockSlot']);
Route::delete('/providers/{providerId}/slots/lock/{lockId}', [AvailabilityController::class, 'releaseSlot']);

Route::prefix('v1')->group(function () {
    // Public webhook routes (no authentication required)
    Route::post('/webhooks/flutterwave', [WebhookController::class, 'handleFlutterwave']);
    Route::post('/webhooks/platform', [WebhookController::class, 'handlePlatformWebhook']);
    Route::post('/webhooks/salon', [WebhookController::class, 'handleSalonWebhook']);
    Route::post('/webhooks/mtn', [WebhookController::class, 'handleMTN']);
    
    // Disbursement webhooks
    Route::post('/webhooks/disbursement/mtn', [\App\Http\Controllers\Api\V1\Webhooks\DisbursementWebhookController::class, 'handleMtn']);
    Route::post('/webhooks/disbursement/airtel', [\App\Http\Controllers\Api\V1\Webhooks\DisbursementWebhookController::class, 'handleAirtel']);

    // Public routes (deprecated /salons routes - use /providers instead)
    Route::get('/salons', [ProviderController::class, 'index']);
    Route::get('/salons/{identifier}', [ProviderController::class, 'show'])->middleware('track.views')->where('identifier', '[a-z0-9-]+');
    Route::get('/salons/id/{id}', [ProviderController::class, 'show']);
    // These routes are deprecated - use the provider show endpoint which includes specialists/services
    Route::get('/salons/{slug}/services', [ProviderController::class, 'show']);
    Route::get('/salons/{slug}/specialists', [ProviderController::class, 'show']);
    // @deprecated - Use /specialists instead
    Route::get('/salons/{slug}/staff', [ProviderController::class, 'show']);
    Route::get('/salons/{slug}/brand-experience', [BrandExperienceController::class, 'showBySlug']);

    // Slug availability check (public, no auth required)
    Route::get('/slug-availability', [ProviderController::class, 'checkSlug']);

    // Public: Resolved booking policy for a service at a specific branch
    Route::get('/providers/{provider}/services/{service}/policy', [LocationServiceController::class, 'policy']);
    
    // Public: Context-aware payment instructions (calculates dynamic pricing & constraints)
    Route::get('/booking-context/payment', [BookingContextController::class, 'paymentInstructions']);

    // Public specialist routes (global search)
    Route::get('/specialists', [SpecialistController::class, 'index']);
    Route::get('/specialists/{idOrHandle}', [SpecialistController::class, 'show'])->middleware('track.views');

    // Public provider routes (marketplace search)
    Route::get('/providers', [ProviderController::class, 'index']);
    // Provider sub-resources - must come BEFORE generic /providers/{id} route
    Route::get('/providers/{provider}/assignable-specialists', [ProviderController::class, 'assignableSpecialists']);
    Route::get('/providers/{slug}/specialists', [ProviderController::class, 'show']);
    Route::get('/providers/{slug}/services', [ProviderController::class, 'show']);
    // Generic provider show route (must come last)
    Route::get('/providers/{id}', [ProviderController::class, 'show']);

    // Public service routes (for portal/discover)
    Route::get('/services/{id}', [ServiceController::class, 'show']);

    // Public review routes (scoped by subject)
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Specialist follow/favorite (authenticated - requires portal auth)
    Route::middleware(['auth:portal'])->group(function () {
        Route::get('/specialists/{id}/follow-status', [SpecialistFollowController::class, 'status']);
        Route::post('/specialists/{id}/follow', [SpecialistFollowController::class, 'follow']);
        Route::post('/specialists/{id}/unfollow', [SpecialistFollowController::class, 'unfollow']);
        Route::post('/specialists/{id}/favorite', [SpecialistFollowController::class, 'favorite']);
        Route::post('/specialists/{id}/unfavorite', [SpecialistFollowController::class, 'unfavorite']);
    });

    // Public website configuration routes
    
    // Specialist portal routes (public - no auth required for login/register)
    Route::prefix('specialist-portal')->group(function () {
        Route::post('/login', [SpecialistPortalController::class, 'login']);
        Route::post('/register', [SpecialistPortalController::class, 'register']);
        // Craft taxonomy for service categorization (public - used by both specialist and owner dashboards)
        Route::get('/craft/taxonomy', [SpecialistPortalController::class, 'getCraftTaxonomy']);
    });

    // Protected specialist portal routes (require specialist auth)
    Route::middleware(['auth:specialist'])->group(function () {
        Route::prefix('specialist-portal')->group(function () {
            Route::get('/context', [SpecialistPortalController::class, 'context']);
            Route::get('/dashboard/stats', [SpecialistPortalController::class, 'dashboardStats']);
            Route::post('/logout', [SpecialistPortalController::class, 'logout']);
            Route::get('/profile', [SpecialistPortalController::class, 'getProfile']);
            Route::put('/profile', [SpecialistPortalController::class, 'updateProfile']);
            Route::get('/customers', [SpecialistPortalController::class, 'getCustomers']);
            Route::post('/customers/invitations', [SpecialistPortalController::class, 'sendInvitation']);
            Route::get('/customers/invitations/stats', [SpecialistPortalController::class, 'getInvitationStats']);
            Route::get('/customers/invitations/pending', [SpecialistPortalController::class, 'getPendingInvitations']);
            Route::post('/customers/invitations/accept', [SpecialistPortalController::class, 'acceptInvitation']);
            Route::post('/customers/invitations/decline', [SpecialistPortalController::class, 'declineInvitation']);
            Route::get('/bookings', [SpecialistPortalController::class, 'getBookings']);
            Route::get('/calendar', [SpecialistPortalController::class, 'calendarData']);
            Route::get('/customers/{customerId}/assessment', [SpecialistPortalController::class, 'getCustomerForAssessment']);
            Route::get('/customers/{customerId}/details', [SpecialistPortalController::class, 'getCustomerDetails']);
            Route::get('/career', [SpecialistPortalController::class, 'getCareerData']);
            Route::get('/journey', [SpecialistPortalController::class, 'journey']);
            Route::get('/craft', [SpecialistPortalController::class, 'getCraftData']);
            Route::get('/earnings', [SpecialistPortalController::class, 'getEarnings']);
            Route::get('/availability', [SpecialistPortalController::class, 'getAvailability']);
            Route::put('/availability', [SpecialistPortalController::class, 'updateAvailability']);
            
            // Payment Profiles
            Route::get('/payment-profiles', [\App\Http\Controllers\Api\V1\SpecialistPaymentProfileController::class, 'index']);
            Route::post('/payment-profiles', [\App\Http\Controllers\Api\V1\SpecialistPaymentProfileController::class, 'store']);
            Route::put('/payment-profiles/{id}', [\App\Http\Controllers\Api\V1\SpecialistPaymentProfileController::class, 'update']);
            Route::delete('/payment-profiles/{id}', [\App\Http\Controllers\Api\V1\SpecialistPaymentProfileController::class, 'destroy']);
            
            // Goal CRUD
            Route::post('/goals', [SpecialistPortalController::class, 'createGoal']);
            Route::get('/goals/{goalId}', [SpecialistPortalController::class, 'getGoal']);
            Route::put('/goals/{goalId}', [SpecialistPortalController::class, 'updateGoal']);
            Route::delete('/goals/{goalId}', [SpecialistPortalController::class, 'deleteGoal']);
            Route::post('/goals/{goalId}/activate', [SpecialistPortalController::class, 'activateGoal']);
            Route::post('/goals/{goalId}/pause', [SpecialistPortalController::class, 'pauseGoal']);
            Route::post('/goals/{goalId}/complete', [SpecialistPortalController::class, 'completeGoal']);
            // Invitation CRUD
            Route::post('/invitations', [SpecialistPortalController::class, 'createInvitation']);
            // Get Provider services for Specialist to attach (Employee/Contractor flow)
            Route::get('/craft/services/{providerSlug}', [SpecialistPortalController::class, 'getProviderServices']);
            // Attach an existing Provider service to the Specialist (Employee/Contractor flow)
            Route::post('/craft/services', [SpecialistPortalController::class, 'attachService']);
            // Create a brand new service under a Provider the Specialist controls (Owner/Manager flow)
            Route::post('/craft/services/create', [SpecialistPortalController::class, 'createAndAttachService']);
            // Update a service (Owner/Manager only)
            Route::put('/craft/services/{serviceId}', [SpecialistPortalController::class, 'updateService']);
            // Expertise CRUD
            Route::post('/craft/expertise', [SpecialistPortalController::class, 'createExpertise']);
            Route::put('/craft/expertise/{expertiseId}', [SpecialistPortalController::class, 'updateExpertise']);
            Route::delete('/craft/expertise/{expertiseId}', [SpecialistPortalController::class, 'deleteExpertise']);
            
            // Capabilities (subscription/plan features)
            Route::get('/capabilities', [SpecialistPortalController::class, 'capabilities']);
            
            // Verification routes
            Route::get('/verification/status', [PublicSpecialistController::class, 'verificationStatus']);
            Route::post('/verification/submit', [PublicSpecialistController::class, 'submitVerification']);
            
            // Career events routes
            Route::get('/career-events', [PublicSpecialistController::class, 'careerEvents']);
            Route::post('/career-events', [PublicSpecialistController::class, 'createCareerEvent']);
            
            // Earnings routes
            Route::get('/earnings', [PublicSpecialistController::class, 'earnings']);

            // Onboarding routes
            Route::prefix('onboarding')->group(function () {
                Route::get('/status', [SpecialistOnboardingController::class, 'status']);
                Route::put('/identity', [SpecialistOnboardingController::class, 'updateIdentity']);
                Route::put('/craft', [SpecialistOnboardingController::class, 'updateCraft']);
                Route::post('/verification', [SpecialistOnboardingController::class, 'submitVerification']);
                Route::put('/work-preferences', [SpecialistOnboardingController::class, 'updateWorkPreferences']);
                Route::put('/availability', [SpecialistOnboardingController::class, 'updateAvailability']);
                Route::post('/complete', [SpecialistOnboardingController::class, 'complete']);
            });

            // Phase 2: Assessments (specialist creates for their clients)
            Route::post('/customers/{customerId}/assessments', [SpecialistPortalController::class, 'createAssessment']);
            Route::get('/customers/{customerId}/assessments', [SpecialistPortalController::class, 'getAssessments']);

            // Phase 2: Notes (specialist creates for their clients)
            Route::post('/customers/{customerId}/notes', [SpecialistPortalController::class, 'createNote']);
            Route::get('/customers/{customerId}/notes', [SpecialistPortalController::class, 'getNotes']);

            // Phase 3: Appointment status transitions
            Route::patch('/bookings/{bookingId}/status', [SpecialistPortalController::class, 'updateBookingStatus']);

            // Live Session Management
            Route::post('/sessions/{bookingServiceId}/start', [\App\Http\Controllers\SessionController::class, 'start']);
            Route::post('/sessions/{bookingServiceId}/complete', [\App\Http\Controllers\SessionController::class, 'complete']);
            Route::post('/sessions/{bookingServiceId}/cancel', [\App\Http\Controllers\SessionController::class, 'cancel']);

            // No-show detection (polling endpoint)
            Route::get('/appointments/attention', [SpecialistPortalController::class, 'appointmentsNeedingAttention']);
        });
    });

    // Public website configuration routes
    Route::get('/website-config/{type}/{id}', [WebsiteConfigurationController::class, 'show']);

    // Authenticated route to get staff by provider ID (for assign staff modal)
    Route::get('/staff/by-provider/{providerId}', [StaffController::class, 'byProvider'])->middleware('auth:sanctum');

    // Public provider website (slug-based)
    // Route::get('/providers/{slug}/website', [PublicSalonWebsiteController::class, 'show'])->where('slug', '[a-z0-9-]+'); // TODO: Controller does not exist

    // Public testimonials
    Route::get('/providers/{slug}/testimonials', [TestimonialController::class, 'publicIndex'])->where('slug', '[a-z0-9-]+');

    // Reference data routes (public - for dropdowns, options, etc.)
    Route::get('/reference-data', [ReferenceDataController::class, 'index']);
    Route::get('/reference-data/{category}', [ReferenceDataController::class, 'getByCategory']);
    Route::post('/reference-data/categories', [ReferenceDataController::class, 'getCategories']);

    // Business categories (public - for onboarding)
    Route::get('/business-categories', [BusinessCategoryController::class, 'index']);
    Route::get('/business-categories/{slug}', [BusinessCategoryController::class, 'show']);

    // Professions (public - for onboarding)
    Route::get('/professions', [ProfessionController::class, 'index']);

    // Service templates (public - for onboarding)
    Route::get('/service-templates', [\App\Http\Controllers\Api\V1\ServiceTemplateController::class, 'index']);

    // Add-ons (public - list available products)
    Route::get('/add-ons', [AddOnPurchaseController::class, 'index']);
    Route::get('/billing/add-ons', [AddOnPurchaseController::class, 'index']);

    // Public staff routes (for portal/discover)
    Route::get('/staff/{id}', [StaffController::class, 'show']);

    // Universal search routes
    Route::get('/search', [SearchController::class, 'universal']);
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);

    // Discover routes (featured/trending content)
    Route::get('/discover/featured', [DiscoverController::class, 'featured']);
    Route::get('/discover/trending', [DiscoverController::class, 'trending']);

    // Public booking route (Journey 4: Booking + Create Account)
    Route::post('/bookings/with-account', [BookingController::class, 'storeWithAccount']);
    
    // Public customer lookup
    Route::post('/customers/lookup', [CustomerController::class, 'lookup']);
    
    // Find booking-safe customer details (for "Find my details" booking convenience)
    Route::post('/customers/find-details', [CustomerController::class, 'findDetails']);
    
    // Public payment methods (no auth required - for booking flow)
    Route::get('/providers/{slug}/payment-methods', [PaymentMethodController::class, 'getPublicMethods']);
    
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
            Route::put('/portal/profile', [PortalAccountController::class, 'updateProfile']);
            Route::get('/portal/grooming-profile', [PortalAccountController::class, 'getGroomingProfile']);
            Route::put('/portal/grooming-profile', [PortalAccountController::class, 'updateGroomingProfile']);

            // Portal recent visits and last booking
            Route::get('/portal/recent-visits', [PortalAccountController::class, 'recentVisits']);
            Route::get('/portal/last-booking', [PortalAccountController::class, 'lastBooking']);

            // Portal reviews
            Route::get('/portal/specialists/{specialistId}/reviews', [PortalAccountController::class, 'specialistReviews']);
            Route::post('/portal/reviews', [PortalAccountController::class, 'createReview']);
            Route::get('/portal/reviews/my', [PortalAccountController::class, 'myReviews']);

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
            Route::post('/portal/bookings', [CustomerBookingController::class, 'store']);
            Route::get('/portal/bookings', [CustomerBookingController::class, 'index']);
            Route::get('/portal/bookings/{id}', [CustomerBookingController::class, 'show']);
            Route::post('/portal/bookings/{id}/cancel', [CustomerBookingController::class, 'cancel']);
            Route::post('/portal/bookings/{id}/reschedule', [CustomerBookingController::class, 'reschedule']);
            Route::get('/portal/bookings/{id}/reschedule-options', [CustomerBookingController::class, 'rescheduleOptions']);

            // Portal loyalty
            Route::get('/portal/loyalty/balance', [LoyaltyController::class, 'getBalance']);
            Route::get('/portal/loyalty/history', [LoyaltyController::class, 'getHistory']);
            Route::post('/portal/loyalty/redeem', [LoyaltyController::class, 'redeem']);

            // Specialist follow/favorite (authenticated)
            Route::post('/specialists/{id}/follow', [SpecialistFollowController::class, 'follow']);
            Route::post('/specialists/{id}/unfollow', [SpecialistFollowController::class, 'unfollow']);
            Route::post('/specialists/{id}/favorite', [SpecialistFollowController::class, 'favorite']);
            Route::post('/specialists/{id}/unfavorite', [SpecialistFollowController::class, 'unfavorite']);
            
            // Portal collections
            Route::get('/portal/collections', [CollectionController::class, 'index']);
            Route::post('/portal/collections', [CollectionController::class, 'store']);
            Route::get('/portal/collections/{id}', [CollectionController::class, 'show']);
            Route::put('/portal/collections/{id}', [CollectionController::class, 'update']);
            Route::delete('/portal/collections/{id}', [CollectionController::class, 'destroy']);
            
            // Portal timeline
            Route::get('/portal/timeline', [TimelineController::class, 'index']);
            Route::get('/portal/timeline/{id}', [TimelineController::class, 'show']);
            Route::post('/portal/timeline/{id}/photos', [TimelineController::class, 'uploadPhoto']);
            Route::put('/portal/timeline/{id}', [TimelineController::class, 'update']);
            Route::delete('/portal/timeline/{id}', [TimelineController::class, 'destroy']);
            
            // Portal booking activities
            Route::get('/portal/bookings/{id}/activities', [BookingActivityController::class, 'show']);
            Route::get('/portal/bookings', [CustomerBookingController::class, 'index']);
            Route::post('/portal/bookings', [CustomerBookingController::class, 'store']);
            Route::post('/portal/bookings/rebook/{id}', [CustomerBookingController::class, 'rebook']);
            Route::patch('/portal/bookings/{id}/cancel', [CustomerBookingController::class, 'cancel']);
            Route::post('/portal/bookings/{id}/reschedule', [CustomerBookingController::class, 'reschedule']);
            Route::get('/portal/bookings/upcoming', [CustomerBookingController::class, 'upcoming']);
            Route::get('/portal/bookings/history', [CustomerBookingController::class, 'history']);
            
            // Portal wallet (loyalty points only)
            Route::get('/portal/wallet', [WalletController::class, 'index'])->middleware('throttle:60,1');
            Route::get('/portal/wallet/transactions', [WalletController::class, 'transactions'])->middleware('throttle:60,1');
            Route::post('/portal/wallet/promo/apply', [WalletController::class, 'applyPromo'])->middleware('throttle:10,1');
            
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
            Route::post('/portal/gift-cards/send', [GiftCardController::class, 'send']);
            
            // Portal specialist relationships
            Route::get('/portal/specialists/followed', [CustomerSpecialistController::class, 'followed']);
            Route::get('/portal/specialists/favorites', [CustomerSpecialistController::class, 'favorites']);
            Route::post('/portal/specialists/{id}/follow', [CustomerSpecialistController::class, 'toggleFollow']);
            Route::post('/portal/specialists/{id}/favorite', [CustomerSpecialistController::class, 'toggleFavorite']);
            Route::post('/portal/specialists/{id}/rating', [CustomerSpecialistController::class, 'setRating']);
            Route::get('/portal/specialists/{id}/relationship', [CustomerSpecialistController::class, 'getRelationship']);
            
            // Portal - Specialist assessments and notes
            Route::get('/portal/assessments/pending', [SpecialistAssessmentController::class, 'getPendingAssessments']);
            Route::post('/portal/assessments/{assessmentId}/review', [SpecialistAssessmentController::class, 'reviewAssessment']);
            Route::post('/portal/notes/{noteId}/view', [SpecialistAssessmentController::class, 'markNoteViewed']);
            Route::get('/portal/timeline', [SpecialistAssessmentController::class, 'getCustomerTimeline']);
            Route::get('/portal/recommendations', [SpecialistAssessmentController::class, 'getPendingRecommendations']);
            Route::post('/portal/recommendations/{recommendationId}/respond', [SpecialistAssessmentController::class, 'respondToRecommendation']);
            
            // Portal - Service recommendations
            Route::get('/portal/service-recommendations', [ServiceRecommendationController::class, 'getMyRecommendations']);
            Route::get('/portal/specialist-recommendations', [ServiceRecommendationController::class, 'getMySpecialistRecommendations']);
        });
    });

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Schedule Migration
        Route::prefix('schedule-migration')->group(function () {
            Route::get('/status', [ScheduleMigrationController::class, 'checkStatus']);
            Route::post('/migrate', [ScheduleMigrationController::class, 'migrate']);
        });

        // Onboarding routes (for users in onboarding status)
        Route::prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'show']);
            Route::post('/draft', [OnboardingController::class, 'updateDraft']);
            Route::post('/complete', [OnboardingController::class, 'complete']);
        });

        // Auth routes (no salon context required)
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/user', [AuthController::class, 'user']);

        // User profile routes (no salon context required)
        Route::get('/me', [UserController::class, 'me']);
        Route::patch('/me', [UserController::class, 'updateMe']);
        Route::post('/me/profile-photo', [UserController::class, 'uploadProfilePhoto']);
        Route::delete('/me/profile-photo', [UserController::class, 'removeProfilePhoto']);

        // Resources (uses provider authorization, not salon context)
        Route::apiResource('resources', ResourceController::class);

        // Provider-Specialist relationships - use TeamController instead
        // Route::get('/providers/{providerId}/specialists', [TeamController::class, 'index']);
        // Route::get('/providers/{providerId}/specialists/{specialistId}', [TeamController::class, 'show']);

        // Provider logo upload (no provider context required - uses route parameter)
        Route::post('/providers/{provider}/logo', [ProviderController::class, 'update']);
        Route::delete('/providers/{provider}/logo', [ProviderController::class, 'update']);

        // Protected routes requiring provider context
        Route::middleware(['provider.context', 'location.context'])->group(function () {
        // Pulse Operational Center
        Route::get('/pulse', [PulseController::class, 'index']);
        Route::apiResource('providers', ProviderController::class)->except(['index']);
        
        // Provider Capabilities (plan features, quotas, credits)
        // Route::get('/providers/{provider}/capabilities', [SalonCapabilityController::class, 'show']); // TODO: Controller does not exist
        // Route::get('/providers/{provider}/capabilities/feature/{featureCode}', [SalonCapabilityController::class, 'checkFeature']); // TODO: Controller does not exist
        // Route::get('/providers/{provider}/capabilities/quota/{resourceCode}', [SalonCapabilityController::class, 'getQuota']); // TODO: Controller does not exist
        // Route::get('/providers/{provider}/capabilities/credits/{creditCode}', [SalonCapabilityController::class, 'getCredits']); // TODO: Controller does not exist

        // Note: assignable-specialists is in the public section for customer booking flow
        // Provider Operating Hours
        Route::get('/providers/{provider}/schedules', [ProviderScheduleController::class, 'index']);
        Route::put('/providers/{provider}/schedules', [ProviderScheduleController::class, 'update']);
        Route::post('/providers/{provider}/schedules/publish', [ProviderScheduleController::class, 'publish']);

        // Provider Business Hours (for single-location providers)
        Route::get('/providers/{provider}/schedules', [ProviderScheduleController::class, 'index']);
        Route::put('/providers/{provider}/schedules', [ProviderScheduleController::class, 'update']);
        Route::post('/providers/{provider}/schedules/publish', [ProviderScheduleController::class, 'publish']);

        // Operational Health
        Route::get('/locations/{locationId}/health', [OperationalHealthController::class, 'branchHealth']);
        Route::get('/providers/{providerId}/health', [OperationalHealthController::class, 'providerHealth']);

        // Assignment Schedules (per-specialist hours)
        Route::get('/locations/{locationId}/specialists/{specialistId}/schedule', [AssignmentScheduleController::class, 'index']);
        Route::put('/locations/{locationId}/specialists/{specialistId}/schedule', [AssignmentScheduleController::class, 'update']);
        Route::post('/locations/{locationId}/specialists/{specialistId}/schedule/publish', [AssignmentScheduleController::class, 'publish']);

        // Schedule Exceptions (Holidays, Vacations, Closures, Training…)
        Route::get('/locations/{locationId}/schedule-exceptions',     [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'index']);
        Route::post('/locations/{locationId}/schedule-exceptions',    [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'store']);
        Route::put('/locations/{locationId}/schedule-exceptions/{id}',   [\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'update']);
        Route::delete('/locations/{locationId}/schedule-exceptions/{id}',[\App\Http\Controllers\Api\V1\ScheduleExceptionController::class, 'destroy']);
        
        // Team — unified identity for all provider payees (staff + employed specialists + independent specialists)
        Route::get('/providers/{provider}/team', [TeamController::class, 'index']);
        Route::get('/providers/{provider}/team/{memberId}/payment-profile', [\App\Http\Controllers\Api\V1\TeamPaymentProfileController::class, 'show']);

        // Compensation Management (Manager access)
        Route::prefix('providers/{provider}/compensation')->group(function () {
            Route::get('policies', [\App\Http\Controllers\Api\V1\Compensation\CompensationPolicyController::class, 'index']);
            Route::post('policies', [\App\Http\Controllers\Api\V1\Compensation\CompensationPolicyController::class, 'store']);
            
            Route::get('earnings', [\App\Http\Controllers\Api\V1\Compensation\CompensationEarningController::class, 'index']);
            
            Route::get('periods', [\App\Http\Controllers\Api\V1\Compensation\CompensationPeriodController::class, 'index']);
            Route::get('periods/{period}', [\App\Http\Controllers\Api\V1\Compensation\CompensationPeriodController::class, 'show']);
            Route::post('periods/{period}/close', [\App\Http\Controllers\Api\V1\Compensation\CompensationPeriodController::class, 'close']);
            
            Route::post('periods/{period}/adjustments', [\App\Http\Controllers\Api\V1\Compensation\CompensationAdjustmentController::class, 'store']);
            
            Route::get('payables', [\App\Http\Controllers\Api\V1\Compensation\CompensationPayableController::class, 'index']);
            Route::post('settlements/{settlement}/payout', [\App\Http\Controllers\Api\V1\Compensation\CompensationPayoutController::class, 'store']);
            Route::get('settlements/{settlement}/payout-readiness', [\App\Http\Controllers\Api\V1\Compensation\CompensationPayoutController::class, 'readiness']);
        });


        // Customers
        Route::apiResource('customers', CustomerController::class);
        
        // Invitations (authenticated — create requires salon context)
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
        
        // Bookings
        Route::get('/bookings/needs-resolution', [BookingController::class, 'needsResolution']);
        Route::apiResource('bookings', BookingController::class);
        Route::post('/bookings/{booking}/resolve', [BookingController::class, 'resolve']);
        Route::get('/providers/{provider}/bookings', [BookingController::class, 'byProvider']);
        Route::get('/customers/{customer}/bookings', [BookingController::class, 'byCustomer']);

        // Operational Floor Lifecycle
        Route::post('/bookings/{booking}/arrive', [\App\Http\Controllers\Api\V1\FloorController::class, 'arrive']);
        Route::post('/bookings/{booking}/wait', [\App\Http\Controllers\Api\V1\FloorController::class, 'wait']);
        Route::post('/bookings/{booking}/services/{bookingServiceId}/start', [\App\Http\Controllers\Api\V1\FloorController::class, 'startSession']);
        Route::post('/bookings/{booking}/services/{bookingServiceId}/complete', [\App\Http\Controllers\Api\V1\FloorController::class, 'completeSession']);
        
        Route::post('/specialists/{specialist}/breaks', [\App\Http\Controllers\Api\V1\FloorController::class, 'startBreak']);
        Route::post('/specialists/{specialist}/breaks/end', [\App\Http\Controllers\Api\V1\FloorController::class, 'endBreak']);
        
        Route::get('/floor/queue', [\App\Http\Controllers\Api\V1\FloorController::class, 'queue']);
        Route::get('/floor/live', [\App\Http\Controllers\Api\V1\FloorController::class, 'live']);
        Route::get('/floor/snapshot', [\App\Http\Controllers\Api\V1\FloorController::class, 'snapshot']);
        
        // Services
        Route::apiResource('services', ServiceController::class);
        Route::get('/providers/{provider}/services', [ServiceController::class, 'byProvider']);

        // Branch-level service management (location_service pivot)
        // Controls which services each branch offers and allows price/duration overrides
        Route::get('/locations/{location}/branch-services',                    [LocationServiceController::class, 'index']);
        Route::post('/locations/{location}/branch-services/{service}',         [LocationServiceController::class, 'attach']);
        Route::patch('/locations/{location}/branch-services/{service}',        [LocationServiceController::class, 'update']);
        Route::delete('/locations/{location}/branch-services/{service}',       [LocationServiceController::class, 'detach']);
        
        // Staff
        Route::apiResource('staff', StaffController::class);

        // Specialists (global)
        Route::apiResource('specialists', SpecialistController::class)->except(['index', 'show']);
        
        // Specialist discovery (for salons to hire)
        Route::get('/specialists/discovery/search', [SpecialistDiscoveryController::class, 'search']);
        Route::get('/specialists/discovery/available', [SpecialistDiscoveryController::class, 'available']);
        Route::get('/specialists/discovery/{id}', [SpecialistDiscoveryController::class, 'show']);
        Route::post('/specialists/discovery/{id}/invite', [SpecialistDiscoveryController::class, 'invite']);
        Route::get('/specialists/discovery/{id}/invitations', [SpecialistDiscoveryController::class, 'invitations']);
        Route::post('/specialists/discovery/{invitationId}/respond', [SpecialistDiscoveryController::class, 'respondToInvitation']);

        // Providers (marketplace)
        Route::apiResource('providers', ProviderController::class);
        
        // Website Configuration
        Route::get('/booking-configuration', [BookingConfigurationController::class, 'getConfiguration']);
        Route::get('/providers/{id}/booking-config', [BookingConfigurationController::class, 'getProviderConfig']);
        Route::put('/providers/{id}/booking-config', [BookingConfigurationController::class, 'updateProviderConfig']);
        Route::post('/providers/{id}/booking-config/reset', [BookingConfigurationController::class, 'resetProviderConfig']);
        Route::get('/locations/{id}/booking-config', [BookingConfigurationController::class, 'getLocationConfig']);
        Route::put('/locations/{id}/booking-config', [BookingConfigurationController::class, 'updateLocationConfig']);
        Route::post('/locations/{id}/booking-config/reset', [BookingConfigurationController::class, 'resetLocationConfig']);
        Route::get('/services/{id}/booking-config', [BookingConfigurationController::class, 'getServiceConfig']);
        Route::put('/services/{id}/booking-config', [BookingConfigurationController::class, 'updateServiceConfig']);
        Route::post('/services/{id}/booking-config/reset', [BookingConfigurationController::class, 'resetServiceConfig']);

        // Website Theme Configuration
        Route::get('/website-config/{type}/{id}', [WebsiteConfigurationController::class, 'show']);
        Route::put('/website-config/{type}/{id}', [WebsiteConfigurationController::class, 'update']);
        Route::post('/website-config/{type}/{id}/reset', [WebsiteConfigurationController::class, 'reset']);

        // Booking Rules
        Route::post('/booking-rules/validate', [BookingRulesController::class, 'validate']);
        Route::get('/booking-rules/cancellation-policy', [BookingRulesController::class, 'getCancellationPolicy']);
        Route::post('/booking-rules/check-cancellation', [BookingRulesController::class, 'checkCancellation']);
        Route::post('/booking-rules/check-no-show', [BookingRulesController::class, 'checkNoShowStatus']);
        Route::post('/booking-rules/check-reschedule', [BookingRulesController::class, 'checkReschedule']);
        Route::get('/booking-rules/requirements', [BookingRulesController::class, 'getRequirements']);

        // Booking Orchestrator
        Route::post('/booking-orchestrator/reserve', [BookingOrchestratorController::class, 'reserve']);

        // Specialist assessments and notes
        Route::get('/customers/{customerId}/profile', [SpecialistAssessmentController::class, 'getCustomerProfile']);
        Route::post('/assessments', [SpecialistAssessmentController::class, 'createAssessment']);
        Route::post('/notes', [SpecialistAssessmentController::class, 'createNote']);
        
        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notification-preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);
        
        // Booking activities
        Route::get('/bookings/{id}/activities', [BookingActivityController::class, 'show']);
        
        // Dashboard
        Route::get('/dashboard/live-stats', [DashboardController::class, 'liveStats']);
        
        // Profiles
        Route::apiResource('profiles', ProfileController::class);
        Route::get('/locations/{location}/profiles', [ProfileController::class, 'byLocation']);

        // Payments — Methods
        Route::apiResource('payment-methods', PaymentMethodController::class)->middleware('throttle:30,1');
        Route::post('/payment-methods/test-connection', [PaymentMethodController::class, 'testConnection'])->middleware('throttle:10,1');
        Route::post('/payment-methods/{paymentMethod}/verify-credentials', [PaymentMethodController::class, 'verifyCredentials'])->middleware('throttle:10,1');

        // Payment Requests
        Route::apiResource('payment-requests', PaymentRequestController::class)->except(['update'])->middleware('throttle:60,1');
        Route::post('/payment-requests', [PaymentRequestController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/payment-requests/{paymentRequest}/status', [PaymentRequestController::class, 'updateStatus'])->middleware('throttle:30,1');
        Route::post('/payment-requests/{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->middleware('throttle:20,1');
        Route::get('/payment-requests/{paymentRequest}/check-status', [PaymentRequestController::class, 'checkStatus'])->middleware('throttle:60,1');

        // Transactions — payment-rail/event records (who paid, when, how)
        Route::get('/transactions/summary', [TransactionController::class, 'summary'])->middleware('throttle:60,1');
        Route::apiResource('transactions', TransactionController::class)->middleware('throttle:60,1');

        // Ledger — financial truth (double-entry journal, LedgerEntry records)
        Route::get('/providers/{provider}/ledger', [LedgerController::class, 'index'])->middleware('throttle:60,1');

        // Payments — Platform (B2B - Subscription Payments)
        Route::prefix('payments/platform')->group(function () {
            Route::post('/initialize', [PlatformPaymentController::class, 'initializeSubscriptionPayment']);
            Route::post('/verify', [PlatformPaymentController::class, 'verifySubscriptionPayment']);
            Route::post('/refund', [PlatformPaymentController::class, 'refundSubscriptionPayment']);
            Route::get('/invoices/{invoiceId}/status', [PlatformPaymentController::class, 'getInvoicePaymentStatus']);
            Route::post('/cash/initialize', [PlatformPaymentController::class, 'initializeCashPayment']);
            Route::post('/cash/confirm', [PlatformPaymentController::class, 'confirmCashPayment']);
        });

        // Admin — Platform Payment Management
        Route::prefix('admin/platform-payments')->group(function () {
            Route::get('/pending-invoices', [AdminPlatformPaymentController::class, 'getPendingInvoices']);
            Route::get('/payment-methods', [AdminPlatformPaymentController::class, 'getPaymentMethods']);
            Route::get('/payment-methods/{id}/details', [AdminPlatformPaymentController::class, 'getPaymentMethodDetails']);
            Route::put('/payment-methods/{id}', [AdminPlatformPaymentController::class, 'updatePaymentMethod']);
            Route::get('/invoices/{invoiceId}', [AdminPlatformPaymentController::class, 'getInvoice']);
            Route::post('/invoices/{invoiceId}/verify', [AdminPlatformPaymentController::class, 'verifyPayment']);
            Route::post('/invoices/{invoiceId}/reject', [AdminPlatformPaymentController::class, 'rejectPayment']);
        });

        // Admin — Seeder Management
        Route::prefix('admin/seeders')->group(function () {
            Route::get('/', [SeederController::class, 'index']);
            Route::get('/{seederClass}', [SeederController::class, 'show']);
            Route::post('/', [SeederController::class, 'register']);
            Route::put('/{seederClass}', [SeederController::class, 'update']);
            Route::delete('/{seederClass}', [SeederController::class, 'destroy']);
            Route::post('/{seederClass}/run', [SeederController::class, 'run']);
            Route::post('/batch-run', [SeederController::class, 'runMultiple']);
            Route::post('/discover', [SeederController::class, 'discover']);
        });

        // Payments — Salon (B2C - Customer Booking Payments)
        Route::prefix('payments/salon')->group(function () {
            Route::post('/initialize', [BookingPaymentController::class, 'initializeBookingPayment']);
            Route::post('/verify', [BookingPaymentController::class, 'verifySalonPayment']);
            Route::post('/manual', [BookingPaymentController::class, 'recordManualPayment']);
            Route::post('/refund', [BookingPaymentController::class, 'refundSalonPayment']);
            Route::get('/bookings/{bookingId}/status', [BookingPaymentController::class, 'getBookingPaymentStatus']);
        });


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

        // Add-ons (billing add-ons)
        Route::get('/add-ons', [AddOnPurchaseController::class, 'index']);
        Route::post('/add-ons/purchase', [AddOnPurchaseController::class, 'purchase']);
        Route::get('/billing/add-ons', [AddOnPurchaseController::class, 'index']);
        Route::post('/billing/add-ons/purchase', [AddOnPurchaseController::class, 'purchase']);

        // Media
        // (Moved to shared auth group at the bottom)
        
        // Gallery
        Route::get('/gallery', [GalleryController::class, 'index']);
        Route::post('/gallery', [GalleryController::class, 'store']);
        Route::put('/gallery/{id}', [GalleryController::class, 'update']);
        Route::delete('/gallery/{id}', [GalleryController::class, 'destroy']);

        // Testimonials
        Route::get('/testimonials', [TestimonialController::class, 'index']);
        Route::post('/testimonials', [TestimonialController::class, 'store']);
        Route::get('/testimonials/{id}', [TestimonialController::class, 'show']);
        Route::put('/testimonials/{id}', [TestimonialController::class, 'update']);
        Route::delete('/testimonials/{id}', [TestimonialController::class, 'destroy']);

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

        // Discover admin endpoints
        Route::post('/discover/featured', [DiscoverController::class, 'setFeatured']);
        Route::post('/discover/refresh-trending', [DiscoverController::class, 'refreshTrendingScores']);

        // Schedule Migration
        Route::get('/schedule-migration/status', [ScheduleMigrationController::class, 'checkStatus']);
        Route::post('/schedule-migration/migrate', [ScheduleMigrationController::class, 'migrate']);
        Route::get('/admin/schedule-migration/providers', [ScheduleMigrationController::class, 'getProvidersNeedingMigration']);
        Route::post('/admin/schedule-migration/notify', [ScheduleMigrationController::class, 'sendNotifications']);
        });
    });
    // Shared universal authenticated routes (accessible by salon admin, specialist, or portal customer)
    Route::middleware(['auth.any'])->group(function () {
        // Media
        Route::post('/media/upload', [MediaController::class, 'upload']);
        Route::post('/media/upload-base64', [MediaController::class, 'uploadBase64']);
        Route::get('/media/{media}', [MediaController::class, 'show']);
        Route::delete('/media/{media}', [MediaController::class, 'destroy']);
    });
});
