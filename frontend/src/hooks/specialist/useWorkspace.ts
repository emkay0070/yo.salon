import { useQuery } from '@tanstack/react-query';
import { specialistWorkspaceApi } from '@/services/specialist/api';

export function useWorkspaceStats() {
  return useQuery({
    queryKey: ['specialist-today-stats'],
    queryFn: specialistWorkspaceApi.getDashboardStats,
  });
}
