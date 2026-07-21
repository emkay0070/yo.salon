'use client';

import { useEffect, useState } from 'react';

interface CursorGlowProps {
  color?: string;
  intensity?: number;
  radius?: number;
}

export function CursorGlow({ 
  color = '#FFD700', 
  intensity = 0.3, 
  radius = 250 
}: CursorGlowProps) {
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      setPosition({ x: e.clientX, y: e.clientY });
      setIsVisible(true);
    };

    const handleMouseLeave = () => {
      setIsVisible(false);
    };

    window.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseleave', handleMouseLeave);

    return () => {
      window.removeEventListener('mousemove', handleMouseMove);
      document.removeEventListener('mouseleave', handleMouseLeave);
    };
  }, []);

  return (
    <div
      className="fixed pointer-events-none z-50 transition-opacity duration-700"
      style={{
        left: position.x - radius / 2,
        top: position.y - radius / 2,
        width: radius,
        height: radius,
        background: `radial-gradient(circle, ${color}44 0%, transparent 70%)`,
        opacity: isVisible ? intensity : 0,
        filter: 'blur(50px)',
      }}
    />
  );
}
