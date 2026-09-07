<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2: Migrate existing Staff records to Specialists
 * 
 * This migration creates Specialist records for existing Staff records
 * following the documented architecture:
 * - Staff.id → Specialist.id (preserve existing mapping from July 31 migration)
 * - Staff.name → Specialist.name
 * - Staff.phone → Specialist.phone
 * - Staff.email → Specialist.email
 * - Staff.specializations → Specialist.specialties
 * - Staff.photo → Specialist.photo_media_id (convert UUID/path)
 * - Staff.active → Specialist.active
 * - Additional professional profile fields if present
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Verify existing ID mapping from July 31 migration
        $missingSpecialists = DB::table('staff')
            ->leftJoin('specialists', 'staff.id', '=', 'specialists.id')
            ->whereNull('specialists.id')
            ->count();

        if ($missingSpecialists > 0) {
            \Log::info("Migration: Found {$missingSpecialists} staff records without matching specialists");
        }

        // Step 2: Create missing Specialists for Staff records
        $staffWithoutSpecialist = DB::table('staff')
            ->leftJoin('specialists', 'staff.id', '=', 'specialists.id')
            ->whereNull('specialists.id')
            ->get();

        foreach ($staffWithoutSpecialist as $staff) {
            // Skip if staff.id is null
            if (!$staff->id) {
                \Log::warning("Skipping staff record with null ID");
                continue;
            }

            // Convert photo format: UUID → photo_media_id, path → photo
            $photoMediaId = null;
            $photo = null;
            
            if ($staff->photo) {
                if (\Illuminate\Support\Str::isUuid($staff->photo)) {
                    $photoMediaId = $staff->photo;
                } else {
                    $photo = $staff->photo; // Keep as path for backward compatibility
                }
            }

            // Merge specializations and specialties if both exist
            $specialties = [];
            if (!empty($staff->specializations)) {
                $specialties = is_string($staff->specializations) 
                    ? json_decode($staff->specializations, true) 
                    : $staff->specializations;
            }
            if (!empty($staff->specialties)) {
                $staffSpecialties = is_string($staff->specialties) 
                    ? json_decode($staff->specialties, true) 
                    : $staff->specialties;
                $specialties = array_unique(array_merge($specialties, $staffSpecialties));
            }

            DB::table('specialists')->insert([
                'id' => $staff->id, // Preserve ID mapping
                'name' => $staff->name,
                'phone' => $staff->phone,
                'email' => $staff->email,
                'specialties' => json_encode($specialties),
                'bio' => $staff->bio ?? null,
                'languages' => $staff->languages ?? null,
                'qualifications' => $staff->qualifications ?? null,
                'portfolio' => $staff->portfolio ?? null,
                'rating' => $staff->rating ?? 0,
                'review_count' => $staff->review_count ?? 0,
                'handle' => $staff->handle ?? null,
                'photo_media_id' => $photoMediaId,
                'photo' => $photo,
                'active' => $staff->active ?? true,
                'created_at' => $staff->created_at,
                'updated_at' => $staff->updated_at,
            ]);
        }

        \Log::info("Migration: Created " . count($staffWithoutSpecialist) . " specialist records from staff");
    }

    public function down(): void
    {
        // Rollback: Delete specialists that were created from staff
        // This is a destructive rollback - only specialists with matching staff IDs are removed
        DB::table('specialists')
            ->whereIn('id', function ($query) {
                $query->select('id')->from('staff');
            })
            ->delete();
    }
};
