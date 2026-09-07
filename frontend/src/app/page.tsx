'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';
import WelcomePage from './welcome/page';

/**
 * Root page — Yo.Salon platform home.
 *
 * Visitors with no session see the landing page.
 * Authenticated users are redirected to wherever the backend tells them to go
 * (dashboard, onboarding, portal, etc.) via the `next_route` field on the
 * /auth/me response.
 */
export default function RootPage() {
  const router = useRouter();
  const [isCheckingAuth, setIsCheckingAuth] = useState(true);

  useEffect(() => {
    async function resolveRoute() {
      if (typeof window === 'undefined') return;
      const token = localStorage.getItem('auth_token');

      // No token — unauthenticated visitor, stay here (landing page)
      if (!token) {
        setIsCheckingAuth(false);
        return;
      }

      try {
        // Backend is the single source of truth — it tells us where to go
        const data = await apiClient.getCurrentUser();
        router.replace(data.next_route || '/dashboard');
      } catch {
        // Token is invalid / expired — clear it and show landing page
        localStorage.removeItem('auth_token');
        setIsCheckingAuth(false);
      }
    }

    resolveRoute();
  }, [router]);

  // Show a brief spinner while we check the backend for an active session
  if (isCheckingAuth) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#070707]">
        <div className="flex flex-col items-center gap-4">
          <div className="w-10 h-10 border-2 border-[#FFD700]/30 border-t-[#FFD700] rounded-full animate-spin" />
          <p className="text-white/30 text-sm">Authenticating…</p>
        </div>
      </div>
    );
  }

  // Unauthenticated users get the premium landing experience!
  return <WelcomePage />;
}
