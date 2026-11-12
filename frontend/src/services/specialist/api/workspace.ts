import { apiClient } from '@/lib/api-client';

export const specialistWorkspaceApi = {
  getDashboardStats: async () => {
    return await apiClient.get('/v1/specialist-portal/dashboard/stats');
  },
  checkAppointmentsNeedingAttention: async (providerSlug?: string) => {
    const params = providerSlug ? { provider_slug: providerSlug } : {};
    return await apiClient.get('/v1/specialist-portal/appointments/attention', { params });
  },
};
