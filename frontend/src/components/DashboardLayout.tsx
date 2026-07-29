'use client';

import { useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import {
  LayoutDashboard,
  Calendar,
  Users,
  Settings,
  Menu,
  Scissors,
  TrendingUp,
  CreditCard,
  LogOut,
  X,
  UserCheck,
  MoreHorizontal,
  Activity,
  Brain
} from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import Link from 'next/link';
import RoleSwitcher from './RoleSwitcher';
import { useSidebar } from '@/contexts/SidebarContext';
import { useSalonBranding } from '@/hooks/useSalonBranding';

interface DashboardLayoutProps {
  children: React.ReactNode;
}

const navItems = [
  { icon: LayoutDashboard, label: 'Dashboard', href: '/dashboard' },
  { icon: Calendar, label: 'Bookings', href: '/bookings' },
  { icon: Users, label: 'Customers', href: '/customers' },
  { icon: Scissors, label: 'Services', href: '/services' },
  { icon: UserCheck, label: 'Staff', href: '/staff' },
  { icon: TrendingUp, label: 'Analytics', href: '/analytics' },
  { icon: Brain, label: 'Intelligence', href: '/analytics/intelligence' },
  { icon: CreditCard, label: 'Payments', href: '/payments' },
  { icon: Activity, label: 'Pulse', href: '/pulse' },
  { icon: Settings, label: 'Settings', href: '/settings' },
];

const mobileNavItems = [
  { icon: LayoutDashboard, label: 'Home', href: '/dashboard' },
  { icon: Calendar, label: 'Bookings', href: '/bookings' },
  { icon: Users, label: 'Clients', href: '/customers' },
  { icon: TrendingUp, label: 'Stats', href: '/analytics' },
];

export default function DashboardLayout({ children }: DashboardLayoutProps) {
  const pathname = usePathname();
  const router = useRouter();
  const { sidebarOpen, setSidebarOpen } = useSidebar();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const { salon, colors, isWhiteLabel } = useSalonBranding();

  const handleLogout = () => {
    localStorage.removeItem('auth_token');
    router.push('/login');
  };

  return (
    <div className="min-h-[100dvh] bg-background flex font-sans overflow-x-hidden relative">

      {/* Global background image texture – very subtle noir depth, hidden in light mode */}
      <div
        className="fixed inset-0 bg-cover bg-center pointer-events-none z-0"
        style={{ 
          backgroundImage: `url('${salon?.logo || '/images/salon-dark.jpg'}')`,
          opacity: 'var(--texture-opacity, 0.04)',
        }}
      />


      {/* Desktop Sidebar (hidden on mobile) */}
      <motion.aside
        initial={false}
        animate={{ 
          width: sidebarOpen ? 260 : 80,
        }}
        className={`hidden lg:flex fixed z-50 flex-col p-4 transition-all duration-300 h-screen overflow-hidden bg-surface/85 backdrop-blur-xl border-r border-border-light`}
        style={{
          WebkitBackdropFilter: 'blur(24px)',
        }}
      >
        <div className="flex items-center justify-between mb-6 overflow-hidden">
          <div className="flex items-center gap-3">
            {salon?.logo ? (
              <img 
                src={salon.logo} 
                alt={salon.name} 
                className="w-10 h-10 rounded-xl object-cover flex-shrink-0 shadow-lg"
              />
            ) : (
              <div 
                className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg"
                style={{ 
                  background: `linear-gradient(135deg, ${colors?.primary || '#FF622B'}, ${colors?.accent || '#FFD700'})` 
                }}
              >
                <Scissors className="w-6 h-6 text-white" />
              </div>
            )}
            {sidebarOpen && (
              <span className="text-xl font-bold text-text-primary tracking-tight whitespace-nowrap">
                {isWhiteLabel ? salon?.name : salon?.name}
              </span>
            )}
          </div>
        </div>

        {sidebarOpen && <RoleSwitcher />}

        <nav className="flex-1 space-y-1 overflow-y-auto mt-4 scrollbar-hide">
          {navItems.map((item) => {
            const isActive = pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 group"
                style={{
                  backgroundColor: isActive ? 'rgba(255,255,255,0.08)' : 'transparent',
                }}
              >
                <item.icon
                  className="w-5 h-5 flex-shrink-0 transition-colors duration-200"
                  style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                />
                {sidebarOpen && (
                  <span 
                    className="text-sm font-normal whitespace-nowrap transition-colors duration-200"
                    style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                  >
                    {item.label}
                  </span>
                )}
              </Link>
            );
          })}
        </nav>

        <div className="mt-auto space-y-2 pt-4 border-t border-border-light">
          <button onClick={handleLogout} className="w-full flex items-center gap-3 p-3 rounded-2xl hover:bg-terracotta/10 hover:text-terracotta transition-all duration-300 group">
            <LogOut className="w-6 h-6 text-text-secondary group-hover:text-terracotta flex-shrink-0" />
            {sidebarOpen && <span className="font-medium text-text-secondary group-hover:text-terracotta whitespace-nowrap">Logout</span>}
          </button>
        </div>
      </motion.aside>

      {/* Main Content Area */}
      <main
        className={`relative z-10 flex-1 min-w-0 overflow-x-hidden transition-all duration-300 pb-20 lg:pb-0 ${sidebarOpen ? 'lg:ml-[260px]' : 'lg:ml-[80px]'}`}
      >
        <div className="p-4 lg:p-8">
          <button
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className="hidden lg:flex mb-6 p-2 rounded-xl bg-surface/50 border border-border-light hover:bg-text-primary/10 transition-colors duration-300 backdrop-blur-md"
          >
            <Menu className="w-6 h-6 text-text-secondary" />
          </button>
          
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
        </div>
      </main>

      {/* Mobile Bottom Navigation */}
      <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface/80 backdrop-blur-xl border-t border-border-light px-2 pb-[env(safe-area-inset-bottom)]">
        <div className="flex items-center justify-between h-16">
          {mobileNavItems.map((item) => {
            const isActive = pathname.startsWith(item.href);
            return (
              <Link
                key={item.href}
                href={item.href}
                className="flex-1 flex flex-col items-center justify-center gap-1 h-full select-none"
              >
                <motion.div whileTap={{ scale: 0.9 }} className="flex flex-col items-center gap-1">
                  <item.icon 
                    className="w-5 h-5" 
                    style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                  />
                  <span 
                    className="text-[10px] font-normal"
                    style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                  >
                    {item.label}
                  </span>
                </motion.div>
              </Link>
            );
          })}
          <button
            onClick={() => setMobileMenuOpen(true)}
            className="flex-1 flex flex-col items-center justify-center gap-1 h-full select-none"
          >
            <motion.div whileTap={{ scale: 0.9 }} className="flex flex-col items-center gap-1">
              <MoreHorizontal className="w-5 h-5" style={{ color: '#9CA3AF' }} />
              <span className="text-[10px] font-normal" style={{ color: '#9CA3AF' }}>
                More
              </span>
            </motion.div>
          </button>
        </div>
      </nav>

      {/* Mobile "More" Sheet/Drawer */}
      <AnimatePresence>
        {mobileMenuOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setMobileMenuOpen(false)}
              className="fixed inset-0 bg-obsidian/60 backdrop-blur-sm z-50 lg:hidden"
            />
            <motion.div
              initial={{ y: '100%' }}
              animate={{ y: 0 }}
              exit={{ y: '100%' }}
              transition={{ type: "spring", damping: 25, stiffness: 200 }}
              className="fixed bottom-0 left-0 right-0 z-50 bg-surface border-t border-border-light rounded-t-[32px] p-6 lg:hidden max-h-[85vh] overflow-y-auto pb-[calc(24px+env(safe-area-inset-bottom))]"
            >
              <div className="flex justify-center mb-6">
                <div className="w-12 h-1.5 bg-border-medium rounded-full" />
              </div>
              
              <div className="mb-6">
                <RoleSwitcher />
              </div>

              <div className="grid grid-cols-4 gap-y-6 gap-x-2">
                {navItems.map((item) => {
                  const isActive = pathname.startsWith(item.href);
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      onClick={() => setMobileMenuOpen(false)}
                      className="flex flex-col items-center gap-2 select-none"
                    >
                      <motion.div 
                        whileTap={{ scale: 0.9 }}
                        className="w-14 h-14 rounded-xl flex items-center justify-center transition-colors"
                        style={{
                          backgroundColor: isActive ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.02)',
                        }}
                      >
                        <item.icon 
                          className="w-5 h-5" 
                          style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                        />
                      </motion.div>
                      <span 
                        className="text-[11px] font-normal text-center"
                        style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                      >
                        {item.label}
                      </span>
                    </Link>
                  );
                })}
              </div>

              <div className="mt-8 pt-6 border-t border-border-light">
                <button 
                  onClick={handleLogout}
                  className="w-full flex items-center justify-center gap-2 p-4 rounded-xl bg-terracotta/10 text-terracotta font-medium select-none"
                >
                  <LogOut className="w-5 h-5" />
                  Logout
                </button>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>

    </div>
  );
}
