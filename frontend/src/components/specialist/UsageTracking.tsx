'use client';

import { motion } from 'framer-motion';
import { 
  BarChart3, 
  TrendingUp, 
  Calendar,
  Briefcase,
  DollarSign,
  Clock,
  ArrowRight
} from 'lucide-react';

interface UsageTrackingProps {
  capabilities: any;
}

interface UsageMetric {
  icon: any;
  name: string;
  value: number;
  limit: number | null;
  period: string;
  color: string;
}

export default function UsageTracking({ capabilities }: UsageTrackingProps) {
  const isPro = capabilities?.plan?.slug === 'specialist-pro';

  if (!isPro) {
    return null;
  }

  // Mock usage data - in production, this would come from the backend
  const usageMetrics: UsageMetric[] = [
    {
      icon: TrendingUp,
      name: 'Intelligence Queries',
      value: 47,
      limit: null,
      period: 'this month',
      color: 'blue',
    },
    {
      icon: Briefcase,
      name: 'Career Milestones',
      value: 12,
      limit: 50,
      period: 'this month',
      color: 'purple',
    },
    {
      icon: DollarSign,
      name: 'Finance Reports',
      value: 8,
      limit: 25,
      period: 'this month',
      color: 'green',
    },
    {
      icon: Clock,
      name: 'Journey Goals',
      value: 3,
      limit: 10,
      period: 'active',
      color: 'amber',
    },
  ];

  const getPercentage = (value: number, limit: number | null) => {
    if (limit === null) return 0;
    return Math.min((value / limit) * 100, 100);
  };

  const getColorClass = (color: string) => {
    const colors = {
      blue: 'bg-blue-500',
      purple: 'bg-purple-500',
      green: 'bg-green-500',
      amber: 'bg-amber-500',
    };
    return colors[color as keyof typeof colors] || 'bg-gold';
  };

  const getBgClass = (color: string) => {
    const colors = {
      blue: 'bg-blue-500/20',
      purple: 'bg-purple-500/20',
      green: 'bg-green-500/20',
      amber: 'bg-amber-500/20',
    };
    return colors[color as keyof typeof colors] || 'bg-gold/20';
  };

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <BarChart3 className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Usage Overview</h3>
        </div>
        <button className="text-sm text-gold hover:underline flex items-center gap-1">
          View Details
          <ArrowRight className="w-4 h-4" />
        </button>
      </div>

      {/* Usage Metrics */}
      <div className="space-y-3">
        {usageMetrics.map((metric, index) => {
          const Icon = metric.icon;
          const percentage = getPercentage(metric.value, metric.limit);
          const colorClass = getColorClass(metric.color);
          const bgClass = getBgClass(metric.color);

          return (
            <motion.div
              key={metric.name}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: index * 0.1 }}
              className="bg-card border border-border-light rounded-xl p-4"
            >
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-3">
                  <div className={`w-10 h-10 rounded-lg ${bgClass} flex items-center justify-center`}>
                    <Icon className={`w-5 h-5 ${colorClass.replace('bg-', 'text-')}`} />
                  </div>
                  <div>
                    <p className="font-medium text-text-primary">{metric.name}</p>
                    <p className="text-xs text-text-secondary">
                      {metric.value} {metric.limit ? `/ ${metric.limit}` : ''} • {metric.period}
                    </p>
                  </div>
                </div>
                {metric.limit && (
                  <span className="text-sm font-medium text-text-primary">
                    {Math.round(percentage)}%
                  </span>
                )}
              </div>

              {metric.limit && (
                <div className="h-2 bg-surface rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${percentage}%` }}
                    transition={{ duration: 0.5, delay: index * 0.1 }}
                    className={`h-full ${colorClass} transition-all`}
                  />
                </div>
              )}

              {!metric.limit && (
                <div className="h-2 bg-surface rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: '100%' }}
                    transition={{ duration: 0.5, delay: index * 0.1 }}
                    className={`h-full ${colorClass} opacity-30 transition-all`}
                  />
                </div>
              )}
            </motion.div>
          );
        })}
      </div>

      {/* Usage Tips */}
      <div className="bg-gradient-to-br from-gold/10 to-amber-600/5 border border-gold/20 rounded-xl p-4">
        <p className="text-sm text-text-primary mb-2">
          <span className="font-semibold">Pro Tip:</span> Your usage is well within limits. 
          Explore all Pro features to get the most value from your subscription.
        </p>
      </div>
    </div>
  );
}
