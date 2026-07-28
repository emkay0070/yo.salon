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

export function middleware(req: NextRequest) {
  const url = req.nextUrl;
  
  // Get hostname of request (e.g. doe-beauty-palor.localhost:3000)
  const hostname = req.headers.get('host') || '';
  
  // Define our known platform domains
  const rootDomains = [
    process.env.NEXT_PUBLIC_ROOT_DOMAIN, 
    'yosalon.vercel.app', 
    'yo.salon',
    'localhost:3000'
  ].filter(Boolean) as string[];

  // Check if the current hostname is exactly one of our root domains
  const isRootDomain = rootDomains.some(domain => hostname === domain || hostname === `www.${domain}`);

  // If it's a root domain, let it hit the platform pages (/login, /register, etc.)
  if (isRootDomain) {
    return NextResponse.next();
  }

  // Otherwise, extract the tenant slug (e.g. freshcuts.yosalon.vercel.app -> freshcuts)
  let subdomain = hostname;
  for (const domain of rootDomains) {
    if (hostname.endsWith(`.${domain}`)) {
      subdomain = hostname.replace(`.${domain}`, '');
      break;
    }
  }

  // If there is a valid subdomain, rewrite to the hidden /salons/[slug] route
  if (subdomain && subdomain !== 'www') {
    const newPath = `/salons/${subdomain}${url.pathname}${url.search}`;
    return NextResponse.rewrite(new URL(newPath, req.url));
  }

  return NextResponse.next();
}
