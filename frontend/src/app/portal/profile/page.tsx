'use client';

import { motion } from 'framer-motion';
import { User, Phone, Mail, MapPin, Calendar, Scissors, Edit, LogOut } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import ClientLayout from '@/components/ClientLayout';
import { useRouter } from 'next/navigation';

export default function ProfilePage() {
  const { customer, salon, logout } = usePortalAuth();
  const router = useRouter();

  const { data: profileData, isLoading, isError } = useQuery({
    queryKey: ['portal-profile'],
    queryFn: () => portalApiClient.get('/v1/portal/context'),
    enabled: !!customer,
  });

  const handleLogout = async () => {
    await logout();
    router.push('/portal/login');
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary text-center">
          <p className="text-red-500 mb-2">Failed to load profile.</p>
          <button onClick={() => window.location.reload()} className="text-gold underline hover:text-dark-gold">Try again</button>
        </div>
      </div>
    );
  }

  const profile = profileData || {};

  return (
    <ClientLayout>
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-3xl font-bold text-text-primary mb-2">My Profile</h1>
          <p className="text-text-secondary">Manage your account settings</p>
        </motion.div>

        {/* Profile Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bg-surface border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-start gap-6">
            <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center flex-shrink-0">
              <User className="w-10 h-10 text-obsidian" />
            </div>
            <div className="flex-1">
              <h2 className="text-2xl font-bold text-text-primary mb-1">{customer?.name || 'Guest'}</h2>
              <p className="text-text-secondary mb-4">Member since {new Date().getFullYear()}</p>
              <button className="flex items-center gap-2 px-4 py-2 bg-gold/10 text-gold rounded-full text-sm font-medium hover:bg-gold/20 transition-colors">
                <Edit className="w-4 h-4" />
                Edit Profile
              </button>
            </div>
          </div>
        </motion.div>

        {/* Contact Information */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="bg-surface border border-border-light rounded-2xl p-6"
        >
          <h3 className="text-lg font-semibold text-text-primary mb-4">Contact Information</h3>
          <div className="space-y-4">
            <InfoRow
              icon={Mail}
              label="Email"
              value={customer?.email || 'Not provided'}
            />
            <InfoRow
              icon={Phone}
              label="Phone"
              value={customer?.phone || 'Not provided'}
            />
          </div>
        </motion.div>

        {/* Salon Information */}
        {salon && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bg-surface border border-border-light rounded-2xl p-6"
          >
            <h3 className="text-lg font-semibold text-text-primary mb-4">Salon Information</h3>
            <div className="space-y-4">
              <InfoRow
                icon={Scissors}
                label="Salon"
                value={salon.name}
              />
              <InfoRow
                icon={MapPin}
                label="Address"
                value={salon.address || 'Not provided'}
              />
              <InfoRow
                icon={Phone}
                label="Phone"
                value={salon.phone || 'Not provided'}
              />
            </div>
          </motion.div>
        )}

        {/* Stats */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="grid grid-cols-2 gap-4"
        >
          <StatCard
            icon={Calendar}
            label="Total Visits"
            value={customer?.visits || 0}
          />
          <StatCard
            icon={Scissors}
            label="Services Booked"
            value={profile?.total_bookings || 0}
          />
        </motion.div>

        {/* Logout Button */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
        >
          <button
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 px-6 py-3 bg-surface border border-border-light rounded-xl text-text-secondary hover:text-terracotta hover:border-terracotta/30 transition-all"
          >
            <LogOut className="w-4 h-4" />
            Logout
          </button>
        </motion.div>
      </div>
    </ClientLayout>
  );
}

function InfoRow({ icon: Icon, label, value }: { icon: any, label: string, value: string }) {
  return (
    <div className="flex items-center gap-4">
      <div className="w-10 h-10 rounded-xl bg-surface border border-border-light flex items-center justify-center flex-shrink-0">
        <Icon className="w-5 h-5 text-text-secondary" />
      </div>
      <div className="flex-1">
        <p className="text-sm text-text-secondary mb-1">{label}</p>
        <p className="text-text-primary font-medium">{value}</p>
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value }: { icon: any, label: string, value: number }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6">
      <div className="flex items-center gap-3 mb-3">
        <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
          <Icon className="w-5 h-5 text-gold" />
        </div>
        <span className="text-sm text-text-secondary">{label}</span>
      </div>
      <p className="text-2xl font-bold text-text-primary">{value}</p>
    </div>
  );
}
