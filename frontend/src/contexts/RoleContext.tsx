'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { apiClient } from '@/lib/api-client';

export type UserRole = 'owner' | 'manager' | 'employee' | 'receptionist' | 'platform_admin';
export type UserStatus = 'registered' | 'email_verified' | 'onboarding_started' | 'onboarding_completed' | 'active';

interface RoleContextType {
  role: UserRole;
  setRole: (role: UserRole) => void;
  userName: string;
  setUserName: (name: string) => void;
  user: any | null;
  /** @deprecated salon_id is now resolved server-side. Use currentSalonId derived from /me salons array. */
  salonId: string | null;
  setSalonId: (id: string | null) => void;
  salonSlug: string | null;
  /** Active salon object with full salon data */
  activeSalon: any | null;
  setActiveSalon: (salon: any | null) => void;
  userStatus: UserStatus | null;
  nextRoute: string | null;
  currentStep: string | null;
  isLoading: boolean;
  /** Refresh the user session from the backend */
  refreshUser: () => Promise<void>;
  /** Specialists for the current salon */
  specialists: any[];
}

const RoleContext = createContext<RoleContextType | undefined>(undefined);

const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function sanitizeId(value: any): string | null {
  if (value == null) return null;
  const str = String(value).trim();
  if (!str || str === 'null' || str === 'undefined' || str === '0') return null;
  if (!UUID_REGEX.test(str)) return null;
  return str;
}

export function RoleProvider({ children }: { children: ReactNode }) {
  console.log('[RoleProvider] Component mounted');
  const [role, setRole] = useState<UserRole>('owner');
  const [userName, setUserName] = useState('');
  const [user, setUser] = useState<any | null>(null);
  const [salonId, setSalonId] = useState<string | null>(null);
  const [salonSlug, setSalonSlug] = useState<string | null>(null);
  const [activeSalon, setActiveSalon] = useState<any | null>(null);
  const [userStatus, setUserStatus] = useState<UserStatus | null>(null);
  const [nextRoute, setNextRoute] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [specialists, setSpecialists] = useState<any[]>([]);

  async function loadUser() {
    console.log('[RoleContext] loadUser called');
    // Check if we're on the server
    if (typeof window === 'undefined') {
      console.log('[RoleContext] On server, skipping');
      setIsLoading(false);
      return;
    }

    const token = localStorage.getItem('auth_token');
    console.log('[RoleContext] Token exists:', !!token, 'Token value:', token?.substring(0, 20) + '...');
    if (!token) {
      console.log('[RoleContext] No token found, setting loading false');
      setIsLoading(false);
      return;
    }
    try {
      const data = await apiClient.getCurrentUser();
      console.log('[RoleContext] /auth/user response:', data);

      if (data?.user) {
        const pivotRole = data.user.salons?.[0]?.pivot?.role;
        setRole((pivotRole as UserRole) || 'owner');
        setUserName(data.user.name || data.user.email);
        setUser(data.user);

        console.log('[RoleContext] User salons:', data.user.salons);

        const firstSalon = data.user.salons?.[0];
        const rawSalonId = firstSalon?.id;
        const cleanId = sanitizeId(rawSalonId);
        setSalonId(cleanId);

        const rawSalonSlug = firstSalon?.slug;
        setSalonSlug(rawSalonSlug || null);

        // Set active salon as the full salon object
        setActiveSalon(firstSalon || null);

        console.log('[RoleContext] Salon ID:', cleanId);
        console.log('[RoleContext] Salon Slug:', rawSalonSlug);
        console.log('[RoleContext] Full salon data:', firstSalon);

        if (rawSalonId && !cleanId) {
          console.warn('[RoleContext] Discarded invalid salon_id from API:', JSON.stringify(rawSalonId));
        }

        if (!cleanId && data.user.salons?.length > 0) {
          console.error('[RoleContext] No valid salon ID found from salons array:', data.user.salons);
        }

        if (!cleanId && data.user.salons?.length === 0) {
          console.warn('[RoleContext] User has no salons assigned - redirecting to salon creation');
          // User has no salons - redirect to salon creation or show no-salon state
          setNextRoute('/onboarding/create-salon');
        }
      } else {
        console.error('[RoleContext] No user in response data:', data);
      }
      setUserStatus(data?.status ?? null);
      setNextRoute(data?.next_route ?? null);
      setCurrentStep(data?.current_step ?? null);
    } catch (error: any) {
      console.error('[RoleContext] Failed to load user profile:', error);
      localStorage.removeItem('auth_token');
      // Redirect to login on 401 error
      if (error?.response?.status === 401) {
        window.location.href = '/login';
      }
    } finally {
      console.log('[RoleContext] Setting loading false');
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadUser();
  }, []);

  const safeSetSalonId = (value: string | null) => {
    setSalonId(sanitizeId(value));
  };

  return (
    <RoleContext.Provider value={{
      role, setRole,
      userName, setUserName,
      user,
      salonId,
      setSalonId: safeSetSalonId,
      salonSlug,
      activeSalon,
      setActiveSalon,
      userStatus,
      nextRoute,
      currentStep,
      isLoading,
      refreshUser: loadUser,
      specialists,
    }}>
      {children}
    </RoleContext.Provider>
  );
}

export function useRole() {
  const context = useContext(RoleContext);
  if (context === undefined) {
    throw new Error('useRole must be used within a RoleProvider');
  }
  return context;
}
