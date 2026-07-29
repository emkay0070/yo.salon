'use client';

import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { portalApiClient } from '@/lib/portal-api-client';
import { usePortalAuth } from '@/contexts/PortalAuthContext';

interface SalonInfo {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
}

interface BrandSettings {
  experience_family: string;
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

interface PortalBrandContextType {
  brand: BrandData | null;
  isLoading: boolean;
  error: Error | null;
  refetch: () => void;
}

const PortalBrandContext = createContext<PortalBrandContextType | undefined>(undefined);

export function PortalBrandProvider({ children }: { children: ReactNode }) {
  const { salon } = usePortalAuth();
  
  const { data: brand, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-brand-experience', salon?.id],
    queryFn: () => portalApiClient.get('/brand-experience'),
    enabled: !!salon?.id,
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: false, // Don't retry on 404 - endpoint may not be deployed yet
  });

  // Refetch brand data when salon changes
  useEffect(() => {
    if (salon?.id) {
      refetch();
    }
  }, [salon?.id, refetch]);

  // Apply brand fonts to document
  useEffect(() => {
    if (brand?.brand && brand?.experience) {
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
      
      // Apply experience family border radius
      const borderRadius = `${brand.experience.border_radius}${brand.experience.border_radius_unit}`;
      root.style.setProperty('--brand-border-radius', borderRadius);
      
      // Apply experience family shadows
      const intensity = brand.experience.shadow_intensity;
      const shadowStyle = brand.experience.shadow_style;
      
      // Map shadow styles to CSS values
      const shadowMap: Record<string, { sm: string; md: string; lg: string }> = {
        deep: {
          sm: `0 2px 8px rgba(0,0,0,${0.4 * intensity})`,
          md: `0 6px 18px rgba(0,0,0,${0.5 * intensity})`,
          lg: `0 12px 32px rgba(0,0,0,${0.6 * intensity})`,
        },
        soft: {
          sm: `0 1px 4px rgba(0,0,0,${0.3 * intensity})`,
          md: `0 4px 12px rgba(0,0,0,${0.4 * intensity})`,
          lg: `0 8px 24px rgba(0,0,0,${0.5 * intensity})`,
        },
        colored: {
          sm: `0 2px 8px ${brand.brand.primary_color}${Math.round(intensity * 20).toString(16)}`,
          md: `0 6px 18px ${brand.brand.primary_color}${Math.round(intensity * 30).toString(16)}`,
          lg: `0 12px 32px ${brand.brand.primary_color}${Math.round(intensity * 40).toString(16)}`,
        },
      };
      
      const shadows = shadowMap[shadowStyle] || shadowMap.deep;
      root.style.setProperty('--brand-shadow-sm', shadows.sm);
      root.style.setProperty('--brand-shadow-md', shadows.md);
      root.style.setProperty('--brand-shadow-lg', shadows.lg);
      
      // Apply experience family motion settings
      const animationSpeed = brand.experience.animation_speed;
      const springStiffness = brand.experience.spring_stiffness;
      const springDamping = brand.experience.spring_damping;
      
      // Set motion CSS variables
      root.style.setProperty('--brand-animation-speed', `${animationSpeed}s`);
      root.style.setProperty('--brand-spring-stiffness', springStiffness.toString());
      root.style.setProperty('--brand-spring-damping', springDamping.toString());
      
      // Build spring bezier curve
      const springBezier = `cubic-bezier(0.16, 1, ${springDamping / 100}, 1)`;
      root.style.setProperty('--brand-spring-easing', springBezier);
    }
  }, [brand]);

  return (
    <PortalBrandContext.Provider value={{ brand, isLoading, error, refetch }}>
      {children}
    </PortalBrandContext.Provider>
  );
}

export function usePortalBrand(): PortalBrandContextType {
  const context = useContext(PortalBrandContext);
  if (context === undefined) {
    throw new Error('usePortalBrand must be used within a PortalBrandProvider');
  }
  return context;
}
