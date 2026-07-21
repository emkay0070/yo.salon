'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { motion } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';

const tabs = [
  { name: 'Overview', href: '/analytics' },
  { name: 'Revenue', href: '/analytics/revenue' },
  { name: 'Customers', href: '/analytics/customers' },
  { name: 'Staff', href: '/analytics/staff' },
  { name: 'Services', href: '/analytics/services' },
  { name: 'Bookings', href: '/analytics/bookings' },
];

export default function AnalyticsLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();

  return (
    <DashboardLayout>
      <div className="max-w-7xl mx-auto space-y-6 pb-20 overflow-x-hidden">
        <div className="flex flex-col gap-4">
          <div>
            <h1 className="text-3xl font-semibold text-text-primary tracking-tight">Business Intelligence</h1>
            <p className="text-text-secondary text-sm mt-1">Real-time pulse of your salon</p>
          </div>
          
          <nav className="flex space-x-2 overflow-x-auto pb-2 border-b border-border-light hide-scrollbar">
            {tabs.map((tab) => {
              const isActive = pathname === tab.href;
              return (
                <Link
                  key={tab.name}
                  href={tab.href}
                  className={`relative px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors rounded-lg ${
                    isActive ? 'text-gold' : 'text-text-secondary hover:text-text-primary hover:bg-card'
                  }`}
                >
                  {tab.name}
                  {isActive && (
                    <motion.div
                      layoutId="activeTab"
                      className="absolute bottom-[-9px] left-0 right-0 h-[2px] bg-[#FFD700]"
                      initial={false}
                    />
                  )}
                </Link>
              );
            })}
          </nav>
        </div>
        
        <div className="bg-card rounded-3xl p-6 md:p-8 min-h-[600px] border border-border-light shadow-2xl">
          {children}
        </div>
      </div>
    </DashboardLayout>
  );
}
