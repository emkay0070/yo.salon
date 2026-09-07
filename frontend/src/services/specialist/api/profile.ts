import { apiClient } from '@/lib/api-client';

export const specialistProfileApi = {
  getProfile: async () => {
    return await apiClient.get('/v1/specialist-portal/profile');
  },

  updateProfile: async (data: any) => {
    return await apiClient.put('/v1/specialist-portal/profile', data);
  },
};
