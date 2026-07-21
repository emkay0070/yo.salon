'use client';

import { motion } from 'framer-motion';
import { ReactNode } from 'react';

interface GlassButtonProps {
  children: ReactNode;
  variant?: 'primary' | 'secondary' | 'ghost';
  size?: 'sm' | 'md' | 'lg';
  disabled?: boolean;
  onClick?: () => void;
  className?: string;
  type?: 'button' | 'submit' | 'reset';
}

const variantStyles = {
  primary: 'bg-gradient-to-r from-gold to-dark-gold text-obsidian shadow-md',
  secondary: 'bg-surface/50 hover:bg-surface/80 border border-border-light text-text-primary shadow-sm',
  ghost: 'bg-transparent hover:bg-white/5 text-text-primary',
};

const sizeStyles = {
  sm: 'px-5 py-2.5 text-sm min-h-[36px]',
  md: 'px-6 py-3 text-sm min-h-[44px]',
  lg: 'px-8 py-4 text-base min-h-[52px]',
};

export function GlassButton({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  onClick,
  className = '',
  type = 'button',
}: GlassButtonProps) {
  return (
    <motion.button
      type={type}
      onClick={onClick}
      disabled={disabled}
      initial={{ opacity: 0, scale: 0.98 }}
      animate={{ opacity: 1, scale: 1 }}
      whileHover={!disabled ? {
        scale: 1.02,
        transition: { type: 'spring', stiffness: 400, damping: 25 },
      } : {}}
      whileTap={!disabled ? {
        scale: 0.97,
        transition: { type: 'spring', stiffness: 400, damping: 25 },
      } : {}}
      className={`
        ${variantStyles[variant]}
        ${sizeStyles[size]}
        ${disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}
        rounded-xl
        font-medium
        backdrop-blur-xl
        transition-colors duration-300
        flex items-center justify-center
        ${className}
      `}
    >
      {children}
    </motion.button>
  );
}
