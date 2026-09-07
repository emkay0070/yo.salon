<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroomingProfile;
use App\Models\Service;
use App\Models\ReferenceData;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceRecommendationController extends Controller
{
    /**
     * Get service recommendations based on customer profile
     */
    public function getRecommendations(string $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $groomingProfile = $customer->groomingProfile;
        if (!$groomingProfile) {
            return response()->json([
                'recommendations' => [],
                'reason' => 'No grooming profile found',
            ]);
        }

        $recommendations = $this->generateServiceRecommendations($groomingProfile);

        return response()->json([
            'customer' => $customer,
            'grooming_profile' => $groomingProfile,
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * Generate service recommendations based on grooming profile
     */
    private function generateServiceRecommendations(CustomerGroomingProfile $profile): array
    {
        $recommendations = [];
        $concerns = $profile->hair_concerns ?? [];
        $hairType = $profile->hair_type;
        $skinType = $profile->skin_type;

        // Hair concern-based recommendations
        $concernMappings = [
            'dandruff' => [
                'service_keywords' => ['scalp', 'treatment', 'therapy', 'dandruff'],
                'reason' => 'Based on your dandruff concern',
                'priority' => 'high',
            ],
            'dry_hair' => [
                'service_keywords' => ['moisturizing', 'hydration', 'treatment', 'conditioning'],
                'reason' => 'Based on your dry hair concern',
                'priority' => 'medium',
            ],
            'oily_hair' => [
                'service_keywords' => ['clarifying', 'balancing', 'treatment'],
                'reason' => 'Based on your oily hair concern',
                'priority' => 'medium',
            ],
            'hair_loss' => [
                'service_keywords' => ['scalp', 'treatment', 'therapy', 'growth'],
                'reason' => 'Based on your hair loss concern',
                'priority' => 'high',
            ],
            'damaged_hair' => [
                'service_keywords' => ['repair', 'treatment', 'restoration', 'keratin'],
                'reason' => 'Based on your damaged hair concern',
                'priority' => 'high',
            ],
            'frizzy' => [
                'service_keywords' => ['smoothing', 'treatment', 'keratin', 'anti-frizz'],
                'reason' => 'Based on your frizzy hair concern',
                'priority' => 'medium',
            ],
            'color_treated' => [
                'service_keywords' => ['color', 'treatment', 'protection', 'maintenance'],
                'reason' => 'Based on your color-treated hair',
                'priority' => 'medium',
            ],
        ];

        foreach ($concerns as $concern) {
            $mapping = $concernMappings[$concern] ?? null;
            if ($mapping) {
                $services = $this->findMatchingServices($mapping['service_keywords']);
                foreach ($services as $service) {
                    $recommendations[] = [
                        'service' => $service,
                        'reason' => $mapping['reason'],
                        'priority' => $mapping['priority'],
                        'type' => 'concern_based',
                    ];
                }
            }
        }

        // Hair type-based recommendations
        $hairTypeMappings = [
            'curly' => [
                'service_keywords' => ['curly', 'hydration', 'definition', 'treatment'],
                'reason' => 'Recommended for curly hair',
                'priority' => 'medium',
            ],
            'coily' => [
                'service_keywords' => ['coily', 'moisture', 'deep', 'treatment'],
                'reason' => 'Recommended for coily hair',
                'priority' => 'medium',
            ],
            'wavy' => [
                'service_keywords' => ['wavy', 'definition', 'treatment'],
                'reason' => 'Recommended for wavy hair',
                'priority' => 'low',
            ],
            'straight' => [
                'service_keywords' => ['straight', 'volume', 'treatment'],
                'reason' => 'Recommended for straight hair',
                'priority' => 'low',
            ],
        ];

        if ($hairType && isset($hairTypeMappings[$hairType])) {
            $mapping = $hairTypeMappings[$hairType];
            $services = $this->findMatchingServices($mapping['service_keywords']);
            foreach ($services as $service) {
                // Avoid duplicates
                $exists = collect($recommendations)->contains('service.id', $service->id);
                if (!$exists) {
                    $recommendations[] = [
                        'service' => $service,
                        'reason' => $mapping['reason'],
                        'priority' => $mapping['priority'],
                        'type' => 'hair_type_based',
                    ];
                }
            }
        }

        // Skin type-based recommendations
        $skinTypeMappings = [
            'dry' => [
                'service_keywords' => ['hydrating', 'moisturizing', 'facial', 'treatment'],
                'reason' => 'Recommended for dry skin',
                'priority' => 'medium',
            ],
            'oily' => [
                'service_keywords' => ['clarifying', 'balancing', 'facial', 'treatment'],
                'reason' => 'Recommended for oily skin',
                'priority' => 'medium',
            ],
            'sensitive' => [
                'service_keywords' => ['soothing', 'gentle', 'facial', 'treatment'],
                'reason' => 'Recommended for sensitive skin',
                'priority' => 'high',
            ],
            'combination' => [
                'service_keywords' => ['balancing', 'facial', 'treatment'],
                'reason' => 'Recommended for combination skin',
                'priority' => 'medium',
            ],
        ];

        if ($skinType && isset($skinTypeMappings[$skinType])) {
            $mapping = $skinTypeMappings[$skinType];
            $services = $this->findMatchingServices($mapping['service_keywords']);
            foreach ($services as $service) {
                $exists = collect($recommendations)->contains('service.id', $service->id);
                if (!$exists) {
                    $recommendations[] = [
                        'service' => $service,
                        'reason' => $mapping['reason'],
                        'priority' => $mapping['priority'],
                        'type' => 'skin_type_based',
                    ];
                }
            }
        }

        // Sort by priority (high > medium > low)
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($recommendations, function ($a, $b) use ($priorityOrder) {
            return $priorityOrder[$b['priority']] <=> $priorityOrder[$a['priority']];
        });

        return array_slice($recommendations, 0, 10); // Limit to top 10
    }

    /**
     * Find services matching given keywords
     */
    private function findMatchingServices(array $keywords): array
    {
        $query = Service::where('is_active', true);

        foreach ($keywords as $keyword) {
            $query->orWhere('name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
        }

        return $query->get()->toArray();
    }

    /**
     * Get service recommendations for authenticated customer (portal)
     */
    public function getMyRecommendations(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return $this->getRecommendations($customer->id);
    }

    /**
     * Get specialist recommendations based on customer profile
     */
    public function getSpecialistRecommendations(string $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $groomingProfile = $customer->groomingProfile;
        if (!$groomingProfile) {
            return response()->json([
                'specialists' => [],
                'reason' => 'No grooming profile found',
            ]);
        }

        $specialists = $this->findMatchingSpecialists($groomingProfile);

        return response()->json([
            'customer' => $customer,
            'grooming_profile' => $groomingProfile,
            'specialists' => $specialists,
        ]);
    }

    /**
     * Find specialists matching customer profile
     */
    private function findMatchingSpecialists(CustomerGroomingProfile $profile): array
    {
        $hairType = $profile->hair_type;
        $skinType = $profile->skin_type;
        $concerns = $profile->hair_concerns ?? [];

        $query = Specialist::where('active', true);

        // Match by specialties
        if ($hairType) {
            $query->whereJsonContains('specialties', $hairType);
        }

        // Get specialists and score them
        $specialists = $query->get()->map(function ($specialist) use ($profile, $concerns) {
            $score = 0;
            $reasons = [];

            // Check if specialist matches hair type
            if ($specialist->specialties && in_array($profile->hair_type, $specialist->specialties)) {
                $score += 30;
                $reasons[] = "Specializes in {$profile->hair_type} hair";
            }

            // Check rating
            if ($specialist->rating >= 4.5) {
                $score += 20;
                $reasons[] = "Highly rated ({$specialist->rating})";
            }

            // Check experience
            if ($specialist->years_experience >= 5) {
                $score += 15;
                $reasons[] = "Experienced ({$specialist->years_experience} years)";
            }

            // Check concerns match
            if ($specialist->skills && !empty(array_intersect($concerns, $specialist->skills))) {
                $score += 25;
                $reasons[] = "Skilled in addressing your concerns";
            }

            // Review count
            if ($specialist->review_count >= 10) {
                $score += 10;
                $reasons[] = "Well-reviewed ({$specialist->review_count} reviews)";
            }

            return [
                'specialist' => $specialist,
                'match_score' => $score,
                'match_reasons' => $reasons,
            ];
        })->sortByDesc('match_score')->values()->take(10)->toArray();

        return $specialists;
    }

    /**
     * Get specialist recommendations for authenticated customer (portal)
     */
    public function getMySpecialistRecommendations(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return $this->getSpecialistRecommendations($customer->id);
    }
}
