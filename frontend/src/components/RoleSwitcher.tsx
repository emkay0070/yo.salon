'use client';

import { useState } from 'react';
import { useRole } from '@/contexts/RoleContext';
import { Settings, ChevronDown } from 'lucide-react';

export default function RoleSwitcher() {
  const { role, setRole, userName, setUserName } = useRole();
  const [isOpen, setIsOpen] = useState(false);

  const roles = [
    { value: 'owner', label: 'Salon Owner' },
    { value: 'manager', label: 'Manager' },
    { value: 'employee', label: 'Employee' },
    { value: 'receptionist', label: 'Receptionist' },
    { value: 'platform_admin', label: 'Platform Admin' },
  ] as const;

  const handleRoleChange = (newRole: typeof roles[number]['value']) => {
    setRole(newRole);
    setIsOpen(false);
  };

  const currentRoleLabel = roles.find(r => r.value === role)?.label || 'Owner';

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
          <div className="absolute right-0 top-full mt-2 w-64 bg-surface border border-border-light rounded-xl shadow-xl z-20 overflow-hidden">
            <div className="p-4 border-b border-border-light">
              <p className="text-text-secondary text-xs mb-2">Switch Role (Testing)</p>
              <input
                type="text"
                value={userName}
                onChange={(e) => setUserName(e.target.value)}
                placeholder="User name"
                className="w-full px-3 py-2 bg-background border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-gold"
              />
            </div>
            <div className="p-2">
              {roles.map((r) => (
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
        </>
      )}
    </div>
  );
}
