'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useRouter } from 'next/navigation';

export default function WorkplaceCraftPage() {
  const { currentWorkplace, isLoading } = useSpecialistAuth();
  const router = useRouter();

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[50vh]">
        <div className="w-8 h-8 border-2 border-gold/40 border-t-gold rounded-full animate-spin" />
      </div>
    );
  }

  if (!currentWorkplace) {
    return (
      <div className="p-6 flex items-center justify-center min-h-[50vh]">
        <div className="text-center">
          <p className="text-text-secondary">Workplace not found or you don't have access.</p>
        </div>
      </div>
    );
  }

  // Redirect to the main Craft page for now (will be refactored later)
  router.push('/specialist-portal/craft');

  return null;
}
