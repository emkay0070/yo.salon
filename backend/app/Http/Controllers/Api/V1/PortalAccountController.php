<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PortalAccount;
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
        $portalAccount = auth('portal')->user();
        $salonId = $request->attributes->get('salon_id');
        
        $homeData = $this->portalAuthService->getHomeData($portalAccount, $salonId);

        return response()->json($homeData);
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
}
