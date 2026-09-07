<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use App\Models\SpecialistCareerEvent;

class SpecialistCareerService
{
    /**
     * Retrieve the specialist's career data.
     */
    public function getCareerData(Specialist $specialist): array
    {
        // Derived reputation data from platform
        $totalReviews = $specialist->review_count ?? 0;
        $totalClients = $specialist->customers()->count();
        
        // Calculate retention rate (clients who rebooked)
        $rebookingClients = $specialist->customers()
            ->where('total_bookings', '>', 1)
            ->count();
        $retentionRate = $totalClients > 0 
            ? round(($rebookingClients / $totalClients) * 100) 
            : 0;
        
        // Rebooking rate (same as retention for now)
        $rebookingRate = $retentionRate;
        
        // Attendance and punctuality from specialist model
        $attendanceRate = $specialist->attendance ?? 98;
        $punctualityRate = $specialist->punctuality ?? 99;
        
        // Manually maintained fields
        $yearsExperience = $specialist->years_experience ?? 0;
        $certifications = $specialist->certifications ?? [];
        
        // Achievements from career events
        $achievements = $specialist->careerEvents()
            ->where('type', SpecialistCareerEvent::TYPE_ACHIEVEMENT)
            ->orWhere('type', SpecialistCareerEvent::TYPE_AWARD)
            ->orWhere('type', SpecialistCareerEvent::TYPE_MILESTONE)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'icon' => $event->icon,
                    'date' => $event->date->format('Y-m-d'),
                    'type' => $event->type,
                ];
            })->toArray();
        
        // Employment history from career events
        $employmentHistory = $specialist->careerEvents()
            ->where('type', SpecialistCareerEvent::TYPE_EMPLOYMENT)
            ->orWhere('type', SpecialistCareerEvent::TYPE_SALON_CHANGE)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($event) {
                $salonName = $event->salon ? $event->salon->name : 'Unknown Salon';
                return [
                    'salon' => $salonName,
                    'period' => $event->date->format('Y') . ' - Present',
                    'role' => $event->data['role'] ?? 'Specialist',
                ];
            })->toArray();
        
        return [
            'rating' => $specialist->rating ?? 0,
            'totalReviews' => $totalReviews,
            'totalClients' => $totalClients,
            'retentionRate' => $retentionRate,
            'rebookingRate' => $rebookingRate,
            'attendanceRate' => $attendanceRate,
            'punctualityRate' => $punctualityRate,
            'yearsExperience' => $yearsExperience,
            'certifications' => $certifications,
            'achievements' => $achievements,
            'employmentHistory' => $employmentHistory,
        ];
    }
}
