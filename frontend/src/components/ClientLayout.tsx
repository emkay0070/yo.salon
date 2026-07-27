'use client';

import { motion, AnimatePresence } from 'framer-motion';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { Wallet, Calendar, User, Scissors, LogOut, Home, Sparkles } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';

interface ClientLayoutProps {
  children: React.ReactNode;
}

const navItems = [
  { icon: Home, label: 'Home', href: '/portal/home' },
  { icon: Calendar, label: 'Bookings', href: '/portal/bookings' },
  { icon: Sparkles, label: 'Discover', href: '/portal/discover' },
  { icon: Wallet, label: 'Wallet', href: '/portal/wallet' },
  { icon: User, label: 'Profile', href: '/portal/profile' },
];

export default function ClientLayout({ children }: ClientLayoutProps) {
  const pathname = usePathname();
  const router = useRouter();
  const { customer, salon, logout, isLoading } = usePortalAuth();

  const handleLogout = async () => {
    await logout();
    router.push('/portal/login');
  };

  const isAuthPage = ['/portal/login', '/portal/create-account'].includes(pathname);

  if (isAuthPage) {
    return <main className="min-h-screen bg-[#070707] font-sans text-text-primary selection:bg-gold/30">{children}</main>;
  }

  if (isLoading) {
    return (
      <div className="min-h-[100dvh] bg-background flex items-center justify-center">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
    <div className="min-h-[100dvh] bg-background font-sans text-text-primary selection:bg-gold/30 flex flex-col overflow-x-hidden">
      {/* Top Navigation */}
      <header className="sticky top-0 z-50 bg-surface/80 backdrop-blur-2xl border-b border-border-light">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 h-16 sm:h-20 flex items-center justify-between">

          {/* Logo Area */}
          <Link href="/portal/home" className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center shadow-md">
              <Scissors className="w-5 h-5 text-obsidian" />
            </div>
            <div className="flex flex-col">
              <span className="text-xl font-bold tracking-tight text-text-primary">Yo Salon</span>
              {salon && (
                <span className="text-xs text-text-secondary">{salon.name}</span>
              )}
            </div>
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-1 bg-surface p-1 rounded-full border border-border-light shadow-sm">
            {navItems.map(item => {
              const isActive = pathname.startsWith(item.href);
              return (
                <Link
                  key={item.href}
                  href={item.href}
                  className={`flex items-center gap-2 px-5 py-2 rounded-full text-sm font-medium transition-all ${isActive
                      ? 'bg-gold/10 text-gold shadow-sm'
                      : 'text-text-secondary hover:text-text-primary hover:bg-text-primary/5'
                    }`}
                >
                  <item.icon className="w-4 h-4" />
                  {item.label}
                </Link>
              );
            })}
          </nav>

          {/* User / Actions */}
          <div className="hidden md:flex items-center gap-4">
            <button
              onClick={handleLogout}
              className="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-text-secondary hover:text-terracotta hover:bg-terracotta/10 transition-all"
            >
              <LogOut className="w-4 h-4" />
              Logout
            </button>
            <div className="w-10 h-10 rounded-full bg-surface border border-border-light flex items-center justify-center overflow-hidden hover:border-gold/30 transition-colors cursor-pointer shadow-sm">
              {/* Avatar Placeholder */}
              <User className="w-5 h-5 text-text-secondary" />
            </div>
          </div>

        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1 min-w-0 overflow-x-hidden max-w-6xl w-full mx-auto px-4 sm:px-6 py-8 pb-24 md:pb-12">
        <AnimatePresence mode="wait">
          <motion.div
            key={pathname}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
          >
            {children}
          </motion.div>
        </AnimatePresence>
      </main>

      {/* Mobile Bottom Navigation */}
      <nav className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface/80 backdrop-blur-xl border-t border-border-light px-2 pb-[env(safe-area-inset-bottom)]">
        <div className="flex items-center justify-around h-16">
          {navItems.map((item) => {
            const isActive = pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                className="flex-1 flex flex-col items-center justify-center gap-1 h-full select-none"
              >
                <motion.div whileTap={{ scale: 0.9 }} className="flex flex-col items-center gap-1">
                  <item.icon className={`w-6 h-6 ${isActive ? 'text-gold' : 'text-text-secondary'}`} />
                  <span className={`text-[10px] font-medium ${isActive ? 'text-gold' : 'text-text-secondary'}`}>
                    {item.label}
                  </span>
                </motion.div>
              </Link>
            );
          })}
        </div>
      </nav>
    </div>
  );
}
