import BusinessProviders from '@/components/providers/BusinessProviders';

export default function SlugBusinessLayout({ children }: { children: React.ReactNode }) {
  return (
    <BusinessProviders>
      {children}
    </BusinessProviders>
  );
}
