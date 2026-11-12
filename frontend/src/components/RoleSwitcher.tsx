'use client';

import { useState } from 'react';
import { useRole } from '@/contexts/RoleContext';
import { useCategory } from '@/contexts/CategoryContext';
import { Settings, ChevronDown, Building2, MapPin, User } from 'lucide-react';

export default function RoleSwitcher() {
  const { 
    role, setRole, 
    userName, setUserName,
    activeProvider,
    activeLocation,
    setActiveLocation,
    locationId,
    providerId
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
          <div className="absolute right-0 top-full mt-2 w-80 bg-surface border border-border-light rounded-xl shadow-xl z-20 overflow-hidden">
            <div className="p-4 border-b border-border-light">
              <p className="text-text-secondary text-xs mb-2">Switch Context (Testing)</p>
              <input
                type="text"
                value={userName}
                onChange={(e) => setUserName(e.target.value)}
                placeholder="User name"
                className="w-full px-3 py-2 bg-background border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-gold"
              />
            </div>

            {/* Provider Section */}
            <div className="p-3 border-b border-border-light">
              <div className="flex items-center gap-2 mb-2">
                <Building2 className="w-4 h-4 text-gold" />
                <span className="text-sm font-medium text-text-primary">{activeProvider?.name || 'No Provider'}</span>
              </div>
            </div>

            {/* Location Section */}
            <div className="p-3 border-b border-border-light">
              <div className="flex items-center gap-2 mb-2">
                <MapPin className="w-4 h-4 text-gold" />
                <span className="text-xs text-text-secondary">Location</span>
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
                <span className="text-xs text-text-secondary">Role</span>
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
