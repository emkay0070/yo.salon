'use client';
import { useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import { providerRoutes } from '@/lib/routes';
import BriefingHero from '@/components/intelligence/BriefingHero';
import SignalsBoard from '@/components/intelligence/SignalsBoard';
import RevenuePipeline from '@/components/intelligence/RevenuePipeline';
import ChurnRadar from '@/components/intelligence/ChurnRadar';
import StaffLeaderboard from '@/components/intelligence/StaffLeaderboard';
import DemandHeatmap from '@/components/intelligence/DemandHeatmap';
import { CopilotChat } from '@/components/analytics/CopilotChat';
import { Brain } from 'lucide-react';

export default function SalonIntelligenceCenterPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const { providerId, user, activeProvider, providerSlug } = useRole();
  const routes = providerRoutes(providerSlug);

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/analytics/intelligence`);
      return;
    }

    if (!providerId) {
      console.warn('[SalonIntelligenceCenterPage] User has no provider assignment');
      return;
    }

    // Verify the salon slug matches the user's active salon
    if (activeProvider && activeProvider.slug !== slug) {
      console.error('[SalonIntelligenceCenterPage] Access denied: user salon does not match URL salon');
      router.push(routes.dashboard);
    }
  }, [providerId, user, router, slug, activeProvider]);

  const { data: intelligence, isLoading, error } = useQuery({
    queryKey: ['intelligence', providerId],
    queryFn: () => apiClient.getIntelligence(),
    enabled: !!providerId,
  });

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center h-96 gap-4 text-gold">
        <Brain className="w-12 h-12 animate-pulse" />
        <p className="animate-pulse">Analyzing operations...</p>
      </div>
    );
  }

  if (error || !intelligence) {
    return (
      <div className="flex items-center justify-center h-96 text-red-400">
        Failed to load Intelligence Center.
      </div>
    );
  }

  return (
    <div className="space-y-8 pb-24 relative">
      {/* Executive Briefing - Command Center Header */}
      <div className="bg-gradient-to-br from-surface to-surface-light border border-border-light rounded-2xl p-8 backdrop-blur-xl">
        {intelligence.briefing && (
          <div className="space-y-6">
            <div>
              <h1 className="text-2xl font-bold text-text-primary mb-2">
                {intelligence.briefing.greeting || 'Good morning.'}
              </h1>
              <p className="text-text-secondary leading-relaxed">
                {intelligence.briefing.narrative || 'Intelligence analysis complete.'}
              </p>
            </div>

            {/* Key Metrics Row */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 pt-6 border-t border-border-medium">
              <div className="bg-card rounded-xl p-4 border border-border-light">
                <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Today's Appointments</div>
                <div className="text-2xl font-bold text-text-primary">
                  {intelligence.analytics?.today?.appointments || 0}
                </div>
              </div>
              <div className="bg-card rounded-xl p-4 border border-border-light">
                <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Expected Revenue</div>
                <div className="text-2xl font-bold text-gold">
                  UGX {((intelligence.analytics?.today?.revenue || 0) / 1000).toFixed(0)}K
                </div>
              </div>
              <div className="bg-card rounded-xl p-4 border border-border-light">
                <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Active Specialists</div>
                <div className="text-2xl font-bold text-text-primary">
                  {intelligence.analytics?.today?.specialists || 0}
                </div>
              </div>
              <div className="bg-card rounded-xl p-4 border border-border-light">
                <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Net Revenue (Month)</div>
                <div className="text-2xl font-bold text-gold">
                  UGX {((intelligence.analytics?.revenue?.net || 0) / 1000000).toFixed(1)}M
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Attention Signals Section */}
      {intelligence.signals && intelligence.signals.length > 0 && (
        <div className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl">
          <h2 className="text-lg font-semibold text-text-primary mb-4">Needs Your Attention</h2>
          <SignalsBoard signals={intelligence.signals} />
        </div>
      )}

      {/* Operational Context Section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Today's Schedule */}
        <div className="lg:col-span-2 bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl">
          <h2 className="text-lg font-semibold text-text-primary mb-4">Today's Schedule</h2>
          <div className="text-text-secondary text-sm">
            Schedule view will appear here once appointments are loaded.
          </div>
        </div>

        {/* Business Pulse */}
        <div className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl">
          <h2 className="text-lg font-semibold text-text-primary mb-4">Business Pulse</h2>
          <div className="space-y-4">
            <div>
              <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Revenue Trend</div>
              <div className="text-sm font-medium text-text-primary">Loading trend data...</div>
            </div>
            <div>
              <div className="text-xs text-text-muted uppercase tracking-wider mb-1">Customer Movement</div>
              <div className="text-sm font-medium text-text-primary">Loading customer data...</div>
            </div>
          </div>
        </div>
      </div>

      {/* Deep Intelligence Section */}
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-text-primary">Deep Intelligence</h2>
          <button className="text-sm text-gold hover:underline">Explore full analytics →</button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <RevenuePipeline revenue={intelligence.analytics?.revenue || {}} fees={intelligence.analytics?.fees || {}} />
          <ChurnRadar risks={intelligence.analytics?.customers?.churn_risks || []} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <DemandHeatmap demand={intelligence.analytics?.demand || { by_day_of_week: [] }} />
          <StaffLeaderboard staff={intelligence.analytics?.staff || []} />
        </div>
      </div>

      {/* Embedded Context-Aware Copilot */}
      <div className="fixed bottom-6 right-6 z-50">
        <CopilotChat defaultOpen={false} initialContext={intelligence._copilot_context || {}} />
      </div>
    </div>
  );
}
