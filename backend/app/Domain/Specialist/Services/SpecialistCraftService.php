<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use Illuminate\Support\Facades\DB;

class SpecialistCraftService
{
    /**
     * Retrieve the specialist's craft data.
     *
     * Returns:
     *  - expertise:      Derived from raw JSON skills (identity layer, unchanged)
     *  - raw_skills:     The specialist's JSON skill tags (preserved as-is)
     *  - services_by_provider: Commercial services from specialist_service pivot,
     *                          grouped by Provider relationship. This is the
     *                          multi-provider-aware layer.
     *  - learning:       Career events of type 'learning'
     *  - products:       Career events of type 'product'
     */
    public function getCraftData(Specialist $specialist): array
    {
        // --- Identity Layer (persistent expertise from database) ---
        $expertise = $specialist->expertise()
            ->get()
            ->map(fn ($e) => [
                'id'    => $e->id,
                'name'  => $e->name,
                'level' => ucfirst($e->skill_level),
                'years' => 0, // No longer derived from years_experience
            ])
            ->values()
            ->toArray();

        // --- Reputation Layer (customer evidence per service) ---
        $serviceReputation = $this->getServiceReputation($specialist);

        $rawSkills = $specialist->skills ?? [];

        // --- Commercial Services Layer (via specialist_service pivot) ---
        // Grouped by Provider, respecting multi-provider architecture.
        $servicesByProvider = $this->getServicesByProvider($specialist);

        // --- Learning (career events) ---
        $learning = $specialist->careerEvents()
            ->where('type', 'learning')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($e) => [
                'id'        => $e->id,
                'name'      => $e->title,
                'progress'  => $e->data['progress'] ?? 0,
                'completed' => $e->data['completed'] ?? false,
            ])
            ->values()
            ->toArray();

        // --- Products (career events) ---
        $products = $specialist->careerEvents()
            ->where('type', 'product')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($e) => [
                'id'      => $e->id,
                'name'    => $e->title,
                'brand'   => $e->data['brand'] ?? 'Unknown',
                'mastered'=> $e->data['mastered'] ?? false,
                'usage'   => $e->data['usage'] ?? 0,
            ])
            ->values()
            ->toArray();

        return [
            'expertise'            => $expertise,
            'service_reputation'   => $serviceReputation,
            'raw_skills'           => $rawSkills,
            'services_by_provider' => $servicesByProvider,
            'learning'             => $learning,
            'products'             => $products,
        ];
    }

    /**
     * Query the specialist_service pivot and group commercial services by Provider.
     *
     * INVARIANT: specialist_assignments is the source of truth for the Specialist's
     * Provider relationships. We join against it here to enrich each service group
     * with the role/employment_type context the Specialist has at that Provider.
     */
    private function getServicesByProvider(Specialist $specialist): array
    {
        // 1. Fetch all services linked to this specialist via the pivot
        $rows = DB::table('specialist_service')
            ->join('services', 'services.id', '=', 'specialist_service.service_id')
            ->join('providers', 'providers.id', '=', 'services.provider_id')
            // Join media to get image URL
            ->leftJoin('media', 'media.id', '=', 'services.image_media_id')
            // Join craft taxonomy to get category information
            ->leftJoin('craft_taxonomy', 'craft_taxonomy.id', '=', 'services.craft_taxonomy_id')
            // Join assignments to get the Specialist's role at each Provider
            ->leftJoin('specialist_assignments', function ($join) use ($specialist) {
                $join->on('specialist_assignments.provider_id', '=', 'services.provider_id')
                     ->where('specialist_assignments.specialist_id', '=', $specialist->id)
                     ->whereIn('specialist_assignments.status', ['ACTIVE', 'active']);
            })
            ->where('specialist_service.specialist_id', $specialist->id)
            ->select(
                // Service fields
                'services.id as service_id',
                'services.name as service_name',
                'services.description as service_description',
                'services.price as service_price',
                'services.duration as service_duration',
                'services.category as service_category',
                'services.active as service_active',
                'services.image_media_id',
                // Craft taxonomy fields
                'craft_taxonomy.name as craft_taxonomy_name',
                'craft_taxonomy.slug as craft_taxonomy_slug',
                // Media fields for image URL
                'media.disk',
                'media.path',
                // Specialist's capability at this service
                'specialist_service.skill_level',
                'specialist_service.is_primary as is_primary_service',
                'specialist_service.price_override',
                // Provider context
                'providers.id as provider_id',
                'providers.display_name as provider_display_name',
                'providers.type as provider_type',
                'providers.slug as provider_slug',
                // Assignment context (role at this Provider)
                'specialist_assignments.role as assignment_role',
                'specialist_assignments.employment_type as assignment_employment_type',
                'specialist_assignments.id as assignment_id',
            )
            ->get();

        // 2. Group services by Provider
        $grouped = [];
        foreach ($rows as $row) {
            $pid = $row->provider_id;

            $isOwner = in_array($row->assignment_role, ['OWNER', 'MANAGER']) || $row->provider_type === 'independent_specialist';

            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [
                    'provider_id'         => $row->provider_id,
                    'provider_name'       => $row->provider_display_name,
                    'provider_type'       => $row->provider_type,
                    'provider_slug'       => $row->provider_slug,
                    'assignment_id'       => $row->assignment_id,
                    'role'                => $row->assignment_role,
                    'employment_type'     => $row->assignment_employment_type,
                    'can_manage_provider' => $isOwner,
                    'can_create_services' => $isOwner,
                    'services'            => [],
                ];
            }

            $grouped[$pid]['services'][] = [
                'id'             => $row->service_id,
                'name'           => $row->service_name,
                'description'    => $row->service_description,
                'price'          => $row->price_override ?? $row->service_price,
                'base_price'     => $row->service_price,
                'price_override' => $row->price_override,
                'duration'       => $row->service_duration,
                'category'       => $row->service_category,
                'craft_taxonomy_name' => $row->craft_taxonomy_name,
                'craft_taxonomy_slug' => $row->craft_taxonomy_slug,
                'active'         => (bool) $row->service_active,
                'skill_level'    => $row->skill_level,
                'is_primary'     => (bool) $row->is_primary_service,
                'image_media_id' => $row->image_media_id,
                'image_url'      => $row->disk && $row->path ? \Illuminate\Support\Facades\Storage::disk($row->disk)->url($row->path) : null,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Get reputation data (customer evidence) per service for this specialist.
     * Aggregates reviews linked to services the specialist has performed.
     */
    private function getServiceReputation(Specialist $specialist): array
    {
        $reputation = DB::table('reviews')
            ->join('services', 'services.id', '=', 'reviews.service_id')
            ->where('reviews.specialist_id', $specialist->id)
            ->whereNotNull('reviews.service_id')
            ->select(
                'services.id as service_id',
                'services.name as service_name',
                DB::raw('COUNT(reviews.id) as review_count'),
                DB::raw('AVG(reviews.rating) as average_rating')
            )
            ->groupBy('services.id', 'services.name')
            ->get()
            ->map(fn ($row) => [
                'service_id' => $row->service_id,
                'service_name' => $row->service_name,
                'review_count' => (int) $row->review_count,
                'average_rating' => round((float) $row->average_rating, 1),
            ])
            ->values()
            ->toArray();

        return $reputation;
    }
}
