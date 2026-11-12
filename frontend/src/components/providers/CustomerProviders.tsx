'use client';

import { PortalAuthProvider } from '@/contexts/PortalAuthContext';
import { PortalBrandProvider } from '@/contexts/PortalBrandContext';
import { CategoryProvider } from '@/contexts/CategoryContext';

export default function CustomerProviders({ children }: { children: React.ReactNode }) {
  return (
    <PortalAuthProvider>
      <PortalBrandProvider>
        <CategoryProvider>
          {children}
        </CategoryProvider>
      </PortalBrandProvider>
    </PortalAuthProvider>
  );
}
