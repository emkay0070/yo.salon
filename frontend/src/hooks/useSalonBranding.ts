import { useBrand } from '@/contexts/BrandContext';

export function useSalonBranding() {
  const { brand, isLoading, error, refetch } = useBrand();

  if (!brand) {
    return {
      salon: null,
      colors: null,
      experience: null,
      isLoading,
      error,
      refetch,
      isWhiteLabel: false,
    };
  }

  return {
    salon: brand.salon,
    colors: brand.colors,
    experience: brand.experience,
    brand: brand.brand,
    isLoading,
    error,
    refetch,
    isWhiteLabel: brand.brand.white_label_enabled,
  };
}
