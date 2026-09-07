import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { specialistSettingsApi } from '@/services/specialist/api';

export function useSettings() {
  return useQuery({
    queryKey: ['specialist-settings'],
    queryFn: specialistSettingsApi.getSettings,
  });
}

export function useUpdateSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: specialistSettingsApi.updateSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-settings'] });
    },
  });
}
