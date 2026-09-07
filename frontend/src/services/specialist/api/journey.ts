import { apiClient } from '@/lib/api-client';

export const specialistJourneyApi = {
  getJourney: async () => {
    return await apiClient.get('/v1/specialist-portal/journey');
  },
};
