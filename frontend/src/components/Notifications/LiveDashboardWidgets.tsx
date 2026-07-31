'use client';

import { useState, useEffect } from 'react';
import { Bell, Wallet, Clock, Users, TrendingUp } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface LiveStats {
  new_bookings_today: number;
  payments_today: number;
  awaiting_approval: number;
  customers_waiting: number;
  revenue_today: number;
}

export function LiveDashboardWidgets() {
  const [stats, setStats] = useState<LiveStats>({
    new_bookings_today: 0,
    payments_today: 0,
    awaiting_approval: 0,
    customers_waiting: 0,
    revenue_today: 0,
  });
  const [loading, setLoading] = useState(true);

  const fetchStats = async () => {
    try {
      const response = await apiClient.get('/dashboard/live-stats');
      setStats(response);
    } catch (error) {
      console.error('Failed to fetch live stats:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStats();
    
    // Poll every 10 seconds (reverted from WebSocket until domain is purchased)
    const interval = setInterval(fetchStats, 10000);
    return () => clearInterval(interval);
  }, []);

  const widgets = [
    {
      icon: Bell,
      label: 'New Bookings',
      value: stats.new_bookings_today,
      color: 'text-gold',
      bgColor: 'bg-gold/10',
    },
    {
      icon: Wallet,
      label: 'Payments Today',
      value: stats.payments_today,
      color: 'text-green-400',
      bgColor: 'bg-green-500/10',
    },
    {
      icon: Clock,
      label: 'Awaiting Approval',
      value: stats.awaiting_approval,
      color: 'text-orange-400',
      bgColor: 'bg-orange-500/10',
    },
    {
      icon: Users,
      label: 'Customers Waiting',
      value: stats.customers_waiting,
      color: 'text-blue-400',
      bgColor: 'bg-blue-500/10',
    },
  ];

  if (loading) {
    return (
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="bg-white/5 border border-white/10 rounded-xl p-4 animate-pulse">
            <div className="h-4 w-24 bg-white/10 rounded mb-2" />
            <div className="h-8 w-12 bg-white/10 rounded" />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      {widgets.map((widget) => (
        <div
          key={widget.label}
          className="bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-colors"
        >
          <div className="flex items-center justify-between mb-2">
            <div className={`w-10 h-10 rounded-lg ${widget.bgColor} flex items-center justify-center`}>
              <widget.icon className={`w-5 h-5 ${widget.color}`} />
            </div>
            <span className="text-xs text-gray-500">Today</span>
          </div>
          <p className="text-2xl font-bold text-white">{widget.value}</p>
          <p className="text-sm text-gray-400">{widget.label}</p>
        </div>
      ))}
      
      {/* Revenue Widget */}
      <div className="col-span-2 lg:col-span-4 bg-gradient-to-r from-gold/20 to-gold/5 border border-gold/20 rounded-xl p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gold/20 flex items-center justify-center">
              <TrendingUp className="w-5 h-5 text-gold" />
            </div>
            <div>
              <p className="text-sm text-gray-400">Revenue Today</p>
              <p className="text-2xl font-bold text-white">
                {stats.revenue_today.toLocaleString()} UGX
              </p>
            </div>
          </div>
          <div className="text-right">
            <p className="text-xs text-gray-500">Live</p>
            <div className="flex items-center gap-1">
              <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse" />
              <span className="text-xs text-green-400">Updating</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
