<?php

namespace App\Http\Controllers;

use App\Models\SpecialistAccount;
use App\Models\Specialist;
use App\Models\Salon;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\SpecialistTimeOff;
use App\Services\CustomerWorkspaceInvitationService;
use App\Services\SpecialistCapabilityResolver;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SpecialistPortalController extends Controller
{
    private CustomerWorkspaceInvitationService $invitationService;
    private SpecialistCapabilityResolver $capabilityResolver;

    public function __construct(CustomerWorkspaceInvitationService $invitationService, SpecialistCapabilityResolver $capabilityResolver)
    {
        $this->invitationService = $invitationService;
        $this->capabilityResolver = $capabilityResolver;
    }

    /**
     * Login specialist
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $account = SpecialistAccount::where('email', $request->email)->first();

        if (!$account || !Hash::check($request->password, $account->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$account->is_active) {
            return response()->json(['message' => 'Account is inactive'], 403);
        }

        $account->updateLastLogin();
        $token = $account->createToken('specialist-portal')->plainTextToken;

        return response()->json([
            'token' => $token,
            'account' => $account,
            'specialist' => $account->specialist,
        ]);
    }

    /**
     * Get specialist context.
     *
     * INVARIANT: specialist_assignments is the single source of truth for
     * Specialist ↔ Provider relationships. Never fall back to the legacy
     * salons() pivot. A Specialist can simultaneously have multiple Provider
     * assignments, including owning an independent_specialist Provider while
     * also being an employee/contractor elsewhere.
     */
    public function context(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Fetch all active Provider relationships from specialist_assignments.
        // Each assignment carries role + employment_type at the Provider level.
        $workplaces = $specialist->assignments()
            ->whereIn('status', ['ACTIVE', 'active'])
            ->with(['provider', 'salon'])
            ->get()
            ->map(function ($assignment) {
                $provider = $assignment->provider;
                    $isOwner = in_array($assignment->role, ['OWNER', 'MANAGER']) || $provider?->type === 'independent_specialist';
                    
                    return [
                        'assignment_id'   => $assignment->id,
                        'provider_id'     => $assignment->provider_id,
                        'provider_name'   => $provider?->display_name,
                        'provider_type'   => $provider?->type,
                        'provider_slug'   => $provider?->slug,
                        'provider_logo'   => $provider?->logo,
                        'salon_id'        => $assignment->salon_id,
                        'salon_name'      => $assignment->salon?->name,
                        'role'            => $assignment->role,
                        'employment_type' => $assignment->employment_type,
                        'is_primary'      => $assignment->is_primary,
                        // Can this assignment control Provider-level settings?
                        'can_manage_provider' => $isOwner,
                        // Can this assignment create new services for this Provider?
                        'can_create_services' => $isOwner,
                    ];
            });

        // The primary workplace is the first OWNER context (their own Provider),
        // or the is_primary assignment if no OWNER context exists.
        $primaryWorkplace = $workplaces->firstWhere('role', 'OWNER')
            ?? $workplaces->firstWhere('is_primary', true)
            ?? $workplaces->first();

        // If provider_slug is provided, resolve the current workplace from URL
        $currentWorkplace = null;
        $providerSlug = $request->query('provider_slug');
        
        if ($providerSlug) {
            $currentWorkplace = $workplaces->firstWhere('provider_slug', $providerSlug);
            
            // Security: Reject if specialist doesn't have an active assignment to this provider
            if (!$currentWorkplace) {
                return response()->json([
                    'message' => 'You do not have access to this workplace',
                ], 403);
            }
        }

        return response()->json([
            'specialist_account' => $account,
            'specialist'         => $specialist,
            'workplaces'         => $workplaces,
            'primary_workplace'  => $primaryWorkplace,
            'current_workplace'  => $currentWorkplace,
        ]);
    }


    /**
     * Get specialist profile
     */
    public function getProfile(Request $request, \App\Domain\Specialist\Services\SpecialistProfileService $profileService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        return response()->json($profileService->getProfile($specialist));
    }

    /**
     * Update specialist profile
     */
    public function updateProfile(Request $request, \App\Domain\Specialist\Services\SpecialistProfileService $profileService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $specialist = $profileService->updateProfile($specialist, $request->all());

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profileService->getProfile($specialist)
        ]);
    }

    /**
     * Get specialist dashboard stats
     */
    public function dashboardStats(Request $request, \App\Domain\Specialist\Services\SpecialistDashboardService $dashboardService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $stats = $dashboardService->getDashboardStats($specialist);

        return response()->json($stats);
    }

    /**
     * Logout specialist
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Register specialist account
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'specialist_id' => 'required|uuid|exists:specialists,id',
            'email' => 'required|email|unique:specialist_accounts,email',
            'password' => 'required|min:8|confirmed',
            'phone' => 'nullable|string',
        ]);

        $account = SpecialistAccount::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'specialist_id' => $request->specialist_id,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        return response()->json([
            'message' => 'Account created successfully',
            'account' => $account,
        ], 201);
    }

    /**
     * Get specialist's customers
     * Requires provider_slug for workspace context to enforce data boundaries
     * 
     * Query params:
     *   ?provider_slug={provider_slug} - Required for workspace-specific customer list
     */
    public function getCustomers(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $providerSlug = $request->query('provider_slug');
        
        // Resolve workspace context
        if ($providerSlug) {
            // Validate specialist has active assignment to this provider
            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialist->id)
                ->where('status', 'active')
                ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
                ->first();

            if (!$assignment) {
                return response()->json(['message' => 'Invalid workspace context - no active assignment'], 403);
            }

            // Filter customers by workspace
            $customers = $specialist->customers()
                ->wherePivot('provider_id', $assignment->provider_id)
                ->withPivot('is_favorite', 'is_following', 'total_bookings', 'total_spent', 'last_interaction_at', 'provider_id', 'relationship_origin', 'acquisition_source')
                ->orderBy('pivot_last_interaction_at', 'desc')
                ->get();

            return response()->json([
                'customers' => $customers,
                'total' => $customers->count(),
                'workspace_filtered' => true,
                'provider_slug' => $providerSlug,
                'provider_id' => $assignment->provider_id,
            ]);
        }

        // If no provider_slug provided, return error - workspace context is required
        return response()->json([
            'message' => 'provider_slug is required for workspace-specific customer access',
            'available_workspaces' => $specialist->assignments()
                ->where('status', 'active')
                ->with('provider')
                ->get()
                ->map(fn($a) => [
                    'provider_slug' => $a->provider->slug,
                    'provider_name' => $a->provider->display_name,
                    'provider_type' => $a->provider->type,
                ])
        ], 400);
    }

    /**
     * Send workspace invitation to a customer
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'target_provider_id' => 'required|uuid|exists:providers,id',
            'channel' => 'nullable|in:specialist_direct_invite,platform_suggestion,referral,direct_link',
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
        ]);

        try {
            $invitation = $this->invitationService->createInvitation(
                $validated['customer_id'],
                $specialist->id,
                $validated['target_provider_id'],
                null, // source_provider_id - can be added if needed
                $validated['channel'] ?? 'specialist_direct_invite',
                $validated['expires_in_hours'] ?? 168 // default 7 days
            );

            return response()->json([
                'message' => 'Invitation sent successfully',
                'invitation' => $invitation->load(['customer', 'specialist', 'targetProvider']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get invitation statistics for the current specialist
     */
    public function getInvitationStats(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $targetProviderId = $request->query('target_provider_id');

        try {
            $stats = $this->invitationService->getInvitationStats($specialist->id, $targetProviderId);

            return response()->json([
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get invitation stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending invitations for a customer (for customer portal)
     */
    public function getPendingInvitations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
        ]);

        try {
            $invitations = $this->invitationService->getPendingInvitationsForCustomer($validated['customer_id']);

            return response()->json([
                'invitations' => $invitations,
                'count' => $invitations->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get pending invitations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Accept a workspace invitation (for customer portal)
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitation_id' => 'required|uuid|exists:customer_workspace_invitations,id',
        ]);

        try {
            $relationship = $this->invitationService->acceptInvitation($validated['invitation_id']);

            return response()->json([
                'message' => 'Invitation accepted successfully',
                'relationship' => $relationship->load(['customer', 'specialist', 'provider']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to accept invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decline a workspace invitation (for customer portal)
     */
    public function declineInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invitation_id' => 'required|uuid|exists:customer_workspace_invitations,id',
        ]);

        try {
            $invitation = $this->invitationService->declineInvitation($validated['invitation_id']);

            return response()->json([
                'message' => 'Invitation declined successfully',
                'invitation' => $invitation->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to decline invitation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specialist's bookings
     * 
     * Query params:
     *   ?scope=today|upcoming|all  (default: upcoming)
     *   ?status=pending|confirmed|completed|cancelled|no-show  (optional)
     */
    public function getBookings(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $scope  = $request->query('scope', 'upcoming');   // today | upcoming | all
        $status = $request->query('status');              // optional filter

        $query = Booking::where('specialist_id', $specialist->id)
            ->with(['customer', 'salon', 'service']);

        // Apply date scope
        if ($scope === 'today') {
            $query->where('date', today()->format('Y-m-d'));
        } elseif ($scope === 'upcoming') {
            $query->where('date', '>=', today()->format('Y-m-d'));
        }
        // 'all' → no date filter

        // Apply optional status filter
        if ($status) {
            // Map frontend tabs to DB values
            $statusMap = [
                'upcoming'  => ['pending', 'confirmed'],
                'completed' => ['completed'],
                'cancelled' => ['cancelled'],
                'no-show'   => ['no_show', 'no-show'],
            ];
            $dbStatuses = $statusMap[$status] ?? [$status];
            $query->whereIn('status', $dbStatuses);
        }

        $bookings = $query->orderBy('date', 'asc')->orderBy('time', 'asc')->get();

        return response()->json([
            'bookings' => $bookings,
            'total'    => $bookings->count(),
        ]);
    }


    /**
     * Get customer details for assessment
     */
    public function getCustomerForAssessment(string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $customer = Customer::with('groomingProfile')->find($customerId);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json([
            'customer' => $customer,
            'grooming_profile' => $customer->groomingProfile,
        ]);
    }

    /**
     * Get customer details with assessments and notes
     */
    public function getCustomerDetails(string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $customer = Customer::with(['groomingProfile'])
            ->whereHas('specialists', function ($query) use ($specialist) {
                $query->where('specialist_id', $specialist->id);
            })
            ->find($customerId);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found or not associated with specialist'], 404);
        }

        // Get assessments for this customer by this specialist
        $assessments = \App\Models\ProfessionalAssessment::where('customer_id', $customerId)
            ->where('specialist_id', $specialist->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get notes - for now, use assessments notes field
        // In future, a dedicated notes table could be added
        $notes = $assessments->map(function ($assessment) {
            return [
                'id' => $assessment->id,
                'content' => $assessment->notes,
                'created_at' => $assessment->created_at->format('Y-m-d H:i:s'),
                'type' => 'assessment',
            ];
        })->filter(function ($note) {
            return !empty($note['content']);
        })->values();

        return response()->json([
            'customer' => $customer,
            'grooming_profile' => $customer->groomingProfile,
            'assessments' => $assessments,
            'notes' => $notes,
        ]);
    }

    /**
     * Get calendar data for the specialist.
     *
     * Delegates entirely to SpecialistCalendarService, which asks the
     * Scheduling Domain (AvailabilityContextBuilder + ScheduleResolver)
     * for the resolved schedule per day. No schedule logic here.
     *
     * Query params:
     *   ?week_start=YYYY-MM-DD  (defaults to start of current week, Monday)
     */
    public function calendarData(
        Request $request,
        \App\Domain\Specialist\Services\SpecialistCalendarService $calendarService
    ): JsonResponse {
        $account    = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $weekStart = $request->query('week_start')
            ? \Carbon\CarbonImmutable::parse($request->query('week_start'))->startOfWeek(\Carbon\Carbon::MONDAY)
            : \Carbon\CarbonImmutable::now()->startOfWeek(\Carbon\Carbon::MONDAY);

        $data = $calendarService->getWeekCalendar($specialist, $weekStart);

        return response()->json($data);
    }

    /**
     * Get specialist career data
     */
    public function getCareerData(Request $request, \App\Domain\Specialist\Services\SpecialistCareerService $careerService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        return response()->json($careerService->getCareerData($specialist));
    }

    /**
     * Get specialist craft data
     */
    public function getCraftData(Request $request, \App\Domain\Specialist\Services\SpecialistCraftService $craftService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        return response()->json($craftService->getCraftData($specialist));
    }

    /**
     * Get specialist availability schedule
     */
    public function getAvailability(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $assignment = $specialist->assignments()->where('is_primary', true)->first();
        if (!$assignment) {
            return response()->json(['message' => 'No assignment found'], 404);
        }

        $schedules = \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)
            ->get()
            ->map(function ($schedule) {
                return [
                    'day' => $schedule->day_of_week,
                    'active' => $schedule->is_working_day,
                    'start' => $schedule->start_time,
                    'end' => $schedule->end_time,
                    'break_start' => $schedule->break_start,
                    'break_end' => $schedule->break_end,
                ];
            });

        return response()->json([
            'assignment_id' => $assignment->id,
            'schedule_mode' => $assignment->schedule_mode,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Update specialist availability schedule
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'availability' => 'required|array',
            'availability.*.day' => 'required|string',
            'availability.*.active' => 'required|boolean',
            'availability.*.start' => 'nullable|string',
            'availability.*.end' => 'nullable|string',
            'availability.*.break_start' => 'nullable|string',
            'availability.*.break_end' => 'nullable|string',
        ]);

        $assignment = $specialist->assignments()->where('is_primary', true)->first();
        if (!$assignment) {
            return response()->json(['message' => 'No assignment found'], 404);
        }

        // Set schedule mode to CUSTOM if not already
        if ($assignment->schedule_mode !== 'CUSTOM') {
            $assignment->update(['schedule_mode' => 'CUSTOM']);
        }

        // Update schedules
        \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)->delete();

        foreach ($validated['availability'] as $day) {
            $isWorking = $day['active'] && !empty($day['start']) && !empty($day['end']);

            \App\Models\AssignmentSchedule::create([
                'assignment_id' => $assignment->id,
                'day_of_week' => $day['day'],
                'is_working_day' => $isWorking,
                'start_time' => $isWorking ? $day['start'] : null,
                'end_time' => $isWorking ? $day['end'] : null,
                'break_start' => $day['break_start'] ?? null,
                'break_end' => $day['break_end'] ?? null,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Attach an existing Provider service to the Specialist (Employee / Contractor flow).
     * 
     * The Specialist must have an active assignment with the Provider that owns this service.
     */
    public function attachService(Request $request): JsonResponse
    {
        $account    = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'service_id'  => 'required|uuid|exists:services,id',
            'provider_id' => 'required|uuid|exists:providers,id',
            'skill_level' => 'sometimes|in:beginner,intermediate,expert',
        ]);

        // Guard: Specialist must have an ACTIVE assignment with this Provider.
        $hasAssignment = $specialist->assignments()
            ->where('provider_id', $validated['provider_id'])
            ->whereIn('status', ['ACTIVE', 'active'])
            ->exists();

        if (!$hasAssignment) {
            return response()->json(['message' => 'You have no active assignment with this Provider.'], 403);
        }

        // Guard: Service must belong to this Provider.
        $service = \App\Models\Service::where('id', $validated['service_id'])
            ->where('provider_id', $validated['provider_id'])
            ->first();

        if (!$service) {
            return response()->json(['message' => 'Service does not belong to this Provider.'], 403);
        }

        // Upsert into specialist_service pivot
        \Illuminate\Support\Facades\DB::table('specialist_service')->updateOrInsert(
            ['specialist_id' => $specialist->id, 'service_id' => $validated['service_id']],
            ['skill_level' => $validated['skill_level'] ?? 'intermediate', 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['message' => 'Service attached successfully.'], 201);
    }

    /**
     * Create a brand new Service under a Provider the Specialist controls (Owner / Manager flow),
     * then auto-attach it to themselves via specialist_service.
     */
    public function createAndAttachService(Request $request): JsonResponse
    {
        $account    = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'provider_id' => 'required|uuid|exists:providers,id',
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'duration'    => 'required|integer|min:5',
            'category'    => 'nullable|string|max:100',
            'craft_taxonomy_id' => 'nullable|uuid|exists:craft_taxonomy,id',
            'description' => 'nullable|string',
            'image_media_id' => 'nullable|uuid|exists:media,id',
        ]);

        // Guard: Specialist must be OWNER or MANAGER of this Provider, or it must be their independent practice.
        $assignment = $specialist->assignments()
            ->where('provider_id', $validated['provider_id'])
            ->whereIn('status', ['ACTIVE', 'active'])
            ->first();

        $provider = \App\Models\Provider::find($validated['provider_id']);
        $isOwner = $assignment && (in_array($assignment->role, ['OWNER', 'MANAGER']) || $provider?->type === 'independent_specialist');

        if (!$isOwner) {
            return response()->json(['message' => 'You do not have permission to create services for this Provider.'], 403);
        }

        // Create the service
        $service = \App\Models\Service::create([
            'provider_id' => $validated['provider_id'],
            'name'        => $validated['name'],
            'price'       => $validated['price'],
            'duration'    => $validated['duration'],
            'category'    => $validated['category'] ?? null,
            'craft_taxonomy_id' => $validated['craft_taxonomy_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'image_media_id' => $validated['image_media_id'] ?? null,
            'active'      => true,
        ]);

        // Auto-attach to the Specialist as expert (they created it)
        \Illuminate\Support\Facades\DB::table('specialist_service')->insert([
            'id'            => \Illuminate\Support\Str::uuid(),
            'specialist_id' => $specialist->id,
            'service_id'    => $service->id,
            'skill_level'   => 'expert',
            'is_primary'    => false,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Also attach to the Provider's salon (if assignment has a salon_id)
        if ($assignment->salon_id) {
            \Illuminate\Support\Facades\DB::table('salon_service')->updateOrInsert(
                ['salon_id' => $assignment->salon_id, 'service_id' => $service->id],
                ['is_available' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        return response()->json(['message' => 'Service created and attached successfully.', 'service' => $service], 201);
    }

    /**
     * Update a service (only for owners/managers of the provider)
     */
    public function updateService(Request $request, string $serviceId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $service = \App\Models\Service::find($serviceId);
        if (!$service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        // Guard: Specialist must be OWNER or MANAGER of this Provider
        $assignment = $specialist->assignments()
            ->where('provider_id', $service->provider_id)
            ->whereIn('status', ['ACTIVE', 'active'])
            ->first();

        $provider = \App\Models\Provider::find($service->provider_id);
        $isOwner = $assignment && (in_array($assignment->role, ['OWNER', 'MANAGER']) || $provider?->type === 'independent_specialist');

        if (!$isOwner) {
            return response()->json(['message' => 'You do not have permission to edit this service.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'duration' => 'sometimes|required|integer|min:5',
            'category' => 'nullable|string|max:100',
            'craft_taxonomy_id' => 'nullable|uuid|exists:craft_taxonomy,id',
            'description' => 'nullable|string',
            'image_media_id' => 'nullable|uuid|exists:media,id',
            'active' => 'sometimes|boolean',
        ]);

        $service->update($validated);

        return response()->json(['message' => 'Service updated successfully.', 'service' => $service]);
    }

    /**
     * Create expertise for the authenticated specialist
     */
    public function createExpertise(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'skill_level' => 'required|in:beginner,intermediate,advanced,expert',
        ]);

        $expertise = \App\Models\SpecialistExpertise::create([
            'specialist_id' => $specialist->id,
            'name' => $validated['name'],
            'skill_level' => $validated['skill_level'],
        ]);

        return response()->json(['message' => 'Expertise created successfully.', 'expertise' => $expertise], 201);
    }

    /**
     * Update expertise for the authenticated specialist
     */
    public function updateExpertise(Request $request, string $expertiseId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $expertise = \App\Models\SpecialistExpertise::where('id', $expertiseId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$expertise) {
            return response()->json(['message' => 'Expertise not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'skill_level' => 'sometimes|required|in:beginner,intermediate,advanced,expert',
        ]);

        $expertise->update($validated);

        return response()->json(['message' => 'Expertise updated successfully.', 'expertise' => $expertise]);
    }

    /**
     * Delete expertise for the authenticated specialist
     */
    public function deleteExpertise(Request $request, string $expertiseId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $expertise = \App\Models\SpecialistExpertise::where('id', $expertiseId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$expertise) {
            return response()->json(['message' => 'Expertise not found'], 404);
        }

        $expertise->delete();

        return response()->json(['message' => 'Expertise deleted successfully.']);
    }

    /**
     * Get craft taxonomy for expertise suggestions
     */
    public function getCraftTaxonomy(): JsonResponse
    {
        $taxonomy = \App\Models\CraftTaxonomy::roots()
            ->with(['children' => function ($query) {
                $query->active()->orderBy('sort_order')->with(['children' => function ($q) {
                    $q->active()->orderBy('sort_order');
                }]);
            }])
            ->get()
            ->map(fn ($root) => [
                'id' => $root->id,
                'name' => $root->name,
                'slug' => $root->slug,
                'icon' => $root->icon,
                'children' => $root->children->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $category->children->map(fn ($expertise) => [
                        'id' => $expertise->id,
                        'name' => $expertise->name,
                        'slug' => $expertise->slug,
                    ])->values(),
                ])->values(),
            ])
            ->values();

        return response()->json(['taxonomy' => $taxonomy]);
    }

    /**
     * Create an assessment for a customer
     */
    public function createAssessment(Request $request, string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Verify relationship
        $hasRelationship = $specialist->customers()->where('customers.id', $customerId)->exists();
        if (!$hasRelationship) {
            $hasBooking = Booking::where('specialist_id', $specialist->id)->where('customer_id', $customerId)->exists();
            if (!$hasBooking) {
                return response()->json(['message' => 'Unauthorized customer access'], 403);
            }
        }

        // Force specialist_id from auth, ignore any client input
        $request->merge([
            'customer_id' => $customerId,
            'specialist_id' => $specialist->id,
            'salon_id' => $request->salon_id ?? $specialist->salons()->first()?->id,
        ]);

        $controller = app(\App\Http\Controllers\SpecialistAssessmentController::class);
        return $controller->createAssessment($request);
    }

    /**
     * Get assessments for a customer
     */
    public function getAssessments(string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        $assessments = \App\Models\ProfessionalAssessment::where('customer_id', $customerId)
            ->where('specialist_id', $specialist->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'assessments' => $assessments,
        ]);
    }

    /**
     * Create a note for a customer
     */
    public function createNote(Request $request, string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Verify relationship
        $hasRelationship = $specialist->customers()->where('customers.id', $customerId)->exists();
        if (!$hasRelationship) {
            $hasBooking = Booking::where('specialist_id', $specialist->id)->where('customer_id', $customerId)->exists();
            if (!$hasBooking) {
                return response()->json(['message' => 'Unauthorized customer access'], 403);
            }
        }

        // Force specialist_id from auth, ignore any client input
        $request->merge([
            'customer_id' => $customerId,
            'specialist_id' => $specialist->id,
            'salon_id' => $request->salon_id ?? $specialist->salons()->first()?->id,
        ]);

        $controller = app(\App\Http\Controllers\SpecialistAssessmentController::class);
        return $controller->createNote($request);
    }

    /**
     * Get notes for a customer
     */
    public function getNotes(string $customerId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        $notes = \App\Models\SpecialistNote::where('customer_id', $customerId)
            ->where('specialist_id', $specialist->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'notes' => $notes,
        ]);
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus(Request $request, string $bookingId, \App\Services\BookingService $bookingService): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        $request->validate([
            'status' => 'required|string|in:confirmed,cancelled,in_progress,completed,no_show',
        ]);

        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        // Authorization: Identity must match
        if ($booking->specialist_id !== $specialist->id) {
            return response()->json(['message' => 'Unauthorized to update this booking'], 403);
        }

        // Authorization: Specialist must have an active assignment at this salon
        $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialist->id)
            ->where('salon_id', $booking->salon_id)
            ->first();

        if (!$assignment) {
            return response()->json(['message' => 'Unauthorized: No active assignment at this branch'], 403);
        }

        try {
            $booking = $bookingService->transitionStatus($booking, $request->status);

            return response()->json([
                'message' => 'Booking status updated successfully',
                'booking' => $booking,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get Journey data for specialist
     */
    public function journey(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        // Resolve scope with strict validation
        $scopeResolver = new \App\Domain\Journey\Services\JourneyScopeResolver();
        $assignment = null;
        $providerSlug = $request->query('provider_slug');

        if ($providerSlug) {
            $assignment = $scopeResolver->validateAccess($specialist, $providerSlug);

            if (!$assignment) {
                return response()->json(['message' => 'You do not have access to this workplace'], 403);
            }
        }

        // Use facade - controller stays thin
        $journeyService = new \App\Domain\Journey\Services\JourneyQueryService(
            new \App\Domain\Journey\Services\JourneyMetricCalculator(),
            new \App\Domain\Journey\Services\JourneyMetricRepository(),
            $scopeResolver,
            new \App\Domain\Journey\Services\JourneyGoalService(
                new \App\Domain\Journey\Services\JourneyMetricRepository()
            ),
            new \App\Domain\Journey\Services\JourneyMilestoneService(),
            new \App\Domain\Journey\Services\JourneyGrowthOpportunityService(
                new \App\Domain\Journey\Services\GrowthOpportunityEngine()
            )
        );

        $journeyData = $journeyService->getJourney($specialist, $assignment);

        return response()->json($journeyData);
    }

    /**
     * Create a new goal
     */
    public function createGoal(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal_type' => 'required|in:NUMERIC,QUALITATIVE',
            'metric_key' => 'nullable|string',
            'target_value' => 'nullable|numeric',
            'unit' => 'nullable|string',
            'deadline' => 'nullable|date',
            'priority' => 'nullable|in:LOW,MEDIUM,HIGH,URGENT',
            'scope' => 'nullable|in:SPECIALIST,WORKPLACE',
            'provider_slug' => 'nullable|string',
            'milestones' => 'nullable|array',
            'milestones.*.title' => 'required_with:milestones|string',
            'milestones.*.description' => 'nullable|string',
            'milestones.*.criteria' => 'required_with:milestones|string',
            'milestones.*.craft_taxonomy_id' => 'nullable|string',
        ]);

        $scopeResolver = new \App\Domain\Journey\Services\JourneyScopeResolver();
        $assignment = null;

        if ($request->scope === 'WORKPLACE' || $request->provider_slug) {
            $providerSlug = $request->provider_slug;
            $assignment = $scopeResolver->validateAccess($specialist, $providerSlug);

            if (!$assignment) {
                return response()->json(['message' => 'You do not have access to this workplace'], 403);
            }
        }

        $goalService = new \App\Domain\Journey\Services\JourneyGoalService(
            new \App\Domain\Journey\Services\JourneyMetricRepository()
        );

        $goal = $goalService->createGoal($request->all(), $specialist, $assignment);

        return response()->json(['goal' => $goal], 201);
    }

    /**
     * Update a goal
     */
    public function updateGoal(Request $request, string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'target_value' => 'nullable|numeric',
            'unit' => 'nullable|string',
            'deadline' => 'nullable|date',
            'priority' => 'nullable|in:LOW,MEDIUM,HIGH,URGENT',
        ]);

        $goal->update($request->only(['title', 'description', 'target_value', 'unit', 'deadline', 'priority']));

        return response()->json(['goal' => $goal]);
    }

    /**
     * Delete a goal
     */
    public function deleteGoal(string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        $goal->delete();

        return response()->json(['message' => 'Goal deleted']);
    }

    /**
     * Activate a goal
     */
    public function activateGoal(string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        $goalService = new \App\Domain\Journey\Services\JourneyGoalService(
            new \App\Domain\Journey\Services\JourneyMetricRepository()
        );

        $goal = $goalService->activateGoal($goal);

        return response()->json(['goal' => $goal]);
    }

    /**
     * Pause a goal
     */
    public function pauseGoal(string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        $goalService = new \App\Domain\Journey\Services\JourneyGoalService(
            new \App\Domain\Journey\Services\JourneyMetricRepository()
        );

        $goal = $goalService->pauseGoal($goal);

        return response()->json(['goal' => $goal]);
    }

    /**
     * Complete a goal
     */
    public function completeGoal(string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        $goalService = new \App\Domain\Journey\Services\JourneyGoalService(
            new \App\Domain\Journey\Services\JourneyMetricRepository()
        );

        $goal = $goalService->completeGoal($goal);

        return response()->json(['goal' => $goal]);
    }

    /**
     * Get earnings data for a period.
     *
     * Source of truth: the Earning model (Finance Compensation domain).
     * Never calculate from Booking.price — that ignores the compensation policy.
     * Free-tier specialists receive basic earning totals; Pro adds analytics layers.
     */
    public function getEarnings(Request $request): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $period = $request->query('period', 'today');
        $now = Carbon::now();

        $startDate = match($period) {
            'today' => $now->copy()->startOfDay(),
            'week'  => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        };

        // Query Earning records for this specialist — the canonical financial truth.
        $earnings = \App\Domain\Finance\Compensation\Earning::forCompensatable(
                \App\Models\Specialist::class,
                $specialist->id
            )
            ->active()
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $now)
            ->with('source', 'policy')
            ->get();

        $totalEarned       = (float) $earnings->sum('gross_amount');
        $totalSettled      = (float) $earnings->whereIn('status', [
            \App\Domain\Finance\Compensation\Earning::STATUS_PAYABLE,
            \App\Domain\Finance\Compensation\Earning::STATUS_PAID,
        ])->sum('gross_amount');
        $outstandingBalance = (float) $earnings->where('status', \App\Domain\Finance\Compensation\Earning::STATUS_EARNED)->sum('gross_amount')
            + (float) $earnings->where('status', \App\Domain\Finance\Compensation\Earning::STATUS_INCLUDED)->sum('gross_amount');

        // Unique clients: count from the source booking relationships
        $clients = $earnings
            ->filter(fn ($e) => $e->source_type === \App\Models\Booking::class)
            ->pluck('source.customer_id')
            ->filter()
            ->unique()
            ->count();

        // Group by salon to show the specialist which locations generated what
        $bySalon = $earnings
            ->filter(fn ($e) => $e->source_type === \App\Models\Booking::class && $e->source)
            ->groupBy(fn ($e) => $e->source->salon_id)
            ->map(function ($group, $salonId) {
                return [
                    'salon_id' => $salonId,
                    'clients'  => $group
                        ->pluck('source.customer_id')
                        ->filter()
                        ->unique()
                        ->count(),
                    'revenue'  => (float) $group->sum('gross_amount'),
                ];
            })
            ->values();

        return response()->json([
            'total_earned'       => $totalEarned,
            'total_settled'      => $totalSettled,
            'outstanding_balance'=> $outstandingBalance,
            'clients'            => $clients,
            'by_salon'           => $bySalon,
        ]);
    }

    /**
     * Get goal details
     */
    public function getGoal(string $goalId): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $goal = \App\Models\JourneyGoal::where('id', $goalId)
            ->where('specialist_id', $specialist->id)
            ->with('milestones')
            ->first();

        if (!$goal) {
            return response()->json(['message' => 'Goal not found'], 404);
        }

        return response()->json(['goal' => $goal]);
    }

    /**
     * Get specialist capabilities summary
     */
    public function capabilities(): JsonResponse
    {
        $account = auth('specialist')->user();
        $specialist = $account->specialist;

        if (!$specialist) {
            return response()->json(['message' => 'Specialist not found'], 404);
        }

        $capabilities = $this->capabilityResolver->getCapabilitiesSummary($specialist->id);

        return response()->json($capabilities);
    }
}
