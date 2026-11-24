import BusinessProviders from '@/components/providers/BusinessProviders';

export default async function SlugBusinessLayout({
  children,
  params,
}: {
  children: React.ReactNode;
  params: Promise<{ slug: string }> | { slug: string };
}) {
  const resolvedParams = await params;
  return (
    <BusinessProviders slug={resolvedParams?.slug}>
      {children}
    </BusinessProviders>
  );
}
