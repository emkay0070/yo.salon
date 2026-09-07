<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SpecialistProfileService
{
    /**
     * Retrieve the specialist's profile data.
     */
    public function getProfile(Specialist $specialist): array
    {
        // To resolve the location, we check if the specialist has an active salon/workspace
        $activeSalon = $specialist->salons()->wherePivot('active', true)->first();
        $location = null;
        if ($activeSalon) {
            $location = $activeSalon->address ?? $activeSalon->city ?? $activeSalon->name;
        }

        // Get portfolio from Media attachable architecture with error handling
        $portfolio = [];
        try {
            $portfolioMedia = $specialist->portfolioMedia()->get(['id', 'url', 'alt_text', 'metadata']);
            $portfolio = $portfolioMedia->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->url,
                    'alt_text' => $media->alt_text,
                    'metadata' => $media->metadata,
                ];
            })->toArray();
        } catch (\Exception $e) {
            // If portfolio media query fails, fall back to empty array
            $portfolio = $specialist->portfolio ?? [];
        }

        return [
            'id' => $specialist->id,
            'name' => $specialist->name,
            'email' => $specialist->email,
            'phone' => $specialist->phone,
            'handle' => $specialist->handle,
            'headline' => $specialist->headline,
            'bio' => $specialist->bio,
            'languages' => $specialist->languages,
            'social_links' => $specialist->social_links,
            'photo_url' => $specialist->photo_url,
            'location' => $location,
            'latitude' => $specialist->latitude,
            'longitude' => $specialist->longitude,
            'specialties' => $specialist->specialties,
            'qualifications' => $specialist->qualifications,
            'certifications' => $specialist->certifications,
            'achievements' => $specialist->achievements,
            'career_timeline' => $specialist->career_timeline,
            'skills' => $specialist->skills,
            'years_experience' => $specialist->years_experience,
            'portfolio' => $portfolio,
            'work_preference' => $specialist->work_preference,
        ];
    }

    /**
     * Update the specialist's profile data.
     */
    public function updateProfile(Specialist $specialist, array $data): Specialist
    {
        $validator = Validator::make($data, [
            'headline' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:1000',
            'languages' => 'nullable|array',
            'languages.*' => 'string',
            'social_links' => 'nullable|array',
            'photo_media_id' => 'nullable|uuid|exists:media,id',
            'latitude' => 'nullable|decimal:8',
            'longitude' => 'nullable|decimal:8',
            'specialties' => 'nullable|array',
            'qualifications' => 'nullable|array',
            'certifications' => 'nullable|array',
            'achievements' => 'nullable|array',
            'career_timeline' => 'nullable|array',
            'skills' => 'nullable|array',
            'years_experience' => 'nullable|integer|min:0',
            'portfolio' => 'nullable|array',
            'work_preference' => 'nullable|string|in:salon,independent,both',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $updatableFields = [
            'headline', 'bio', 'languages', 'social_links', 'photo_media_id',
            'latitude', 'longitude', 'specialties', 'qualifications',
            'certifications', 'achievements', 'career_timeline',
            'skills', 'years_experience', 'portfolio', 'work_preference'
        ];

        foreach ($updatableFields as $field) {
            if (array_key_exists($field, $validated)) {
                $specialist->$field = $validated[$field];
            }
        }

        $specialist->save();

        return $specialist;
    }
}
