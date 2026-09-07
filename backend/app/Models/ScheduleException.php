<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ScheduleException
 *
 * One-off or recurring exceptions to the recurring weekly schedule.
 * Examples: Holidays, Vacations, Training days, Power outages, Renovations.
 *
 * Hierarchical (from broadest to narrowest):
 *   provider_id → applies to all branches of the business
 *   salon_id    → applies only to one branch
 *   assignment_id → applies to one specialist at one branch
 */
class ScheduleException extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'scope_level',
        'provider_id',
        'salon_id',
        'assignment_id',
        'start_date',
        'end_date',
        'title',
        'notes',
        'type',
        'is_closed',
        'open_time',
        'close_time',
        'break_start',
        'break_end',
        'recurring_yearly',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_closed'  => 'boolean',
        'recurring_yearly' => 'boolean',
    ];

    /**
     * Scopes
     */
    public function scopeForProvider($query, string $providerId)
    {
        return $query->where(fn($q) => $q
            ->where('scope_level', 'provider')->where('provider_id', $providerId)
            ->orWhereIn('salon_id', function ($sub) use ($providerId) {
                $sub->select('id')->from('salons')->where('provider_id', $providerId);
            })
            ->orWhereIn('assignment_id', function ($sub) use ($providerId) {
                $sub->select('id')->from('specialist_assignments')->where('provider_id', $providerId);
            })
        );
    }

    public function scopeForSalon($query, string $salonId)
    {
        $salon = Salon::findOrFail($salonId);

        return $query->where(fn($q) => $q
            // Provider-level exceptions cascade to all branches
            ->where('scope_level', 'provider')->where('provider_id', $salon->provider_id)
            ->orWhere(fn($q2) => $q2->where('scope_level', 'salon')->where('salon_id', $salonId))
            ->orWhere(fn($q2) => $q2->where('scope_level', 'assignment')
                ->whereIn('assignment_id', function ($sub) use ($salonId) {
                    $sub->select('id')->from('specialist_assignments')->where('salon_id', $salonId);
                })
            )
        );
    }

    public function scopeForAssignment($query, string $assignmentId)
    {
        $assignment = SpecialistAssignment::findOrFail($assignmentId);

        return $query->where(fn($q) => $q
            // Broadest: provider exceptions
            ->where('scope_level', 'provider')->where('provider_id', $assignment->provider_id)
            // Salon-level
            ->orWhere(fn($q2) => $q2->where('scope_level', 'salon')->where('salon_id', $assignment->salon_id))
            // Narrowest: assignment-level
            ->orWhere(fn($q2) => $q2->where('scope_level', 'assignment')->where('assignment_id', $assignmentId))
        );
    }

    public function scopeOnDate($query, string|\DateTimeInterface $date)
    {
        $d = is_string($date) ? $date : $date->format('Y-m-d');

        return $query->where(function ($q) use ($d) {
            $q->where('start_date', '<=', $d)
              ->where(function ($q2) use ($d) {
                  $q2->whereNull('end_date')->orWhere('end_date', '>=', $d);
              });
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SpecialistAssignment::class);
    }
}
