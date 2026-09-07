<?php

namespace App\Domain\Specialist;

use App\Models\Specialist;
use Illuminate\Database\Eloquent\Builder;

class SpecialistRankingEngine
{
    protected array $weights = [
        'is_pro' => 25,
        'is_verified' => 15,
        'rating' => 20,
        'review_count' => 10,
        'punctuality' => 10,
        'attendance' => 10,
        'years_experience' => 5,
        'response_rate' => 5,
    ];

    public function rank(Builder $query): Builder
    {
        return $query->selectRaw('
            specialists.*,
            (
                (CASE WHEN specialists.is_pro = 1 THEN ? ELSE 0 END) +
                (CASE WHEN specialists.is_verified = 1 THEN ? ELSE 0 END) +
                (COALESCE(specialists.rating, 0) * ?) +
                (LEAST(specialists.review_count, 100) / 100 * ?) +
                (COALESCE(specialists.punctuality, 0) / 100 * ?) +
                (COALESCE(specialists.attendance, 0) / 100 * ?) +
                (LEAST(COALESCE(specialists.years_experience, 0), 10) / 10 * ?) +
                (COALESCE(specialists.response_rate, 0) / 100 * ?)
            ) as ranking_score
        ', [
            $this->weights['is_pro'],
            $this->weights['is_verified'],
            $this->weights['rating'],
            $this->weights['review_count'],
            $this->weights['punctuality'],
            $this->weights['attendance'],
            $this->weights['years_experience'],
            $this->weights['response_rate'],
        ])->orderBy('ranking_score', 'desc');
    }

    public function rankByDistance(Builder $query, float $lat, float $lng): Builder
    {
        // Add distance calculation and weight by proximity
        return $query->selectRaw('
            specialists.*,
            (
                6371 * acos(
                    cos(radians(?)) * cos(radians(specialists.latitude)) *
                    cos(radians(specialists.longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(specialists.latitude))
                )
            ) as distance_km
        ', [$lat, $lng, $lat])->orderByRaw('distance_km ASC');
    }

    public function rankByAvailability(Builder $query, string $date): Builder
    {
        // Rank by availability on specific date
        // This would integrate with the Availability Engine
        return $query->withCount([
            'availableSlots as available_slots_count' => function ($q) use ($date) {
                $q->where('date', $date);
            }
        ])->orderByDesc('available_slots_count');
    }

    public function setWeight(string $factor, float $weight): self
    {
        $this->weights[$factor] = $weight;
        return $this;
    }

    public function getWeights(): array
    {
        return $this->weights;
    }

    public function calculateScore(Specialist $specialist): float
    {
        $score = 0;

        if ($specialist->is_pro) {
            $score += $this->weights['is_pro'];
        }

        if ($specialist->is_verified) {
            $score += $this->weights['is_verified'];
        }

        $score += ($specialist->rating ?? 0) * $this->weights['rating'];
        $score += (min($specialist->review_count ?? 0, 100) / 100) * $this->weights['review_count'];
        $score += (($specialist->punctuality ?? 0) / 100) * $this->weights['punctuality'];
        $score += (($specialist->attendance ?? 0) / 100) * $this->weights['attendance'];
        $score += (min($specialist->years_experience ?? 0, 10) / 10) * $this->weights['years_experience'];
        $score += (($specialist->response_rate ?? 0) / 100) * $this->weights['response_rate'];

        return round($score, 2);
    }
}
