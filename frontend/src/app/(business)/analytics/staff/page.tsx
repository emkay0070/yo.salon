'use client';
import { useQuery } from '@tanstack/react-query';
import { InsightHero } from '@/components/analytics/InsightHero';
import { StaffPerformanceCard } from '@/components/analytics/StaffPerformanceCard';
import { TrendChart } from '@/components/analytics/TrendChart';
import { apiClient } from '@/lib/api-client';

export default function StaffAnalytics() {
  // Fetch analytics data from API
  const { data: analyticsData, isLoading } = useQuery({
    queryKey: ['analytics'],
    queryFn: () => apiClient.getAnalytics(),
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="animate-pulse w-10 h-10 rounded-full bg-accent/20 border border-text-secondary/30" />
      </div>
    );
  }

  // Transform staff stats from API data
  const staffStats = analyticsData?.staff_stats?.map((item: any) => ({
    name: item.staff_name,
    role: 'Stylist',
    revenue: item.revenue,
    clients: item.count,
    rating: 4.5, // Default rating since not in API
    utilization: Math.min(100, Math.round((item.count / 50) * 100)), // Calculate utilization
    avatarText: item.staff_name?.charAt(0) || 'S',
  })) || [];

  const totalRevenue = analyticsData?.total_revenue || 0;
  const totalBookings = analyticsData?.total_bookings || 0;
  const avgUtilization = staffStats.length > 0 
    ? Math.round(staffStats.reduce((sum: number, s: any) => sum + s.utilization, 0) / staffStats.length)
    : 0;

  return (
    <div className="space-y-12">
      <InsightHero 
        title="Team Performance"
        narrative={`Team operating at ${avgUtilization}% capacity. Total revenue: UGX ${new Intl.NumberFormat('en-UG').format(totalRevenue)}.`}
        imagePath="/images/salon-station.jpg"
      />

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Staff Roster</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {staffStats.length > 0 ? staffStats.map((staff: any, index: number) => (
            <StaffPerformanceCard 
              key={index}
              name={staff.name}
              role={staff.role}
              revenue={staff.revenue}
              clients={staff.clients}
              rating={staff.rating}
              utilization={staff.utilization}
              avatarText={staff.avatarText}
            />
          )) : (
            <StaffPerformanceCard 
              name="No Staff Data"
              role="N/A"
              revenue={0}
              clients={0}
              rating={0}
              utilization={0}
              avatarText="N"
            />
          )}
        </div>
      </div>

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Staff Performance Overview</h3>
        <TrendChart 
          data={staffStats.length > 0 ? staffStats.map((s: any) => ({
            time: s.name,
            utilization: s.utilization,
          })) : [{ time: 'No Data', utilization: 0 }]}
          dataKey="utilization"
          xAxisKey="time"
          height={280}
          formatTooltip={(val) => `${val}% Capacity`}
        />
      </div>
    </div>
  );
}
