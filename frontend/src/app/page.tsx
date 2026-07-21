'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    async function resolveRoute() {
      const token = localStorage.getItem('auth_token');

      if (!token) {
        router.replace('/welcome');
        return;
      }

      try {
        // Backend is the single source of truth — it tells us exactly where to go
        const data = await apiClient.getCurrentUser();
        router.replace(data.next_route || '/dashboard');
      } catch {
        // Token is invalid / expired — clear it and go to login
        localStorage.removeItem('auth_token');
        router.replace('/login');
      }
    }

    resolveRoute();
  }, [router]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#070707]">
      <div className="flex flex-col items-center gap-4">
        <div className="w-10 h-10 border-2 border-[#FFD700]/30 border-t-[#FFD700] rounded-full animate-spin" />
        <p className="text-white/30 text-sm">Loading...</p>
      </div>
    </div>
  );
}
