'use client';
import { useQuery } from '@tanstack/react-query';
import { TrendChart } from '@/components/analytics/TrendChart';
import { InsightHero } from '@/components/analytics/InsightHero';
import { BusinessSignal } from '@/components/analytics/BusinessSignal';
import { BarChart } from '@/components/analytics/BarChart';
import { RecommendationCard } from '@/components/analytics/RecommendationCard';
import { Users, Clock, Sparkles, Brain } from 'lucide-react';
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

  const basicInsights = analyticsData?.basic_insights || {};
  const executiveSummary = analyticsData?.executive_summary || "Your business intelligence hub is analyzing your data...";
  const aiInsights = analyticsData?.insights || [];

  return (
    <div className="space-y-6 sm:space-y-10">
      
      {/* Top Level BI Hero */}
      <InsightHero 
        title="Business Intelligence Hub"
        narrative={executiveSummary}
        metric={basicInsights.metric || "UGX 0"}
        trend={{ 
          value: basicInsights.trend_value || "+0% vs last month", 
          isPositive: basicInsights.trend_positive ?? true 
        }}
        imagePath="/images/salon-mirror.jpg"
      />
      
      {/* AI Dynamic Actionable Insights */}
      {aiInsights.length > 0 && (
        <div>
          <div className="flex items-center gap-2 mb-6">
            <Brain className="w-5 h-5 text-[#6C5CE7]" />
            <h3 className="text-lg font-medium text-text-primary">Intelligence Engine Signals</h3>
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {aiInsights.map((insight: any, i: number) => (
              <RecommendationCard 
                key={i}
                title={insight.title}
                description={insight.description}
                actionText={insight.action_text}
                isPredictive={insight.is_predictive}
                onAction={() => console.log('BI action clicked')}
              />
            ))}
          </div>
        </div>
      )}
      <div>
        <h3 className="text-base sm:text-lg font-medium text-text-primary mb-4 sm:mb-6">Revenue Trend (30 Days)</h3>
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
          value={basicInsights.retention || "N/A"}
          narrative={basicInsights.retention_narrative || "Data not available"}
          icon={Users}
          trend={basicInsights.retention_trend || "neutral"}
        />
        <BusinessSignal 
          title="Peak Hours"
          value={basicInsights.peak_hours || "N/A"}
          narrative={basicInsights.peak_hours_narrative || "Data not available"}
          icon={Clock}
          trend={basicInsights.peak_hours_trend || "neutral"}
        />
        <BusinessSignal 
          title="Growth Opportunity"
          value={basicInsights.growth_opportunity || "N/A"}
          narrative={basicInsights.growth_opportunity_narrative || "Data not available"}
          icon={Sparkles}
          trend={basicInsights.growth_opportunity_trend || "neutral"}
        />
      </div>

      <div>
        <h3 className="text-base sm:text-lg font-medium text-text-primary mb-4 sm:mb-6">Week at a Glance (Bookings)</h3>
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
