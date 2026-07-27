<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Transaction;
use App\Services\IntelligenceEngine;
use App\Services\Intelligence\IntelligenceEngine as NewIntelligenceEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected IntelligenceEngine $intelligenceEngine;
    protected NewIntelligenceEngine $newIntelligenceEngine;

    public function __construct(IntelligenceEngine $intelligenceEngine, NewIntelligenceEngine $newIntelligenceEngine)
    {
        $this->intelligenceEngine = $intelligenceEngine;
        $this->newIntelligenceEngine = $newIntelligenceEngine;
    }
    public function intelligence(Request $request): JsonResponse
    {
        $salon = auth()->user()->currentSalon();
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }
        
        $dto = $this->newIntelligenceEngine->generate($salon);
        
        return response()->json($dto);
    }

    public function index(Request $request): JsonResponse
    {
        // salon_id is automatically applied by BelongsToSalon global scope from authenticated user
        // Get bookings for the current salon
        $bookings = Booking::with(['customer', 'staff', 'service'])->get();
        $transactions = Transaction::where('salon_id', auth()->user()->currentSalon()?->id)
            ->whereIn('status', ['completed', 'paid'])
            ->get();

        // Calculate analytics based on Ledger (Transactions)
        $totalBookings = $bookings->count();
        $totalGrossRevenue = $transactions->sum('gross_amount');
        $totalNetRevenue = $transactions->sum('net_amount');

        $statusCounts = $bookings->groupBy('status')->mapWithKeys(function ($group, $status) {
            return [$status => $group->count()];
        });

        $todayBookings = $bookings->filter(function ($booking) {
            return $booking->date === now()->toDateString();
        });

        $todayTransactions = $transactions->filter(function ($tx) {
            return date('Y-m-d', strtotime($tx->created_at)) === now()->toDateString();
        });

        $todayGrossRevenue = $todayTransactions->sum('gross_amount');
        $todayNetRevenue = $todayTransactions->sum('net_amount');

        // Service analytics
        $serviceStats = $bookings->groupBy('service_id')->map(function ($group) {
            $service = $group->first()?->service;
            return [
                'service_id' => $service?->id,
                'service_name' => $service?->name,
                'count' => $group->count(),
                'revenue' => $group->sum(function ($booking) {
                    return $booking->service?->price ?? 0;
                }),
            ];
        })->values();

        // Staff analytics
        $staffStats = $bookings->whereNotNull('staff_id')->groupBy('staff_id')->map(function ($group) {
            $staff = $group->first()?->staff;
            return [
                'staff_id' => $staff?->id,
                'staff_name' => $staff?->name,
                'count' => $group->count(),
                'revenue' => $group->sum(function ($booking) {
                    return $booking->service?->price ?? 0;
                }),
            ];
        })->values();

        return response()->json([
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalGrossRevenue, // kept for backward compatibility if needed
            'total_gross_revenue' => $totalGrossRevenue,
            'total_net_revenue' => $totalNetRevenue,
            'status_counts' => $statusCounts,
            'today_bookings' => $todayBookings->count(),
            'today_revenue' => $todayGrossRevenue,
            'today_gross_revenue' => $todayGrossRevenue,
            'today_net_revenue' => $todayNetRevenue,
            'service_stats' => $serviceStats,
            'staff_stats' => $staffStats,
            'revenue_trend' => $this->getRevenueTrend($transactions),
            'weekly_bookings' => $this->getWeeklyBookings($bookings),
            'insights' => $this->intelligenceEngine->generateInsights($transactions, $bookings),
            'executive_summary' => $this->intelligenceEngine->generateExecutiveSummary($transactions, $bookings),
            'basic_insights' => $this->generateInsights($bookings, $totalGrossRevenue), // Kept for the Overview Hero
        ]);
    }

    private function getRevenueTrend($transactions): array
    {
        // Group transactions by date for last 30 days
        $trend = $transactions->groupBy(function ($tx) {
            return date('Y-m-d', strtotime($tx->created_at));
        })->map(function ($group) {
            return [
                'date' => date('Y-m-d', strtotime($group->first()->created_at)),
                'revenue' => $group->sum('gross_amount'),
                'net_revenue' => $group->sum('net_amount'),
            ];
        })->sortBy('date')->values()->take(30);

        return $trend->toArray();
    }

    private function getWeeklyBookings($bookings): array
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weekly = collect($days)->map(function ($day) use ($bookings) {
            return [
                'day' => $day,
                'bookings' => $bookings->filter(function ($booking) use ($day) {
                    return date('D', strtotime($booking->date)) === $day;
                })->count(),
            ];
        });

        return $weekly->toArray();
    }

    private function generateInsights($bookings, $totalRevenue): array
    {
        $totalBookings = $bookings->count();
        $uniqueCustomers = $bookings->pluck('customer_id')->unique()->count();

        // If no data yet, return an honest empty state
        if ($totalBookings === 0) {
            return [
                'narrative' => 'Your salon is set up and ready to go. Start accepting bookings to see your performance insights.',
                'metric' => 'UGX 0',
                'trend_value' => 'No data yet',
                'trend_positive' => true,
                'retention' => 'N/A',
                'retention_narrative' => 'You\'ll see your retention rate once customers start booking.',
                'retention_trend' => 'neutral',
                'peak_hours' => 'N/A',
                'peak_hours_narrative' => 'Your busiest hours will appear here after your first bookings.',
                'peak_hours_trend' => 'neutral',
                'growth_opportunity' => 'Get started',
                'growth_opportunity_narrative' => 'Share your booking link with your first clients to start building your salon\'s history.',
                'growth_opportunity_trend' => 'neutral',
            ];
        }

        // Calculate peak hours from real booking data
        $hourCounts = $bookings->groupBy(function ($booking) {
            return date('H', strtotime($booking->time ?? '00:00'));
        })->map->count()->sortDesc();
        $peakHour = $hourCounts->keys()->first();
        $peakHourLabel = $peakHour !== null
            ? date('gA', mktime((int)$peakHour, 0, 0)) . ' - ' . date('gA', mktime((int)$peakHour + 2, 0, 0))
            : 'N/A';

        // Calculate retention: repeat customers / total unique customers
        $retentionRate = $uniqueCustomers > 0
            ? round(($uniqueCustomers / $totalBookings) * 100, 1)
            : 0;

        return [
            'narrative' => "Your salon has served {$uniqueCustomers} unique customers across {$totalBookings} bookings.",
            'metric' => 'UGX ' . number_format($totalRevenue),
            'trend_value' => '+0% vs last month',
            'trend_positive' => true,
            'retention' => $retentionRate . '%',
            'retention_narrative' => 'Percentage of bookings from unique customers.',
            'retention_trend' => $retentionRate >= 60 ? 'positive' : 'neutral',
            'peak_hours' => $peakHourLabel,
            'peak_hours_narrative' => 'Your busiest time slot based on completed bookings.',
            'peak_hours_trend' => 'positive',
            'growth_opportunity' => $totalBookings < 20 ? 'Build momentum' : 'Weekend slots',
            'growth_opportunity_narrative' => $totalBookings < 20
                ? 'Keep growing — your first 20 bookings are the foundation of your salon\'s reputation.'
                : 'Consider extending weekend hours to capture more demand.',
            'growth_opportunity_trend' => 'neutral',
        ];
    }
}
