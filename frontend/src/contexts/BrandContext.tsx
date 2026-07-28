'use client';

import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

interface SalonInfo {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
}

interface BrandSettings {
  logo: string | null;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  font_heading: string;
  font_body: string;
  background_image: string | null;
  custom_domain: string | null;
  white_label_enabled: boolean;
}

interface ExperienceSettings {
  family: string;
  name: string;
  description: string;
  glass_opacity: number;
  glass_blur: number;
  shadow_style: string;
  shadow_intensity: number;
  border_radius: number;
  border_radius_unit: string;
  card_style: string;
  motion_preset: string;
  animation_speed: number;
  spring_stiffness: number;
  spring_damping: number;
  icon_style: string;
  icon_weight: string;
  background_type: string;
  cursor_style: string;
  sidebar_style: string;
  button_style: string;
}

interface ColorPalette {
  primary: string;
  secondary: string;
  accent: string;
  background: string;
  surface: string;
}

interface BrandData {
  salon: SalonInfo;
  brand: BrandSettings;
  experience: ExperienceSettings;
  colors: ColorPalette;
}

interface BrandContextType {
  brand: BrandData | null;
  isLoading: boolean;
  error: Error | null;
  refetch: () => void;
}

const BrandContext = createContext<BrandContextType | undefined>(undefined);

export function BrandProvider({ children }: { children: ReactNode }) {
  const { salonId } = useRole();
  
  const { data: brand, isLoading, error, refetch } = useQuery({
    queryKey: ['brand-experience', salonId],
    queryFn: () => apiClient.get('/brand-experience'),
    enabled: !!salonId,
    staleTime: 5 * 60 * 1000, // 5 minutes
  });

  return (
    <BrandContext.Provider value={{ brand, isLoading, error, refetch }}>
      {children}
    </BrandContext.Provider>
  );
}

export function useBrand(): BrandContextType {
  const context = useContext(BrandContext);
  if (context === undefined) {
    throw new Error('useBrand must be used within a BrandProvider');
  }
  return context;
}
