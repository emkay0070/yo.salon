import { NextResponse } from 'next/server';
import type { NextRequest } from 'next/server';

export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - api (API routes)
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - images/ (public images folder)
     */
    '/((?!api|_next/static|_next/image|favicon.ico|images/).*)',
  ],
};

/**
 * Platform application routes — these belong to Yo.Salon the product,
 * not to any tenant. Even when accessed via a tenant subdomain or custom
 * domain, these routes must resolve to the existing src/app/* pages
 * WITHOUT being rewritten into /salons/[slug]/* (which doesn't exist).
 *
 * The tenant context (slug) is passed as a header so application pages
 * can still know which salon the user is operating under.
 */
const PLATFORM_ROUTES = [
  '/dashboard',
  '/bookings',
  '/booking',
  '/customers',
  '/services',
  '/staff',
  '/payments',
  '/pulse',
  '/analytics',
  '/settings',
  '/calendar',
  '/login',
  '/register',
  '/forgot-password',
  '/onboarding',
  '/invite',
  '/portal',
  '/specialist-portal',
  '/specialists',
  '/admin',
  '/welcome',
  '/book',
];

function isPlatformRoute(pathname: string): boolean {
  // Root is always a platform route
  if (pathname === '/') return true;
  return PLATFORM_ROUTES.some(
    (route) => pathname === route || pathname.startsWith(`${route}/`)
  );
}

export function middleware(req: NextRequest) {
  const url = req.nextUrl;

  // Get hostname of request (e.g. doe-beauty-palor.localhost:3000)
  const hostname = req.headers.get('host') || '';

  // Define our known platform domains
  const rootDomains = [
    process.env.NEXT_PUBLIC_ROOT_DOMAIN,
    'yosalon.vercel.app',
    'yo.salon',
    'localhost:3000',
  ].filter(Boolean) as string[];

  // Check if the current hostname is exactly one of our root domains
  const isRootDomain = rootDomains.some(
    (domain) => hostname === domain || hostname === `www.${domain}`
  );

  // Root domain — serve everything as-is
  if (isRootDomain) {
    return NextResponse.next();
  }

  // Determine if this is a tenant subdomain
  let tenantSlug: string | null = null;
  for (const domain of rootDomains) {
    if (hostname.endsWith(`.${domain}`)) {
      tenantSlug = hostname.replace(`.${domain}`, '');
      break;
    }
  }

  // Check for custom domain (not a subdomain of our root domains)
  const isCustomDomain = !rootDomains.some(
    (domain) => hostname.endsWith(`.${domain}`) || hostname === domain
  );

  // ---------------------------------------------------------
  // PLATFORM ROUTES: Never rewrite, regardless of hostname.
  // Just add the tenant context header if we know the slug.
  // ---------------------------------------------------------
  if (isPlatformRoute(url.pathname)) {
    const response = NextResponse.next();
    if (tenantSlug) {
      response.headers.set('x-tenant-slug', tenantSlug);
    }
    if (isCustomDomain) {
      response.headers.set('x-custom-domain', hostname);
    }
    return response;
  }

  // ---------------------------------------------------------
  // CUSTOM DOMAIN (non-platform route)
  // Pass the custom domain header for the page to handle.
  // ---------------------------------------------------------
  if (isCustomDomain) {
    const response = NextResponse.next();
    response.headers.set('x-custom-domain', hostname);
    return response;
  }

  // ---------------------------------------------------------
  // TENANT SUBDOMAIN (non-platform route)
  // Rewrite to the tenant public salon experience:
  //   freshcuts.localhost:3000/  → /salons/freshcuts
  //   freshcuts.localhost:3000/services → /salons/freshcuts/services
  // ---------------------------------------------------------
  if (tenantSlug && tenantSlug !== 'www') {
    const newPath = `/salons/${tenantSlug}${url.pathname}${url.search}`;
    return NextResponse.rewrite(new URL(newPath, req.url));
  }

  return NextResponse.next();
}
