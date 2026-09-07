'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useRouter, usePathname } from 'next/navigation';
import { useState, useEffect } from 'react';
import { 
  LayoutDashboard, 
  Calendar, 
  CalendarClock, 
  Users, 
  Briefcase, 
  DollarSign, 
  User, 
  Settings,
  LogOut,
  Menu,
  X,
  Scissors,
  TrendingUp,
  MoreHorizontal,
  Building2,
  Sparkles
} from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import Link from 'next/link';
import WorkplaceSwitcher from './WorkplaceSwitcher';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

export default function SpecialistLayout({ children, providerSlug }: { children: React.ReactNode; providerSlug?: string }) {
  const { specialist, specialistAccount, logout, isLoading, currentWorkplace, workplaces } = useSpecialistAuth();
  const router = useRouter();
  const pathname = usePathname();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const { data: capabilities } = useQuery({
    queryKey: ['specialist-capabilities'],
    queryFn: () => apiClient.getSpecialistCapabilities(),
    enabled: !!specialist,
  });

  // Pro-only features
  const proFeatures = ['Career', 'Intelligence', 'Finance', 'Journey'];

  // Build contextual navigation based on whether we're in a workplace environment
  const navigation = providerSlug ? [
    { name: 'Workspace', href: `/${providerSlug}/specialist`, icon: LayoutDashboard },
    { name: 'Calendar', href: `/${providerSlug}/specialist/calendar`, icon: Calendar },
    { name: 'Appointments', href: `/${providerSlug}/specialist/appointments`, icon: CalendarClock },
    { name: 'Clients', href: `/${providerSlug}/specialist/clients`, icon: Users },
    { name: 'Craft', href: `/${providerSlug}/specialist/craft`, icon: Scissors },
    { name: 'Career', href: `/${providerSlug}/specialist/career`, icon: Briefcase },
    { name: 'Intelligence', href: `/${providerSlug}/specialist/intelligence`, icon: TrendingUp },
    { name: 'Finance', href: `/${providerSlug}/specialist/finance`, icon: DollarSign },
    { name: 'Journey', href: `/${providerSlug}/specialist/journey`, icon: Calendar },
    { name: 'Profile', href: `/${providerSlug}/specialist/profile`, icon: User },
    { name: 'Settings', href: `/${providerSlug}/specialist/settings`, icon: Settings },
  ] : [
    { name: 'Workspace', href: '/specialist-portal/workspace', icon: LayoutDashboard },
    { name: 'Calendar', href: '/specialist-portal/calendar', icon: Calendar },
    { name: 'Appointments', href: '/specialist-portal/appointments', icon: CalendarClock },
    { name: 'Clients', href: '/specialist-portal/clients', icon: Users },
    { name: 'Craft', href: '/specialist-portal/craft', icon: Scissors },
    { name: 'Career', href: '/specialist-portal/career', icon: Briefcase },
    { name: 'Intelligence', href: '/specialist-portal/intelligence', icon: TrendingUp },
    { name: 'Finance', href: '/specialist-portal/finance', icon: DollarSign },
    { name: 'Journey', href: '/specialist-portal/journey', icon: Calendar },
    { name: 'Profile', href: '/specialist-portal/profile', icon: User },
    { name: 'Settings', href: '/specialist-portal/settings', icon: Settings },
  ];

  const mobileNavItems = providerSlug ? [
    { label: 'Home', href: `/${providerSlug}/specialist`, icon: LayoutDashboard },
    { label: 'Calendar', href: `/${providerSlug}/specialist/calendar`, icon: Calendar },
    { label: 'Clients', href: `/${providerSlug}/specialist/clients`, icon: Users },
    { label: 'Craft', href: `/${providerSlug}/specialist/craft`, icon: Scissors },
  ] : [
    { label: 'Home', href: '/specialist-portal/workspace', icon: LayoutDashboard },
    { label: 'Calendar', href: '/specialist-portal/calendar', icon: Calendar },
    { label: 'Clients', href: '/specialist-portal/clients', icon: Users },
    { label: 'Craft', href: '/specialist-portal/craft', icon: Scissors },
  ];

  useEffect(() => {
    if (!isLoading) {
      if (!specialist) {
        router.push('/specialist-portal/login');
      } else if (specialistAccount && !specialistAccount.onboarding_completed_at) {
        router.push('/specialist-portal/onboarding');
      }
    }
  }, [isLoading, specialist, specialistAccount, router]);

  if (isLoading || !specialist || (specialistAccount && !specialistAccount.onboarding_completed_at)) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="w-10 h-10 border-4 border-gold border-t-transparent rounded-full animate-spin"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background flex font-sans overflow-x-hidden relative">
      {/* Mobile sidebar backdrop (no longer used since we use bottom nav, but keeping logic just in case) */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed top-0 left-0 z-50 h-full bg-card border-r border-border-light transition-all duration-300 ease-in-out lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        } ${sidebarCollapsed ? 'w-16' : 'w-64'}`}
      >
        <div className="flex flex-col h-full">
          {/* Logo */}
          <div className="group relative flex items-center px-4 h-16 border-b border-border-light">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center shrink-0 mx-auto lg:mx-0">
              <Scissors className="w-5 h-5 text-black" />
            </div>
            <div className={`min-w-0 ml-3 ${sidebarCollapsed ? 'hidden' : ''}`}>
              <h1 className="font-bold text-lg text-text-primary truncate">yo.salon</h1>
              <p className="text-xs text-text-secondary truncate">Specialist OS</p>
            </div>
            {/* Toggle button - hidden when collapsed, shows on logo hover */}
            <button
              onClick={() => setSidebarCollapsed(!sidebarCollapsed)}
              className={`hidden lg:flex w-10 h-10 rounded-lg hover:bg-surface text-text-secondary shrink-0 transition-opacity items-center justify-center ${
                sidebarCollapsed ? 'opacity-0 group-hover:opacity-100 absolute right-2' : 'opacity-100'
              }`}
              title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            >
              <Menu className="w-5 h-5" />
            </button>
            <button
              onClick={() => setSidebarOpen(false)}
              className="lg:hidden ml-auto p-2 rounded-lg hover:bg-surface text-text-secondary shrink-0"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Navigation */}
          <nav className="flex-1 overflow-y-auto p-4 space-y-1">
            {navigation.map((item) => {
              const Icon = item.icon;
              const isProFeature = proFeatures.includes(item.name);
              const hasProAccess = capabilities?.plan?.slug === 'specialist-pro';
              
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors text-text-secondary hover:text-text-primary hover:bg-surface justify-center lg:justify-start"
                  onClick={() => setSidebarOpen(false)}
                  title={sidebarCollapsed ? item.name : undefined}
                >
                  <Icon className="w-5 h-5 shrink-0" />
                  {!sidebarCollapsed && (
                    <div className="flex items-center gap-2 flex-1">
                      <span>{item.name}</span>
                      {isProFeature && !hasProAccess && (
                        <span className="px-1.5 py-0.5 bg-gold/20 text-gold text-[10px] font-bold rounded-full uppercase tracking-wider">
                          Pro
                        </span>
                      )}
                    </div>
                  )}
                </Link>
              );
            })}
          </nav>

          {/* Logout */}
          <div className="p-4 border-t border-border-light">
            <button
              onClick={logout}
              className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-colors text-text-secondary hover:text-text-primary hover:bg-surface w-full justify-center lg:justify-start"
              title={sidebarCollapsed ? 'Logout' : undefined}
            >
              <LogOut className="w-5 h-5 shrink-0" />
              {!sidebarCollapsed && <span>Logout</span>}
            </button>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <main className={`relative z-10 flex-1 min-w-0 overflow-x-hidden transition-all duration-300 pb-20 lg:pb-0 ${sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'}`}>
        {/* Top bar */}
        <header className="sticky top-0 z-30 bg-card/80 backdrop-blur-lg border-b border-border-light">
          <div className="flex items-center justify-between px-4 sm:px-6 lg:px-8 h-16">
            <div className="flex items-center gap-4">
              {providerSlug && currentWorkplace ? (
                <>
                  {currentWorkplace.provider_logo ? (
                    <img 
                      src={currentWorkplace.provider_logo} 
                      alt={currentWorkplace.provider_name} 
                      className="w-8 h-8 rounded-lg object-cover"
                    />
                  ) : (
                    <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center">
                      <Building2 className="w-4 h-4 text-black" />
                    </div>
                  )}
                  <div>
                    <h2 className="text-lg font-semibold text-text-primary">{currentWorkplace.provider_name}</h2>
                    <p className="text-xs text-text-muted capitalize">{currentWorkplace.role}</p>
                  </div>
                  {workplaces.length > 1 && <WorkplaceSwitcher />}
                </>
              ) : (
                <>
                  <h2 className="text-lg font-semibold text-text-primary">Workspace</h2>
                  {workplaces.length > 1 && <WorkplaceSwitcher />}
                </>
              )}
            </div>
            <div className="flex items-center gap-4">
              <button className="p-2 rounded-lg hover:bg-surface text-text-secondary relative">
                <div className="w-5 h-5" />
                <span className="absolute top-1 right-1 w-2 h-2 bg-gold rounded-full"></span>
              </button>
              <div className="flex items-center gap-3">
                <div className="hidden sm:block text-right">
                  <p className="text-sm font-medium text-text-primary">{specialist.name}</p>
                  <p className="text-xs text-text-secondary">Specialist</p>
                </div>
                {specialist.photo_url ? (
                  <img 
                    src={specialist.photo_url} 
                    alt={specialist.name} 
                    className="w-10 h-10 rounded-full object-cover border-2 border-gold"
                  />
                ) : (
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold">
                    {specialist.name.charAt(0)}
                  </div>
                )}
              </div>
            </div>
          </div>
        </header>

        {/* Page content */}
        <div className="min-h-screen">{children}</div>
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

              <div className="grid grid-cols-4 gap-y-6 gap-x-2">
                {navigation.map((item) => {
                  const isActive = pathname.startsWith(item.href);
                  const isProFeature = proFeatures.includes(item.name);
                  const hasProAccess = capabilities?.plan?.slug === 'specialist-pro';
                  
                  return (
                    <Link
                      key={item.href}
                      href={item.href}
                      onClick={() => setMobileMenuOpen(false)}
                      className="flex flex-col items-center gap-2 select-none"
                    >
                      <motion.div 
                        whileTap={{ scale: 0.9 }}
                        className="w-14 h-14 rounded-xl flex items-center justify-center transition-colors relative"
                        style={{
                          backgroundColor: isActive ? 'rgba(255,255,255,0.08)' : 'rgba(255,255,255,0.02)',
                        }}
                      >
                        <item.icon 
                          className="w-5 h-5" 
                          style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                        />
                        {isProFeature && !hasProAccess && (
                          <span className="absolute -top-1 -right-1 px-1.5 py-0.5 bg-gold/20 text-gold text-[8px] font-bold rounded-full uppercase tracking-wider">
                            Pro
                          </span>
                        )}
                      </motion.div>
                      <span 
                        className="text-[11px] font-normal text-center"
                        style={{ color: isActive ? '#FFFFFF' : '#9CA3AF' }}
                      >
                        {item.name}
                      </span>
                    </Link>
                  );
                })}
              </div>

              <div className="mt-8 pt-6 border-t border-border-light">
                <button 
                  onClick={logout}
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
