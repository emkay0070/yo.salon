'use client';

import { motion } from 'framer-motion';

// Story-telling animation variants for engaging user experiences
export const storyVariants = {
  // Fade in with slide up - classic reveal
  fadeInUp: {
    hidden: { opacity: 0, y: 30 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const } }
  },

  // Fade in with slide down
  fadeInDown: {
    hidden: { opacity: 0, y: -30 },
    visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: [0.16, 1, 0.3, 1] as const } }
  },

  // Fade in with scale - for cards and highlights
  fadeInScale: {
    hidden: { opacity: 0, scale: 0.9 },
    visible: { opacity: 1, scale: 1, transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const } }
  },

  // Stagger children for list items
  staggerContainer: {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.2
      }
    }
  },

  // Slide from left
  slideInLeft: {
    hidden: { opacity: 0, x: -50 },
    visible: { opacity: 1, x: 0, transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const } }
  },

  // Slide from right
  slideInRight: {
    hidden: { opacity: 0, x: 50 },
    visible: { opacity: 1, x: 0, transition: { duration: 0.5, ease: [0.16, 1, 0.3, 1] as const } }
  },

  // Pulse for attention-grabbing elements
  pulse: {
    hidden: { scale: 1 },
    visible: {
      scale: [1, 1.05, 1],
      transition: {
        duration: 2,
        repeat: Infinity,
        ease: "easeInOut" as const
      }
    }
  },

  // Bounce for interactive elements
  bounce: {
    hidden: { y: 0 },
    visible: {
      y: [0, -10, 0],
      transition: {
        duration: 1,
        repeat: Infinity,
        ease: "easeInOut" as const
      }
    }
  },

  // Rotate for loading states
  rotate: {
    hidden: { rotate: 0 },
    visible: {
      rotate: 360,
      transition: {
        duration: 1,
        repeat: Infinity,
        ease: "linear" as const
      }
    }
  },

  // Shimmer effect for loading skeletons
  shimmer: {
    hidden: { backgroundPositionX: '-200%' },
    visible: {
      backgroundPositionX: '200%',
      transition: {
        duration: 1.5,
        repeat: Infinity,
        ease: "linear" as const
      }
    }
  }
};

// Reusable animated components
export function StorySection({ children, delay = 0 }: { children: React.ReactNode, delay?: number }) {
  return (
    <motion.div
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-100px" }}
      variants={storyVariants.fadeInUp}
      transition={{ delay }}
    >
      {children}
    </motion.div>
  );
}

export function StoryCard({ children, delay = 0 }: { children: React.ReactNode, delay?: number }) {
  return (
    <motion.div
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-50px" }}
      variants={storyVariants.fadeInScale}
      transition={{ delay }}
    >
      {children}
    </motion.div>
  );
}

export function StaggerList({ children }: { children: React.ReactNode }) {
  return (
    <motion.div
      initial="hidden"
      whileInView="visible"
      viewport={{ once: true, margin: "-100px" }}
      variants={storyVariants.staggerContainer}
    >
      {children}
    </motion.div>
  );
}

export function StaggerItem({ children }: { children: React.ReactNode }) {
  return (
    <motion.div variants={storyVariants.fadeInUp}>
      {children}
    </motion.div>
  );
}

// Hover effects for interactive elements
export const hoverEffects = {
  lift: {
    whileHover: { y: -4, transition: { duration: 0.2 } },
    whileTap: { scale: 0.98 }
  },

  glow: {
    whileHover: {
      boxShadow: '0 0 20px rgba(255, 98, 43, 0.4)',
      transition: { duration: 0.2 }
    }
  },

  scale: {
    whileHover: { scale: 1.02, transition: { duration: 0.2 } },
    whileTap: { scale: 0.98 }
  }
};

// Page transition variants
export const pageTransition = {
  initial: { opacity: 0, y: 20 },
  animate: { opacity: 1, y: 0, transition: { duration: 0.4, ease: [0.16, 1, 0.3, 1] } },
  exit: { opacity: 0, y: -20, transition: { duration: 0.3, ease: [0.16, 1, 0.3, 1] } }
};

// Hero animation for featured content
export function HeroAnimation({ children }: { children: React.ReactNode }) {
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.95, y: 20 }}
      animate={{ opacity: 1, scale: 1, y: 0 }}
      transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
    >
      {children}
    </motion.div>
  );
}

// Floating animation for decorative elements
export function FloatingElement({ children, delay = 0 }: { children: React.ReactNode, delay?: number }) {
  return (
    <motion.div
      animate={{
        y: [0, -10, 0],
        rotate: [0, 5, -5, 0]
      }}
      transition={{
        duration: 4,
        repeat: Infinity,
        ease: "easeInOut",
        delay
      }}
    >
      {children}
    </motion.div>
  );
}
