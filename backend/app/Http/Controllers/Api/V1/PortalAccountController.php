<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroomingProfile;
use App\Models\PortalAccount;
use App\Models\ReferenceData;
use App\Models\Review;
use App\Models\Staff;
use App\Models\Specialist;
use App\Services\PortalAuthService;
use App\Services\CustomerPortalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PortalAccountController extends Controller
{
    private PortalAuthService $portalAuthService;
    private CustomerPortalService $customerPortalService;

    public function __construct(
        PortalAuthService $portalAuthService,
        CustomerPortalService $customerPortalService
    ) {
        $this->portalAuthService = $portalAuthService;
        $this->customerPortalService = $customerPortalService;
    }

    /**
     * Create a new portal account for an existing customer
     * 
     * Principle: A Portal Account must always be attached to a Customer.
     * Customer records are created by business interactions, not authentication.
     * 
     * This endpoint requires salon context and links to an existing customer.
     * If no customer exists with the provided phone/email, registration fails.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:portal_accounts,email',
            'password' => 'required|string|min:8',
            'phone' => 'required|string',
            'salon_id' => 'required|exists:salons,id',
        ]);

        try {
            $result = $this->portalAuthService->register($validated);

            return response()->json([
                'message' => 'Account linked to existing customer',
                'customer' => $result['customer'],
                'salon' => $result['salon'],
                'portal_account' => $result['portal_account'],
                'is_new_customer' => false, // Always false - auth never creates customers
                'is_new_salon_relationship' => $result['is_new_salon_relationship'],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Login to portal account
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->portalAuthService->login($validated);

            return response()->json([
                'message' => 'Login successful',
                'token' => $result['token'],
                'context' => $result['context'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }
    }

    /**
     * Get operating context for portal
     * Returns everything needed for session state
     * salonId is provided by middleware
     */
    public function context(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $salonId = $request->attributes->get('salon_id');
        
        $contextData = $this->portalAuthService->getContext($portalAccount, $salonId);

        return response()->json($contextData);
    }

    /**
     * Get authenticated portal user (me endpoint)
     * Legacy endpoint, now maps to context
     */
    public function me(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $salonId = $request->attributes->get('salon_id');
        
        $userData = $this->portalAuthService->getContext($portalAccount, $salonId);

        return response()->json($userData);
    }

    /**
     * Get portal home data
     * Returns dynamic content for the home page
     * salonId is provided by middleware
     */
    public function home(Request $request): JsonResponse
    {
        try {
            $portalAccount = auth('portal')->user();
            $salonId = $request->attributes->get('salon_id');

            if (!$portalAccount) {
                return response()->json(['message' => 'Portal account not found'], 401);
            }

            if (!$salonId) {
                return response()->json(['message' => 'Salon context not found'], 400);
            }

            $homeData = $this->portalAuthService->getHomeData($portalAccount, $salonId);

            return response()->json($homeData);
        } catch (\Exception $e) {
            \Log::error('Portal home data error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salon_id' => $request->attributes->get('salon_id'),
                'portal_account_id' => auth('portal')->user()?->id,
            ]);

            return response()->json([
                'message' => 'Unable to load home data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout from portal account
     */
    public function logout(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        
        $this->portalAuthService->logout($portalAccount);

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Send invitation to customer
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $customer = Customer::find($validated['customer_id']);

        if ($customer->hasPortalAccount()) {
            return response()->json([
                'message' => 'Customer already has a portal account',
            ], 409);
        }

        // Generate invitation token
        $invitationToken = bin2hex(random_bytes(32));
        
        // Store invitation token - in production, use a separate invitations table
        // For now, we'll store it in the first salon relationship's notes
        $salonRelationship = $customer->salons()->first();
        if ($salonRelationship) {
            $currentNotes = $salonRelationship->pivot->notes ?? '';
            $customer->salons()->updateExistingPivot($salonRelationship->id, [
                'notes' => $currentNotes . "\ninvitation_token:" . $invitationToken,
            ]);
        }

        // In production, send email/SMS with invitation link
        // For now, return the token for testing
        return response()->json([
            'message' => 'Invitation sent',
            'invitation_token' => $invitationToken,
            'customer' => $customer,
        ]);
    }

    /**
     * Accept invitation and create account
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitation_token' => 'required|string',
            'email' => 'required|email|unique:portal_accounts,email',
            'password' => 'required|string|min:8',
        ]);

        // Find customer by invitation token (simplified)
        $customer = Customer::where('notes', 'like', '%' . $validated['invitation_token'] . '%')->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Invalid invitation token',
            ], 404);
        }

        if ($customer->hasPortalAccount()) {
            return response()->json([
                'message' => 'Customer already has a portal account',
            ], 409);
        }

        $portalAccount = PortalAccount::create([
            'customer_id' => $customer->id,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Clear invitation token from salon notes
        $salonRelationship = $customer->salons()->first();
        if ($salonRelationship) {
            $currentNotes = $salonRelationship->pivot->notes ?? '';
            $newNotes = str_replace("\ninvitation_token:" . $validated['invitation_token'], '', $currentNotes);
            $customer->salons()->updateExistingPivot($salonRelationship->id, [
                'notes' => $newNotes,
            ]);
        }

        return response()->json([
            'message' => 'Account created successfully',
            'customer' => $customer,
            'salons' => $customer->salons,
            'portal_account' => $portalAccount,
        ], 201);
    }

    /**
     * Get recent visits for the customer
     */
    public function recentVisits(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;
        $salonId = $request->attributes->get('salon_id');
        $limit = $request->query('limit', 5);

        $visits = $this->customerPortalService->getRecentVisits($customer, $salonId, $limit);

        return response()->json([
            'visits' => $visits,
        ]);
    }

    /**
     * Get last booking for quick rebook
     */
    public function lastBooking(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;
        $salonId = $request->attributes->get('salon_id');

        $lastBooking = $this->customerPortalService->getLastBooking($customer, $salonId);

        return response()->json([
            'last_booking' => $lastBooking,
        ]);
    }

    /**
     * Get reviews for a specialist
     */
    public function specialistReviews(Request $request, string $specialistId): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');

        $specialist = Specialist::where('id', $specialistId)
            ->where('active', true)
            ->whereHas('salons', function ($q) use ($salonId) {
                $q->where('salon_id', $salonId)->where('salon_specialist.active', true);
            })
            ->first();

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $reviews = Review::where('specialist_id', $specialistId)
            ->with(['customer:id,name', 'booking:id,date,time'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'specialist' => $specialist->only(['id', 'name', 'photo_url', 'rating', 'review_count']),
            'reviews' => $reviews,
        ]);
    }

    /**
     * Create a review for a booking
     */
    public function createReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'specialist_id' => 'required|exists:specialists,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;
        $salonId = $request->attributes->get('salon_id');

        // Check if booking belongs to customer
        $booking = \App\Models\Booking::where('id', $validated['booking_id'])
            ->where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Check if review already exists
        $existingReview = Review::where('booking_id', $validated['booking_id'])->first();
        if ($existingReview) {
            return response()->json(['message' => 'Review already exists for this booking'], 409);
        }

        // Create review
        $review = Review::create([
            'booking_id' => $validated['booking_id'],
            'specialist_id' => $validated['specialist_id'],
            'customer_id' => $customer->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        // Update specialist rating
        $specialist = Specialist::find($validated['specialist_id']);
        if ($specialist) {
            $avgRating = Review::where('specialist_id', $specialist->id)->avg('rating') ?? 0;
            $reviewCount = Review::where('specialist_id', $specialist->id)->count();
            $specialist->update([
                'rating' => round($avgRating, 2),
                'review_count' => $reviewCount,
            ]);
        }

        return response()->json([
            'message' => 'Review created successfully',
            'review' => $review->load(['customer:id,name', 'booking:id,date,time']),
        ], 201);
    }

    /**
     * Get reviews by current customer
     */
    public function myReviews(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;
        $salonId = $request->attributes->get('salon_id');

        $reviews = Review::where('customer_id', $customer->id)
            ->whereHas('booking', function ($query) use ($salonId) {
                $query->where('salon_id', $salonId);
            })
            ->with(['staff:id,name,photo_url', 'booking:id,date,time'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'reviews' => $reviews,
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'birthday' => 'nullable|date',
            'address' => 'nullable|string|max:500',
        ]);

        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Update customer fields
        $updateData = [];
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        if (isset($validated['phone'])) {
            $updateData['phone'] = $validated['phone'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (isset($validated['birthday'])) {
            $updateData['birthday'] = $validated['birthday'];
        }
        if (isset($validated['address'])) {
            $updateData['address'] = $validated['address'];
        }

        if (!empty($updateData)) {
            $customer->update($updateData);
        }

        // Update portal account email if provided
        if (isset($validated['email'])) {
            $portalAccount->update(['email' => $validated['email']]);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'customer' => $customer->fresh(),
        ]);
    }

    /**
     * Get customer grooming profile
     */
    public function getGroomingProfile(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $groomingProfile = CustomerGroomingProfile::where('customer_id', $customer->id)->first();

        return response()->json([
            'grooming_profile' => $groomingProfile,
        ]);
    }

    /**
     * Update customer grooming profile
     */
    public function updateGroomingProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hair_type' => 'nullable|string|max:100',
            'preferred_hair_style' => 'nullable|string|max:100',
            'hair_concerns' => 'nullable|array',
            'beard_style' => 'nullable|string|max:100',
            'beard_products' => 'nullable|string|max:100',
            'skin_type' => 'nullable|string|max:100',
            'allergies' => 'nullable|array',
        ]);

        // Validate against reference data (only if reference data exists)
        if (isset($validated['hair_type'])) {
            $validHairTypes = ReferenceData::byCategory('hair_type')->active()->pluck('value')->toArray();
            if (!empty($validHairTypes) && !in_array($validated['hair_type'], $validHairTypes)) {
                return response()->json(['message' => 'Invalid hair type'], 422);
            }
        }

        if (isset($validated['preferred_hair_style'])) {
            $validStyles = ReferenceData::byCategory('hair_style')->active()->pluck('value')->toArray();
            if (!empty($validStyles) && !in_array($validated['preferred_hair_style'], $validStyles)) {
                return response()->json(['message' => 'Invalid hair style'], 422);
            }
        }

        if (isset($validated['beard_style'])) {
            $validBeardStyles = ReferenceData::byCategory('beard_style')->active()->pluck('value')->toArray();
            if (!empty($validBeardStyles) && !in_array($validated['beard_style'], $validBeardStyles)) {
                return response()->json(['message' => 'Invalid beard style'], 422);
            }
        }

        if (isset($validated['beard_products'])) {
            $validProducts = ReferenceData::byCategory('beard_product')->active()->pluck('value')->toArray();
            if (!empty($validProducts) && !in_array($validated['beard_products'], $validProducts)) {
                return response()->json(['message' => 'Invalid beard product'], 422);
            }
        }

        if (isset($validated['skin_type'])) {
            $validSkinTypes = ReferenceData::byCategory('skin_type')->active()->pluck('value')->toArray();
            if (!empty($validSkinTypes) && !in_array($validated['skin_type'], $validSkinTypes)) {
                return response()->json(['message' => 'Invalid skin type'], 422);
            }
        }

        if (isset($validated['hair_concerns']) && is_array($validated['hair_concerns'])) {
            $validConcerns = ReferenceData::byCategory('hair_concern')->active()->pluck('value')->toArray();
            if (!empty($validConcerns)) {
                foreach ($validated['hair_concerns'] as $concern) {
                    if (!in_array($concern, $validConcerns)) {
                        return response()->json(['message' => 'Invalid hair concern: ' . $concern], 422);
                    }
                }
            }
        }

        if (isset($validated['allergies']) && is_array($validated['allergies'])) {
            $validAllergies = ReferenceData::byCategory('allergy')->active()->pluck('value')->toArray();
            if (!empty($validAllergies)) {
                foreach ($validated['allergies'] as $allergy) {
                    if (!in_array($allergy, $validAllergies)) {
                        return response()->json(['message' => 'Invalid allergy: ' . $allergy], 422);
                    }
                }
            }
        }

        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $groomingProfile = CustomerGroomingProfile::firstOrNew(
            ['customer_id' => $customer->id]
        );

        // Generate UUID if it's a new record
        if (!$groomingProfile->id) {
            $groomingProfile->id = \Illuminate\Support\Str::uuid();
        }

        $groomingProfile->fill($validated);
        $groomingProfile->save();

        return response()->json([
            'message' => 'Grooming profile updated successfully',
            'grooming_profile' => $groomingProfile->fresh(),
        ]);
    }
}
