/**
 * Tenant-aware route helper for salon-based routing
 * 
 * All salon management routes must include the salon slug in the URL.
 * This helper ensures consistent slug-based routing throughout the application.
 * 
 * Usage:
 *   salonRoutes(slug).dashboard      -> '/em-cuts/dashboard'
 *   salonRoutes(slug).bookings      -> '/em-cuts/bookings'
 *   salonRoutes(slug).customers     -> '/em-cuts/customers'
 *   salonRoutes(null).dashboard     -> '/dashboard' (fallback for no slug)
 */

export interface SalonRoutes {
  dashboard: string;
  bookings: string;
  customers: string;
  staff: string;
  services: string;
  payments: string;
  settings: string;
  analytics: string;
  pulse: string;
  financeTeam: string;
}

export function salonRoutes(slug: string | null | undefined): SalonRoutes {
  const prefix = slug ? `/${slug}` : '';
  
  return {
    dashboard: `${prefix}/dashboard`,
    bookings: `${prefix}/bookings`,
    customers: `${prefix}/customers`,
    staff: `${prefix}/staff`,
    services: `${prefix}/services`,
    payments: `${prefix}/payments`,
    settings: `${prefix}/settings`,
    analytics: `${prefix}/analytics`,
    pulse: `${prefix}/pulse`,
    financeTeam: `${prefix}/finance/team`,
  };
}

/**
 * Public routes (not tenant-specific)
 */
export const publicRoutes = {
  login: '/login',
  register: '/register',
  booking: '/booking',
  portal: '/portal',
  specialistPortal: '/specialist-portal',
  discovery: '/discover',
} as const;

/**
 * Helper to check if a route is tenant-specific
 */
export function isTenantRoute(path: string): boolean {
  const tenantPaths = [
    '/dashboard',
    '/bookings',
    '/customers',
    '/staff',
    '/services',
    '/payments',
    '/settings',
    '/analytics',
    '/pulse',
  ];
  
  return tenantPaths.some(tenantPath => path.includes(tenantPath));
}

/**
 * Extract slug from a tenant route URL
 * Returns null if the route is not tenant-specific or slug cannot be extracted
 */
export function extractSlugFromPath(path: string): string | null {
  // Match pattern: /slug/tenant-route
  const match = path.match(/^\/([^\/]+)\/(dashboard|bookings|customers|staff|services|payments|settings|analytics|pulse)/);
  return match ? match[1] : null;
}
