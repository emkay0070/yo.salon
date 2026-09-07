'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  Award,
  Briefcase,
  Building2,
  Calendar,
  CheckCircle,
  CheckCircle2,
  Clock,
  Star,
  Target,
  Users,
  Zap
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useSpecialistCapabilities } from '@/hooks/useSpecialistCapabilities';
import UpgradeBanner from '@/components/specialist/UpgradeBanner';

export default function CareerPage() {
  const { specialist } = useSpecialistAuth();
  const { hasCapability, isPro } = useSpecialistCapabilities();

  const { data: careerStats, isLoading } = useQuery({
    queryKey: ['specialist-career'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/career');
    },
    enabled: !!specialist && isPro(),
  });

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Career</h1>
        <p className="text-text-secondary">Your professional identity and reputation</p>
      </div>

      {!isPro() && (
        <UpgradeBanner 
          featureName="Career"
          description="Track your professional milestones, achievements, and career growth"
        />
      )}

      {/* Pro Dashboard - only render when user has Pro access */}
      {isPro() && (
        <>
      {/* Reputation Score */}
      {isLoading ? (
        <div className="bg-gradient-to-br from-gold/20 to-amber-600/20 border border-gold/30 rounded-2xl p-6 animate-pulse">
          <div className="h-12 w-32 bg-surface rounded mb-4"></div>
          <div className="h-8 w-24 bg-surface rounded"></div>
        </div>
      ) : (
        <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gradient-to-br from-gold/20 to-amber-600/20 border border-gold/30 rounded-2xl p-6"
      >
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <Star className="w-8 h-8 text-gold fill-gold" />
              <span className="text-4xl font-bold text-text-primary">{(Number(careerStats?.rating) || 0).toFixed(2)}</span>
            </div>
            <p className="text-text-secondary">{careerStats?.totalReviews} Reviews</p>
          </div>
          <div className="text-right">
            <p className="text-sm text-text-secondary mb-1">Total Clients</p>
            <p className="text-2xl font-bold text-text-primary">{careerStats?.totalClients}</p>
          </div>
        </div>
      </motion.div>
      )}

      {/* Key Metrics */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {[
          { label: 'Retention Rate', value: `${careerStats?.retentionRate}%`, icon: Users, color: 'text-green-400' },
          { label: 'Rebooking Rate', value: `${careerStats?.rebookingRate}%`, icon: Calendar, color: 'text-blue-400' },
          { label: 'Attendance', value: `${careerStats?.attendanceRate}%`, icon: CheckCircle2, color: 'text-green-400' },
          { label: 'Punctuality', value: `${careerStats?.punctualityRate}%`, icon: Clock, color: 'text-green-400' },
        ].map((stat, index) => {
          const Icon = stat.icon;
          return (
            <motion.div
              key={stat.label}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 + index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-4"
            >
              <Icon className={`w-5 h-5 ${stat.color} mb-2`} />
              <p className="text-2xl font-bold text-text-primary">{stat.value}</p>
              <p className="text-sm text-text-secondary">{stat.label}</p>
            </motion.div>
          );
        })}
      </div>

      {/* Experience */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Briefcase className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Experience</h3>
        </div>
        <p className="text-3xl font-bold text-text-primary">{careerStats?.yearsExperience} Years</p>
        <p className="text-sm text-text-secondary mt-2">Professional experience</p>
      </motion.div>

      {/* Certifications */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.8 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Award className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Certifications</h3>
        </div>
        <div className="flex flex-wrap gap-2">
          {careerStats?.certifications?.map((cert: string, index: number) => (
            <span
              key={index}
              className="px-4 py-2 bg-surface rounded-full text-sm text-text-primary border border-border-light"
            >
              {cert}
            </span>
          ))}
        </div>
      </motion.div>

      {/* Achievements */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.9 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Zap className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Achievements</h3>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {careerStats?.achievements?.map((achievement: any, index: number) => (
            <motion.div
              key={achievement.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 1.0 + index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-6 text-center"
            >
              <div className="text-4xl mb-3">{achievement.icon}</div>
              <p className="font-medium text-text-primary mb-2">{achievement.title}</p>
              <p className="text-xs text-text-secondary">{new Date(achievement.date).toLocaleDateString()}</p>
              <span className="inline-block mt-3 px-2 py-1 rounded-full text-xs font-medium bg-gold/20 text-gold capitalize">
                {achievement.type}
              </span>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* Employment History */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 1.0 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Briefcase className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Employment History</h3>
        </div>
        <div className="space-y-4">
          {careerStats?.employmentHistory?.map((job: any, index: number) => (
            <div key={index} className="flex items-start gap-4 p-4 bg-surface rounded-xl">
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold flex-shrink-0">
                {job.salon.charAt(0)}
              </div>
              <div>
                <p className="font-medium text-text-primary">{job.salon}</p>
                <p className="text-sm text-text-secondary">{job.role}</p>
                <p className="text-xs text-text-secondary">{job.period}</p>
              </div>
            </div>
          ))}
        </div>
      </motion.div>
      </>
      )}
    </div>
  );
}
