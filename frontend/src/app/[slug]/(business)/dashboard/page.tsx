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
import { AlertTriangle, Building2, ArrowRight } from 'lucide-react';

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
  const {
    providerId,
    locationId,
    user,
    activeProvider,
    providerSlug,
    role,
    userName,
    isLoading,
    providerAccessError,
  } = useRole();

  // Authentication check: redirect to login if unauthenticated
  useEffect(() => {
    if (isLoading) return;

    const token = localStorage.getItem('auth_token');
    if (!user && !token) {
      router.push(`/login?returnTo=/${slug}/dashboard`);
    }
  }, [user, router, slug, isLoading]);

  // Ensure queries only execute when context is completely resolved for this specific slug
  const isContextReady = !isLoading && !!providerId && !providerAccessError && activeProvider?.slug?.toLowerCase() === slug.toLowerCase();

  const { data: bookingsData, error: bookingsError } = useQuery({
    queryKey: ['bookings', providerId, locationId],
    queryFn: () => apiClient.getBookings({ provider_id: providerId, ...(locationId ? { location_id: locationId } : {}) }),
    enabled: isContextReady,
    refetchInterval: 15000,
    refetchIntervalInBackground: false,
  });

  const { data: staffData, error: staffError } = useQuery({
    queryKey: ['staff', providerId, slug, locationId],
    queryFn: () => apiClient.getAssignableSpecialists(slug as string, locationId || undefined),
    enabled: isContextReady,
  });

  const bookings = Array.isArray(bookingsData) ? bookingsData : [];
  const staff = Array.isArray(staffData) ? staffData : [];
  const error = bookingsError || staffError ? 'Failed to load data' : null;

  if (isLoading || (!user && typeof window !== 'undefined' && localStorage.getItem('auth_token'))) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full min-h-[50vh]">
          <div className="text-text-secondary">Loading workspace...</div>
        </div>
      </DashboardLayout>
    );
  }

  // Safe handling of unauthorized or nonexistent provider slugs: Never silently redirect to another business
  if (providerAccessError || (!isLoading && user && !activeProvider)) {
    return (
      <DashboardLayout>
        <div className="flex flex-col items-center justify-center h-full min-h-[60vh] p-8 text-center">
          <div className="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-5">
            <AlertTriangle className="w-8 h-8" />
          </div>
          <h2 className="text-2xl font-bold text-text-primary mb-2">Business Access Denied</h2>
          <p className="text-sm text-text-secondary max-w-md mb-6 leading-relaxed">
            You do not have access to the business <span className="text-gold font-mono">&ldquo;{slug}&rdquo;</span>, or it does not exist.
          </p>

          {user?.providers && user.providers.length > 0 && (
            <div className="w-full max-w-md bg-card border border-border-light rounded-2xl p-5 text-left">
              <p className="text-xs font-semibold uppercase tracking-wider text-text-secondary mb-3">
                Your Authorized Businesses
              </p>
              <div className="space-y-2">
                {user.providers.map((p: any) => (
                  <button
                    key={p.id}
                    onClick={() => router.push(`/${p.slug}/dashboard`)}
                    className="w-full flex items-center justify-between p-3 rounded-xl bg-surface hover:bg-border-light border border-border-medium text-text-primary text-sm font-medium transition-all group"
                  >
                    <div className="flex items-center gap-3 min-w-0">
                      <div className="w-8 h-8 rounded-lg bg-gold/10 text-gold flex items-center justify-center shrink-0">
                        <Building2 className="w-4 h-4" />
                      </div>
                      <span className="truncate">{p.name || p.display_name}</span>
                    </div>
                    <ArrowRight className="w-4 h-4 text-text-secondary group-hover:text-gold transition-colors shrink-0" />
                  </button>
                ))}
              </div>
            </div>
          )}
        </div>
      </DashboardLayout>
    );
  }

  if (error) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full min-h-[50vh]">
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

