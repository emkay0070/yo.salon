'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useRouter } from 'next/navigation';
import { LayoutDashboard, Calendar, Users, Scissors, Briefcase, TrendingUp, DollarSign } from 'lucide-react';
import { motion } from 'framer-motion';

export default function WorkplacePortalPage() {
  const { currentWorkplace, specialist, isLoading, workplaces } = useSpecialistAuth();
  const router = useRouter();

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[50vh]">
        <div className="w-8 h-8 border-2 border-gold/40 border-t-gold rounded-full animate-spin" />
      </div>
    );
  }

  if (!currentWorkplace) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[50vh]">
        <div className="text-center">
          <p className="text-text-secondary font-medium">Workplace not found</p>
          <p className="text-sm text-text-muted mt-2">You may not have access to this workplace, or it doesn't exist.</p>
          {workplaces.length > 0 && (
            <button
              onClick={() => router.push(`/${workplaces[0].provider_slug}/specialist`)}
              className="mt-4 px-4 py-2 bg-gold text-black rounded-lg font-medium hover:opacity-90 transition-opacity"
            >
              Go to {workplaces[0].provider_name}
            </button>
          )}
        </div>
      </div>
    );
  }

  const quickActions = [
    {
      icon: Calendar,
      label: 'Appointments',
      description: 'View and manage bookings',
      href: `/${currentWorkplace.provider_slug}/specialist/appointments`,
      color: 'bg-blue-500/10 text-blue-500',
    },
    {
      icon: Scissors,
      label: 'Craft',
      description: 'Services and expertise',
      href: `/${currentWorkplace.provider_slug}/specialist/craft`,
      color: 'bg-gold/10 text-gold',
    },
    {
      icon: Users,
      label: 'Clients',
      description: 'Customer relationships',
      href: `/${currentWorkplace.provider_slug}/specialist/clients`,
      color: 'bg-purple-500/10 text-purple-500',
    },
    {
      icon: DollarSign,
      label: 'Finance',
      description: 'Earnings and payments',
      href: `/${currentWorkplace.provider_slug}/specialist/finance`,
      color: 'bg-green-500/10 text-green-500',
    },
  ];

  return (
    <div className="p-6 space-y-8">
      {/* Welcome Header */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="space-y-2"
      >
        <h1 className="text-2xl font-bold text-text-primary">
          Welcome back, {specialist?.name}
        </h1>
        <p className="text-text-secondary">
          You're operating in <span className="text-gold font-medium">{currentWorkplace.provider_name}</span>
        </p>
      </motion.div>

      {/* Quick Actions */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Quick Actions</h2>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {quickActions.map((action, index) => {
            const Icon = action.icon;
            return (
              <motion.button
                key={action.label}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 + index * 0.05 }}
                onClick={() => router.push(action.href)}
                className="bg-card border border-border-light rounded-xl p-6 text-left hover:border-gold/60 hover:bg-gold/5 transition-all group"
              >
                <div className={`w-12 h-12 rounded-lg ${action.color} flex items-center justify-center mb-4 group-hover:scale-110 transition-transform`}>
                  <Icon className="w-6 h-6" />
                </div>
                <h3 className="font-semibold text-text-primary mb-1">{action.label}</h3>
                <p className="text-sm text-text-secondary">{action.description}</p>
              </motion.button>
            );
          })}
        </div>
      </motion.div>

      {/* Role Information */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="bg-card border border-border-light rounded-xl p-6"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Your Role</h2>
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center">
            <span className="text-2xl font-bold text-gold">
              {currentWorkplace.role.charAt(0)}
            </span>
          </div>
          <div>
            <p className="text-xl font-medium text-text-primary capitalize">
              {currentWorkplace.role}
            </p>
            <p className="text-sm text-text-secondary capitalize">
              {currentWorkplace.employment_type}
            </p>
            <p className="text-xs text-text-muted mt-1">
              {currentWorkplace.can_manage_provider ? 'Can manage provider settings' : 'Limited access'}
            </p>
          </div>
        </div>
      </motion.div>
    </div>
  );
}
