<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Customer;
use App\Models\PortalAccount;
use App\Models\User;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * List invitations for the current provider (authenticated owner/manager)
     */
    public function index(Request $request): JsonResponse
    {
        $providerId = auth()->user()->currentProvider()?->id
            ?? $request->attributes->get('provider_id');
        if (!$providerId) {
            return response()->json(['message' => 'No active provider context'], 403);
        }

        $invitations = Invitation::where('provider_id', $providerId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Create a new invitation (authenticated owner/manager)
     */
    public function store(Request $request): JsonResponse
    {
        $providerId = auth()->user()->currentProvider()?->id;
        if (!$providerId) {
            $providerId = $request->attributes->get('provider_id');
        }
        if (!$providerId) {
            return response()->json(['message' => 'No active provider context'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:customer,staff,specialist,manager,receptionist',
            'email' => 'nullable|email',
            'target_id' => 'nullable|uuid', // e.g. Customer ID or Staff ID
            'location_id' => 'nullable|uuid', // Location-scoped invitation
        ]);

        $issueData = Invitation::issue([
            'provider_id' => $providerId,
            'location_id' => $validated['location_id'] ?? null,
            'role' => $validated['role'],
            'email' => $validated['email'] ?? null,
            'target_id' => $validated['target_id'] ?? null,
            'status' => 'pending',
            'created_by' => auth()->id(),
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

        $invitation->load(['provider', 'location', 'createdBy']);

        if (!$invitation->isValid()) {
            return response()->json(['message' => 'Invitation is expired or already accepted'], 400);
        }

        $response = [
            'provider_name' => $invitation->provider->display_name,
            'provider_id' => $invitation->provider->id,
            'role' => $invitation->role,
            'email' => $invitation->email,
        ];

        // Include location context if invitation is location-scoped
        if ($invitation->location_id && $invitation->location) {
            $response['location'] = $invitation->location->name;
        }

        // Include who sent the invitation
        if ($invitation->createdBy) {
            $response['invited_by'] = $invitation->createdBy->name;
        }

        // Load target information if target_id exists
        if ($invitation->target_id) {
            if ($invitation->role === 'customer') {
                $target = Customer::find($invitation->target_id);
                if ($target) {
                    $response['target'] = [
                        'id' => $target->id,
                        'name' => $target->name,
                        'email' => $target->email,
                        'phone' => $target->phone,
                    ];
                }
            } elseif (in_array($invitation->role, ['staff', 'specialist', 'manager', 'receptionist'])) {
                $target = \App\Models\Staff::find($invitation->target_id);
                if ($target) {
                    $response['target'] = [
                        'id' => $target->id,
                        'name' => $target->name,
                        'email' => $target->email,
                        'role' => $target->role,
                    ];
                }
            }
        }

        return response()->json($response);
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
            // staff, manager, receptionist all go to provider workspace
            return $this->acceptStaff($invitation, $validated, $invitation->role);
        }
    }

    private function acceptCustomer(Invitation $invitation, array $validated): JsonResponse
    {
        $invitation->load(['provider', 'provider.primaryLocation', 'location']);

        // 1. Resolve Customer Record
        $customer = null;
        if ($invitation->target_id) {
            $customer = Customer::find($invitation->target_id);
        }

        // If no target_id was provided, try matching by email in that provider
        if (!$customer) {
            $customer = Customer::whereHas('providers', function($q) use ($invitation) {
                $q->where('providers.id', $invitation->provider_id);
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

        // Always ensure the customer has a provider relationship for this invitation's provider.
        // This is critical for existing customers found by target_id — they may have been
        // added to the system (e.g. via a booking) but never formally linked to the provider pivot.
        $hasProviderRelationship = $customer->providers()->where('providers.id', $invitation->provider_id)->exists();
        if (!$hasProviderRelationship) {
            $customer->providers()->attach($invitation->provider_id, [
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
            'status'           => 'accepted',
            'accepted_at'      => now(),
            'accepted_user_id' => $portalAccount->id, // Store portal account ID as accepting user
        ]);

        return response()->json([
            'message'        => 'Customer account created successfully',
            'portal_account' => $portalAccount,
        ]);
    }

    private function acceptSpecialist(Invitation $invitation, array $validated): JsonResponse
    {
        // Load provider and location
        $invitation->load(['provider', 'provider.primaryLocation', 'location']);
        $provider = $invitation->provider;
        
        if (!$provider) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        // Use invitation location if specified, otherwise NULL for Provider-wide
        $locationId = $invitation->location_id;

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

        // Create SpecialistAssignment to the inviting provider
        // Only include location_id if invitation is location-scoped
        $assignmentData = [
            'specialist_id' => $specialist->id,
            'provider_id' => $provider->id,
        ];
        
        if ($locationId) {
            $assignmentData['location_id'] = $locationId;
        }

        $assignment = \App\Models\SpecialistAssignment::firstOrCreate(
            $assignmentData,
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
            'status'           => 'accepted',
            'accepted_at'      => now(),
            'accepted_user_id' => $account->id ?? $existingAccount->id, // Store specialist account ID
        ]);

        return response()->json([
            'message' => 'Specialist invitation accepted successfully',
            'specialist' => $specialist,
            'assignment' => $assignment,
        ]);
    }

    private function acceptStaff(Invitation $invitation, array $validated, string $role = 'staff'): JsonResponse
    {
        $invitation->load(['provider', 'provider.primaryLocation', 'location']);

        // Use invitation location if specified, otherwise NULL for Provider-wide
        $locationId = $invitation->location_id;

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

        // Attach User to Provider with the specified role (idempotent)
        // Explicitly set authority_scope to location_scoped - do NOT infer provider-wide authority from NULL location_id
        $exists = $user->providers()->where('providers.id', $invitation->provider_id)->exists();
        if (!$exists) {
            $user->providers()->attach($invitation->provider_id, [
                'id' => (string) Str::uuid(),
                'role' => $role,
                'authority_scope' => 'location_scoped',
            ]);
        } else {
            $user->providers()->updateExistingPivot($invitation->provider_id, [
                'role' => $role,
                'authority_scope' => 'location_scoped',
            ]);
        }

        // If invitation is location-scoped, create ProviderUserLocation record
        if ($locationId) {
            $providerUser = \App\Models\ProviderUser::where('user_id', $user->id)
                ->where('provider_id', $invitation->provider_id)
                ->first();

            if ($providerUser) {
                \App\Models\ProviderUserLocation::firstOrCreate([
                    'provider_user_id' => $providerUser->id,
                    'location_id' => $locationId,
                ]);
            }
        }

        // Case C: invitation has target_id pointing to existing Staff
        if ($invitation->target_id) {
            $staff = \App\Models\Staff::find($invitation->target_id);
            if ($staff) {
                // Ensure Staff belongs to the inviting provider + location
                if ($staff->provider_id !== $invitation->provider_id || 
                    ($locationId && $staff->location_id !== $locationId)) {
                    return response()->json(['message' => 'Staff record does not belong to this provider/location'], 400);
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
            // Case D: Create or find Staff record for this provider/location/user
            $staffData = [
                'provider_id' => $invitation->provider_id,
                'user_id' => $user->id,
            ];
            
            // Only include location_id if invitation is location-scoped
            if ($locationId) {
                $staffData['location_id'] = $locationId;
            }

            $staff = \App\Models\Staff::firstOrCreate(
                $staffData,
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
            'status'           => 'accepted',
            'accepted_at'      => now(),
            'accepted_user_id' => $user->id,
        ]);

        return response()->json([
            'message' => ucfirst($role) . ' account created successfully',
            'user' => $user,
            'staff' => $staff,
        ]);
    }
}
