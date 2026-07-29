'use client';

import { createContext, useContext, ReactNode } from 'react';
import { useTheme as useNextTheme } from 'next-themes';

export type ThemePreset = 'noir' | 'apple-store' | 'vision-pro' | 'modern-glass' | 'light';

interface ThemePresetConfig {
  name: string;
  description: string;
  colors: {
    background: string;
    surface: string;
    card: string;
    border: string;
    text: string;
    textSecondary: string;
    accent: string;
  };
  effects: {
    blur: string;
    shadow: string;
    glass: boolean;
  };
}

const THEME_PRESETS: Record<ThemePreset, ThemePresetConfig> = {
  noir: {
    name: 'Luxury Noir',
    description: 'Dark, elegant, premium feel',
    colors: {
      background: '#0A0A0A',
      surface: '#141414',
      card: '#1A1A1A',
      border: 'rgba(255,255,255,0.08)',
      text: '#FFFFFF',
      textSecondary: 'rgba(255,255,255,0.6)',
      accent: '#FFD700',
    },
    effects: {
      blur: 'blur(24px)',
      shadow: '0 8px 32px rgba(0,0,0,0.12)',
      glass: true,
    },
  },
  'apple-store': {
    name: 'Apple Store',
    description: 'Clean, minimalist, bright',
    colors: {
      background: '#F5F5F7',
      surface: '#FFFFFF',
      card: '#FFFFFF',
      border: 'rgba(0,0,0,0.08)',
      text: '#1D1D1F',
      textSecondary: 'rgba(0,0,0,0.6)',
      accent: '#0071E3',
    },
    effects: {
      blur: 'blur(20px)',
      shadow: '0 4px 24px rgba(0,0,0,0.06)',
      glass: false,
    },
  },
  'vision-pro': {
    name: 'Vision Pro',
    description: 'Spatial, ethereal, depth',
    colors: {
      background: '#000000',
      surface: 'rgba(255,255,255,0.05)',
      card: 'rgba(255,255,255,0.08)',
      border: 'rgba(255,255,255,0.15)',
      text: '#FFFFFF',
      textSecondary: 'rgba(255,255,255,0.5)',
      accent: '#0A84FF',
    },
    effects: {
      blur: 'blur(40px)',
      shadow: '0 0 60px rgba(0,0,0,0.3)',
      glass: true,
    },
  },
  'modern-glass': {
    name: 'Modern Glass',
    description: 'Contemporary, translucent, sleek',
    colors: {
      background: '#0F172A',
      surface: 'rgba(30,41,59,0.8)',
      card: 'rgba(51,65,85,0.6)',
      border: 'rgba(148,163,184,0.1)',
      text: '#F1F5F9',
      textSecondary: 'rgba(148,163,184,0.7)',
      accent: '#38BDF8',
    },
    effects: {
      blur: 'blur(16px)',
      shadow: '0 8px 32px rgba(0,0,0,0.2)',
      glass: true,
    },
  },
  light: {
    name: 'Light',
    description: 'Simple, clean, readable',
    colors: {
      background: '#FFFFFF',
      surface: '#FAFAFA',
      card: '#FFFFFF',
      border: 'rgba(0,0,0,0.1)',
      text: '#1A1A1A',
      textSecondary: 'rgba(0,0,0,0.6)',
      accent: '#FF622B',
    },
    effects: {
      blur: 'blur(12px)',
      shadow: '0 2px 12px rgba(0,0,0,0.08)',
      glass: false,
    },
  },
};

interface ThemeContextType {
  theme: ThemePreset;
  setTheme: (theme: ThemePreset) => void;
  themeConfig: ThemePresetConfig;
  allPresets: typeof THEME_PRESETS;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const { theme: nextTheme, setTheme: setNextTheme } = useNextTheme();

  // Map next-themes to our preset system
  const theme = (nextTheme as ThemePreset) || 'noir';
  const setTheme = (newTheme: ThemePreset) => setNextTheme(newTheme);
  const themeConfig = THEME_PRESETS[theme];

  return (
    <ThemeContext.Provider value={{ theme, setTheme, themeConfig, allPresets: THEME_PRESETS }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (context === undefined) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}
