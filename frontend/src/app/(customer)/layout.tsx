import CustomerProviders from '@/components/providers/CustomerProviders';

export default function CustomerLayout({ children }: { children: React.ReactNode }) {
  return (
    <CustomerProviders>
      {children}
    </CustomerProviders>
  );
}
