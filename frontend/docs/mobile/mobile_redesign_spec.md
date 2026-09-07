# Mobile-First App Experience & Theming Redesign

## Overview
The frontend application has been redesigned to provide a highly polished, mobile-first experience that feels like a native app (inspired by Apple, Vision Pro, and Linear aesthetics). This document outlines the structural changes and the new theming system.

## Key Changes

### 1. Robust Theme Infrastructure (`next-themes`)
- The application now uses `next-themes` mapped to CSS variables in `globals.css` via the `[data-theme="..."]` attribute.
- This allows us to scale up to 30+ custom themes. To add a new theme, simply add a block like `[data-theme="glass"] { ... }` in `globals.css` with the respective color variables.
- The Tailwind configuration (`tailwind.config.ts`) extends standard colors mapped directly to these CSS variables (e.g., `--color-gold`, `--color-surface`).

### 2. Native App Navigation
- **Mobile Bottom Navigation**: The primary navigation for mobile users has been moved to a fixed bottom tab bar on `DashboardLayout.tsx` and `ClientLayout.tsx`. 
- **"More" Drawer**: Secondary navigation items are placed inside an iOS-style bottom drawer/sheet powered by `framer-motion`, giving users an intuitive app-like flow.
- **Desktop Sidebar**: Desktop users retain the standard glassmorphic sidebar layout.

### 3. Apple/Linear Aesthetics & Micro-Interactions
- **Tap States**: Interactive elements like buttons utilize `framer-motion` for physical tap feedback (`whileTap={{ scale: 0.97 }}`).
- **Page Transitions**: Fluid cross-fades and vertical slides are added when navigating between routes via `<AnimatePresence>`.
- **Glassmorphism**: Surfaces heavily utilize `backdrop-filter: blur(20px)` and subtle, translucent borders (`rgba(255,255,255,0.06)`).

### 4. Mobile Ergonomics & Constraints
- **Native Scroll Lock**: Body `overscroll-behavior-y` and text selection are disabled to prevent pull-to-refresh bouncing and text highlighting on UI elements, matching native behavior.
- **Touch Targets**: Buttons and touch areas are scaled to a minimum of `44px` in height.
- **Typography**: Inputs utilize a minimum font size of `16px` to prevent automatic zooming on iOS devices.
