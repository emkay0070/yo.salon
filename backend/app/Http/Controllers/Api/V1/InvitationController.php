<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Customer;
use App\Models\PortalAccount;
use App\Models\User;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * List invitations for the current salon (authenticated owner/manager)
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id
            ?? $request->attributes->get('salon_id');
        if (!$salonId) {
            return response()->json(['message' => 'No active salon context'], 403);
        }

        $invitations = Invitation::where('salon_id', $salonId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Create a new invitation (authenticated owner/manager)
     */
    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) {
            $salonId = $request->attributes->get('salon_id');
        }
        if (!$salonId) {
            return response()->json(['message' => 'No active salon context'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:customer,staff,specialist,manager,receptionist',
            'email' => 'nullable|email',
            'target_id' => 'nullable|uuid', // e.g. Customer ID or Staff ID
        ]);

        $issueData = Invitation::issue([
            'salon_id' => $salonId,
            'role' => $validated['role'],
            'email' => $validated['email'] ?? null,
            'target_id' => $validated['target_id'] ?? null,
            'status' => 'pending',
        ], now()->addDays(7));

        $invitation = $issueData['invitation'];
        $rawToken = $issueData['rawToken'];
        
        $joinUrl = $invitation->publicJoinUrl($rawToken);

        return response()->json([
            'message' => 'Invitation created successfully',
            'invitation' => $invitation,
            'join_url' => $joinUrl,
            'raw_token' => $rawToken,
        ], 201);
    }

    /**
     * Validate an invitation token (public endpoint)
     */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

        $invitation->load('salon');

        if (!$invitation->isValid()) {
            return response()->json(['message' => 'Invitation is expired or already accepted'], 400);
        }

        // Hide token just in case, return public info
        return response()->json([
            'salon_name' => $invitation->salon->name,
            'role' => $invitation->role,
            'email' => $invitation->email,
        ]);
    }

    /**
     * Accept an invitation and register (public endpoint)
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation || !$invitation->isValid()) {
            return response()->json(['message' => 'Invalid or expired invitation'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($invitation->role === 'customer') {
            return $this->acceptCustomer($invitation, $validated);
        } elseif ($invitation->role === 'specialist') {
            return $this->acceptSpecialist($invitation, $validated);
        } else {
            // staff, manager, receptionist all go to salon workspace
            return $this->acceptStaff($invitation, $validated, $invitation->role);
        }
    }

    private function acceptCustomer(Invitation $invitation, array $validated): JsonResponse
    {
        // 1. Resolve Customer Record
        $customer = null;
        if ($invitation->target_id) {
            $customer = Customer::find($invitation->target_id);
        }

        // If no target_id was provided, try matching by email in that salon
        if (!$customer) {
            $customer = Customer::whereHas('salons', function($q) use ($invitation) {
                $q->where('salons.id', $invitation->salon_id);
            })->where('email', $validated['email'])->first();
        }

        // If still no customer, create a new one (guest turning into regular)
        if (!$customer) {
            $customer = Customer::create([
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => 'TBD', // Requires phone update later
            ]);
        }

        // Always ensure the customer has a salon relationship for this invitation's salon.
        // This is critical for existing customers found by target_id — they may have been
        // added to the system (e.g. via a booking) but never formally linked to the salon pivot.
        $hasSalonRelationship = $customer->salons()->where('salons.id', $invitation->salon_id)->exists();
        if (!$hasSalonRelationship) {
            $customer->salons()->attach($invitation->salon_id, [
                'id'        => (string) Str::uuid(),
                'visits'    => 0,
                'joined_at' => now(),
            ]);
        }

        // 2. Create Portal Account
        if ($customer->hasPortalAccount()) {
            return response()->json(['message' => 'Customer already has a portal account'], 409);
        }

        // Check if email already used for another portal account globally
        if (PortalAccount::where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'Email is already registered'], 409);
        }

        $portalAccount = PortalAccount::create([
            'customer_id' => $customer->id,
            'email'       => $validated['email'],
            'password'    => Hash::make($validated['password']),
        ]);

        $invitation->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message'        => 'Customer account created successfully',
            'portal_account' => $portalAccount,
        ]);
    }

    private function acceptSpecialist(Invitation $invitation, array $validated): JsonResponse
    {
        // Load salon with provider relationship
        $invitation->load('salon.provider');
        $salon = $invitation->salon;
        
        if (!$salon || !$salon->provider) {
            return response()->json(['message' => 'Invalid salon or provider'], 400);
        }

        // Check if SpecialistAccount already exists
        $existingAccount = \App\Models\SpecialistAccount::where('email', $validated['email'])->first();
        
        if ($existingAccount) {
            // Case A: Existing SpecialistAccount - verify password
            if (!Hash::check($validated['password'], $existingAccount->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }
            
            $specialist = $existingAccount->specialist;
            
            if (!$specialist) {
                return response()->json(['message' => 'Specialist account exists but has no specialist profile'], 400);
            }
        } else {
            // Case B: New specialist - create Specialist + SpecialistAccount
            $specialist = \App\Models\Specialist::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'active' => true,
            ]);

            $account = \App\Models\SpecialistAccount::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'specialist_id' => $specialist->id,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);
        }

        // Create SpecialistAssignment to the inviting salon/provider
        // Use firstOrCreate for idempotency based on specialist_id + provider_id + salon_id
        $assignment = \App\Models\SpecialistAssignment::firstOrCreate(
            [
                'specialist_id' => $specialist->id,
                'provider_id' => $salon->provider_id,
                'salon_id' => $salon->id,
            ],
            [
                'is_primary' => false,
                'status' => 'ACTIVE',
                'role' => 'SPECIALIST',
                'employment_type' => 'EMPLOYEE',
                'starts_at' => now(),
            ]
        );

        // Mark Invitation Accepted
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Specialist invitation accepted successfully',
            'specialist' => $specialist,
            'assignment' => $assignment,
        ]);
    }

    private function acceptStaff(Invitation $invitation, array $validated, string $role = 'staff'): JsonResponse
    {
        // Case A/B: Find or create User account
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // Case A: New User - create account
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active', // Staff bypass onboarding
            ]);
        } else {
            // Case B: Existing User - verify password
            if (!Hash::check($validated['password'], $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }
        }

        // Attach User to Salon with the specified role (idempotent)
        $user->salons()->syncWithoutDetaching([$invitation->salon_id => ['role' => $role]]);

        // Case C: invitation has target_id pointing to existing Staff
        if ($invitation->target_id) {
            $staff = \App\Models\Staff::find($invitation->target_id);
            if ($staff) {
                // Ensure Staff belongs to the inviting salon
                if ($staff->salon_id !== $invitation->salon_id) {
                    return response()->json(['message' => 'Staff record does not belong to this salon'], 400);
                }
                // Link Staff to User
                $staff->update([
                    'user_id' => $user->id,
                    'email' => $validated['email'],
                    'name' => $validated['name'],
                ]);
            } else {
                return response()->json(['message' => 'Target staff record not found'], 404);
            }
        } else {
            // Case D: Create or find Staff record for this salon/user
            $staff = \App\Models\Staff::firstOrCreate(
                [
                    'salon_id' => $invitation->salon_id,
                    'user_id' => $user->id,
                ],
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'active' => true,
                    'role' => ucfirst($role),
                ]
            );
        }

        // Mark Invitation Accepted
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => ucfirst($role) . ' account created successfully',
            'user' => $user,
            'staff' => $staff,
        ]);
    }
}
