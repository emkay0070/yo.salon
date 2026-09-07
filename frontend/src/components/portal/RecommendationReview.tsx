'use client';

import { motion } from 'framer-motion';
import { CheckCircle, XCircle, Clock, AlertTriangle, User, Calendar } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { portalApiClient } from '@/lib/portal-api-client';

interface ProfileRecommendation {
  id: string;
  field: string;
  current_value: string | null;
  recommended_value: string;
  reason: string;
  confidence_level: string;
  status: string;
  created_at: string;
  expires_at: string;
  specialist: {
    id: string;
    name: string;
  };
  assessment: {
    id: string;
    salon: {
      name: string;
    };
  };
}

export default function RecommendationReview() {
  const queryClient = useQueryClient();

  const { data: recommendationsData, isLoading } = useQuery({
    queryKey: ['portal-recommendations'],
    queryFn: () => portalApiClient.get('/recommendations'),
  });

  const respondMutation = useMutation({
    mutationFn: ({ recommendationId, accepted, feedback }: { recommendationId: string; accepted: boolean; feedback?: string }) =>
      portalApiClient.post(`/recommendations/${recommendationId}/respond`, { accepted, feedback }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-recommendations'] });
      queryClient.invalidateQueries({ queryKey: ['portal-timeline'] });
    },
  });

  const handleRespond = (recommendation: ProfileRecommendation, accepted: boolean) => {
    const feedback = accepted 
      ? null 
      : prompt('Why would you like to decline this recommendation? (optional)');

    respondMutation.mutate({
      recommendationId: recommendation.id,
      accepted,
      feedback: feedback || undefined,
    });
  };

  const getFieldLabel = (field: string) => {
    const labels: Record<string, string> = {
      hair_type: 'Hair Type',
      beard_style: 'Beard Style',
      skin_type: 'Skin Type',
    };
    return labels[field] || field;
  };

  const getConfidenceColor = (level: string) => {
    switch (level) {
      case 'High': return 'text-green-400 bg-green-500/20';
      case 'Medium': return 'text-yellow-400 bg-yellow-500/20';
      case 'Low': return 'text-red-400 bg-red-500/20';
      default: return 'text-gray-400 bg-gray-500/20';
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const recommendations = recommendationsData?.recommendations || [];

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <div className="text-text-secondary">Loading recommendations...</div>
      </div>
    );
  }

  if (recommendations.length === 0) {
    return (
      <div className="text-center py-12">
        <CheckCircle className="w-16 h-16 text-green-400 mx-auto mb-4" />
        <h3 className="text-xl font-semibold text-text-primary mb-2">All caught up!</h3>
        <p className="text-text-secondary">You have no pending recommendations.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-text-primary">Profile Recommendations</h2>
          <p className="text-text-secondary mt-1">
            {recommendations.length} recommendation{recommendations.length !== 1 ? 's' : ''} waiting for your review
          </p>
        </div>
      </div>

      <div className="space-y-4">
        {recommendations.map((recommendation: ProfileRecommendation, index: number) => (
          <motion.div
            key={recommendation.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
          >
            <div className="flex items-start justify-between gap-4 mb-4">
              <div className="flex-1">
                <div className="flex items-center gap-3 mb-2">
                  <h3 className="text-lg font-semibold text-text-primary">
                    {getFieldLabel(recommendation.field)}
                  </h3>
                  <span className={`text-xs px-2 py-1 rounded-full ${getConfidenceColor(recommendation.confidence_level)}`}>
                    {recommendation.confidence_level} Confidence
                  </span>
                </div>

                <div className="flex items-center gap-2 text-sm text-text-secondary mb-3">
                  <User className="w-4 h-4" />
                  <span>{recommendation.specialist.name}</span>
                  {recommendation.assessment?.salon && (
                    <>
                      <span>•</span>
                      <span>{recommendation.assessment.salon.name}</span>
                    </>
                  )}
                </div>

                <div className="bg-surface rounded-xl p-4 mb-4">
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm text-text-secondary">Current</span>
                    <span className="text-sm font-medium text-text-primary">
                      {recommendation.current_value || 'Not set'}
                    </span>
                  </div>
                  <div className="border-t border-border-medium my-2" />
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-text-secondary">Recommended</span>
                    <span className="text-sm font-medium text-gold">
                      {recommendation.recommended_value}
                    </span>
                  </div>
                </div>

                <p className="text-sm text-text-secondary mb-4">
                  {recommendation.reason}
                </p>

                <div className="flex items-center gap-2 text-xs text-text-secondary">
                  <Clock className="w-3 h-3" />
                  <span>Expires {formatDate(recommendation.expires_at)}</span>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-3 pt-4 border-t border-border-medium">
              <button
                onClick={() => handleRespond(recommendation, true)}
                disabled={respondMutation.isPending}
                className="flex-1 px-4 py-3 xl:px-6 xl:py-3 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity disabled:opacity-50 flex items-center justify-center gap-2"
              >
                <CheckCircle className="w-5 h-5" />
                Accept
              </button>
              <button
                onClick={() => handleRespond(recommendation, false)}
                disabled={respondMutation.isPending}
                className="flex-1 px-4 py-3 xl:px-6 xl:py-3 rounded-xl bg-surface border border-border-medium text-text-primary font-medium hover:bg-surface/80 transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
              >
                <XCircle className="w-5 h-5" />
                Decline
              </button>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 flex items-start gap-3">
        <AlertTriangle className="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" />
        <div className="text-sm text-text-secondary">
          <p className="font-medium text-blue-400 mb-1">About recommendations</p>
          <p>
            These suggestions are based on professional assessments from specialists who have worked with you. 
            Accepting will update your profile with their observations. You can always update your profile manually at any time.
          </p>
        </div>
      </div>
    </div>
  );
}
