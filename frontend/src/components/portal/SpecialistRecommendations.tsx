'use client';

import { motion } from 'framer-motion';
import { Scissors, Star, Award, TrendingUp, CheckCircle, ChevronRight } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { portalApiClient } from '@/lib/portal-api-client';

interface SpecialistRecommendation {
  specialist: {
    id: string;
    name: string;
    photo_url: string | null;
    rating: number;
    review_count: number;
    years_experience: number;
    specialties: string[];
    bio: string;
  };
  match_score: number;
  match_reasons: string[];
}

export default function SpecialistRecommendations() {
  const { data: recommendationsData, isLoading } = useQuery({
    queryKey: ['portal-specialist-recommendations'],
    queryFn: () => portalApiClient.get('/specialist-recommendations'),
  });

  const recommendations = recommendationsData?.specialists || [];

  const getMatchColor = (score: number) => {
    if (score >= 80) return 'text-green-400 bg-green-500/20';
    if (score >= 60) return 'text-yellow-400 bg-yellow-500/20';
    return 'text-blue-400 bg-blue-500/20';
  };

  const getMatchLabel = (score: number) => {
    if (score >= 80) return 'Excellent Match';
    if (score >= 60) return 'Good Match';
    return 'Potential Match';
  };

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
        <Scissors className="w-16 h-16 text-gold mx-auto mb-4" />
        <h3 className="text-xl font-semibold text-text-primary mb-2">Complete your profile</h3>
        <p className="text-text-secondary">
          Add your grooming profile to get personalized specialist recommendations.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-text-primary flex items-center gap-2">
            <Scissors className="w-6 h-6 text-gold" />
            Recommended Specialists
          </h2>
          <p className="text-text-secondary mt-1">
            Personalized based on your grooming profile
          </p>
        </div>
      </div>

      <div className="space-y-4">
        {recommendations.map((recommendation: SpecialistRecommendation, index: number) => (
          <motion.div
            key={recommendation.specialist.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl hover:border-gold/30 transition-colors cursor-pointer"
          >
            <div className="flex items-start gap-4">
              {/* Avatar */}
              <div className="w-16 h-16 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold text-xl flex-shrink-0">
                {recommendation.specialist.photo_url ? (
                  <img
                    src={recommendation.specialist.photo_url}
                    alt={recommendation.specialist.name}
                    className="w-full h-full rounded-full object-cover"
                  />
                ) : (
                  recommendation.specialist.name.charAt(0)
                )}
              </div>

              <div className="flex-1 min-w-0">
                {/* Header */}
                <div className="flex items-start justify-between mb-2">
                  <div>
                    <h3 className="text-lg font-semibold text-text-primary">
                      {recommendation.specialist.name}
                    </h3>
                    <div className="flex items-center gap-2 mt-1">
                      <div className="flex items-center gap-1">
                        <Star className="w-4 h-4 text-yellow-400 fill-yellow-400" />
                        <span className="text-sm font-medium text-text-primary">
                          {recommendation.specialist.rating.toFixed(1)}
                        </span>
                      </div>
                      <span className="text-sm text-text-secondary">
                        ({recommendation.specialist.review_count} reviews)
                      </span>
                      <span className="text-sm text-text-secondary">•</span>
                      <span className="text-sm text-text-secondary">
                        {recommendation.specialist.years_experience} years exp
                      </span>
                    </div>
                  </div>
                  <div className={`text-xs px-3 py-1 rounded-full ${getMatchColor(recommendation.match_score)}`}>
                    {getMatchLabel(recommendation.match_score)} ({recommendation.match_score}%)
                  </div>
                </div>

                {/* Specialties */}
                {recommendation.specialist.specialties && recommendation.specialist.specialties.length > 0 && (
                  <div className="flex flex-wrap gap-2 mb-3">
                    {recommendation.specialist.specialties.slice(0, 3).map((specialty, idx) => (
                      <span
                        key={idx}
                        className="text-xs px-2 py-1 rounded-full bg-surface text-text-secondary"
                      >
                        {specialty}
                      </span>
                    ))}
                  </div>
                )}

                {/* Match Reasons */}
                <div className="space-y-2 mb-3">
                  {recommendation.match_reasons.slice(0, 3).map((reason, idx) => (
                    <div key={idx} className="flex items-center gap-2 text-sm">
                      <CheckCircle className="w-4 h-4 text-green-400 flex-shrink-0" />
                      <span className="text-text-secondary">{reason}</span>
                    </div>
                  ))}
                </div>

                {/* Bio */}
                {recommendation.specialist.bio && (
                  <p className="text-sm text-text-secondary line-clamp-2">
                    {recommendation.specialist.bio}
                  </p>
                )}
              </div>

              <ChevronRight className="w-5 h-5 text-text-secondary flex-shrink-0" />
            </div>
          </motion.div>
        ))}
      </div>

      <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 flex items-start gap-3">
        <TrendingUp className="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" />
        <div className="text-sm text-text-secondary">
          <p className="font-medium text-blue-400 mb-1">Personalized matching</p>
          <p>
            These specialists are recommended based on your hair type, skin type, and concerns. 
            Match scores consider specialties, ratings, experience, and skills.
          </p>
        </div>
      </div>
    </div>
  );
}
