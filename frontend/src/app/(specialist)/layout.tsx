import SpecialistProviders from '@/components/providers/SpecialistProviders';

export default function SpecialistLayout({ children }: { children: React.ReactNode }) {
  return (
    <SpecialistProviders>
      {children}
    </SpecialistProviders>
  );
}
