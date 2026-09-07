'use client';

import { useState, useRef, useEffect } from 'react';
import { useSpecialistAuth, Workplace } from '@/contexts/SpecialistAuthContext';
import { ChevronDown, Building2, Crown, Briefcase, User, UserCircle } from 'lucide-react';
import { useRouter, useParams } from 'next/navigation';

export default function WorkplaceSwitcher() {
  const { specialist, workplaces, currentWorkplace } = useSpecialistAuth();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const params = useParams();
  const currentSlug = params['provider-slug'] as string;

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const getRoleIcon = (role: string, employmentType?: string) => {
    const roleUpper = role.toUpperCase();
    const typeUpper = employmentType?.toUpperCase();

    // Independent specialist (no salon)
    if (!currentWorkplace) {
      return <UserCircle className="w-4 h-4 text-emerald-400" />;
    }

    switch (roleUpper) {
      case 'OWNER':
        return <Crown className="w-4 h-4 text-gold" />;
      case 'MANAGER':
        return <Building2 className="w-4 h-4 text-gold" />;
      case 'EMPLOYEE':
        return <Briefcase className="w-4 h-4 text-text-muted" />;
      case 'CONTRACTOR':
        return <User className="w-4 h-4 text-text-muted" />;
      default:
        return <User className="w-4 h-4 text-text-muted" />;
    }
  };

  const getRoleLabel = (role: string, employmentType?: string) => {
    const roleUpper = role.toUpperCase();
    const typeUpper = employmentType?.toUpperCase();

    // Independent specialist (no salon)
    if (!currentWorkplace) {
      return 'Independent';
    }

    switch (roleUpper) {
      case 'OWNER':
        return 'Owner';
      case 'MANAGER':
        return 'Manager';
      case 'EMPLOYEE':
        return typeUpper === 'FREELANCER' ? 'Freelancer' : typeUpper === 'CONTRACTOR' ? 'Contractor' : 'Employee';
      case 'CONTRACTOR':
        return 'Contractor';
      default:
        return role;
    }
  };

  const handleSwitchWorkplace = (workplace: Workplace) => {
    setIsOpen(false);
    const currentPath = window.location.pathname;
    const newPath = currentPath.replace(/^\/[^\/]+/, `/${workplace.provider_slug}`);
    router.push(newPath);
  };

  const handleGoToPersonal = () => {
    setIsOpen(false);
    router.push('/specialist-portal/workspace');
  };

  const getCurrentLabel = () => {
    if (currentWorkplace) {
      return currentWorkplace.provider_name;
    }
    return 'Independent Practice';
  };

  const getCurrentIcon = () => {
    if (currentWorkplace) {
      return getRoleIcon(currentWorkplace.role, currentWorkplace.employment_type);
    }
    return getRoleIcon('SPECIALIST', 'INDEPENDENT');
  };

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3 py-2 rounded-lg bg-surface border border-border-light hover:border-gold/60 transition-colors"
      >
        {getCurrentIcon()}
        <span className="text-sm font-medium text-text-primary">{getCurrentLabel()}</span>
        {currentWorkplace && (
          <span className="text-xs text-text-muted capitalize">
            {getRoleLabel(currentWorkplace.role, currentWorkplace.employment_type)}
          </span>
        )}
        <ChevronDown className={`w-4 h-4 text-text-muted transition-transform ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute top-full right-0 mt-2 w-72 bg-card border border-border-light rounded-xl shadow-2xl z-50 overflow-hidden">
          <div className="p-2 space-y-1">
            {/* Personal Option */}
            <button
              onClick={handleGoToPersonal}
              className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors hover:bg-surface text-text-secondary hover:text-text-primary"
            >
              <UserCircle className="w-4 h-4" />
              <div className="flex-1">
                <p className="text-sm font-medium">Personal</p>
                <p className="text-xs text-text-muted">My overall activity</p>
              </div>
            </button>

            <div className="border-t border-border-light my-2" />

            {/* Individual Workplaces */}
            {workplaces.map((workplace) => (
              <button
                key={workplace.provider_id}
                onClick={() => handleSwitchWorkplace(workplace)}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors ${
                  currentSlug === workplace.provider_slug
                    ? 'bg-gold/20 text-gold'
                    : 'hover:bg-surface text-text-secondary hover:text-text-primary'
                }`}
              >
                {getRoleIcon(workplace.role, workplace.employment_type)}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium truncate">{workplace.provider_name}</p>
                  <p className="text-xs text-text-muted capitalize">
                    {getRoleLabel(workplace.role, workplace.employment_type)} · {workplace.employment_type}
                  </p>
                </div>
                {workplace.is_primary && (
                  <span className="text-xs text-gold">Primary</span>
                )}
              </button>
            ))}
          </div>

          {/* Specialist Info Footer */}
          {specialist && (
            <div className="border-t border-border-light p-3 bg-surface/50">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-full bg-gold/10 flex items-center justify-center">
                  <span className="text-xs font-bold text-gold">
                    {specialist.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()}
                  </span>
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-text-primary truncate">{specialist.name}</p>
                  <p className="text-xs text-text-muted truncate">{specialist.email}</p>
                </div>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
