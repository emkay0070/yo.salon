import { useQuery } from '@tanstack/react-query';
import { specialistJourneyApi } from '@/services/specialist/api';

export function useJourney() {
  return useQuery({
    queryKey: ['specialist-journey'],
    queryFn: specialistJourneyApi.getJourney,
  });
}
