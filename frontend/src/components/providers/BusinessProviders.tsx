'use client';

import { RoleProvider } from '@/contexts/RoleContext';
import { SidebarProvider } from '@/contexts/SidebarContext';
import { BrandProvider } from '@/contexts/BrandContext';
import { CategoryProvider } from '@/contexts/CategoryContext';

export default function BusinessProviders({ children }: { children: React.ReactNode }) {
  return (
    <RoleProvider>
      <SidebarProvider>
        <BrandProvider>
          <CategoryProvider>
            {children}
          </CategoryProvider>
        </BrandProvider>
      </SidebarProvider>
    </RoleProvider>
  );
}
