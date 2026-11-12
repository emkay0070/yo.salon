import BusinessProviders from '@/components/providers/BusinessProviders';

export default function BusinessLayout({ children }: { children: React.ReactNode }) {
  return (
    <BusinessProviders>
      {children}
    </BusinessProviders>
  );
}
