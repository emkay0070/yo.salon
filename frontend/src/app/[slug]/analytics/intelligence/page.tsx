'use client';
import { useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import { salonRoutes } from '@/lib/routes';
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
  const { salonId, user, activeSalon, salonSlug } = useRole();
  const routes = salonRoutes(salonSlug);

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/analytics/intelligence`);
      return;
    }

    if (!salonId) {
      console.warn('[SalonIntelligenceCenterPage] User has no salon assignment');
      return;
    }

    // Verify the salon slug matches the user's active salon
    if (activeSalon && activeSalon.slug !== slug) {
      console.error('[SalonIntelligenceCenterPage] Access denied: user salon does not match URL salon');
      router.push(routes.dashboard);
    }
  }, [salonId, user, router, slug, activeSalon]);

  const { data: intelligence, isLoading, error } = useQuery({
    queryKey: ['intelligence', salonId],
    queryFn: () => apiClient.getIntelligence(),
    enabled: !!salonId,
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
    <div className="space-y-6 pb-24 relative">
      {/* 1. Morning Briefing */}
      <BriefingHero 
        greeting={intelligence.briefing.greeting}
        narrative={intelligence.briefing.narrative}
        generatedAt={intelligence.generated_at}
      />

      {/* 2. Top Row: Signals & Pipeline */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <SignalsBoard signals={intelligence.signals} />
        <RevenuePipeline revenue={intelligence.analytics.revenue} fees={intelligence.analytics.fees} />
      </div>

      {/* 3. Middle Row: Churn & Demand */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <ChurnRadar risks={intelligence.analytics.customers.churn_risks} />
        <DemandHeatmap demand={intelligence.analytics.demand} />
      </div>

      {/* 4. Bottom Row: Staff */}
      <div className="grid grid-cols-1 gap-6">
        <StaffLeaderboard staff={intelligence.analytics.staff} />
      </div>

      {/* Embedded Context-Aware Copilot */}
      <div className="fixed bottom-6 right-6 z-50">
        <CopilotChat defaultOpen={false} initialContext={intelligence._copilot_context} />
      </div>
    </div>
  );
}
