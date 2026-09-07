'use client';

import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';

interface Specialist {
  id: string;
  name: string;
  email: string;
  phone: string;
  handle: string;
  bio: string;
  specialties: string[];
  photo_url: string | null;
  rating: number;
  review_count: number;
}

/**
 * Represents a single Provider relationship for a Specialist.
 * A Specialist can have multiple of these (multi-provider architecture).
 * `can_manage_provider` and `can_create_services` are derived from role by the API.
 */
export interface Workplace {
  assignment_id: string;
  provider_id: string;
  provider_name: string;
  provider_type: string;
  provider_slug: string | null;
  provider_logo: string | null;
  salon_id: string | null;
  salon_name: string | null;
  role: string;             // 'OWNER' | 'MANAGER' | 'SPECIALIST' | 'TRAINEE' | etc.
  employment_type: string;  // 'EMPLOYEE' | 'FREELANCER' | 'CONTRACTOR'
  is_primary: boolean;
  can_manage_provider: boolean; // derived: role === OWNER | MANAGER
  can_create_services: boolean; // derived: role === OWNER | MANAGER
}

interface SpecialistAccount {
  id: string;
  email: string;
  phone: string;
  is_active: boolean;
  last_login_at: string;
  onboarding_completed_at?: string | null;
}

interface SpecialistAuthContextType {
  specialistAccount: SpecialistAccount | null;
  specialist: Specialist | null;
  /** All active Provider relationships for this Specialist. */
  workplaces: Workplace[];
  /** The primary workplace (OWNER context first, then is_primary, then first). */
  primaryWorkplace: Workplace | null;
  /** The current workplace from URL context. */
  currentWorkplace: Workplace | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}

const SpecialistAuthContext = createContext<SpecialistAuthContextType | undefined>(undefined);

export function SpecialistAuthProvider({ children, providerSlug }: { children: ReactNode; providerSlug?: string }) {
  const [specialistAccount, setSpecialistAccount] = useState<SpecialistAccount | null>(null);
  const [specialist, setSpecialist] = useState<Specialist | null>(null);
  const [workplaces, setWorkplaces] = useState<Workplace[]>([]);
  const [primaryWorkplace, setPrimaryWorkplace] = useState<Workplace | null>(null);
  const [currentWorkplace, setCurrentWorkplace] = useState<Workplace | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const refresh = useCallback(async () => {
    const token = localStorage.getItem('specialist_auth_token');
    if (token) {
      try {
        const url = providerSlug 
          ? `${process.env.NEXT_PUBLIC_API_URL}/v1/specialist-portal/context?provider_slug=${providerSlug}`
          : `${process.env.NEXT_PUBLIC_API_URL}/v1/specialist-portal/context`;
        
        const response = await fetch(url, {
          headers: {
            'Authorization': `Bearer ${token}`,
          },
        });
        
        if (response.ok) {
          const data = await response.json();
          setSpecialistAccount(data.specialist_account);
          setSpecialist(data.specialist);
          // Multi-provider: consume workplaces from the new context API
          setWorkplaces(data.workplaces ?? []);
          setPrimaryWorkplace(data.primary_workplace ?? null);
          setCurrentWorkplace(data.current_workplace ?? null);
        } else if (response.status === 403) {
          // Specialist doesn't have access to this workplace
          localStorage.removeItem('specialist_auth_token');
          setSpecialistAccount(null);
          setSpecialist(null);
          setWorkplaces([]);
          setPrimaryWorkplace(null);
          setCurrentWorkplace(null);
        } else {
          // Token invalid, clear it
          localStorage.removeItem('specialist_auth_token');
          setSpecialistAccount(null);
          setSpecialist(null);
        }
      } catch (error) {
        console.error('Failed to load specialist context:', error);
      }
    }
    setIsLoading(false);
  }, [providerSlug]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const login = async (email: string, password: string) => {
    const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/v1/specialist-portal/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Login failed');
    }

    const data = await response.json();
    localStorage.setItem('specialist_auth_token', data.token);
    setSpecialistAccount(data.account);
    setSpecialist(data.specialist);
    // Load workplaces after successful login
    await refresh();
  };

  const logout = async () => {
    const token = localStorage.getItem('specialist_auth_token');
    if (token) {
      try {
        await fetch(`${process.env.NEXT_PUBLIC_API_URL}/v1/specialist-portal/logout`, {
          method: 'POST',
          headers: {
            'Authorization': `Bearer ${token}`,
          },
        });
      } catch (error) {
        console.error('Logout error:', error);
      }
    }
    
    localStorage.removeItem('specialist_auth_token');
    setSpecialistAccount(null);
    setSpecialist(null);
    setWorkplaces([]);
    setPrimaryWorkplace(null);
  };

  return (
    <SpecialistAuthContext.Provider
      value={{
        specialistAccount,
        specialist,
        workplaces,
        primaryWorkplace,
        currentWorkplace,
        isAuthenticated: !!specialistAccount,
        isLoading,
        login,
        logout,
        refresh,
      }}
    >
      {children}
    </SpecialistAuthContext.Provider>
  );
}

export function useSpecialistAuth() {
  const context = useContext(SpecialistAuthContext);
  if (context === undefined) {
    throw new Error('useSpecialistAuth must be used within a SpecialistAuthProvider');
  }
  return context;
}
