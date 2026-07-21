'use client';

import { motion } from 'framer-motion';
import { Building2, Users, CreditCard, Activity, MessageSquare, TrendingUp, AlertTriangle, CheckCircle, DollarSign, Zap } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface PlatformAdminDashboardProps {
  userName: string;
}

export default function PlatformAdminDashboard({ userName }: PlatformAdminDashboardProps) {
  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });

  // Fetch real platform data from API
  const { data: platformStats, isLoading: statsLoading } = useQuery({
    queryKey: ['platform-stats'],
    queryFn: () => apiClient.getPlatformStats(),
  });

  const { data: healthMetrics } = useQuery({
    queryKey: ['health-metrics'],
    queryFn: () => apiClient.getHealthMetrics(),
  });

  const { data: revenueData } = useQuery({
    queryKey: ['revenue-data'],
    queryFn: () => apiClient.getRevenueData(),
  });

  const { data: supportTickets } = useQuery({
    queryKey: ['support-tickets'],
    queryFn: () => apiClient.getSupportTickets(),
  });

  const { data: recentActivity } = useQuery({
    queryKey: ['recent-activity'],
    queryFn: () => apiClient.getRecentActivity(),
  });

  const { data: systemAlerts } = useQuery({
    queryKey: ['system-alerts'],
    queryFn: () => apiClient.getSystemAlerts(),
  });

  // Use API data or fallback to empty objects
  const stats = platformStats || {
    totalSalons: 0,
    activeSalons: 0,
    activeSubscriptions: 0,
    totalUsers: 0,
    newSignups: 0
  };

  const health = healthMetrics || {
    uptime: '99.9%',
    avgResponseTime: '120ms',
    errorRate: '0.02%',
    activeConnections: 0
  };

  const revenue = revenueData || {
    monthlyRevenue: 0,
    growth: '+0%',
    pendingPayments: 0,
    overduePayments: 0
  };

  const tickets = supportTickets || {
    open: 0,
    highPriority: 0,
    avgResponseTime: '0 hours',
    resolvedToday: 0
  };

  const activity = recentActivity || [];
  const alerts = systemAlerts || [];

  const getAlertColor = (type: string) => {
    switch (type) {
      case 'warning': return 'text-yellow-400 bg-yellow-500/20';
      case 'info': return 'text-blue-400 bg-blue-500/20';
      case 'success': return 'text-green-400 bg-green-500/20';
      default: return 'text-gray-400 bg-gray-500/20';
    }
  };

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'signup': return Building2;
      case 'revenue': return CreditCard;
      case 'support': return MessageSquare;
      case 'system': return Activity;
      default: return CheckCircle;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Platform Admin Dashboard</h1>
          <p className="text-text-secondary mt-1">{formattedDate}</p>
        </div>
        <div className="flex items-center gap-2">
          <Zap className="w-5 h-5 text-gold" />
          <span className="text-gold font-medium">System Healthy</span>
        </div>
      </motion.div>

      {/* Platform Stats */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
        {[
          { label: 'Total Salons', value: stats.totalSalons, icon: Building2, color: 'text-gold', bg: 'from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]' },
          { label: 'Active Salons', value: stats.activeSalons, icon: CheckCircle, color: 'text-green-400', bg: 'from-[rgba(74,222,128,0.2)] to-[rgba(74,222,128,0.05)]' },
          { label: 'Subscriptions', value: stats.activeSubscriptions, icon: CreditCard, color: 'text-blue-400', bg: 'from-[rgba(96,165,250,0.2)] to-[rgba(96,165,250,0.05)]' },
          { label: 'Total Users', value: stats.totalUsers, icon: Users, color: 'text-purple-400', bg: 'from-[rgba(168,85,247,0.2)] to-[rgba(168,85,247,0.05)]' },
          { label: 'New Signups', value: `+${stats.newSignups}`, icon: TrendingUp, color: 'text-obsidian', bg: 'from-[rgba(47,122,92,0.2)] to-[rgba(47,122,92,0.05)]' },
        ].map((stat, index) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="bg-card border border-border-light rounded-2xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <div className={`p-2 rounded-xl bg-gradient-to-br ${stat.bg}`}>
                <stat.icon className={`w-5 h-5 ${stat.color}`} />
              </div>
              <div>
                <p className="text-text-secondary text-xs">{stat.label}</p>
                <p className="text-xl font-bold text-text-primary">{stat.value}</p>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Health Metrics */}
        <motion.div
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <Activity className="w-5 h-5 text-gold" />
            Platform Health
          </h2>
          
          <div className="space-y-4">
            {[
              { label: 'Uptime', value: health.uptime, color: 'text-green-400' },
              { label: 'Avg Response Time', value: health.avgResponseTime, color: 'text-blue-400' },
              { label: 'Error Rate', value: health.errorRate, color: 'text-green-400' },
              { label: 'Active Connections', value: health.activeConnections, color: 'text-purple-400' },
            ].map((metric) => (
              <div key={metric.label} className="flex items-center justify-between p-3 rounded-xl bg-surface border border-border-medium">
                <p className="text-text-secondary text-sm">{metric.label}</p>
                <p className={`font-semibold ${metric.color}`}>{metric.value}</p>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Revenue Overview */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <DollarSign className="w-5 h-5 text-gold" />
            Revenue Overview
          </h2>
          
          <div className="space-y-4">
            <div className="p-4 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.1)] to-[rgba(255,215,0,0.05)] border border-[rgba(255,215,0,0.2)]">
              <p className="text-text-secondary text-sm">Monthly Revenue</p>
              <p className="text-2xl font-bold text-text-primary mt-1">UGX {revenue.monthlyRevenue.toLocaleString()}</p>
              <p className="text-green-400 text-sm mt-1">{revenue.growth} from last month</p>
            </div>
            
            <div className="grid grid-cols-2 gap-3">
              <div className="p-3 rounded-xl bg-surface border border-border-medium">
                <p className="text-text-secondary text-xs">Pending</p>
                <p className="text-lg font-bold text-text-primary">{revenue.pendingPayments}</p>
              </div>
              <div className="p-3 rounded-xl bg-surface border border-border-medium">
                <p className="text-text-secondary text-xs">Overdue</p>
                <p className="text-lg font-bold text-red-400">{revenue.overduePayments}</p>
              </div>
            </div>
          </div>
        </motion.div>

        {/* Support Tickets */}
        <motion.div
          initial={{ opacity: 0, x: 10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-gold" />
            Support Tickets
          </h2>
          
          <div className="space-y-4">
            {[
              { label: 'Open Tickets', value: tickets.open, color: 'text-yellow-400' },
              { label: 'High Priority', value: tickets.highPriority, color: 'text-red-400' },
              { label: 'Avg Response', value: tickets.avgResponseTime, color: 'text-blue-400' },
              { label: 'Resolved Today', value: tickets.resolvedToday, color: 'text-green-400' },
            ].map((ticket) => (
              <div key={ticket.label} className="flex items-center justify-between p-3 rounded-xl bg-surface border border-border-medium">
                <p className="text-text-secondary text-sm">{ticket.label}</p>
                <p className={`font-semibold ${ticket.color}`}>{ticket.value}</p>
              </div>
            ))}
          </div>
        </motion.div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Activity */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Recent Activity</h2>
          
          <div className="space-y-3">
            {activity.map((activity: any, index: number) => {
              const Icon = getActivityIcon(activity.type);
              return (
                <div key={index} className="flex items-center gap-4 p-3 rounded-xl bg-surface border border-border-medium">
                  <div className="p-2 rounded-lg bg-white/10">
                    <Icon className="w-4 h-4 text-gold" />
                  </div>
                  <div className="flex-1">
                    <p className="text-text-primary text-sm">{activity.action}</p>
                    <p className="text-text-secondary text-xs">{activity.time}</p>
                  </div>
                </div>
              );
            })}
          </div>
        </motion.div>

        {/* System Alerts */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-gold" />
            System Alerts
          </h2>
          
          <div className="space-y-3">
            {alerts.map((alert: any, index: number) => (
              <div key={index} className="flex items-center gap-3 p-3 rounded-xl bg-surface border border-border-medium">
                <div className={`p-2 rounded-lg ${getAlertColor(alert.type)}`}>
                  <AlertTriangle className="w-4 h-4" />
                </div>
                <p className="text-text-primary text-sm">{alert.message}</p>
              </div>
            ))}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
