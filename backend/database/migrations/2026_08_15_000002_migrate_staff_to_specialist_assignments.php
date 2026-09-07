<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3: Migrate existing Staff records to SpecialistAssignments
 * 
 * This migration creates SpecialistAssignment records for existing Staff records
 * following the documented architecture:
 * - Staff.salon_id → SpecialistAssignment.salon_id
 * - Staff.role → SpecialistAssignment.role
 * - Staff.commission_rate → SpecialistAssignment.commission_rate
 * - Derive provider_id from salon.provider_id
 * - Set default employment_type, is_primary, status, starts_at
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Get all staff records with their salon information
        $staffRecords = DB::table('staff')
            ->join('salons', 'staff.salon_id', '=', 'salons.id')
            ->select('staff.id as staff_id', 'staff.salon_id', 'salons.provider_id', 'staff.role', 'staff.commission_rate', 'staff.active', 'staff.created_at')
            ->get();

        $assignmentsCreated = 0;
        $assignmentsSkipped = 0;

        foreach ($staffRecords as $staff) {
            // Skip if specialist doesn't exist
            if (!DB::table('specialists')->where('id', $staff->staff_id)->exists()) {
                \Log::warning("Skipping staff {$staff->staff_id} - no matching specialist");
                continue;
            }

            // Check if assignment already exists for this specialist/salon pair
            $existing = DB::table('specialist_assignments')
                ->where('specialist_id', $staff->staff_id)
                ->where('salon_id', $staff->salon_id)
                ->first();

            if ($existing) {
                $assignmentsSkipped++;
                continue;
            }

            // Create SpecialistAssignment
            DB::table('specialist_assignments')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'specialist_id' => $staff->staff_id,
                'provider_id' => $staff->provider_id,
                'salon_id' => $staff->salon_id,
                'role' => strtoupper($staff->role ?? 'SPECIALIST'),
                'employment_type' => 'EMPLOYEE', // Default for migrated staff
                'commission_rate' => $staff->commission_rate ?? 0,
                'is_primary' => true, // Default to primary for single-salon staff
                'status' => $staff->active ? 'ACTIVE' : 'INACTIVE',
                'starts_at' => $staff->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $assignmentsCreated++;
        }

        \Log::info("Migration: Created {$assignmentsCreated} specialist assignments, skipped {$assignmentsSkipped} existing");
    }

    public function down(): void
    {
        // Rollback: Delete specialist assignments that were created from staff
        // This is based on the assumption that staff_id === specialist_id
        DB::table('specialist_assignments')
            ->whereIn('specialist_id', function ($query) {
                $query->select('id')->from('staff');
            })
            ->delete();
    }
};
