<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Staff;
use Carbon\Carbon;

class PulseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Use salon_id injected by ResolveSalonContext middleware (via request attributes)
        $salonId = $request->attributes->get('salon_id');

        // Fallback to query param (for direct testing), then to user's first salon
        if (!$salonId) {
            $salonId = $request->query('salon_id');
        }

        if (!$salonId) {
            $salon = $user->salons()->first();
            if (!$salon) {
                return response()->json(['error' => 'No salon associated with user'], 400);
            }
            $salonId = $salon->id;
        }

        $today = Carbon::today();
        $currentTime = Carbon::now();

        // 1. Fetch Staff (Stations/Chairs)
        $staffMembers = Staff::where('salon_id', $salonId)->get();

        // 2. Fetch Today's Bookings
        $bookings = Booking::with(['customer', 'service', 'staff'])
            ->where('salon_id', $salonId)
            ->whereDate('date', $today)
            ->orderBy('time', 'asc')
            ->get();

        // 3. Process Stations & Active Sessions
        $stations = [];
        $busyCount = 0;
        $totalChairs = count($staffMembers);

        $nextUp = null;
        $activeAppointments = [];
        $notifications = [];
        $appointmentsRemaining = 0;

        foreach ($staffMembers as $index => $staff) {
            $chairNum = $index + 1;
            
            // Find current and upcoming bookings for this staff
            $staffBookings = $bookings->where('staff_id', $staff->id)->values();
            
            $currentBooking = null;
            foreach ($staffBookings as $b) {
                $startTime = Carbon::parse($today->toDateString() . ' ' . $b->time);
                $duration = $b->service ? $b->service->duration : 30; // Default 30 min if missing
                $endTime = $startTime->copy()->addMinutes($duration);

                // Is the booking happening right now?
                if ($currentTime->between($startTime, $endTime) && !in_array($b->status, ['cancelled', 'completed'])) {
                    $currentBooking = $b;
                    break;
                }
            }

            if ($currentBooking) {
                $busyCount++;
                $startTime = Carbon::parse($today->toDateString() . ' ' . $currentBooking->time);
                $duration = $currentBooking->service ? $currentBooking->service->duration : 30;
                $endTime = $startTime->copy()->addMinutes($duration);
                $minutesRemaining = max(0, $currentTime->diffInMinutes($endTime, false));

                $activeAppointments[] = $currentBooking;

                $stations[] = [
                    'chair' => "Chair $chairNum",
                    'staff_name' => $staff->name,
                    'status' => 'Busy',
                    'service_name' => $currentBooking->service ? $currentBooking->service->name : 'Unknown',
                    'customer_name' => $currentBooking->customer ? $currentBooking->customer->name : 'Guest',
                    'minutes_remaining' => ceil($minutesRemaining),
                ];
            } else {
                $stations[] = [
                    'chair' => "Chair $chairNum",
                    'staff_name' => $staff->name,
                    'status' => 'Available',
                ];
            }
        }

        // 4. Calculate Next Up & Appointments Remaining & Timeline
        $timeline = [];
        foreach ($bookings as $b) {
            $startTime = Carbon::parse($today->toDateString() . ' ' . $b->time);
            $duration = $b->service ? $b->service->duration : 30;
            $endTime = $startTime->copy()->addMinutes($duration);

            // Appointments remaining
            if ($startTime->isAfter($currentTime) && $b->status !== 'cancelled') {
                $appointmentsRemaining++;
                
                if (!$nextUp) {
                    $nextUp = [
                        'time' => Carbon::parse($b->time)->format('H:i'),
                        'customer_name' => $b->customer ? $b->customer->name : 'Guest',
                        'service_name' => $b->service ? $b->service->name : 'Unknown',
                        'staff_name' => $b->staff ? $b->staff->name : 'Unassigned',
                        'minutes_until' => ceil($currentTime->diffInMinutes($startTime, false)),
                        'start_time_iso' => $startTime->toIso8601String(),
                    ];
                }
            }

            // Timeline status logic
            $timelineStatus = 'waiting';
            if ($b->status === 'completed') {
                $timelineStatus = 'completed';
            } elseif ($b->status === 'cancelled') {
                $timelineStatus = 'cancelled';
            } elseif ($currentTime->between($startTime, $endTime)) {
                $timelineStatus = 'in_progress';
            } elseif ($currentTime->isAfter($endTime)) {
                 $timelineStatus = 'completed'; // Should be completed if past end time and not cancelled
            } elseif (ceil($currentTime->diffInMinutes($startTime, false)) <= 15) {
                $timelineStatus = 'checking_in';
            }

            $timeline[] = [
                'time' => Carbon::parse($b->time)->format('H:i'),
                'customer_name' => $b->customer ? $b->customer->name : 'Guest',
                'staff_name' => $b->staff ? $b->staff->name : 'Unassigned',
                'status' => $timelineStatus,
            ];
        }

        // 5. Generate Notifications
        // We will generate 3-4 dynamic notifications based on recent/current events
        foreach ($bookings as $b) {
            $startTime = Carbon::parse($today->toDateString() . ' ' . $b->time);
            $duration = $b->service ? $b->service->duration : 30;
            $endTime = $startTime->copy()->addMinutes($duration);
            
            $customerName = $b->customer ? $b->customer->name : 'Guest';
            $staffName = $b->staff ? $b->staff->name : 'Unassigned';
            $serviceName = $b->service ? $b->service->name : 'Service';

            // Just completed (within last 30 mins)
            if ($b->status === 'completed' && $currentTime->isAfter($endTime) && $currentTime->diffInMinutes($endTime) < 30) {
                $notifications[] = [
                    'id' => 'comp_' . $b->id,
                    'type' => 'success',
                    'message' => "$staffName completed $serviceName for $customerName.",
                    'time' => $endTime->format('H:i'),
                    'timestamp' => $endTime->timestamp
                ];
            }

            // Late arrival
            if ($b->status === 'pending' && $currentTime->isAfter($startTime) && $currentTime->diffInMinutes($startTime) > 5) {
                $minsLate = ceil($currentTime->diffInMinutes($startTime));
                $notifications[] = [
                    'id' => 'late_' . $b->id,
                    'type' => 'warning',
                    'message' => "$customerName is $minsLate minutes late for $serviceName.",
                    'time' => $currentTime->format('H:i'),
                    'timestamp' => $currentTime->timestamp
                ];
            }

            // Recently started
            if ($currentTime->between($startTime, $startTime->copy()->addMinutes(15))) {
                 $notifications[] = [
                    'id' => 'start_' . $b->id,
                    'type' => 'info',
                    'message' => "$staffName began $serviceName with $customerName.",
                    'time' => $startTime->format('H:i'),
                    'timestamp' => $startTime->timestamp
                ];
            }
        }

        // Sort notifications by timestamp descending
        usort($notifications, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Take top 4 recent notifications
        $notifications = array_slice($notifications, 0, 4);

        if (count($notifications) === 0) {
             // Fallback default notification if empty
             $notifications[] = [
                 'id' => 'sys_1',
                 'type' => 'success',
                 'message' => 'System online. Ready for the day.',
                 'time' => $currentTime->format('H:i'),
                 'timestamp' => $currentTime->timestamp
             ];
        }


        // 6. Contextual Atmosphere
        $capacityPct = $totalChairs > 0 ? round(($busyCount / $totalChairs) * 100) : 0;
        $contextLabel = 'Quiet';
        if ($capacityPct >= 80) $contextLabel = 'Peak Time';
        elseif ($capacityPct >= 50) $contextLabel = 'Steady Flow';
        elseif ($capacityPct > 0) $contextLabel = 'Quiet Morning';
        elseif ($appointmentsRemaining > 0) $contextLabel = 'Preparing';
        else $contextLabel = 'Closed / Empty';


        return response()->json([
            'time' => $currentTime->toIso8601String(),
            'capacity' => [
                'busy' => $busyCount,
                'total' => $totalChairs,
                'percentage' => $capacityPct,
                'label' => $contextLabel
            ],
            'stations' => $stations,
            'nextAppointment' => $nextUp,
            'appointmentsRemaining' => $appointmentsRemaining,
            'timeline' => $timeline,
            'notifications' => $notifications,
            'totalBookingsToday' => count($bookings),
        ]);
    }
}
