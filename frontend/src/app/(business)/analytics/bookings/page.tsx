'use client';
import { useQuery } from '@tanstack/react-query';
import { InsightHero } from '@/components/analytics/InsightHero';
import { BarChart } from '@/components/analytics/BarChart';
import { BusinessSignal } from '@/components/analytics/BusinessSignal';
import { CalendarX, Users, CalendarCheck } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

export default function BookingsAnalytics() {
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

  // Calculate booking density by hour from real data
  const bookingsByHour = analyticsData?.bookings?.reduce((acc: any, booking: any) => {
    const hour = new Date(`2000-01-01T${booking.time}`).getHours();
    const timeLabel = hour >= 12 ? `${hour === 12 ? 12 : hour - 12}PM` : `${hour}AM`;
    acc[timeLabel] = (acc[timeLabel] || 0) + 1;
    return acc;
  }, {});

  const hourlyData = Object.entries(bookingsByHour || {}).map(([time, bookings]) => ({
    time,
    bookings,
  })).sort((a: any, b: any) => {
    const hourA = parseInt(a.time);
    const hourB = parseInt(b.time);
    return hourA - hourB;
  });

  const totalBookings = analyticsData?.total_bookings || 0;
  const cancelledBookings = analyticsData?.status_counts?.cancelled || 0;
  const cancellationRate = totalBookings > 0 ? ((cancelledBookings / totalBookings) * 100).toFixed(1) : '0';

  return (
    <div className="space-y-12">
      <InsightHero 
        title="Salon Rhythm"
        narrative={`Total bookings: ${totalBookings}. Peak hours show your busiest times.`}
        imagePath="/images/salon-styling.jpg"
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <BusinessSignal 
          title="Total Bookings"
          value={totalBookings.toString()}
          narrative="Total appointments across all time periods."
          icon={Users}
          trend="neutral"
        />
        <BusinessSignal 
          title="Cancellation Rate"
          value={`${cancellationRate}%`}
          narrative="Percentage of cancelled bookings."
          icon={CalendarX}
          trend={parseFloat(cancellationRate) < 5 ? 'positive' : 'negative'}
        />
        <BusinessSignal 
          title="Today's Bookings"
          value={analyticsData?.today_bookings?.toString() || '0'}
          narrative="Appointments scheduled for today."
          icon={CalendarCheck}
          trend="neutral"
        />
      </div>

      <div>
        <h3 className="text-lg font-medium text-text-primary mb-6">Booking Density by Hour</h3>
        <BarChart 
          data={hourlyData.length > 0 ? hourlyData : [{ time: 'No Data', bookings: 0 }]}
          dataKey="bookings"
          xAxisKey="time"
          height={320}
        />
      </div>
    </div>
  );
}
