'use client';

import { SpecialistAuthProvider } from '@/contexts/SpecialistAuthContext';
import SpecialistLayout from '@/components/specialist/SpecialistLayout';
import { usePathname } from 'next/navigation';
import { useParams } from 'next/navigation';

export default function WorkplaceSpecialistLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();
  const params = useParams();
  const slug = params.slug as string;
  
  const isAuthPage = pathname.includes('/login') || pathname.includes('/register') || pathname.includes('/onboarding');

  return (
    <SpecialistAuthProvider providerSlug={slug}>
      {isAuthPage ? (
        children
      ) : (
        <SpecialistLayout providerSlug={slug}>{children}</SpecialistLayout>
      )}
    </SpecialistAuthProvider>
  );
}
