'use client';

import { motion } from 'framer-motion';
import { Scissors, Sparkles, Clock, AlertTriangle, ChevronRight } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { portalApiClient } from '@/lib/portal-api-client';

interface ServiceRecommendation {
  service: {
    id: string;
    name: string;
    description: string;
    duration: number;
    price: number;
  };
  reason: string;
  priority: string;
  type: string;
}

export default function ServiceRecommendations() {
  const { data: recommendationsData, isLoading } = useQuery({
    queryKey: ['portal-service-recommendations'],
    queryFn: () => portalApiClient.get('/service-recommendations'),
  });

  const recommendations = recommendationsData?.recommendations || [];

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high': return 'text-red-400 bg-red-500/20';
      case 'medium': return 'text-yellow-400 bg-yellow-500/20';
      case 'low': return 'text-blue-400 bg-blue-500/20';
      default: return 'text-gray-400 bg-gray-500/20';
    }
  };

  const getPriorityLabel = (priority: string) => {
    switch (priority) {
      case 'high': return 'Highly Recommended';
      case 'medium': return 'Recommended';
      case 'low': return 'Suggested';
      default: return 'Suggested';
    }
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'concern_based': return AlertTriangle;
      case 'hair_type_based': return Scissors;
      case 'skin_type_based': return Sparkles;
      default: return Sparkles;
    }
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
        <Sparkles className="w-16 h-16 text-gold mx-auto mb-4" />
        <h3 className="text-xl font-semibold text-text-primary mb-2">Complete your profile</h3>
        <p className="text-text-secondary">
          Add your grooming profile to get personalized service recommendations.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-text-primary flex items-center gap-2">
            <Sparkles className="w-6 h-6 text-gold" />
            Recommended Services
          </h2>
          <p className="text-text-secondary mt-1">
            Personalized based on your grooming profile
          </p>
        </div>
      </div>

      <div className="space-y-4">
        {recommendations.map((recommendation: ServiceRecommendation, index: number) => {
          const Icon = getTypeIcon(recommendation.type);
          return (
            <motion.div
              key={recommendation.service.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl hover:border-gold/30 transition-colors cursor-pointer"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <Icon className="w-5 h-5 text-gold" />
                    <h3 className="text-lg font-semibold text-text-primary">
                      {recommendation.service.name}
                    </h3>
                    <span className={`text-xs px-2 py-1 rounded-full ${getPriorityColor(recommendation.priority)}`}>
                      {getPriorityLabel(recommendation.priority)}
                    </span>
                  </div>

                  <p className="text-text-secondary text-sm mb-3">
                    {recommendation.service.description}
                  </p>

                  <div className="flex items-center gap-4 text-sm text-text-secondary mb-3">
                    <span className="flex items-center gap-1">
                      <Clock className="w-4 h-4" />
                      {recommendation.service.duration} min
                    </span>
                    <span className="text-gold font-medium">
                      UGX {recommendation.service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                    </span>
                  </div>

                  <div className="bg-surface rounded-lg p-3 text-sm">
                    <span className="text-text-secondary">Why: </span>
                    <span className="text-text-primary">{recommendation.reason}</span>
                  </div>
                </div>

                <ChevronRight className="w-5 h-5 text-text-secondary flex-shrink-0" />
              </div>
            </motion.div>
          );
        })}
      </div>

      <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 flex items-start gap-3">
        <Sparkles className="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" />
        <div className="text-sm text-text-secondary">
          <p className="font-medium text-blue-400 mb-1">Personalized for you</p>
          <p>
            These recommendations are based on your hair type, skin type, and concerns. 
            Book any of these services to address your specific needs.
          </p>
        </div>
      </div>
    </div>
  );
}
