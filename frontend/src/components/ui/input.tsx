import React from 'react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  icon?: React.ReactNode;
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, icon, className = '', ...props }, ref) => {
    return (
      <div className="space-y-2">
        {label && (
          <label className="block text-text-primary text-sm font-medium">
            {label}
          </label>
        )}
        <div className="relative">
          {icon && (
            <div className="absolute left-3 top-1/2 transform -translate-y-1/2 text-text-secondary">
              {icon}
            </div>
          )}
          <input
            ref={ref}
            className={`w-full py-3 bg-surface/50 border border-border-light rounded-xl text-text-primary placeholder-text-muted focus:outline-none focus:border-gold focus:ring-1 focus:ring-gold transition-colors text-[16px] backdrop-blur-md shadow-sm ${icon ? 'pl-10 pr-4' : 'px-4'} ${error ? 'border-terracotta' : ''} ${className}`}
            {...props}
          />
        </div>
        {error && (
          <p className="text-terracotta text-sm">{error}</p>
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';
