'use client';

import { SpecialistAuthProvider } from '@/contexts/SpecialistAuthContext';

export default function SpecialistProviders({ children }: { children: React.ReactNode }) {
  return (
    <SpecialistAuthProvider>
      {children}
    </SpecialistAuthProvider>
  );
}
