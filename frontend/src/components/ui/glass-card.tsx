'use client';

import { motion } from 'framer-motion';
import { ReactNode } from 'react';

interface GlassCardProps {
  children: ReactNode;
  elevation?: 0 | 1 | 2 | 3 | 4;
  hoverable?: boolean;
  className?: string;
  onClick?: () => void;
}

const elevationStyles = {
  0: 'shadow-none',
  1: 'shadow-lg',
  2: 'shadow-xl',
  3: 'shadow-2xl',
  4: 'shadow-[0_32px_64px_rgba(0,0,0,0.6)]',
};

export function GlassCard({ 
  children, 
  elevation = 1, 
  hoverable = false, 
  className = '',
  onClick 
}: GlassCardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ 
        type: 'spring', 
        stiffness: 100, 
        damping: 25 
      }}
      whileHover={hoverable ? {
        scale: 1.01,
        backgroundColor: 'rgba(255, 255, 255, 0.08)',
        borderColor: 'rgba(255, 215, 0, 0.3)',
      } : {}}
      onClick={onClick}
      className={`
        backdrop-blur-[40px] 
        rounded-xl
        ${elevationStyles[elevation]}
        ${hoverable ? 'cursor-pointer' : ''}
        ${className}
      `}
      style={{
        backgroundColor: 'rgba(255, 255, 255, 0.04)',
        border: '1px solid rgba(255, 255, 255, 0.06)',
        boxShadow: hoverable ? '0 20px 60px rgba(0, 0, 0, 0.5)' : undefined,
      }}
    >
      {children}
    </motion.div>
  );
}
