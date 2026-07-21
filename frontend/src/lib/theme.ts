export const theme = {
  // Primary Colors - Luxury Noir Theme
  colors: {
    // Navigation, headers
    obsidian: '#0A0A0A',
    
    // Background - deep black
    noir: '#0A0A0A',
    
    // Surface - cards
    darkSurface: '#1A1A1A',
    
    // Accent - signature gold color
    champagne: '#C8A96A',
    gold: '#FFD700',
    darkGold: '#C9A227',
    
    // Secondary accent
    emerald: '#2F7A5C',
    
    // Warning
    amber: '#D89B2D',
    
    // Error - instead of bright red
    terracotta: '#C55A4A',
    
    // Text colors
    text: {
      primary: '#FFFFFF',
      secondary: '#A0A0A0',
      muted: '#606060',
    },
    
    // Border colors
    border: {
      light: 'rgba(255,255,255,0.06)',
      medium: 'rgba(255,255,255,0.12)',
    },
  },
  
  // Spacing
  spacing: {
    xs: '4px',
    sm: '8px',
    md: '16px',
    lg: '24px',
    xl: '32px',
    '2xl': '48px',
  },
  
  // Border radius
  radius: {
    sm: '8px',
    md: '12px',
    lg: '16px',
    xl: '20px',
    '2xl': '24px',
  },
  
  // Shadows - soft, not floating
  shadow: {
    sm: '0 4px 12px rgba(0,0,0,0.3)',
    md: '0 8px 20px rgba(0,0,0,0.4)',
    lg: '0 12px 30px rgba(0,0,0,0.5)',
    xl: '0 16px 40px rgba(0,0,0,0.6)',
  },
  
  // Typography
  typography: {
    fontFamily: {
      heading: 'var(--font-inter)',
      body: 'var(--font-inter)',
      number: 'var(--font-inter)',
    },
    fontWeight: {
      heading: '700',
      body: '400',
      number: '600',
    },
  },
  
  // Animation
  animation: {
    hover: 'scale(1.02)',
    cardHover: 'translateY(-2px)',
    transition: 'all 0.4s ease-in-out',
  },
};

// Future theme presets for salon customization
export const themePresets = {
  luxuryNoir: {
    primary: '#0A0A0A',
    accent: '#FFD700',
    darkAccent: '#C9A227',
    background: '#0A0A0A',
    surface: '#1A1A1A',
  },
  modernSpa: {
    primary: '#2D5A4A',
    accent: '#8FB996',
    background: '#F5F8F5',
    surface: '#FFFFFF',
  },
  urbanStudio: {
    primary: '#2C3E50',
    accent: '#3498DB',
    background: '#F0F2F5',
    surface: '#FFFFFF',
  },
  classicBarber: {
    primary: '#1A365D',
    accent: '#B87333',
    background: '#F5F0E8',
    surface: '#FFFFFF',
  },
};
