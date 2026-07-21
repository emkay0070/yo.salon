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
  userStatus: UserStatus | null;
  nextRoute: string | null;
  currentStep: string | null;
  isLoading: boolean;
  /** Refresh the user session from the backend */
  refreshUser: () => Promise<void>;
}

const RoleContext = createContext<RoleContextType | undefined>(undefined);

export function RoleProvider({ children }: { children: ReactNode }) {
  const [role, setRole] = useState<UserRole>('owner');
  const [userName, setUserName] = useState('');
  const [user, setUser] = useState<any | null>(null);
  const [salonId, setSalonId] = useState<string | null>(null);
  const [userStatus, setUserStatus] = useState<UserStatus | null>(null);
  const [nextRoute, setNextRoute] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  async function loadUser() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      setIsLoading(false);
      return;
    }
    try {
      // /me returns { user, status, current_step, next_route }
      const data = await apiClient.getCurrentUser();
      if (data?.user) {
        // Role is determined by pivot record. Default to 'owner' for salon owners.
        const pivotRole = data.user.salons?.[0]?.pivot?.role;
        setRole((pivotRole as UserRole) || 'owner');
        setUserName(data.user.name || data.user.email);
        setUser(data.user);
        // First salon from the pivot table
        setSalonId(data.user.salons?.[0]?.id ?? null);
      }
      setUserStatus(data?.status ?? null);
      setNextRoute(data?.next_route ?? null);
      setCurrentStep(data?.current_step ?? null);
    } catch (error) {
      console.error('Failed to load user profile:', error);
      // 401 is handled by the axios interceptor which clears token + redirects
      localStorage.removeItem('auth_token');
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadUser();
  }, []);

  return (
    <RoleContext.Provider value={{
      role, setRole,
      userName, setUserName,
      user,
      salonId, setSalonId,
      userStatus,
      nextRoute,
      currentStep,
      isLoading,
      refreshUser: loadUser,
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
