'use client';
import { useQuery } from '@tanstack/react-query';
import { InsightHero } from '@/components/analytics/InsightHero';
import { ServiceRanking, ServiceStat } from '@/components/analytics/ServiceRanking';
import { RecommendationCard } from '@/components/analytics/RecommendationCard';
import { apiClient } from '@/lib/api-client';

export default function ServicesAnalytics() {
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

  // Transform service stats from API data
  const serviceStats = analyticsData?.service_stats?.map((item: any) => {
    const maxRevenue = Math.max(...(analyticsData.service_stats?.map((s: any) => s.revenue) || [1]));
    return {
      name: item.service_name,
      revenue: item.revenue,
      bookings: item.count,
      percentage: Math.round((item.revenue / maxRevenue) * 100),
    };
  }) || [];

  const topServices = serviceStats.slice(0, 5);

  return (
    <div className="space-y-12">
      <InsightHero 
        title="Service Intelligence"
        narrative={`Top performing services by revenue. ${serviceStats.length} services tracked.`}
        imagePath="/images/salon-mirror.jpg"
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <div>
          <h3 className="text-lg font-medium text-text-primary mb-6">Top Performing Services</h3>
          <ServiceRanking services={topServices.length > 0 ? topServices : [{ name: 'No Data', revenue: 0, bookings: 0, percentage: 0 }]} />
        </div>
        
        <div>
          <h3 className="text-lg font-medium text-text-primary mb-6">Strategic Recommendations</h3>
          <div className="space-y-6">
            <RecommendationCard 
              title="Service Optimization"
              description="Analyze your top-performing services and consider bundling complementary treatments to increase average ticket size."
              actionText="View Service Details"
              onAction={() => console.log('Services')}
            />
            <RecommendationCard 
              title="Pricing Strategy"
              description="Review service pricing based on demand and capacity utilization to optimize revenue."
            />
          </div>
        </div>
      </div>
    </div>
  );
}
