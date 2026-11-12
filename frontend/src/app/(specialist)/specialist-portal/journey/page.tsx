'use client';

import { useState } from 'react';
import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  Target,
  Calendar,
  TrendingUp,
  Lightbulb,
  Plus,
  ArrowRight,
  Trophy,
  Users,
  Star,
  Award,
  Sparkles,
  MoreVertical,
  Play,
  Pause,
  CheckCircle2,
  Edit
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useSpecialistCapabilities } from '@/hooks/useSpecialistCapabilities';
import UpgradeBanner from '@/components/specialist/UpgradeBanner';
import GoalDrawer from '@/components/journey/GoalDrawer';
import GoalDetailsDrawer from '@/components/journey/GoalDetailsDrawer';

export default function JourneyPage() {
  const { specialist } = useSpecialistAuth();
  const { hasCapability, isPro } = useSpecialistCapabilities();
  const queryClient = useQueryClient();
  const [isEditDrawerOpen, setIsEditDrawerOpen] = useState(false);
  const [isDetailsDrawerOpen, setIsDetailsDrawerOpen] = useState(false);
  const [selectedGoal, setSelectedGoal] = useState<any>(null);

  const { data: journey, isLoading } = useQuery({
    queryKey: ['specialist-journey'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/journey');
    },
    enabled: !!specialist && isPro(),
  });

  const activateMutation = useMutation({
    mutationFn: (goalId: string) => apiClient.post(`/v1/specialist-portal/goals/${goalId}/activate`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
    },
  });

  const pauseMutation = useMutation({
    mutationFn: (goalId: string) => apiClient.post(`/v1/specialist-portal/goals/${goalId}/pause`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
    },
  });

  const completeMutation = useMutation({
    mutationFn: (goalId: string) => apiClient.post(`/v1/specialist-portal/goals/${goalId}/complete`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
    },
  });

  const handleOpenEditDrawer = (goal?: any, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    setSelectedGoal(goal || null);
    setIsEditDrawerOpen(true);
  };

  const handleOpenDetailsDrawer = (goal: any) => {
    setSelectedGoal(goal);
    setIsDetailsDrawerOpen(true);
  };

  const handleCloseDrawers = () => {
    setIsEditDrawerOpen(false);
    setIsDetailsDrawerOpen(false);
    setSelectedGoal(null);
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'HIGH': return 'bg-red-400/20 text-red-400 border-red-400/30';
      case 'URGENT': return 'bg-red-500/20 text-red-500 border-red-500/30';
      case 'MEDIUM': return 'bg-amber-400/20 text-amber-400 border-amber-400/30';
      default: return 'bg-blue-400/20 text-blue-400 border-blue-400/30';
    }
  };

  const getTierColor = (tier: string) => {
    switch (tier) {
      case 'PLATINUM': return 'text-gray-300';
      case 'GOLD': return 'text-yellow-400';
      case 'SILVER': return 'text-gray-400';
      default: return 'text-amber-600';
    }
  };

  const getTierIcon = (tier: string) => {
    switch (tier) {
      case 'PLATINUM': return <Award className="w-5 h-5" />;
      case 'GOLD': return <Trophy className="w-5 h-5" />;
      case 'SILVER': return <Star className="w-5 h-5" />;
      default: return <Sparkles className="w-5 h-5" />;
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Journey</h1>
        <p className="text-text-secondary">Your professional growth and progression</p>
      </div>

      {!isPro() && (
        <UpgradeBanner 
          featureName="Journey"
          description="Set goals, track milestones, and achieve your professional growth targets"
        />
      )}

      {/* Pro Dashboard - only render when user has Pro access */}
      {isPro() && (
        <>
      {/* Growth Snapshot */}
      {isLoading ? (
        <div className="bg-card border border-border-light rounded-2xl p-6 animate-pulse">
          <div className="h-6 w-32 bg-surface rounded mb-6"></div>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="bg-surface rounded-xl p-4 border border-border-light">
                <div className="h-4 w-16 bg-surface rounded mb-2"></div>
                <div className="h-8 w-12 bg-surface rounded"></div>
              </div>
            ))}
          </div>
        </div>
      ) : (
        <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-6">
          <TrendingUp className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Growth Snapshot</h3>
        </div>
        
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              <Users className="w-4 h-4 text-gold" />
              <span className="text-sm text-text-secondary">Clients</span>
            </div>
            <p className="text-2xl font-bold text-text-primary">{journey?.growth_snapshot?.clients_served || 0}</p>
          </div>
          
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              <Star className="w-4 h-4 text-gold" />
              <span className="text-sm text-text-secondary">Rating</span>
            </div>
            <p className="text-2xl font-bold text-text-primary">{journey?.growth_snapshot?.rating?.toFixed(1) || '0.0'}</p>
          </div>
          
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              <TrendingUp className="w-4 h-4 text-gold" />
              <span className="text-sm text-text-secondary">Repeat Rate</span>
            </div>
            <p className="text-2xl font-bold text-text-primary">{((journey?.growth_snapshot?.repeat_client_rate || 0) * 100).toFixed(0)}%</p>
          </div>
          
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              <Calendar className="w-4 h-4 text-gold" />
              <span className="text-sm text-text-secondary">Services</span>
            </div>
            <p className="text-2xl font-bold text-text-primary">{journey?.growth_snapshot?.services_performed || 0}</p>
          </div>
        </div>
      </motion.div>
      )}

      {/* Goals */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-2">
            <Target className="w-5 h-5 text-gold" />
            <h3 className="font-semibold text-text-primary">Your Goals</h3>
          </div>
          <button 
            onClick={() => handleOpenEditDrawer()}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white text-sm font-medium hover:opacity-90 transition-opacity"
          >
            <Plus className="w-4 h-4" />
            Add Goal
          </button>
        </div>
        
        <div className="space-y-4">
          {journey?.goals?.map((goal: any, index: number) => (
            <motion.div
              key={goal.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 + index * 0.1 }}
              onClick={() => handleOpenDetailsDrawer(goal)}
              className="bg-surface rounded-xl p-4 border border-border-light cursor-pointer hover:border-gold/30 transition-colors"
            >
              <div className="flex items-start justify-between gap-4 mb-3">
                <div className="flex-1">
                  <p className="font-medium text-text-primary mb-1">{goal.title}</p>
                  {goal.deadline && (
                    <p className="text-sm text-text-secondary">Deadline: {new Date(goal.deadline).toLocaleDateString()}</p>
                  )}
                  {goal.scope && (
                    <span className="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold capitalize">
                      {goal.scope === 'WORKPLACE' ? 'Workplace' : 'Personal'}
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                    goal.status === 'ACTIVE' ? 'bg-green-400/20 text-green-400' : 
                    goal.status === 'PAUSED' ? 'bg-amber-400/20 text-amber-400' : 
                    'bg-blue-400/20 text-blue-400'
                  }`}>
                    {goal.status}
                  </span>
                  <button
                    onClick={(e) => handleOpenEditDrawer(goal, e)}
                    className="p-1.5 rounded-lg hover:bg-surface transition-colors text-text-secondary hover:text-text-primary"
                  >
                    <Edit className="w-4 h-4" />
                  </button>
                </div>
              </div>
              
              {goal.goal_type === 'NUMERIC' && (
                <div className="flex items-center gap-4">
                  <div className="flex-1">
                    <div className="flex justify-between mb-1">
                      <span className="text-sm text-text-secondary">
                        {goal.current_value || 0} / {goal.target_value} {goal.unit || ''}
                      </span>
                      <span className="text-sm text-text-primary">{goal.progress_percent?.toFixed(0) || 0}%</span>
                    </div>
                    <div className="h-2 bg-surface rounded-full overflow-hidden">
                      <div
                        className="h-full bg-gradient-to-r from-gold to-amber-600 transition-all"
                        style={{ width: `${Math.min(goal.progress_percent || 0, 100)}%` }}
                      />
                    </div>
                  </div>
                </div>
              )}

              {/* Goal Actions */}
              <div className="flex items-center gap-2 pt-3 border-t border-border-light">
                {goal.status === 'DRAFT' && (
                  <button
                    onClick={(e) => { e.stopPropagation(); activateMutation.mutate(goal.id); }}
                    className="flex items-center gap-1 px-3 py-1.5 bg-green-400/20 text-green-400 rounded-lg text-xs font-medium hover:bg-green-400/30 transition-colors"
                  >
                    <Play className="w-3 h-3" />
                    Activate
                  </button>
                )}
                {goal.status === 'ACTIVE' && (
                  <button
                    onClick={(e) => { e.stopPropagation(); pauseMutation.mutate(goal.id); }}
                    className="flex items-center gap-1 px-3 py-1.5 bg-amber-400/20 text-amber-400 rounded-lg text-xs font-medium hover:bg-amber-400/30 transition-colors"
                  >
                    <Pause className="w-3 h-3" />
                    Pause
                  </button>
                )}
                {goal.status === 'ACTIVE' && (
                  <button
                    onClick={(e) => { e.stopPropagation(); completeMutation.mutate(goal.id); }}
                    className="flex items-center gap-1 px-3 py-1.5 bg-gold/20 text-gold rounded-lg text-xs font-medium hover:bg-gold/30 transition-colors"
                  >
                    <CheckCircle2 className="w-3 h-3" />
                    Complete
                  </button>
                )}
                {goal.status === 'PAUSED' && (
                  <button
                    onClick={(e) => { e.stopPropagation(); activateMutation.mutate(goal.id); }}
                    className="flex items-center gap-1 px-3 py-1.5 bg-green-400/20 text-green-400 rounded-lg text-xs font-medium hover:bg-green-400/30 transition-colors"
                  >
                    <Play className="w-3 h-3" />
                    Resume
                  </button>
                )}
              </div>
            </motion.div>
          ))}
          
          {(!journey?.goals || journey.goals.length === 0) && (
            <div className="text-center py-8 text-text-secondary">
              <Target className="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>No goals yet. Start by creating your first goal!</p>
            </div>
          )}
        </div>
      </motion.div>

      {/* Milestones */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
        className="bg-card border border-border-light rounded-2xl p-6 hidden"
      >
        <div className="flex items-center gap-2 mb-6">
          <Trophy className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Milestones</h3>
        </div>
        
        <div className="space-y-3">
          {journey?.milestones?.map((milestone: any, index: number) => (
            <motion.div
              key={milestone.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.4 + index * 0.1 }}
              className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-center gap-4">
                <div className={`w-10 h-10 rounded-full bg-surface flex items-center justify-center ${getTierColor(milestone.tier)}`}>
                  {getTierIcon(milestone.tier)}
                </div>
                <div>
                  <p className="font-medium text-text-primary">{milestone.title}</p>
                  <p className="text-sm text-text-secondary">Achieved {new Date(milestone.achieved_at).toLocaleDateString()}</p>
                </div>
              </div>
              <span className={`px-2 py-1 rounded-full text-xs font-medium capitalize ${getTierColor(milestone.tier)} bg-surface`}>
                {milestone.tier}
              </span>
            </motion.div>
          ))}
          
          {(!journey?.milestones || journey.milestones.length === 0) && (
            <div className="text-center py-8 text-text-secondary">
              <Trophy className="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>No milestones achieved yet. Keep working towards your goals!</p>
            </div>
          )}
        </div>
      </motion.div>

      {/* Growth Opportunities */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="bg-card border border-border-light rounded-2xl p-6 hidden"
      >
        <div className="flex items-center gap-2 mb-6">
          <Lightbulb className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Growth Opportunities</h3>
        </div>
        
        <div className="space-y-3">
          {journey?.growth_opportunities?.map((opportunity: any, index: number) => (
            <motion.div
              key={opportunity.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.6 + index * 0.1 }}
              className={`flex items-center justify-between p-4 rounded-xl border ${getPriorityColor(opportunity.priority)}`}
            >
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-full bg-surface flex items-center justify-center">
                  <Lightbulb className="w-5 h-5" />
                </div>
                <div className="flex-1">
                  <p className="font-medium text-text-primary">{opportunity.title}</p>
                  {opportunity.description && (
                    <p className="text-sm text-text-secondary mt-1">{opportunity.description}</p>
                  )}
                  {opportunity.action_suggestion && (
                    <p className="text-xs text-text-secondary mt-1 italic">{opportunity.action_suggestion}</p>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className="px-2 py-1 rounded-full text-xs font-medium capitalize">
                  {opportunity.priority}
                </span>
                <button 
                  onClick={() => handleOpenEditDrawer({
                    title: opportunity.title,
                    description: opportunity.description + (opportunity.action_suggestion ? '\n\nAction Plan: ' + opportunity.action_suggestion : ''),
                    priority: opportunity.priority,
                    goal_type: 'QUALITATIVE',
                  })}
                  className="p-2 rounded-lg hover:bg-surface transition-colors text-gold hover:bg-gold/10"
                  title="Create Goal from Opportunity"
                >
                  <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </motion.div>
          ))}
          
          {(!journey?.growth_opportunities || journey.growth_opportunities.length === 0) && (
            <div className="text-center py-8 text-text-secondary">
              <Lightbulb className="w-12 h-12 mx-auto mb-2 opacity-50" />
              <p>No growth opportunities at the moment. Check back later!</p>
            </div>
          )}
        </div>
      </motion.div>
      </>
      )}

      {/* Goal Edit Drawer */}
      <GoalDrawer
        isOpen={isEditDrawerOpen}
        onClose={handleCloseDrawers}
        goal={selectedGoal}
      />

      {/* Goal Details Drawer */}
      <GoalDetailsDrawer
        isOpen={isDetailsDrawerOpen}
        onClose={handleCloseDrawers}
        goal={selectedGoal}
      />
    </div>
  );
}
