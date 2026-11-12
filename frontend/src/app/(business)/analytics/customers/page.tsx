'use client';
import { useQuery } from '@tanstack/react-query';
import { InsightHero } from '@/components/analytics/InsightHero';
import { BusinessSignal } from '@/components/analytics/BusinessSignal';
import { BarChart } from '@/components/analytics/BarChart';
import { Heart, UserPlus, Star } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

export default function CustomerAnalytics() {
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

  const totalBookings = analyticsData?.total_bookings || 0;
  const totalRevenue = analyticsData?.total_revenue || 0;
  const uniqueCustomers = analyticsData?.bookings?.length > 0 
    ? new Set(analyticsData.bookings.map((b: any) => b.customer_id)).size 
    : 0;
  
  const avgSpend = uniqueCustomers > 0 ? Math.round(totalRevenue / uniqueCustomers) : 0;

  // Calculate customer retention funnel from real data
  const customerVisits = analyticsData?.bookings?.reduce((acc: any, booking: any) => {
    acc[booking.customer_id] = (acc[booking.customer_id] || 0) + 1;
    return acc;
  }, {});

  const visitCounts = Object.values(customerVisits || {}) as number[];
  const firstVisit = visitCounts.filter((c) => c === 1).length;
  const secondVisit = visitCounts.filter((c) => c === 2).length;
  const regular = visitCounts.filter((c) => c >= 3 && c < 10).length;
  const vip = visitCounts.filter((c) => c >= 10).length;

  const funnelData = [
    { stage: 'First Visit', count: firstVisit },
    { stage: 'Second Visit', count: secondVisit },
    { stage: 'Regular (3+)', count: regular },
    { stage: 'VIP (10+)', count: vip },
  ];

  return (
    <div className="space-y-12">
      <InsightHero 
        title="Customer Insights"
        narrative={`Total unique customers: ${uniqueCustomers}. Building loyalty through repeat visits.`}
        imagePath="/images/salon-barber.jpg"
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <BusinessSignal 
          title="Unique Customers"
          value={uniqueCustomers.toString()}
          narrative="Total unique clients served."
          icon={UserPlus}
          trend="neutral"
        />
        <BusinessSignal 
          title="Average Spend"
          value={`UGX ${(avgSpend / 1000).toFixed(0)}k`}
          narrative="Average revenue per customer."
          icon={Heart}
          trend="neutral"
        />
        <BusinessSignal 
          title="VIP Members"
          value={vip.toString()}
          narrative="Clients with 10+ visits. They generate significant revenue."
          icon={Star}
          trend={vip > 0 ? 'positive' : 'neutral'}
        />
      </div>

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Retention Waterfall</h3>
        <p className="text-sm text-text-secondary mb-6 max-w-2xl">
          Visualizing how many clients progress from their first visit to becoming loyal VIPs.
        </p>
        <BarChart 
          data={funnelData}
          dataKey="count"
          xAxisKey="stage"
          height={320}
        />
      </div>
    </div>
  );
}
