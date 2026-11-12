<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Provider;
use App\Models\LocationSchedule;
use App\Models\ProviderSchedule;
use Illuminate\Support\Facades\Log;

class ScheduleMigrationService
{
    private array $dayMap = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 0,
    ];

    /**
     * Check if a provider needs schedule migration
     */
    public function needsMigration(string $providerId): bool
    {
        $location = Location::where('provider_id', $providerId)
            ->whereNotNull('opening_hours')
            ->first();

        if (!$location) {
            return false;
        }

        // Check if location already has canonical schedules
        $hasLocationSchedules = LocationSchedule::where('location_id', $location->id)->exists();
        $hasProviderSchedules = ProviderSchedule::where('provider_id', $providerId)->exists();

        return !$hasLocationSchedules || !$hasProviderSchedules;
    }

    /**
     * Get all providers that need migration
     */
    public function getProvidersNeedingMigration(): array
    {
        $locations = Location::whereNotNull('opening_hours')
            ->whereDoesntHave('schedules')
            ->with('provider')
            ->get();

        $providers = [];
        foreach ($locations as $location) {
            // Skip locations without a valid provider
            if (!$location->provider) {
                continue;
            }
            
            $providers[] = [
                'provider_id' => $location->provider->id,
                'provider_name' => $location->provider->display_name,
                'location_id' => $location->id,
                'location_name' => $location->name,
                'opening_hours' => $location->opening_hours,
            ];
        }

        return $providers;
    }

    /**
     * Migrate schedules for a specific provider
     */
    public function migrateProvider(string $providerId): array
    {
        $location = Location::where('provider_id', $providerId)
            ->whereNotNull('opening_hours')
            ->first();

        if (!$location) {
            return [
                'success' => false,
                'message' => 'No location with opening_hours found for this provider',
            ];
        }

        $openingHours = $location->opening_hours;
        if (!is_array($openingHours)) {
            return [
                'success' => false,
                'message' => 'Invalid opening_hours format',
            ];
        }

        $workingDays = array_keys($openingHours);
        $locationSchedulesCreated = 0;
        $providerSchedulesCreated = 0;

        // Create LocationSchedule records
        foreach (array_keys($this->dayMap) as $frontendDay) {
            if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
                $hours = $openingHours[$frontendDay];
                LocationSchedule::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'day_of_week' => $frontendDay,
                    ],
                    [
                        'open_time' => $hours['open'] . ':00',
                        'close_time' => $hours['close'] . ':00',
                        'is_closed' => false,
                    ]
                );
                $locationSchedulesCreated++;
            } else {
                LocationSchedule::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'day_of_week' => $frontendDay,
                    ],
                    [
                        'open_time' => null,
                        'close_time' => null,
                        'is_closed' => true,
                    ]
                );
                $locationSchedulesCreated++;
            }
        }

        // Create ProviderSchedule records
        foreach ($this->dayMap as $frontendDay => $dayOfWeekInt) {
            if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
                $hours = $openingHours[$frontendDay];
                ProviderSchedule::updateOrCreate(
                    [
                        'provider_id' => $providerId,
                        'day_of_week' => $dayOfWeekInt,
                    ],
                    [
                        'open_time' => $hours['open'] . ':00',
                        'close_time' => $hours['close'] . ':00',
                        'is_closed' => false,
                    ]
                );
                $providerSchedulesCreated++;
            } else {
                ProviderSchedule::updateOrCreate(
                    [
                        'provider_id' => $providerId,
                        'day_of_week' => $dayOfWeekInt,
                    ],
                    [
                        'open_time' => null,
                        'close_time' => null,
                        'is_closed' => true,
                    ]
                );
                $providerSchedulesCreated++;
            }
        }

        Log::info('Schedule migration completed', [
            'provider_id' => $providerId,
            'location_schedules_created' => $locationSchedulesCreated,
            'provider_schedules_created' => $providerSchedulesCreated,
        ]);

        return [
            'success' => true,
            'message' => 'Schedule migration completed successfully',
            'location_schedules_created' => $locationSchedulesCreated,
            'provider_schedules_created' => $providerSchedulesCreated,
        ];
    }

    /**
     * Create notification for provider needing migration
     */
    public function createMigrationNotification(string $providerId, string $providerName): void
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return;
        }

        // Get the owner user for this provider
        $ownerUser = $provider->users()->first();
        if (!$ownerUser) {
            return;
        }

        \App\Models\Notification::create([
            'provider_id' => $providerId,
            'user_id' => $ownerUser->id,
            'type' => 'schedule_migration',
            'category' => 'system',
            'priority' => 'high',
            'icon' => 'clock',
            'title' => 'Schedule Update Required',
            'message' => "Your business hours need to be updated to our new scheduling system. This one-time update will improve booking accuracy and enable new features.",
            'data' => [
                'provider_id' => $providerId,
                'provider_name' => $providerName,
                'action' => 'migrate_schedule',
            ],
            'action_url' => '/settings/schedule',
        ]);

        Log::info('Migration notification created', [
            'provider_id' => $providerId,
            'user_id' => $ownerUser->id,
        ]);
    }

    /**
     * Send migration notifications to all providers needing migration
     */
    public function notifyAllProvidersNeedingMigration(): int
    {
        $providers = $this->getProvidersNeedingMigration();
        $notificationsCreated = 0;

        foreach ($providers as $provider) {
            $this->createMigrationNotification(
                $provider['provider_id'],
                $provider['provider_name']
            );
            $notificationsCreated++;
        }

        return $notificationsCreated;
    }
}
