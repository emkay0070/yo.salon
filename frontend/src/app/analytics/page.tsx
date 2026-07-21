'use client';
import { useQuery } from '@tanstack/react-query';
import { TrendChart } from '@/components/analytics/TrendChart';
import { InsightHero } from '@/components/analytics/InsightHero';
import { BusinessSignal } from '@/components/analytics/BusinessSignal';
import { BarChart } from '@/components/analytics/BarChart';
import { Users, Clock, Sparkles } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

export default function AnalyticsOverview() {
  const { salonId } = useRole();

  // Fetch analytics data from API
  // salon_id is automatically applied by backend from authenticated user
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

  // Transform API data to chart format
  const revenueData = analyticsData?.revenue_trend?.map((item: any) => ({
    date: item.date,
    revenue: item.revenue,
  })) || [];

  const weeklyBookings = analyticsData?.weekly_bookings?.map((item: any) => ({
    day: item.day,
    bookings: item.bookings,
  })) || [];

  const insights = analyticsData?.insights || {};

  return (
    <div className="space-y-12">
      <InsightHero 
        title="Salon Pulse"
        narrative={insights.narrative || "Your salon performance overview"}
        metric={insights.metric || "UGX 0"}
        trend={{ 
          value: insights.trend_value || "+0% vs last month", 
          isPositive: insights.trend_positive ?? true 
        }}
        imagePath="/images/salon-mirror.jpg"
      />
      
      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Revenue Trend (30 Days)</h3>
        <TrendChart 
          data={revenueData}
          dataKey="revenue"
          xAxisKey="date"
          height={320}
          formatTooltip={(val) => `UGX ${(val/1000).toFixed(0)}k`}
        />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <BusinessSignal 
          title="Customer Retention"
          value={insights.retention || "N/A"}
          narrative={insights.retention_narrative || "Data not available"}
          icon={Users}
          trend={insights.retention_trend || "neutral"}
        />
        <BusinessSignal 
          title="Peak Hours"
          value={insights.peak_hours || "N/A"}
          narrative={insights.peak_hours_narrative || "Data not available"}
          icon={Clock}
          trend={insights.peak_hours_trend || "neutral"}
        />
        <BusinessSignal 
          title="Growth Opportunity"
          value={insights.growth_opportunity || "N/A"}
          narrative={insights.growth_opportunity_narrative || "Data not available"}
          icon={Sparkles}
          trend={insights.growth_opportunity_trend || "neutral"}
        />
      </div>

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Week at a Glance (Bookings)</h3>
        <BarChart 
          data={weeklyBookings}
          dataKey="bookings"
          xAxisKey="day"
          height={240}
        />
      </div>
    </div>
  );
}
