import React from 'react';

interface CardProps {
  children: React.ReactNode;
  className?: string;
  hover?: boolean;
}

export const Card = React.forwardRef<HTMLDivElement, CardProps>(
  ({ children, className = '', hover = false, ...props }, ref) => {
    const baseStyles = 'bg-white rounded-2xl shadow-lg transition-all';
    const hoverStyles = hover ? 'hover:translate-y-[-2px] hover:shadow-xl' : '';
    
    return (
      <div
        ref={ref}
        className={`${baseStyles} ${hoverStyles} ${className}`}
        {...props}
      >
        {children}
      </div>
    );
  }
);

Card.displayName = 'Card';
