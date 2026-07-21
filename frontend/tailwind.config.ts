import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        background: "var(--color-background)",
        surface: "var(--color-surface)",
        card: "var(--color-card)",
        obsidian: "var(--color-obsidian)",
        noir: "var(--color-noir)",
        "dark-surface": "var(--color-dark-surface)",
        gold: "var(--color-gold)",
        "dark-gold": "var(--color-dark-gold)",
        champagne: "var(--color-champagne)",
        emerald: "var(--color-emerald)",
        amber: "var(--color-amber)",
        terracotta: "var(--color-terracotta)",
        "text-primary": "var(--color-text-primary)",
        "text-secondary": "var(--color-text-secondary)",
        "text-muted": "var(--color-text-muted)",
        "border-light": "var(--color-border-light)",
        "border-medium": "var(--color-border-medium)",
      },
      fontFamily: {
        sans: ["var(--font-inter)", "system-ui", "sans-serif"],
        serif: ["var(--font-playfair)", "Georgia", "serif"],
      },
    },
  },
  plugins: [],
};

export default config;
