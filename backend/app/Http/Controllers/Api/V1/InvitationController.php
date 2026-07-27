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
     * Create a new invitation (authenticated owner/manager)
     */
    public function store(Request $request): JsonResponse
    {
        $salonId = auth()->user()->currentSalon()?->id;
        if (!$salonId) {
            return response()->json(['message' => 'No active salon context'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|in:customer,staff',
            'email' => 'nullable|email',
            'target_id' => 'nullable|uuid', // e.g. Customer ID
        ]);

        $token = Str::random(32);

        $invitation = Invitation::create([
            'salon_id' => $salonId,
            'role' => $validated['role'],
            'email' => $validated['email'] ?? null,
            'target_id' => $validated['target_id'] ?? null,
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
        ]);

        return response()->json([
            'message' => 'Invitation created successfully',
            'invitation' => $invitation,
        ], 201);
    }

    /**
     * Validate an invitation token (public endpoint)
     */
    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::with('salon')->where('token', $token)->first();

        if (!$invitation) {
            return response()->json(['message' => 'Invitation not found'], 404);
        }

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
        $invitation = Invitation::where('token', $token)->first();

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
        } else {
            return $this->acceptStaff($invitation, $validated);
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
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => 'TBD', // Requires phone update later
            ]);
            $customer->salons()->attach($invitation->salon_id);
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
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Customer account created successfully',
            'portal_account' => $portalAccount,
        ]);
    }

    private function acceptStaff(Invitation $invitation, array $validated): JsonResponse
    {
        // 1. Create or Find User account
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json(['message' => 'Email is already registered'], 409);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'active', // Staff bypass onboarding
        ]);

        // 2. Attach User to Salon
        $user->salons()->attach($invitation->salon_id, ['role' => 'staff']);

        // 3. Mark Invitation Accepted
        $invitation->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        // In a real app we'd also link or create the `Staff` record
        if ($invitation->target_id) {
            $staff = \App\Models\Staff::find($invitation->target_id);
            if ($staff) {
                $staff->update(['email' => $validated['email']]);
            }
        } else {
            \App\Models\Staff::create([
                'salon_id' => $invitation->salon_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'active' => true,
                'role' => 'Staff',
            ]);
        }

        return response()->json([
            'message' => 'Staff account created successfully',
            'user' => $user,
        ]);
    }
}
