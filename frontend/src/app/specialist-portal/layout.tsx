'use client';

import { SpecialistAuthProvider } from '@/contexts/SpecialistAuthContext';
import SpecialistLayout from '@/components/specialist/SpecialistLayout';
import { usePathname } from 'next/navigation';

export default function SpecialistPortalLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const isAuthPage = pathname.includes('/login') || pathname.includes('/register') || pathname.includes('/onboarding');

  // Extract provider_slug from URL if present
  const urlParts = pathname.split('/');
  const providerSlug = urlParts[1] && urlParts[1] !== 'specialist-portal' ? urlParts[1] : undefined;

  return (
    <SpecialistAuthProvider providerSlug={providerSlug}>
      {isAuthPage ? (
        children
      ) : (
        <SpecialistLayout providerSlug={providerSlug}>{children}</SpecialistLayout>
      )}
    </SpecialistAuthProvider>
  );
}
