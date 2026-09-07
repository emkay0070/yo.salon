'use client';

import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import { motion } from 'framer-motion';
import {
  Activity, CheckCircle2, XCircle, AlertTriangle,
  Calendar, ChevronRight, Loader2,
  CreditCard, Users, Scissors, Crown, CalendarCheck, Globe,
} from 'lucide-react';
import Link from 'next/link';
import { salonRoutes } from '@/lib/routes';

interface CheckResult {
  key: string;
  label: string;
  passed: boolean;
  settingsPath?: string;
  icon?: React.ReactNode;
}

function StatusDot({ passed }: { passed: boolean }) {
  return passed ? (
    <CheckCircle2 className="w-4 h-4 text-emerald-400 flex-shrink-0" />
  ) : (
    <XCircle className="w-4 h-4 text-red-400 flex-shrink-0" />
  );
}

function ScoreRing({ score }: { score: number }) {
  const color =
    score >= 80 ? '#34d399' :
    score >= 50 ? '#facc15' :
    '#f87171';
  const circumference = 2 * Math.PI * 20;
  const strokeDashoffset = circumference - (score / 100) * circumference;

  return (
    <div className="relative w-14 h-14 flex-shrink-0">
      <svg className="w-14 h-14 -rotate-90" viewBox="0 0 48 48">
        {/* Track */}
        <circle cx="24" cy="24" r="20" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="4" />
        {/* Progress */}
        <circle
          cx="24" cy="24" r="20"
          fill="none"
          stroke={color}
          strokeWidth="4"
          strokeDasharray={circumference}
          strokeDashoffset={strokeDashoffset}
          strokeLinecap="round"
          style={{ transition: 'stroke-dashoffset 0.6s ease' }}
        />
      </svg>
      <span className="absolute inset-0 flex items-center justify-center text-xs font-bold text-text-primary">
        {score}%
      </span>
    </div>
  );
}

const CHECK_META: Record<string, { label: string; settingsPath: string; icon: React.ReactNode }> = {
  scheduling: {
    label: 'Business Hours',
    settingsPath: '/settings?tab=hours',
    icon: <CalendarCheck className="w-3.5 h-3.5" />,
  },
  payments: {
    label: 'Connect Payment Methods',
    settingsPath: '/settings?tab=payments',
    icon: <CreditCard className="w-3.5 h-3.5" />,
  },
  staff: {
    label: 'Assign Specialists',
    settingsPath: '/staff',
    icon: <Users className="w-3.5 h-3.5" />,
  },
  services: {
    label: 'Publish Services',
    settingsPath: '/services',
    icon: <Scissors className="w-3.5 h-3.5" />,
  },
  subscription: {
    label: 'Subscription Active',
    settingsPath: '/settings?tab=billing',
    icon: <Crown className="w-3.5 h-3.5" />,
  },
  booking: {
    label: 'Booking Rules',
    settingsPath: '/settings?tab=booking',
    icon: <Calendar className="w-3.5 h-3.5" />,
  },
  website: {
    label: 'Website Config',
    settingsPath: '/settings?tab=website',
    icon: <Globe className="w-3.5 h-3.5" />,
  },
  provider_healthy: {
    label: 'Business Configuration',
    settingsPath: '/settings',
    icon: <Activity className="w-3.5 h-3.5" />,
  },
  branch_scheduling: {
    label: 'Branch Hours',
    settingsPath: '/settings?tab=hours',
    icon: <CalendarCheck className="w-3.5 h-3.5" />,
  },
};

export default function BusinessHealthWidget() {
  const { salonId, isLoading: roleLoading, salonSlug } = useRole();
  const routes = salonRoutes(salonSlug);

  const validSalonId = salonId && salonId !== 'null' && salonId !== 'undefined' ? salonId : null;

  const { data, isLoading, isError, failureCount, refetch } = useQuery({
    queryKey: ['branch-health', validSalonId],
    queryFn: () => apiClient.getBranchHealth(validSalonId!),
    enabled: !!validSalonId && !roleLoading,
    staleTime: 60_000,
    retry: 1,
  });

  if (roleLoading || isLoading) {
    return (
      <div className="rounded-2xl border border-white/5 bg-white/[0.02] p-5 flex items-center gap-3">
        <Loader2 className="w-4 h-4 animate-spin text-text-secondary" />
        <span className="text-sm text-text-secondary">Checking business health…</span>
      </div>
    );
  }

  if (!validSalonId) return null;

  if (isError || !data?.data) {
    // Silent fallback — never break the dashboard over a transient health failure.
    // After first retry we show a small recoverable hint so users aren't confused.
    if (failureCount < 2) return null;
    return (
      <div className="rounded-2xl border border-white/5 bg-white/[0.02] px-5 py-3.5 flex items-center gap-3">
        <AlertTriangle className="w-4 h-4 text-amber-400 flex-shrink-0" />
        <p className="text-xs text-text-secondary flex-1">
          Business health check is temporarily unavailable.
        </p>
        <button
          onClick={() => refetch()}
          className="text-[10px] font-semibold text-[#FFD700]/80 hover:text-[#FFD700] transition-colors"
        >
          Retry
        </button>
      </div>
    );
  }

  const health = data.data;
  const score: number = health.score ?? 0;
  const isHealthy = health.status === 'configured';

  // Map all checks to typed CheckResult objects with metadata + icons
  const checks: CheckResult[] = Object.entries(health.checks ?? {}).map(([key, passed]) => {
    const meta = CHECK_META[key];
    return {
      key,
      label: meta?.label ?? key,
      passed: !!passed,
      settingsPath: meta?.settingsPath,
      icon: meta?.icon,
    };
  });

  // Filter only the 7 core "business pillars" for the issue list + progress display
  const CORE_PILLAR_KEYS = [
    'scheduling', 'payments', 'staff', 'services', 'subscription', 'booking', 'website',
  ];
  const coreChecks = checks.filter(c => CORE_PILLAR_KEYS.includes(c.key));
  const failedChecks = coreChecks.filter(c => !c.passed);

  // If everything is healthy, render a compact "all clear" strip
  if (isHealthy) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        className="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 px-5 py-3.5 flex items-center gap-3"
      >
        <Activity className="w-4 h-4 text-emerald-400 flex-shrink-0" />
        <div className="flex items-center gap-3 flex-1">
          <p className="text-sm text-emerald-300">
            <span className="font-semibold">Business Health {score}%</span> — Online booking is fully operational.
          </p>
          {/* Small progress bar */}
          <div className="hidden sm:block ml-auto w-32 h-1.5 rounded-full bg-emerald-500/10 overflow-hidden">
            <div className="h-full bg-emerald-400 rounded-full" style={{ width: `${score}%` }} />
          </div>
        </div>
        <Link href={routes.settings} className="text-xs text-emerald-400/70 hover:text-emerald-300 flex items-center gap-1">
          Settings <ChevronRight className="w-3 h-3" />
        </Link>
      </motion.div>
    );
  }

  // Incomplete — render the full widget per the spec: ScoreRing, Issues list, 7-section checklist
  return (
    <motion.section
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35 }}
      className="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5"
    >
      <div className="flex items-start gap-4">
        <ScoreRing score={score} />

        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <AlertTriangle className="w-4 h-4 text-amber-400 flex-shrink-0" />
            <h3 className="text-sm font-semibold text-text-primary">
              Business Health
            </h3>
            <span className="text-xs font-bold text-amber-300 ml-auto">{score}%</span>
          </div>

          {/* Progress bar */}
          <div className="mt-2 mb-4 w-full h-1.5 rounded-full bg-white/5 overflow-hidden">
            <div
              className="h-full rounded-full"
              style={{
                width: `${score}%`,
                background: score >= 80 ? '#34d399' : score >= 50 ? '#facc15' : '#f87171',
              }}
            />
          </div>

          {/* ── Issues list (per spec: ⚠ Configure Branch Hours, etc.) ── */}
          {failedChecks.length > 0 && (
            <div className="mb-5 space-y-2">
              <p className="text-[11px] uppercase tracking-wider text-text-secondary font-semibold">
                {failedChecks.length} issue{failedChecks.length !== 1 ? 's' : ''} to resolve
              </p>
              {failedChecks.map(check => (
                <div
                  key={`issue-${check.key}`}
                  className="flex items-center gap-2 rounded-lg border border-white/5 bg-black/30 px-3 py-2"
                >
                  <AlertTriangle className="w-3.5 h-3.5 text-amber-400 flex-shrink-0" />
                  {check.icon && (
                    <span className="text-text-secondary flex-shrink-0">{check.icon}</span>
                  )}
                  <span className="text-xs text-text-primary flex-1">
                    {check.label}
                  </span>
                  {check.settingsPath && (
                    <Link
                      href={check.settingsPath}
                      className="text-[10px] text-[#FFD700]/80 hover:text-[#FFD700] flex items-center gap-0.5 transition-colors"
                    >
                      Fix <ChevronRight className="w-3 h-3" />
                    </Link>
                  )}
                </div>
              ))}
            </div>
          )}

          {/* ── 7-pillar status checklist ── */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
            {coreChecks.map(check => (
              <div key={check.key} className="flex items-center gap-2">
                <StatusDot passed={check.passed} />
                {check.icon && (
                  <span className={`flex-shrink-0 ${check.passed ? 'text-emerald-400/80' : 'text-text-secondary'}`}>
                    {check.icon}
                  </span>
                )}
                <span className={`text-xs flex-1 ${check.passed ? 'text-text-secondary line-through opacity-70' : 'text-text-primary'}`}>
                  {check.label}
                </span>
              </div>
            ))}
          </div>

          {/* Footer count */}
          {failedChecks.length > 0 && (
            <div className="mt-4 flex items-center gap-2 pt-2 border-t border-white/5">
              <Calendar className="w-3.5 h-3.5 text-text-secondary" />
              <span className="text-xs text-text-secondary">
                {failedChecks.length} item{failedChecks.length !== 1 ? 's' : ''} pending
              </span>
              <Link
                href={routes.settings}
                className="ml-auto text-[10px] text-[#FFD700]/80 hover:text-[#FFD700] flex items-center gap-0.5 transition-colors"
              >
                Open Settings <ChevronRight className="w-3 h-3" />
              </Link>
            </div>
          )}
        </div>
      </div>
    </motion.section>
  );
}
