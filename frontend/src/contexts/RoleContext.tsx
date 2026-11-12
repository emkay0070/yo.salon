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
  /** @deprecated Use providerId instead. Kept for backward compatibility during migration. */
  salonId: string | null;
  /** @deprecated Use setProviderId instead. Kept for backward compatibility during migration. */
  setSalonId: (id: string | null) => void;
  /** @deprecated Use providerSlug instead. Kept for backward compatibility during migration. */
  salonSlug: string | null;
  /** @deprecated Use activeProvider instead. Kept for backward compatibility during migration. */
  activeSalon: any | null;
  /** @deprecated Use setActiveProvider instead. Kept for backward compatibility during migration. */
  setActiveSalon: (salon: any | null) => void;
  /** Canonical: provider ID (tenant/business identity) */
  providerId: string | null;
  /** Canonical: setter for provider ID */
  setProviderId: (id: string | null) => void;
  /** Canonical: provider slug for URL routing */
  providerSlug: string | null;
  /** Canonical: active provider object with full provider data */
  activeProvider: any | null;
  /** Canonical: setter for active provider */
  setActiveProvider: (provider: any | null) => void;
  /** Canonical: active location object (currently selected location) */
  activeLocation: any | null;
  /** Canonical: setter for active location */
  setActiveLocation: (location: any | null) => void;
  /** Canonical: location ID (primary location of provider) */
  locationId: string | null;
  userStatus: UserStatus | null;
  nextRoute: string | null;
  currentStep: string | null;
  isLoading: boolean;
  /** Refresh the user session from the backend */
  refreshUser: () => Promise<void>;
  /** Specialists for the current provider */
  specialists: any[];
  /** Capabilities map for the active provider context */
  capabilities: Record<string, boolean> | null;
  /** Check if the current user has a specific capability in the active provider context */
  hasCapability: (action: string) => boolean;
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
  // Canonical provider context
  const [providerId, setProviderId] = useState<string | null>(null);
  const [providerSlug, setProviderSlug] = useState<string | null>(null);
  const [activeProvider, setActiveProvider] = useState<any | null>(null);
  // Location context (physical operating context)
  const [activeLocation, setActiveLocation] = useState<any | null>(null);
  const [locationId, setLocationId] = useState<string | null>(null);
  const [userStatus, setUserStatus] = useState<UserStatus | null>(null);
  const [nextRoute, setNextRoute] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [specialists, setSpecialists] = useState<any[]>([]);
  const [capabilities, setCapabilities] = useState<Record<string, boolean> | null>(null);

  const hasCapability = (action: string): boolean => {
    return capabilities ? !!capabilities[action] : false;
  };

  async function loadUser() {
    console.log('[RoleContext] loadUser called');
    console.log('[RoleContext] Current localStorage location_id:', localStorage.getItem('location_id'));
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
        // Role from canonical Provider relationship
        const pivotRole = data.user.providers?.[0]?.pivot?.role;
        
        // Map backend roles to frontend roles
        // Backend: staff, manager, receptionist, admin, owner
        // Frontend: employee, manager, receptionist, owner, owner
        const mappedRole: UserRole = pivotRole === 'staff' ? 'employee' :
                                      pivotRole === 'admin' ? 'owner' :
                                      pivotRole === 'owner' ? 'owner' :
                                      (pivotRole as UserRole) || 'owner';
        
        setRole(mappedRole);
        setUserName(data.user.name || data.user.email);
        setUser(data.user);

        console.log('[RoleContext] User providers:', data.user.providers);

        const firstProvider = data.user.providers?.[0];
        const rawProviderId = firstProvider?.id;
        const cleanId = sanitizeId(rawProviderId);
        // Set canonical provider context
        setProviderId(cleanId);
        setProviderSlug(firstProvider?.slug || null);
        setActiveProvider(firstProvider || null);

        // Load capabilities from provider_authorities for the current provider
        if (cleanId && data.provider_authorities) {
          const auth = data.provider_authorities.find((a: any) => a.provider_id === cleanId);
          if (auth && auth.capabilities) {
            setCapabilities(auth.capabilities);
          } else {
            setCapabilities(null);
          }
        } else {
          setCapabilities(null);
        }

        // Location context: validate persisted location belongs to active provider
        const persistedLocationId = localStorage.getItem('location_id');
        const primaryLocation = firstProvider?.primary_location;
        const locations = firstProvider?.locations || [];
        
        console.log('[RoleContext] Persisted location_id:', persistedLocationId);
        console.log('[RoleContext] Primary location:', primaryLocation);
        console.log('[RoleContext] Provider locations:', locations);
        
        let finalLocationId: string | null = null;
        
        // Validate persisted location belongs to current provider
        if (persistedLocationId && locations.length > 0) {
          const persistedLocation = locations.find((loc: any) => loc.id === persistedLocationId);
          if (persistedLocation) {
            // Valid persisted location
            finalLocationId = sanitizeId(persistedLocationId);
            setActiveLocation(persistedLocation);
            console.log('[RoleContext] Using valid persisted location:', persistedLocation);
          } else {
            // Invalid/stale location - clear and fall back to primary
            localStorage.removeItem('location_id');
            console.log('[RoleContext] Persisted location not in provider locations, cleared');
          }
        }
        
        // If no valid location, use primary_location (but allow NULL for Provider-wide contexts)
        if (!finalLocationId && primaryLocation?.id) {
          finalLocationId = sanitizeId(primaryLocation.id);
          setActiveLocation(primaryLocation);
          localStorage.setItem('location_id', primaryLocation.id);
          console.log('[RoleContext] Using primary location:', primaryLocation);
        } else if (!finalLocationId) {
          // No location available - Provider-wide context
          setActiveLocation(null);
          localStorage.removeItem('location_id');
          console.log('[RoleContext] No location available, Provider-wide context');
        }
        
        setLocationId(finalLocationId);

        console.log('[RoleContext] Provider ID:', cleanId);
        console.log('[RoleContext] Provider Slug:', firstProvider?.slug);
        console.log('[RoleContext] Location ID:', finalLocationId);
        console.log('[RoleContext] Full provider data:', firstProvider);

        if (rawProviderId && !cleanId) {
          console.warn('[RoleContext] Discarded invalid provider_id from API:', JSON.stringify(rawProviderId));
        }

        if (!cleanId && data.user.providers?.length > 0) {
          console.error('[RoleContext] No valid provider ID found from providers array:', data.user.providers);
        }

        if (!cleanId && data.user.providers?.length === 0) {
          console.warn('[RoleContext] User has no providers assigned - redirecting to provider creation');
          // User has no providers - redirect to provider creation or show no-provider state
          setNextRoute('/onboarding/create-provider');
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

  const safeSetProviderId = (value: string | null) => {
    const cleanId = sanitizeId(value);
    setProviderId(cleanId);
  };

  const safeSetActiveProvider = (provider: any | null) => {
    setActiveProvider(provider);
  };

  const safeSetActiveLocation = (location: any | null) => {
    setActiveLocation(location);
    if (location?.id) {
      localStorage.setItem('location_id', location.id);
      setLocationId(location.id);
    } else {
      localStorage.removeItem('location_id');
      setLocationId(null);
    }
  };


  return (
    <RoleContext.Provider value={{
      role, setRole,
      userName, setUserName,
      user,
      // Deprecated: kept for backward compatibility
      salonId: providerId,
      setSalonId: safeSetProviderId,
      salonSlug: providerSlug,
      activeSalon: activeProvider,
      setActiveSalon: safeSetActiveProvider,
      // Canonical provider context
      providerId,
      setProviderId: safeSetProviderId,
      providerSlug,
      activeProvider,
      setActiveProvider: safeSetActiveProvider,
      // Location context
      activeLocation,
      setActiveLocation: safeSetActiveLocation,
      locationId,
      userStatus,
      nextRoute,
      currentStep,
      isLoading,
      refreshUser: loadUser,
      specialists,
      capabilities,
      hasCapability,
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
