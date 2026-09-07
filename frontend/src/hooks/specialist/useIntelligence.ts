import { useQuery } from '@tanstack/react-query';
import { specialistIntelligenceApi } from '@/services/specialist/api';

export function useIntelligence() {
  return useQuery({
    queryKey: ['specialist-intelligence'],
    queryFn: specialistIntelligenceApi.getInsights,
  });
}
