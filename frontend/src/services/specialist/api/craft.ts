import { apiClient } from '@/lib/api-client';

export const specialistCraftApi = {
  getTaxonomy: async () => {
    const data = await apiClient.get('/v1/specialist-portal/craft/taxonomy');
    return data.taxonomy;
  },

  getCareer: async () => {
    return await apiClient.get('/v1/specialist-portal/career');
  },
};
