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
  
  // Get the main root domain (e.g. localhost:3000 or yosalon.app)
  const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000';

  // Extract the subdomain (if it exists)
  // For example: doe-beauty-palor.localhost:3000 -> doe-beauty-palor
  // For localhost:3000 -> ""
  const subdomain = hostname
    .replace(`.${rootDomain}`, '')
    .replace(rootDomain, '');

  // If there is a subdomain and it's not "www"
  if (subdomain && subdomain !== 'www') {
    // We rewrite the URL to point to our hidden /salons/[slug] route
    // e.g., if path is /book, we rewrite to /salons/doe-beauty-palor/book
    const newPath = `/salons/${subdomain}${url.pathname}${url.search}`;
    return NextResponse.rewrite(new URL(newPath, req.url));
  }

  // Otherwise, let the request pass through to the main domain
  return NextResponse.next();
}
