import { useState, useEffect } from 'react';

interface CapabilitySummary {
  has_subscription: boolean;
  plan: {
    id: string;
    slug: string;
    name: string;
  } | null;
  subscription: {
    status: string;
    is_trialing: boolean;
    trial_ends_at: string | null;
    renews_at: string | null;
  } | null;
  features: Record<string, boolean>;
  resources: Record<string, {
    used: number;
    base_limit: number;
    addon_limit: number;
    effective_limit: number;
    available: number;
    is_over_limit: boolean;
  }>;
  credits: Record<string, {
    included: number;
    purchased: number;
    consumed: number;
    available: number;
  }>;
}

interface UsePlanFeaturesResult {
  capabilities: CapabilitySummary | null;
  loading: boolean;
  error: string | null;
  can: (featureCode: string) => boolean;
  getQuota: (resourceCode: string) => {
    used: number;
    limit: number;
    remaining: number;
    canAdd: boolean;
    isOverLimit: boolean;
  } | null;
  getCredits: (creditCode: string) => {
    available: number;
  } | null;
  refresh: () => void;
}

/**
 * Hook for accessing salon plan capabilities and features.
 * 
 * This hook provides a unified interface for checking:
 * - Feature access (e.g., CALENDAR_WEEK, CUSTOMER_PORTAL)
 * - Quota limits (e.g., STAFF_SEAT, BRANCH, STORAGE_GB)
 * - Credit balances (e.g., SMS, AI_REQUEST)
 * 
 * @param salonId - The salon ID to fetch capabilities for
 * @returns Object with capability data and helper methods
 */
export function usePlanFeatures(salonId: string | null): UsePlanFeaturesResult {
  const [capabilities, setCapabilities] = useState<CapabilitySummary | null>(null);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const fetchCapabilities = async () => {
    if (!salonId) {
      setCapabilities(null);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/v1/salons/${salonId}/capabilities`, {
        headers: {
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error(`Failed to fetch capabilities: ${response.statusText}`);
      }

      const data = await response.json();
      setCapabilities(data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unknown error');
      console.error('Error fetching plan capabilities:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCapabilities();
  }, [salonId]);

  /**
   * Check if a specific feature is enabled for the salon.
   * 
   * @param featureCode - The feature code (e.g., 'CALENDAR_WEEK', 'CUSTOMER_PORTAL')
   * @returns true if the feature is enabled, false otherwise
   */
  const can = (featureCode: string): boolean => {
    if (!capabilities || !capabilities.features) {
      return false;
    }
    return capabilities.features[featureCode] === true;
  };

  /**
   * Get quota information for a specific resource.
   * 
   * @param resourceCode - The resource code (e.g., 'STAFF_SEAT', 'BRANCH', 'STORAGE_GB')
   * @returns Quota information or null if not available
   */
  const getQuota = (resourceCode: string) => {
    if (!capabilities || !capabilities.resources) {
      return null;
    }

    const quota = capabilities.resources[resourceCode];
    if (!quota) {
      return null;
    }

    return {
      used: quota.used,
      limit: quota.effective_limit,
      remaining: quota.available,
      canAdd: quota.available > 0,
      isOverLimit: quota.is_over_limit,
    };
  };

  /**
   * Get credit balance for a specific credit type.
   * 
   * @param creditCode - The credit code (e.g., 'SMS', 'AI_REQUEST')
   * @returns Credit balance or null if not available
   */
  const getCredits = (creditCode: string) => {
    if (!capabilities || !capabilities.credits) {
      return null;
    }

    const credit = capabilities.credits[creditCode];
    if (!credit) {
      return null;
    }

    return {
      available: credit.available,
    };
  };

  return {
    capabilities,
    loading,
    error,
    can,
    getQuota,
    getCredits,
    refresh: fetchCapabilities,
  };
}
