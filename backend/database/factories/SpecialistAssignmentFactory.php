<?php

namespace Database\Factories;

use App\Models\SpecialistAssignment;
use App\Models\Specialist;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpecialistAssignmentFactory extends Factory
{
    protected $model = SpecialistAssignment::class;

    public function definition(): array
    {
        return [
            'specialist_id' => Specialist::factory(),
            'provider_id' => Provider::factory(),
            'salon_id' => null,
            'role' => 'SPECIALIST',
            'employment_type' => 'EMPLOYEE',
            'is_primary' => true,
            'status' => 'active',
        ];
    }
}
