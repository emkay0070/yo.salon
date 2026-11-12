'use client';

import { useEffect, use } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';
import DashboardLayout from '@/components/DashboardLayout';
import { useRole } from '@/contexts/RoleContext';
import OwnerDashboard from '@/components/dashboard/OwnerDashboard';
import ManagerDashboard from '@/components/dashboard/ManagerDashboard';
import EmployeeDashboard from '@/components/dashboard/EmployeeDashboard';
import ReceptionistDashboard from '@/components/dashboard/ReceptionistDashboard';
import PlatformAdminDashboard from '@/components/dashboard/PlatformAdminDashboard';

// ─── Canonical API Booking shape ──────────────────────────────────────────────
// All relationships are optional because:
//   - Walk-in bookings may not have a customer record (anonymous)
//   - Bookings may not have a specialist/staff assigned yet
//   - Service relationship may not be eagerly loaded on all endpoints
export interface ApiBooking {
  id: string;
  date: string;
  time: string;
  status: string;
  notes?: string;
  // Relationships — all nullable/optional to match actual backend responses
  customer?: { name: string; phone: string; email?: string } | null;
  service?:  { id: string; name: string; price: number; duration?: number } | null;
  specialist?: { id: string; name: string } | null;
  staff?:     { id: string; name: string } | null;
  location?:  { id: string; name: string } | null;
  provider?:  { id: string; display_name: string } | null;
}

interface Staff {
  id: string;
  name: string;
  specializations?: string[];
}

export default function SalonDashboardPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const { providerId, locationId, user, activeProvider, providerSlug, role, userName, isLoading } = useRole();

  // Verify user has access to this provider
  useEffect(() => {
    if (isLoading) return;

    const token = localStorage.getItem('auth_token');
    if (!user && !token) {
      router.push(`/login?returnTo=/${slug}/dashboard`);
      return;
    }
    if (!user && token) return; // wait for RoleContext

    if (!providerId) {
      console.warn('[SalonDashboardPage] User has no provider assignment');
      return;
    }

    if (activeProvider && activeProvider.slug !== slug) {
      router.push(`/${activeProvider.slug}/dashboard`);
    }
  }, [providerId, user, router, slug, activeProvider, isLoading]);

  const { data: bookingsData, error: bookingsError } = useQuery({
    queryKey: ['bookings', providerId, locationId],
    queryFn: () => apiClient.getBookings(),
    enabled: !!providerId,
    refetchInterval: 15000,
    refetchIntervalInBackground: false,
  });

  const { data: staffData, error: staffError } = useQuery({
    queryKey: ['staff', providerId, slug],
    queryFn: () => apiClient.getAssignableSpecialists(slug as string),
    enabled: !!providerId && !!slug,
  });

  const bookings = Array.isArray(bookingsData) ? bookingsData : [];
  const staff = Array.isArray(staffData) ? staffData : [];
  const error = bookingsError || staffError ? 'Failed to load data' : null;

  if (isLoading || (!user && typeof window !== 'undefined' && localStorage.getItem('auth_token'))) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full">
          <div className="text-text-secondary">Loading...</div>
        </div>
      </DashboardLayout>
    );
  }

  if (error) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full">
          <div className="text-gold">{error}</div>
        </div>
      </DashboardLayout>
    );
  }

  const renderDashboard = () => {
    switch (role) {
      case 'owner':
        return <OwnerDashboard userName={userName} bookings={bookings} staff={staff} />;
      case 'manager':
        return <ManagerDashboard userName={userName} />;
      case 'employee':
        return <EmployeeDashboard userName={userName} />;
      case 'receptionist':
        return <ReceptionistDashboard userName={userName} />;
      case 'platform_admin':
        return <PlatformAdminDashboard userName={userName} />;
      default:
        return <OwnerDashboard userName={userName} bookings={bookings} staff={staff} />;
    }
  };

  return (
    <DashboardLayout>
      {renderDashboard()}
    </DashboardLayout>
  );
}

