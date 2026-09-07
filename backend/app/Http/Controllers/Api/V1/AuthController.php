<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'journey' => 'nullable|in:customer,specialist,salon',
        ]);

        $journey = $validated['journey'] ?? 'salon';

        // Cross-table email uniqueness check to prevent collision
        $emailExists = \App\Models\User::where('email', $validated['email'])->exists() ||
                      \App\Models\SpecialistAccount::where('email', $validated['email'])->exists() ||
                      \App\Models\PortalAccount::where('email', $validated['email'])->exists();

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered on Yo.Salon.'],
            ]);
        }

        if ($journey === 'specialist') {
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
            ]);

            $token = $account->createToken('specialist-portal')->plainTextToken;

            return response()->json([
                'user' => $account,
                'token' => $token,
                'status' => 'registered',
                'journey' => $journey,
                'next_route' => '/specialist-portal/onboarding',
            ], 201);
        }

        if ($journey === 'customer') {
            $customer = \App\Models\Customer::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => null,
            ]);

            $account = \App\Models\PortalAccount::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'customer_id' => $customer->id,
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $token = $account->createToken('portal-account')->plainTextToken;

            return response()->json([
                'user' => $account,
                'token' => $token,
                'status' => 'registered',
                'journey' => $journey,
                'next_route' => '/portal',
            ], 201);
        }

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'registered',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'status' => $user->status,
            'journey' => $journey,
            'next_route' => $this->resolveNextRoute($user, $journey),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Try SpecialistAccount authentication first
        $specialistAccount = \App\Models\SpecialistAccount::where('email', $validated['email'])->first();
        if ($specialistAccount && Hash::check($validated['password'], $specialistAccount->password)) {
            if (!$specialistAccount->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Account is inactive.'],
                ]);
            }

            $specialistAccount->updateLastLogin();
            $token = $specialistAccount->createToken('specialist-portal')->plainTextToken;

            // Determine next route based on onboarding status
            $nextRoute = '/specialist-portal';
            if (!$specialistAccount->onboarding_completed_at) {
                $nextRoute = '/specialist-portal/onboarding';
            }

            return response()->json([
                'account_type' => 'specialist',
                'account' => $specialistAccount,
                'specialist' => $specialistAccount->specialist,
                'token' => $token,
                'next_route' => $nextRoute,
            ]);
        }

        // Try PortalAccount authentication (customer portal) - check before User
        $portalAccount = \App\Models\PortalAccount::where('email', $validated['email'])->first();
        if ($portalAccount && Hash::check($validated['password'], $portalAccount->password)) {
            $token = $portalAccount->createToken('portal-account')->plainTextToken;
            return response()->json([
                'account_type' => 'portal',
                'account' => $portalAccount,
                'customer' => $portalAccount->customer,
                'token' => $token,
                'next_route' => '/portal/home',
            ]);
        }

        // Try User authentication (salon owners/admins)
        $user = \App\Models\User::where('email', $validated['email'])->first();
        if ($user && Hash::check($validated['password'], $user->password)) {
            $token = $user->createToken('auth-token')->plainTextToken;
            return response()->json([
                'account_type' => 'user',
                'user' => $user->load('salons'),
                'token' => $token,
                'status' => $user->status,
                'current_step' => $user->onboardingSession?->current_step,
                'next_route' => $this->resolveNextRoute($user),
            ]);
        }

        // No account found
        \Illuminate\Support\Facades\Log::info("Login failed: No account found for email " . $validated['email']);
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => $user->load('salons'),
            'status' => $user->status,
            'current_step' => $user->onboardingSession?->current_step,
            'next_route' => $this->resolveNextRoute($user),
        ]);
    }

    protected function resolveNextRoute(\App\Models\User $user, string $journey = 'salon'): string
    {
        if ($user->isActive()) {
            // Get the user's first salon and use slug-based routing
            $salon = $user->salons()->first();
            if ($salon && $salon->slug) {
                return "/{$salon->slug}/dashboard";
            }
            // Active user without salon - send to onboarding to create one
            return '/onboarding';
        }

        if ($user->isOnboarding()) {
            $step = $user->onboardingSession?->current_step ?? 'welcome';
            
            // Route based on journey type
            return match($journey) {
                'customer' => '/portal',
                'specialist' => '/specialist-portal/onboarding',
                'salon' => '/onboarding',
                default => '/onboarding',
            };
        }

        return '/';
    }
}
