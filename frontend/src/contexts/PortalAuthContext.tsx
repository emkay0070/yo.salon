'use client';

import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQueryClient } from '@tanstack/react-query';

interface PortalCustomer {
  id: string;
  name: string;
  phone: string;
  email: string;
  visits: number;
}

interface Salon {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
  phone: string;
  email: string;
  address: string | null;
  opening_hours: any;
}

interface SalonSummary {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
  is_active: boolean;
  visits: number;
}

interface PortalAccount {
  id: string;
  email: string;
  email_verified_at: string | null;
  phone_verified_at: string | null;
}

interface PortalAuthContextType {
  customer: PortalCustomer | null;
  salon: Salon | null;
  salons: SalonSummary[];
  portalAccount: PortalAccount | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  isSwitching: boolean;
  capabilities: Record<string, boolean>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  switchSalon: (salonId: string) => Promise<void>;
}

const PortalAuthContext = createContext<PortalAuthContextType | undefined>(undefined);

export function PortalAuthProvider({ children }: { children: ReactNode }) {
  const [customer, setCustomer] = useState<PortalCustomer | null>(null);
  const [salon, setSalon] = useState<Salon | null>(null);
  const [salons, setSalons] = useState<SalonSummary[]>([]);
  const [portalAccount, setPortalAccount] = useState<PortalAccount | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSwitching, setIsSwitching] = useState(false);
  const [capabilities, setCapabilities] = useState<Record<string, boolean>>({});

  const hydrateFromContext = useCallback((data: any) => {
    console.log('Hydrating from context:', data);
    if (data.customer) {
      setCustomer(data.customer);
    } else {
      console.warn('No customer data in context response');
    }
    if (data.active_salon) {
      setSalon(data.active_salon);
    } else {
      console.warn('No active_salon data in context response');
    }
    if (data.salons) {
      setSalons(data.salons);
    }
    if (data.portal_account) {
      setPortalAccount(data.portal_account);
    }
    if (data.capabilities) {
      setCapabilities(data.capabilities);
    }
    console.log('After hydration - customer:', data.customer, 'salon:', data.active_salon);
  }, []);

    const refresh = useCallback(async () => {
    // Check if we're on the server
    if (typeof window === 'undefined') {
      setIsLoading(false);
      return;
    }

    const token = localStorage.getItem('portal_auth_token');
    if (token) {
      try {
        const data = await portalApiClient.get('/portal/context');
        hydrateFromContext(data);
      } catch (error: any) {
        console.error("Failed to load portal user:", error);
        // Only clear the token on a genuine 401 Unauthorized.
        // For other errors (403, 500, network) keep the token so the user
        // isn't silently logged out due to a transient backend issue.
        const status = error?.response?.status;
        if (status === 401) {
          localStorage.removeItem('portal_auth_token');
        }
      }
    }
    setIsLoading(false);
  }, [hydrateFromContext]);


  useEffect(() => {
    refresh();
  }, [refresh]);

  const login = async (email: string, password: string) => {
    const result = await portalApiClient.login(email, password);
    hydrateFromContext(result.context);
  };

  const logout = async () => {
    await portalApiClient.logout();
    setCustomer(null);
    setSalon(null);
    setSalons([]);
    setPortalAccount(null);
    setCapabilities({});
  };

  const switchSalon = async (salonId: string) => {
    setIsSwitching(true);
    try {
      // Update the stored salon ID so subsequent requests use it
      portalApiClient.setActiveSalonId(salonId);
      // Re-fetch context with new salon
      const data = await portalApiClient.get('/portal/context');
      hydrateFromContext(data);
    } catch (error) {
      console.error("Failed to switch salon:", error);
    } finally {
      setIsSwitching(false);
    }
  };

  return (
    <PortalAuthContext.Provider
      value={{
        customer,
        salon,
        salons,
        portalAccount,
        isAuthenticated: !!customer,
        isLoading,
        isSwitching,
        capabilities,
        login,
        logout,
        refresh,
        switchSalon,
      }}
    >
      {children}
    </PortalAuthContext.Provider>
  );
}

export function usePortalAuth() {
  const context = useContext(PortalAuthContext);
  if (context === undefined) {
    throw new Error('usePortalAuth must be used within a PortalAuthProvider');
  }
  return context;
}
