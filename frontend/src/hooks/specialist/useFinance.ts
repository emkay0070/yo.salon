import { useQuery } from '@tanstack/react-query';
import { specialistFinanceApi } from '@/services/specialist/api';

export function useFinance(period: 'today' | 'week' | 'month') {
  return useQuery({
    queryKey: ['specialist-finance', period],
    queryFn: () => specialistFinanceApi.getEarnings(period),
  });
}
