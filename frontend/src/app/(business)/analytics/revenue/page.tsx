'use client';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { InsightHero } from '@/components/analytics/InsightHero';
import { TrendChart } from '@/components/analytics/TrendChart';
import { BarChart } from '@/components/analytics/BarChart';
import { PeriodToggle } from '@/components/analytics/PeriodToggle';
import { RecommendationCard } from '@/components/analytics/RecommendationCard';
import { apiClient } from '@/lib/api-client';
import { motion, AnimatePresence } from 'framer-motion';
import { AlertCircle, TrendingUp, Info } from 'lucide-react';

export default function RevenueAnalytics() {
  const [period, setPeriod] = useState('Month');

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

  const totalGrossRevenue = analyticsData?.total_gross_revenue || 0;
  const totalNetRevenue = analyticsData?.total_net_revenue || 0;
  const todayGrossRevenue = analyticsData?.today_gross_revenue || 0;
  const todayNetRevenue = analyticsData?.today_net_revenue || 0;
  
  // Transform revenue trend data
  const revenueTrend = analyticsData?.revenue_trend?.map((item: any) => ({
    month: new Date(item.date).toLocaleString('default', { month: 'short', day: 'numeric' }),
    gross: item.revenue,
    net: item.net_revenue || item.revenue, // fallback
  })) || [];

  const aiInsights = analyticsData?.insights || [];

  // Transform service stats for revenue by category
  const revenueByCategory = analyticsData?.service_stats?.map((item: any) => ({
    category: item.service_name,
    value: item.revenue,
  })) || [];

  return (
    <div className="space-y-12">
      <div className="flex flex-col md:flex-row md:justify-between md:items-start gap-6">
        <InsightHero 
          title="Revenue Intelligence"
          narrative={`Total Gross: UGX ${number_format(totalGrossRevenue)}. Today's Net: UGX ${number_format(todayNetRevenue)}.`}
          metric={`UGX ${number_format(totalNetRevenue)} (Net)`}
          trend={{ value: "+0% vs last month", isPositive: true }}
          imagePath="/images/salon-luxury.png"
        />
        <div className="pt-2">
          <PeriodToggle 
            periods={['Today', 'Week', 'Month', 'Year']} 
            activePeriod={period} 
            onChange={setPeriod} 
          />
        </div>
      </div>

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Gross vs Net Revenue (30 Days)</h3>
        <TrendChart 
          data={revenueTrend.length > 0 ? revenueTrend : [{ month: 'No Data', gross: 0, net: 0 }]}
          dataKey="gross"
          dataKeySecondary="net"
          xAxisKey="month"
          height={320}
          formatTooltip={(val) => `UGX ${(val/1000).toFixed(0)}k`}
        />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2">
          <h3 className="text-lg font-medium text-text-primary mb-6">Revenue by Service Category</h3>
          <BarChart 
            data={revenueByCategory.length > 0 ? revenueByCategory : [{ category: 'No Data', value: 0 }]}
            dataKey="value"
            xAxisKey="category"
            height={280}
            layout="vertical"
            formatTooltip={(val) => `UGX ${(val/1000).toFixed(0)}k`}
          />
        </div>
        
        <div>
          <h3 className="text-lg font-medium text-text-primary mb-6">Actionable Insights</h3>
          <div className="space-y-4">
            {aiInsights.length > 0 ? (
              aiInsights.map((insight: any, i: number) => (
                <RecommendationCard 
                  key={i}
                  title={insight.title}
                  description={insight.description}
                  actionText={insight.action_text}
                  isPredictive={insight.is_predictive}
                  onAction={() => console.log('action clicked')}
                />
              ))
            ) : (
              <RecommendationCard 
                title="Revenue Analysis"
                description="Your intelligence engine is collecting data. Check back soon for AI-generated insights based on your ledger."
                actionText="View Ledger"
                onAction={() => console.log('action')}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function number_format(num: number): string {
  return new Intl.NumberFormat('en-UG').format(num);
}
