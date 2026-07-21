<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FeaturePolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'feature_key',
        'policy_type',
        'rule_value',
        'description',
        'active',
    ];

    protected $casts = [
        'rule_value' => 'array',
        'active' => 'boolean',
    ];

    /**
     * Get active policies for a feature
     */
    public static function getActivePolicies(string $featureKey): array
    {
        return self::where('feature_key', $featureKey)
            ->where('active', true)
            ->get()
            ->toArray();
    }

    /**
     * Seed default feature policies
     */
    public static function seedDefaultPolicies(): void
    {
        $policies = [
            [
                'feature_key' => 'loyalty',
                'policy_type' => 'subscription',
                'rule_value' => ['>=', 'Professional'],
                'description' => 'Loyalty requires Professional subscription or higher',
                'active' => true,
            ],
            [
                'feature_key' => 'loyalty',
                'policy_type' => 'customer_count',
                'rule_value' => ['>', 100],
                'description' => 'Loyalty requires more than 100 customers',
                'active' => true,
            ],
            [
                'feature_key' => 'packages',
                'policy_type' => 'subscription',
                'rule_value' => ['>=', 'Premium'],
                'description' => 'Packages require Premium subscription or higher',
                'active' => true,
            ],
            [
                'feature_key' => 'gift_cards',
                'policy_type' => 'subscription',
                'rule_value' => ['>=', 'Professional'],
                'description' => 'Gift cards require Professional subscription or higher',
                'active' => true,
            ],
            [
                'feature_key' => 'gift_cards',
                'policy_type' => 'payments_enabled',
                'rule_value' => true,
                'description' => 'Gift cards require payments to be enabled',
                'active' => true,
            ],
            [
                'feature_key' => 'ai_recommendations',
                'policy_type' => 'booking_count',
                'rule_value' => ['>', 500],
                'description' => 'AI recommendations require more than 500 bookings',
                'active' => true,
            ],
            [
                'feature_key' => 'ai_recommendations',
                'policy_type' => 'customer_history_exists',
                'rule_value' => true,
                'description' => 'AI recommendations require customer history data',
                'active' => true,
            ],
        ];

        foreach ($policies as $policy) {
            self::firstOrCreate(
                [
                    'feature_key' => $policy['feature_key'],
                    'policy_type' => $policy['policy_type'],
                ],
                $policy
            );
        }
    }
}
