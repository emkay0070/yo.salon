'use client';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { useState } from 'react';
import { ThemeProvider } from 'next-themes';
import { RoleProvider } from '@/contexts/RoleContext';
import { SidebarProvider } from '@/contexts/SidebarContext';
import { PortalAuthProvider } from '@/contexts/PortalAuthContext';
import { BrandProvider } from '@/contexts/BrandContext';

export default function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(() => new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 1000 * 60 * 5, // 5 minutes
        refetchOnWindowFocus: false,
      },
    },
  }));

  return (
    <QueryClientProvider client={queryClient}>
      <SidebarProvider>
        <RoleProvider>
          <ThemeProvider attribute="data-theme" defaultTheme="noir" enableSystem={false}>
            <BrandProvider>
              <PortalAuthProvider>
                {children}
              </PortalAuthProvider>
            </BrandProvider>
          </ThemeProvider>
        </RoleProvider>
      </SidebarProvider>
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  );
}
