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
    retry: false, // Don't retry on 404 - endpoint may not be deployed yet
  });

  // Apply brand fonts to document
  useEffect(() => {
    if (brand?.brand) {
      const root = document.documentElement;
      
      // Map font IDs to CSS variable names
      const fontMap: Record<string, string> = {
        sora: 'var(--font-sora)',
        playfair: 'var(--font-playfair)',
        inter: 'var(--font-inter)',
        poppins: 'var(--font-poppins)',
      };
      
      // Apply heading font
      root.style.setProperty('--brand-font-heading', fontMap[brand.brand.font_heading] || 'var(--font-sora)');
      
      // Apply body font
      root.style.setProperty('--brand-font-body', fontMap[brand.brand.font_body] || 'var(--font-inter)');
      
      // Apply brand colors as CSS variables for global use
      root.style.setProperty('--brand-primary', brand.brand.primary_color);
      root.style.setProperty('--brand-secondary', brand.brand.secondary_color);
      root.style.setProperty('--brand-accent', brand.brand.accent_color);
    }
  }, [brand]);

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
