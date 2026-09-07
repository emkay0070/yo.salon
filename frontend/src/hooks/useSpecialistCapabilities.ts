import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

export type SpecialistCapability = 
  | 'SPECIALIST_WORKSPACE'
  | 'SPECIALIST_CALENDAR'
  | 'SPECIALIST_APPOINTMENTS'
  | 'SPECIALIST_PROFILE'
  | 'SPECIALIST_CRAFT'
  | 'SPECIALIST_CLIENTS'
  | 'SPECIALIST_SETTINGS'
  | 'SPECIALIST_VERIFICATION'
  | 'SPECIALIST_CAREER'
  | 'SPECIALIST_INTELLIGENCE'
  | 'SPECIALIST_FINANCE'
  | 'SPECIALIST_JOURNEY';

export type SpecialistPlan = 'specialist-free' | 'specialist-pro';

interface SpecialistCapabilities {
  has_subscription: boolean;
  plan: {
    id: string;
    slug: SpecialistPlan;
    name: string;
  };
  subscription: {
    status: string;
    is_trialing: boolean;
    trial_ends_at: string | null;
    renews_at: string | null;
  };
  features: Record<SpecialistCapability, boolean>;
}

export function useSpecialistCapabilities() {
  const { data, isLoading, error } = useQuery<SpecialistCapabilities>({
    queryKey: ['specialist-capabilities'],
    queryFn: () => apiClient.getSpecialistCapabilities(),
    staleTime: 5 * 60 * 1000, // 5 minutes
  });

  const hasCapability = (capability: SpecialistCapability): boolean => {
    return data?.features?.[capability] ?? false;
  };

  const isPro = (): boolean => {
    return data?.plan?.slug === 'specialist-pro';
  };

  const isFree = (): boolean => {
    return data?.plan?.slug === 'specialist-free' || !data?.has_subscription;
  };

  const isTrialing = (): boolean => {
    return data?.subscription?.is_trialing ?? false;
  };

  return {
    capabilities: data,
    isLoading,
    error,
    hasCapability,
    isPro,
    isFree,
    isTrialing,
  };
}
