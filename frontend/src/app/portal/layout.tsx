'use client';

import ClientLayout from '@/components/ClientLayout';

export default function PortalLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <ClientLayout>{children}</ClientLayout>;
}
