import { apiClient } from '@/lib/api-client';

export const specialistWorkspaceApi = {
  getDashboardStats: async () => {
    return await apiClient.get('/v1/specialist-portal/dashboard/stats');
  },
};
