import { apiClient } from '@/lib/api-client';

export interface Salon {
  id: string;
  name: string;
  slug: string;
  description?: string;
  logo?: string;
  phone?: string;
  email?: string;
  address?: string;
  city?: string;
  whatsapp?: string;
  lat?: number;
  lng?: number;
  opening_hours?: any;
  category?: string;
  vibe?: string;
  timezone?: string;
}

export interface Brand {
  logo?: string;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  font_heading: string;
  font_body: string;
  background_image?: string;
  custom_domain?: string;
  white_label_enabled: boolean;
}

export interface Theme {
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
  // Color properties for public-salon components
  primary?: string;
  secondary?: string;
  background?: string;
  text?: string;
  font_heading?: string;
  font_body?: string;
}

export interface Sections {
  enabled: string[];
  order: string[];
}

export interface Navigation {
  items: Array<{
    id: string;
    label: string;
    anchor: string;
  }>;
  show_booking_cta: boolean;
}

export interface Colors {
  primary: string;
  secondary: string;
  accent: string;
  background?: string;
  surface?: string;
  text?: string;
}

export interface EffectiveWebsiteConfiguration {
  salon: Salon;
  brand: Brand;
  theme: Theme;
  sections: Sections;
  navigation: Navigation;
  colors: Colors;
}

export class PublicSalonExperienceResolver {
  /**
   * Resolve the complete public salon experience using the unified backend endpoint
   */
  static async resolve(slug: string): Promise<EffectiveWebsiteConfiguration> {
    try {
      const response = await apiClient.get(`/v1/salons/${slug}/website`);
      return response;
    } catch (error) {
      console.error('Failed to resolve salon experience:', error);
      throw new Error('Salon not found');
    }
  }

  /**
   * Get the booking URL for a salon
   */
  static getBookingUrl(slug: string): string {
    return `/${slug}/book`;
  }

  /**
   * Check if a section should be rendered
   */
  static shouldRenderSection(
    section: string,
    sections: Sections
  ): boolean {
    return sections.enabled.includes(section);
  }

  /**
   * Get sections in the correct order
   */
  static getOrderedSections(sections: Sections): string[] {
    return sections.order;
  }

  /**
   * Get final CSS variables from the configuration
   */
  static getCssVariables(config: EffectiveWebsiteConfiguration): Record<string, string> {
    const { colors, theme, brand } = config;
    
    return {
      '--primary': colors.primary,
      '--secondary': colors.secondary,
      '--accent': colors.accent,
      '--background': colors.background || '#0A0A0A',
      '--surface': colors.surface || '#1A1A1A',
      '--border-radius': `${theme.border_radius}${theme.border_radius_unit}`,
      '--font-heading': brand.font_heading,
      '--font-body': brand.font_body,
      '--glass-opacity': theme.glass_opacity.toString(),
      '--glass-blur': theme.glass_blur.toString(),
    };
  }
}
