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
    setCustomer(data.customer);
    setSalon(data.active_salon);
    setSalons(data.salons || []);
    setPortalAccount(data.portal_account);
    setCapabilities(data.capabilities || {});
  }, []);

  const refresh = useCallback(async () => {
    const token = localStorage.getItem('portal_auth_token');
    if (token) {
      try {
        const data = await portalApiClient.get('/v1/portal/context');
        hydrateFromContext(data);
      } catch (error) {
        console.error("Failed to load portal user:", error);
        // If 401, apiClient interceptor will handle redirect
        localStorage.removeItem('portal_auth_token');
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
      const data = await portalApiClient.get('/v1/portal/context');
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
