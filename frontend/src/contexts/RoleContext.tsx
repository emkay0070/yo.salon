'use client';

import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { useParams } from 'next/navigation';
import { apiClient } from '@/lib/api-client';

export type UserRole = 'owner' | 'manager' | 'employee' | 'receptionist' | 'platform_admin';
export type UserStatus = 'registered' | 'email_verified' | 'onboarding_started' | 'onboarding_completed' | 'active';

/**
 * Resolved provider type context returned by GET /v1/auth/user under
 * `provider_type_context` (Phase 2B). Produced exclusively by the backend's
 * ProviderTypeContext resolver from server-side provider selection — the
 * frontend must not guess or hardcode provider types.
 */
export interface ProviderTypeContextData {
  /** Resolved provider (tenant) ID, null when no provider in context */
  provider_id: string | null;
  /** Registered provider type key (salon | independent_specialist | spa | beauty_school | mobile_team), null when unresolved */
  provider_type: string | null;
  /** Registry definition for display purposes; null when unresolved or unsupported */
  provider_type_definition: {
    id: string;
    type: string;
    display_name: string;
    is_independent: boolean;
    is_owner_managed: boolean;
    is_active: boolean;
  } | null;
  is_independent: boolean;
  is_owner_managed: boolean;
  /** Explicit context error (missing provider / unsupported type); null when resolved */
  error: string | null;
}

/**
 * Commercial Organization Context data returned by GET /v1/auth/user
 * under `organization_context`.
 */
export interface OrganizationContextData {
  organization_id: string | null;
  name: string | null;
  slug: string | null;
  type: string | null;
  status: string | null;
  role: string | null;
  is_owner: boolean;
  is_admin: boolean;
  error: string | null;
}

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
  /** Error when requested provider slug is not authorized or not found */
  providerAccessError: string | null;
  /** Switch to another authorized provider by slug */
  switchProviderBySlug: (slug: string) => boolean;

  // Commercial Organization Context (governance layer)
  organizationId: string | null;
  setOrganizationId: (id: string | null) => void;
  organizationContext: OrganizationContextData | null;
  activeOrganization: any | null;
  organizations: any[];

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
  /** Resolved provider type context for the selected provider (server-resolved, never client-guessed) */
  providerTypeContext: ProviderTypeContextData | null;
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

export function RoleProvider({ children, initialSlug }: { children: ReactNode; initialSlug?: string }) {
  console.log('[RoleProvider] Component mounted');
  const params = useParams();
  const currentUrlSlug = initialSlug || (typeof params?.slug === 'string' ? params.slug : (Array.isArray(params?.slug) ? params.slug[0] : undefined));

  const [role, setRole] = useState<UserRole>('owner');
  const [userName, setUserName] = useState('');
  const [user, setUser] = useState<any | null>(null);
  
  // Canonical provider context
  const [providerId, setProviderId] = useState<string | null>(null);
  const [providerSlug, setProviderSlug] = useState<string | null>(null);
  const [activeProvider, setActiveProvider] = useState<any | null>(null);
  const [providerAccessError, setProviderAccessError] = useState<string | null>(null);

  // Commercial Organization context
  const [organizationId, setOrganizationIdState] = useState<string | null>(null);
  const [organizationContext, setOrganizationContext] = useState<OrganizationContextData | null>(null);
  const [activeOrganization, setActiveOrganization] = useState<any | null>(null);
  const [organizations, setOrganizations] = useState<any[]>([]);

  // Location context (physical operating context)
  const [activeLocation, setActiveLocation] = useState<any | null>(null);
  const [locationId, setLocationId] = useState<string | null>(null);
  const [userStatus, setUserStatus] = useState<UserStatus | null>(null);
  const [nextRoute, setNextRoute] = useState<string | null>(null);
  const [currentStep, setCurrentStep] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [specialists, setSpecialists] = useState<any[]>([]);
  const [capabilities, setCapabilities] = useState<Record<string, boolean> | null>(null);
  const [providerTypeContext, setProviderTypeContext] = useState<ProviderTypeContextData | null>(null);

  // Cached raw response data for dynamic resolution on slug changes
  const [rawAuthData, setRawAuthData] = useState<any | null>(null);

  const hasCapability = (action: string): boolean => {
    return capabilities ? !!capabilities[action] : false;
  };

  /**
   * Apply context for a specific provider safely:
   * 1. Confirms user authorization for this provider.
   * 2. Sets role & capabilities scoped strictly to this provider.
   * 3. Sets organization context matching this provider's parent organization.
   * 4. Validates location belongs to this provider, preventing cross-tenant leakage.
   */
  const applyProviderContext = useCallback((
    targetSlug: string | undefined,
    userData: any,
    authorities: any[],
    serverOrgContext: any
  ) => {
    const providers: any[] = userData?.providers || [];

    if (!providers || providers.length === 0) {
      console.warn('[RoleContext] User has no providers assigned');
      setProviderId(null);
      setProviderSlug(null);
      setActiveProvider(null);
      setProviderAccessError(null);
      setOrganizationIdState(null);
      setActiveOrganization(null);
      setActiveLocation(null);
      setLocationId(null);
      setCapabilities(null);
      localStorage.removeItem('provider_id');
      localStorage.removeItem('location_id');
      localStorage.removeItem('organization_id');
      return;
    }

    let selectedProvider: any = null;
    let accessError: string | null = null;

    if (targetSlug) {
      // Find matching provider by slug (case-insensitive)
      selectedProvider = providers.find(
        (p: any) => p.slug && p.slug.toLowerCase() === targetSlug.toLowerCase()
      );

      if (!selectedProvider) {
        console.warn(`[RoleContext] Access denied: User does not belong to provider slug "${targetSlug}"`);
        accessError = `You do not have access to the business "${targetSlug}", or it does not exist.`;
      }
    } else {
      // No slug requested in URL (e.g. root dashboard route)
      // Check if a previously selected valid provider exists in localStorage
      const storedProviderId = localStorage.getItem('provider_id');
      if (storedProviderId) {
        selectedProvider = providers.find((p: any) => p.id === storedProviderId) || null;
      }
      // Otherwise default to the first provider
      if (!selectedProvider) {
        selectedProvider = providers[0];
      }
    }

    if (accessError || !selectedProvider) {
      // Unauthorized or nonexistent slug requested
      setProviderId(null);
      setProviderSlug(targetSlug || null);
      setActiveProvider(null);
      setProviderAccessError(accessError);
      setCapabilities(null);
      setActiveLocation(null);
      setLocationId(null);
      setOrganizationIdState(null);
      setActiveOrganization(null);
      localStorage.removeItem('provider_id');
      localStorage.removeItem('location_id');
      localStorage.removeItem('organization_id');
      return;
    }

    // Provider resolved successfully
    const cleanProvId = sanitizeId(selectedProvider.id);
    setProviderId(cleanProvId);
    setProviderSlug(selectedProvider.slug);
    setActiveProvider(selectedProvider);
    setProviderAccessError(null);

    if (cleanProvId) {
      localStorage.setItem('provider_id', cleanProvId);
    } else {
      localStorage.removeItem('provider_id');
    }

    // Role mapping for this provider
    const pivotRole = selectedProvider.pivot?.role;
    const mappedRole: UserRole = pivotRole === 'staff' ? 'employee' :
                                  pivotRole === 'admin' ? 'owner' :
                                  pivotRole === 'owner' ? 'owner' :
                                  (pivotRole as UserRole) || 'owner';
    setRole(mappedRole);

    // Capabilities for this provider
    if (cleanProvId && authorities) {
      const auth = authorities.find((a: any) => a.provider_id === cleanProvId);
      setCapabilities(auth?.capabilities || null);
    } else {
      setCapabilities(null);
    }

    // Commercial Organization Context resolution
    const userOrgs = userData.organizations || [];
    const targetOrgId = sanitizeId(
      selectedProvider.organization_id ||
      selectedProvider.organization?.id ||
      serverOrgContext?.organization_id
    );

    if (targetOrgId) {
      setOrganizationIdState(targetOrgId);
      localStorage.setItem('organization_id', targetOrgId);

      const resolvedOrg = userOrgs.find((o: any) => o.id === targetOrgId) ||
        selectedProvider.organization ||
        (serverOrgContext?.organization_id === targetOrgId ? serverOrgContext : null);

      setActiveOrganization(resolvedOrg || null);
      if (serverOrgContext && serverOrgContext.organization_id === targetOrgId) {
        setOrganizationContext(serverOrgContext);
      } else if (resolvedOrg) {
        setOrganizationContext({
          organization_id: targetOrgId,
          name: resolvedOrg.name || null,
          slug: resolvedOrg.slug || null,
          type: resolvedOrg.type || null,
          status: resolvedOrg.status || null,
          role: resolvedOrg.pivot?.role || mappedRole,
          is_owner: resolvedOrg.pivot?.role === 'owner' || mappedRole === 'owner',
          is_admin: resolvedOrg.pivot?.role === 'admin' || mappedRole === 'owner',
          error: null,
        });
      }
    } else {
      setOrganizationIdState(null);
      setActiveOrganization(null);
      localStorage.removeItem('organization_id');
    }

    // Location Context resolution
    const persistedLocationId = localStorage.getItem('location_id');
    const primaryLocation = selectedProvider.primaryLocation || selectedProvider.primary_location;
    const locations = selectedProvider.locations || [];

    let finalLocationId: string | null = null;
    let finalLocationObj: any = null;

    // Validate if persisted location belongs to THIS provider
    if (persistedLocationId && locations.length > 0) {
      const matched = locations.find((loc: any) => loc.id === persistedLocationId);
      if (matched) {
        finalLocationId = sanitizeId(persistedLocationId);
        finalLocationObj = matched;
        console.log('[RoleContext] Using valid persisted location for provider:', matched);
      } else {
        localStorage.removeItem('location_id');
        console.log('[RoleContext] Stale location_id cleared (did not belong to active provider)');
      }
    }

    // Fall back to provider's primary location if no valid location was persisted
    if (!finalLocationId && primaryLocation?.id) {
      finalLocationId = sanitizeId(primaryLocation.id);
      finalLocationObj = primaryLocation;
      localStorage.setItem('location_id', primaryLocation.id);
      console.log('[RoleContext] Defaulting to primary location:', primaryLocation);
    } else if (!finalLocationId) {
      // Provider-wide context
      localStorage.removeItem('location_id');
      console.log('[RoleContext] No location selected, Provider-wide context');
    }

    setLocationId(finalLocationId);
    setActiveLocation(finalLocationObj);
  }, []);

  async function loadUser() {
    console.log('[RoleContext] loadUser called');
    if (typeof window === 'undefined') {
      setIsLoading(false);
      return;
    }

    const token = localStorage.getItem('auth_token');
    if (!token) {
      setIsLoading(false);
      return;
    }

    try {
      const data = await apiClient.getCurrentUser();
      console.log('[RoleContext] /auth/user response:', data);

      if (data?.user) {
        setUser(data.user);
        setUserName(data.user.name || data.user.email);
        setOrganizations(data.user.organizations || []);
        setRawAuthData(data);

        // Apply provider context based on current URL slug
        applyProviderContext(
          currentUrlSlug,
          data.user,
          data.provider_authorities || [],
          data.organization_context || null
        );

        if (!data.user.providers || data.user.providers.length === 0) {
          setNextRoute('/onboarding/create-provider');
        }
      } else {
        console.error('[RoleContext] No user in response data:', data);
      }

      setUserStatus(data?.status ?? null);
      setNextRoute(data?.next_route ?? null);
      setCurrentStep(data?.current_step ?? null);
      setProviderTypeContext(data?.provider_type_context ?? null);
    } catch (error: any) {
      console.error('[RoleContext] Failed to load user profile:', error);
      localStorage.removeItem('auth_token');
      if (error?.response?.status === 401) {
        window.location.href = '/login';
      }
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadUser();
  }, []);

  // When URL slug changes dynamically, update context without reloading user
  useEffect(() => {
    if (rawAuthData?.user && currentUrlSlug) {
      console.log('[RoleContext] URL slug changed to:', currentUrlSlug);
      applyProviderContext(
        currentUrlSlug,
        rawAuthData.user,
        rawAuthData.provider_authorities || [],
        rawAuthData.organization_context || null
      );
    }
  }, [currentUrlSlug, rawAuthData, applyProviderContext]);

  const safeSetProviderId = (value: string | null) => {
    const cleanId = sanitizeId(value);
    setProviderId(cleanId);
    if (cleanId) {
      localStorage.setItem('provider_id', cleanId);
    } else {
      localStorage.removeItem('provider_id');
    }
  };

  const safeSetActiveProvider = (provider: any | null) => {
    if (provider && rawAuthData?.user) {
      applyProviderContext(
        provider.slug,
        rawAuthData.user,
        rawAuthData.provider_authorities || [],
        rawAuthData.organization_context || null
      );
    } else {
      setActiveProvider(provider);
    }
  };

  const safeSetOrganizationId = (value: string | null) => {
    const cleanId = sanitizeId(value);
    setOrganizationIdState(cleanId);
    if (cleanId) {
      localStorage.setItem('organization_id', cleanId);
    } else {
      localStorage.removeItem('organization_id');
    }
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

  const switchProviderBySlug = useCallback((slug: string): boolean => {
    if (!rawAuthData?.user?.providers) return false;
    const found = rawAuthData.user.providers.find(
      (p: any) => p.slug && p.slug.toLowerCase() === slug.toLowerCase()
    );
    if (found) {
      applyProviderContext(
        found.slug,
        rawAuthData.user,
        rawAuthData.provider_authorities || [],
        rawAuthData.organization_context || null
      );
      return true;
    }
    return false;
  }, [rawAuthData, applyProviderContext]);

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
      providerAccessError,
      switchProviderBySlug,
      // Commercial Organization context
      organizationId,
      setOrganizationId: safeSetOrganizationId,
      organizationContext,
      activeOrganization,
      organizations,
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
      providerTypeContext,
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
