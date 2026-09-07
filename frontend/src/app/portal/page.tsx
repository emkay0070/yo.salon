'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Loader2 } from 'lucide-react';

/**
 * /portal — redirect root to /portal/login
 * Prevents the [slug] wildcard from catching "/portal" and treating it as a salon slug.
 */
export default function PortalRootPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/portal/login');
  }, [router]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#070707]">
      <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
    </div>
  );
}
