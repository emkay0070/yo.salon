import { useQuery } from '@tanstack/react-query';
import { specialistCraftApi } from '@/services/specialist/api';

export function useCraftTaxonomy() {
  return useQuery({
    queryKey: ['craft-taxonomy'],
    queryFn: specialistCraftApi.getTaxonomy,
  });
}

export function useCareer() {
  return useQuery({
    queryKey: ['specialist-career'],
    queryFn: specialistCraftApi.getCareer,
  });
}
