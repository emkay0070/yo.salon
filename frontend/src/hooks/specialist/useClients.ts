import { useQuery } from '@tanstack/react-query';
import { specialistClientsApi } from '@/services/specialist/api';

export function useClients() {
  return useQuery({
    queryKey: ['specialist-clients'],
    queryFn: specialistClientsApi.getClients,
  });
}

export function useClientDetails(clientId: string) {
  return useQuery({
    queryKey: ['customer-details', clientId],
    queryFn: () => specialistClientsApi.getClientDetails(clientId),
    enabled: !!clientId,
  });
}
