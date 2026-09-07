import { apiClient } from '@/lib/api-client';

export const specialistClientsApi = {
  getClients: async () => {
    const json = await apiClient.get('/v1/specialist-portal/customers');
    return json.customers;
  },

  getClientDetails: async (clientId: string) => {
    return await apiClient.get(`/v1/specialist-portal/customers/${clientId}/details`);
  },
};
