'use client';

import { motion } from 'framer-motion';
import { Terminal, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';

interface BriefingHeroProps {
  greeting: string;
  narrative: string;
  generatedAt: string;
}

export default function BriefingHero({ greeting, narrative, generatedAt }: BriefingHeroProps) {
  const [displayedText, setDisplayedText] = useState('');
  
  // Typewriter effect
  useEffect(() => {
    let index = 0;
    setDisplayedText('');
    
    const interval = setInterval(() => {
      setDisplayedText(prev => prev + narrative.charAt(index));
      index++;
      if (index >= narrative.length) clearInterval(interval);
    }, 20); // 20ms per character
    
    return () => clearInterval(interval);
  }, [narrative]);

  return (
    <motion.div
      initial={{ opacity: 0, y: -20 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-8 backdrop-blur-2xl relative overflow-hidden"
    >
      {/* Decorative gradient */}
      <div className="absolute top-0 right-0 w-64 h-64 bg-gold/10 rounded-full blur-3xl -mr-20 -mt-20"></div>

      <div className="relative z-10">
        <h1 className="text-2xl sm:text-3xl lg:text-4xl font-bold text-text-primary mb-4 tracking-tight leading-tight">
          {greeting}
        </h1>
        
        <div className="bg-surface/50 border border-border-medium rounded-xl p-6 font-mono text-text-primary leading-relaxed min-h-[120px] relative shadow-inner">
          <div className="absolute top-3 right-3 opacity-30">
            <Terminal className="w-5 h-5" />
          </div>
          {/* We replace markdown bold with HTML for the typewriter effect */}
          <p dangerouslySetInnerHTML={{ __html: displayedText.replace(/\*\*(.*?)\*\*/g, '<span class="text-gold font-bold">$1</span>') }} />
          <span className="inline-block w-2 h-5 bg-gold animate-pulse ml-1 align-middle"></span>
        </div>

        <div className="mt-4 flex items-center gap-4 text-xs text-text-muted">
          <span className="flex items-center gap-1.5">
            <Clock className="w-3.5 h-3.5" />
            Generated {new Date(generatedAt).toLocaleTimeString()}
          </span>
        </div>
      </div>
    </motion.div>
  );
}
