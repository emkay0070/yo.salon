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
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

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
            'next_route' => $this->resolveNextRoute($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $validated['email'])->first();

        if (!$user) {
            \Illuminate\Support\Facades\Log::info("Login failed: User not found for email " . $validated['email']);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            \Illuminate\Support\Facades\Log::info("Login failed: Password hash mismatch for user " . $user->email);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('salons'),
            'token' => $token,
            'status' => $user->status,
            'current_step' => $user->onboardingSession?->current_step,
            'next_route' => $this->resolveNextRoute($user),
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

    protected function resolveNextRoute(\App\Models\User $user): string
    {
        if ($user->isActive()) {
            return '/dashboard';
        }

        if ($user->isOnboarding()) {
            $step = $user->onboardingSession?->current_step ?? 'welcome';
            // Frontend might just route to /onboarding and the provider handles the step
            return '/onboarding'; 
        }

        return '/';
    }
}
