'use client';

import { usePathname } from 'next/navigation';
import Link from 'next/link';
import { motion } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useEffect, useRef } from 'react';

const tabs = [
  { name: 'Overview', href: '/analytics' },
  { name: 'Revenue', href: '/analytics/revenue' },
  { name: 'Customers', href: '/analytics/customers' },
  { name: 'Staff', href: '/analytics/staff' },
  { name: 'Services', href: '/analytics/services' },
  { name: 'Bookings', href: '/analytics/bookings' },
  { name: '⚡ Intelligence', href: '/analytics/intelligence' },
];

export default function AnalyticsLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const navRef = useRef<HTMLDivElement>(null);
  const activeTabRef = useRef<HTMLAnchorElement>(null);

  // Auto-scroll the active tab into view (controlled scroll)
  useEffect(() => {
    if (activeTabRef.current && navRef.current) {
      const nav = navRef.current;
      const tab = activeTabRef.current;
      const tabLeft = tab.offsetLeft;
      const tabWidth = tab.offsetWidth;
      const navWidth = nav.offsetWidth;
      const scrollLeft = tabLeft - navWidth / 2 + tabWidth / 2;
      nav.scrollTo({ left: scrollLeft, behavior: 'smooth' });
    }
  }, [pathname]);

  return (
    <DashboardLayout>
      <div className="max-w-7xl mx-auto space-y-4 sm:space-y-6 pb-20 overflow-x-hidden">
        {/* Header */}
        <div className="flex flex-col gap-1">
          <h1 className="text-xl sm:text-3xl font-semibold text-text-primary tracking-tight">Business Intelligence</h1>
          <p className="text-text-secondary text-xs sm:text-sm">Real-time pulse of your salon</p>
        </div>

        {/* Pill-style Tab Nav with controlled scroll */}
        <div className="relative">
          {/* Fade indicators for overflow hint */}
          <div className="pointer-events-none absolute left-0 top-0 bottom-0 w-6 bg-gradient-to-r from-background to-transparent z-10 sm:hidden" />
          <div className="pointer-events-none absolute right-0 top-0 bottom-0 w-6 bg-gradient-to-l from-background to-transparent z-10 sm:hidden" />

          <div
            ref={navRef}
            className="flex gap-1.5 overflow-x-auto scroll-smooth pb-1"
            style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
          >
            {tabs.map((tab) => {
              const isActive = pathname === tab.href || 
                (tab.href !== '/analytics' && pathname.startsWith(tab.href));
              return (
                <Link
                  key={tab.name}
                  href={tab.href}
                  ref={isActive ? activeTabRef : null}
                  className={`relative flex-shrink-0 px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium whitespace-nowrap rounded-full transition-all duration-200 ${
                    isActive
                      ? 'bg-gold text-obsidian shadow-[0_0_12px_rgba(255,215,0,0.4)]'
                      : 'text-text-secondary hover:text-text-primary hover:bg-card border border-transparent hover:border-border-light'
                  }`}
                >
                  {tab.name}
                  {isActive && (
                    <motion.span
                      layoutId="activeTabPill"
                      className="absolute inset-0 rounded-full bg-gold -z-10"
                      initial={false}
                      transition={{ type: 'spring', stiffness: 400, damping: 35 }}
                    />
                  )}
                </Link>
              );
            })}
          </div>
        </div>

        {/* Content */}
        {pathname.startsWith('/analytics/intelligence') ? (
          children
        ) : (
          <div className="bg-card rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 min-h-[400px] sm:min-h-[600px] border border-border-light shadow-2xl">
            {children}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
