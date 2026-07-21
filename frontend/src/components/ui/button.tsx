'use client';

import React from 'react';
import { motion, HTMLMotionProps } from 'framer-motion';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';
type ButtonSize = 'sm' | 'md' | 'lg';

// Build on HTMLMotionProps so we never have React vs Framer type conflicts
interface ButtonProps extends Omit<HTMLMotionProps<'button'>, 'children'> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  children: React.ReactNode;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'primary', size = 'md', className = '', children, ...props }, ref) => {
    const baseStyles = 'font-semibold transition-colors disabled:opacity-50 flex items-center justify-center';
    
    const variantStyles: Record<ButtonVariant, string> = {
      primary: 'bg-gradient-to-br from-gold to-dark-gold text-obsidian shadow-md hover:brightness-110',
      secondary: 'bg-surface border border-border-light text-text-primary hover:bg-white/5 shadow-sm',
      ghost: 'bg-transparent text-text-primary hover:bg-white/5',
      danger: 'bg-terracotta text-text-primary shadow-md hover:brightness-110',
    };
    
    const sizeStyles: Record<ButtonSize, string> = {
      sm: 'px-4 py-2 rounded-lg text-sm min-h-[36px]',
      md: 'px-6 py-3 rounded-xl text-base min-h-[44px]',
      lg: 'px-8 py-4 rounded-2xl text-lg min-h-[52px]',
    };
    
    return (
      <motion.button
        ref={ref}
        whileHover={!props.disabled ? { scale: 1.02 } : {}}
        whileTap={!props.disabled ? { scale: 0.97 } : {}}
        className={`${baseStyles} ${variantStyles[variant]} ${sizeStyles[size]} ${className}`}
        {...props}
      >
        {children}
      </motion.button>
    );
  }
);

Button.displayName = 'Button';
