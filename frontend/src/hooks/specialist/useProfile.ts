import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { specialistProfileApi } from '@/services/specialist/api';

export function useProfile() {
  return useQuery({
    queryKey: ['specialist-profile'],
    queryFn: specialistProfileApi.getProfile,
  });
}

export function useUpdateProfile() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: specialistProfileApi.updateProfile,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-profile'] });
    },
  });
}
