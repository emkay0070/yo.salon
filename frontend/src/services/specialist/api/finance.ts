import { apiClient } from '@/lib/api-client';

export const specialistFinanceApi = {
  getEarnings: async (period: 'today' | 'week' | 'month') => {
    const data = await apiClient.get(`/v1/specialist-portal/earnings?period=${period}`);
    return {
      revenue: data.total_earned || 0,
      commission: data.total_settled || 0,
      tips: 0,
      averageTicket: data.clients > 0 ? Math.round(data.total_earned / data.clients) : 0,
      clients: data.by_salon?.reduce((sum: number, salon: any) => sum + (salon.clients || 0), 0) || 0,
      projected: data.outstanding_balance || 0,
    };
  },
};
