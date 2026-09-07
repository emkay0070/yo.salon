<?php

namespace App\Services;

use App\Models\CustomerWorkspaceInvitation;
use App\Models\CustomerSpecialist;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;

class CustomerWorkspaceInvitationService
{
    /**
     * Create a workspace invitation for a customer
     * This does NOT create a CustomerSpecialist relationship - only the invitation
     */
    public function createInvitation(
        string $customerId,
        string $specialistId,
        string $targetProviderId,
        ?string $sourceProviderId = null,
        string $channel = CustomerWorkspaceInvitation::CHANNEL_SPECIALIST_DIRECT,
        ?int $expiresInHours = null
    ): CustomerWorkspaceInvitation {
        // Validate that specialist has access to target provider
        $specialist = Specialist::find($specialistId);
        if (!$specialist) {
            throw new \InvalidArgumentException('Specialist not found');
        }

        $targetProvider = Provider::find($targetProviderId);
        if (!$targetProvider) {
            throw new \InvalidArgumentException('Target provider not found');
        }

        // Verify specialist has active assignment to target provider
        $assignment = SpecialistAssignment::where('specialist_id', $specialistId)
            ->where('provider_id', $targetProviderId)
            ->where('status', 'active')
            ->first();

        if (!$assignment) {
            throw new \InvalidArgumentException('Specialist does not have active assignment to target provider');
        }

        // Check for existing pending invitation
        $existing = CustomerWorkspaceInvitation::where('customer_id', $customerId)
            ->where('specialist_id', $specialistId)
            ->where('target_provider_id', $targetProviderId)
            ->pending()
            ->first();

        if ($existing) {
            return $existing; // Return existing pending invitation
        }

        // Create new invitation
        $invitation = CustomerWorkspaceInvitation::create([
            'customer_id' => $customerId,
            'specialist_id' => $specialistId,
            'target_provider_id' => $targetProviderId,
            'source_provider_id' => $sourceProviderId,
            'channel' => $channel,
            'status' => CustomerWorkspaceInvitation::STATUS_SENT,
            'sent_at' => now(),
            'expires_at' => $expiresInHours ? now()->addHours($expiresInHours) : null,
        ]);

        return $invitation;
    }

    /**
     * Mark invitation as opened
     */
    public function markAsOpened(string $invitationId): CustomerWorkspaceInvitation
    {
        $invitation = CustomerWorkspaceInvitation::findOrFail($invitationId);
        $invitation->markAsOpened();
        return $invitation->fresh();
    }

    /**
     * Accept invitation and create the CustomerSpecialist relationship
     * This is where the actual relationship is created, not when invitation is sent
     */
    public function acceptInvitation(string $invitationId): CustomerSpecialist
    {
        return DB::transaction(function () use ($invitationId) {
            $invitation = CustomerWorkspaceInvitation::findOrFail($invitationId);

            if (!$invitation->canBeAccepted()) {
                throw new \InvalidArgumentException('Invitation cannot be accepted');
            }

            // Mark invitation as accepted
            $invitation->markAsAccepted();

            // Create the actual CustomerSpecialist relationship
            $relationship = CustomerSpecialist::firstOrCreate(
                [
                    'customer_id' => $invitation->customer_id,
                    'specialist_id' => $invitation->specialist_id,
                    'provider_id' => $invitation->target_provider_id,
                ],
                [
                    'relationship_origin' => CustomerSpecialist::ORIGIN_INDEPENDENT,
                    'acquisition_source' => CustomerSpecialist::SOURCE_INVITATION,
                    'source_provider_id' => $invitation->source_provider_id,
                    'total_bookings' => 0,
                    'total_spent' => 0,
                    'relationship_score' => 0,
                    'last_interaction_at' => now(),
                    'last_seen_at' => now(),
                ]
            );

            return $relationship;
        });
    }

    /**
     * Decline invitation
     */
    public function declineInvitation(string $invitationId): CustomerWorkspaceInvitation
    {
        $invitation = CustomerWorkspaceInvitation::findOrFail($invitationId);
        $invitation->markAsDeclined();
        return $invitation->fresh();
    }

    /**
     * Get pending invitations for a customer
     */
    public function getPendingInvitationsForCustomer(string $customerId)
    {
        return CustomerWorkspaceInvitation::where('customer_id', $customerId)
            ->pending()
            ->with(['specialist', 'targetProvider', 'sourceProvider'])
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    /**
     * Get invitations sent by a specialist
     */
    public function getInvitationsBySpecialist(string $specialistId, ?string $targetProviderId = null)
    {
        $query = CustomerWorkspaceInvitation::where('specialist_id', $specialistId)
            ->with(['customer', 'targetProvider', 'sourceProvider']);

        if ($targetProviderId) {
            $query->where('target_provider_id', $targetProviderId);
        }

        return $query->orderBy('sent_at', 'desc')->get();
    }

    /**
     * Get invitation statistics for a specialist
     */
    public function getInvitationStats(string $specialistId, ?string $targetProviderId = null): array
    {
        $query = CustomerWorkspaceInvitation::where('specialist_id', $specialistId);

        if ($targetProviderId) {
            $query->where('target_provider_id', $targetProviderId);
        }

        $invitations = $query->get();

        return [
            'total_sent' => $invitations->count(),
            'opened' => $invitations->where('status', CustomerWorkspaceInvitation::STATUS_OPENED)->count(),
            'accepted' => $invitations->where('status', CustomerWorkspaceInvitation::STATUS_ACCEPTED)->count(),
            'declined' => $invitations->where('status', CustomerWorkspaceInvitation::STATUS_DECLINED)->count(),
            'expired' => $invitations->where('status', CustomerWorkspaceInvitation::STATUS_EXPIRED)->count(),
            'pending' => $invitations->whereIn('status', [CustomerWorkspaceInvitation::STATUS_SENT, CustomerWorkspaceInvitation::STATUS_OPENED])->count(),
        ];
    }

    /**
     * Expire old pending invitations
     */
    public function expireOldInvitations(): int
    {
        $expired = CustomerWorkspaceInvitation::pending()
            ->where('expires_at', '<', now())
            ->get();

        $count = $expired->count();
        foreach ($expired as $invitation) {
            $invitation->markAsExpired();
        }

        return $count;
    }
}
