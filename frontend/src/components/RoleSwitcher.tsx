'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useRole } from '@/contexts/RoleContext';
import { useCategory } from '@/contexts/CategoryContext';
import { Settings, ChevronDown, Building2, MapPin, User, ShieldCheck, Check } from 'lucide-react';

export default function RoleSwitcher() {
  const router = useRouter();
  const { 
    role, setRole, 
    activeProvider,
    setActiveLocation,
    locationId,
    user,
    activeOrganization,
  } = useRole();
  const { terminology } = useCategory();
  const [isOpen, setIsOpen] = useState(false);

  const providerRoles = [
    { value: 'owner', label: `${terminology.business} Owner` },
    { value: 'manager', label: 'Manager' },
    { value: 'employee', label: 'Employee' },
    { value: 'receptionist', label: 'Receptionist' },
  ] as const;

  const handleLocationChange = (location: any) => {
    setActiveLocation(location);
    setIsOpen(false);
  };

  const handleProviderSwitch = (slug: string) => {
    setIsOpen(false);
    router.push(`/${slug}/dashboard`);
  };

  const handleRoleChange = (newRole: typeof providerRoles[number]['value']) => {
    setRole(newRole);
    setIsOpen(false);
  };

  const handleProviderWide = () => {
    setActiveLocation(null);
    setIsOpen(false);
  };

  const currentRoleLabel = providerRoles.find(r => r.value === role)?.label || 'Owner';
  const locations = activeProvider?.locations || [];
  const userProviders: any[] = user?.providers || [];

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2 bg-surface border border-border-light rounded-xl text-text-primary hover:bg-border-light transition-all"
      >
        <Settings className="w-4 h-4 text-gold" />
        <span className="text-sm">{currentRoleLabel}</span>
        <ChevronDown className={`w-4 h-4 text-text-secondary transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <>
          <div
            className="fixed inset-0 z-10"
            onClick={() => setIsOpen(false)}
          />
          <div className="absolute right-0 top-full mt-2 w-80 bg-surface border border-border-light rounded-xl shadow-xl z-20 overflow-hidden max-h-[85vh] overflow-y-auto">
            {/* Organization Section */}
            {activeOrganization && (
              <div className="p-3 border-b border-border-light bg-white/[0.02]">
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0" />
                  <div className="min-w-0">
                    <p className="text-[10px] uppercase font-semibold text-text-secondary tracking-wider">Organization</p>
                    <p className="text-xs font-medium text-text-primary truncate">{activeOrganization.name || 'Commercial Org'}</p>
                  </div>
                </div>
              </div>
            )}

            {/* Provider / Business Switcher Section */}
            <div className="p-3 border-b border-border-light">
              <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2">
                  <Building2 className="w-4 h-4 text-gold" />
                  <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">Business</span>
                </div>
                {userProviders.length > 1 && (
                  <span className="text-[10px] text-gold font-medium">{userProviders.length} available</span>
                )}
              </div>

              {userProviders.length > 1 ? (
                <div className="space-y-1">
                  {userProviders.map((p: any) => {
                    const isCurrent = activeProvider?.id === p.id;
                    return (
                      <button
                        key={p.id}
                        onClick={() => handleProviderSwitch(p.slug)}
                        className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all ${
                          isCurrent
                            ? 'bg-gold/15 text-gold font-medium'
                            : 'text-text-primary hover:bg-border-light'
                        }`}
                      >
                        <span className="truncate">{p.name || p.display_name}</span>
                        {isCurrent && <Check className="w-3.5 h-3.5 text-gold shrink-0 ml-2" />}
                      </button>
                    );
                  })}
                </div>
              ) : (
                <p className="text-sm font-medium text-text-primary px-1">{activeProvider?.name || activeProvider?.display_name || 'No Provider'}</p>
              )}
            </div>

            {/* Location Section */}
            <div className="p-3 border-b border-border-light">
              <div className="flex items-center gap-2 mb-2">
                <MapPin className="w-4 h-4 text-gold" />
                <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">Location</span>
              </div>
              <div className="space-y-1">
                <button
                  onClick={handleProviderWide}
                  className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-all ${
                    !locationId
                      ? 'bg-gold/20 text-gold font-medium'
                      : 'text-text-primary hover:bg-border-light'
                  }`}
                >
                  All Locations (Provider-wide)
                </button>
                {locations.map((loc: any) => (
                  <button
                    key={loc.id}
                    onClick={() => handleLocationChange(loc)}
                    className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-all ${
                      locationId === loc.id
                        ? 'bg-gold/20 text-gold font-medium'
                        : 'text-text-primary hover:bg-border-light'
                    }`}
                  >
                    {loc.name}
                  </button>
                ))}
              </div>
            </div>

            {/* Role Section */}
            <div className="p-3">
              <div className="flex items-center gap-2 mb-2">
                <User className="w-4 h-4 text-gold" />
                <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">Role</span>
              </div>
              <div className="space-y-1">
                {providerRoles.map((r) => (
                  <button
                    key={r.value}
                    onClick={() => handleRoleChange(r.value)}
                    className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-all ${
                      role === r.value
                        ? 'bg-gold/20 text-gold font-medium'
                        : 'text-text-primary hover:bg-border-light'
                    }`}
                  >
                    {r.label}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
