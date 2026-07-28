'use client';

import { useOnboarding, SCENES, SCENE_META } from '@/contexts/OnboardingContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Crown, Cloud, Smartphone, Palette } from 'lucide-react';
import { useState } from 'react';
import { useTheme } from 'next-themes';

// Scene components
import WelcomeScene from './scenes/WelcomeScene';
import SalonIdentityScene from './scenes/SalonIdentityScene';
import SalonStoryScene from './scenes/SalonStoryScene';
import SalonContactScene from './scenes/SalonContactScene';
import TeamScene from './scenes/TeamScene';
import ServicesScene from './scenes/ServicesScene';
import WorkspaceScene from './scenes/WorkspaceScene';
import LaunchPreviewScene from './scenes/LaunchPreviewScene';
import MembershipScene from './scenes/MembershipScene';
import CelebrationScene from './scenes/CelebrationScene';
import SalonProfileRenderer from '../salon/SalonProfileRenderer';

const sceneComponents: Record<string, any> = {
  'welcome':        WelcomeScene,
  'salon-identity': SalonIdentityScene,
  'salon-story':    SalonStoryScene,
  'salon-contact':  SalonContactScene,
  'team':           TeamScene,
  'services':       ServicesScene,
  'workspace':      WorkspaceScene,
  'launch-preview': LaunchPreviewScene,
  'membership':     MembershipScene,
  'celebration':    CelebrationScene,
};

export default function OnboardingFlow() {
  const { scene, progress, isSaving, salonData, services, staff, showMobilePreview, setShowMobilePreview } = useOnboarding();
  const { theme, setTheme } = useTheme();

  const CurrentScene = sceneComponents[scene];
  const meta = SCENE_META[scene];
  
  // Header shows on form steps (not welcome, launch, celebration)
  const showHeader = !['welcome', 'celebration', 'launch-preview'].includes(scene);
  const showPreviewPane = !['welcome', 'celebration', 'launch-preview'].includes(scene);

  // Estimate minutes left (about 45s per remaining scene)
  const remainingScenes = SCENES.filter(s => !['welcome', 'celebration', 'launch-preview'].includes(s));
  const currentIndex = remainingScenes.indexOf(scene as any);
  const minutesLeft = Math.max(1, Math.ceil(((remainingScenes.length - currentIndex - 1) * 45) / 60));

  return (
    <div className="h-screen w-full bg-[#060608] flex flex-col relative overflow-hidden">

      {/* === Ambient background === */}
      <div className="pointer-events-none absolute inset-0 z-0">
        <div className="absolute -top-48 -left-48 w-[600px] h-[600px] rounded-full bg-[#FFD700]/[0.04] blur-3xl" />
        <div className="absolute -bottom-48 -right-48 w-[700px] h-[700px] rounded-full bg-[#C9A227]/[0.03] blur-3xl" />
        <div
          className="absolute inset-0 opacity-[0.015]"
          style={{
            backgroundImage:
              'linear-gradient(to right, #FFD700 1px, transparent 1px), linear-gradient(to bottom, #FFD700 1px, transparent 1px)',
            backgroundSize: '64px 64px',
          }}
        />
        <motion.div
          className="absolute w-72 h-72 rounded-full bg-[#FFD700]/[0.025] blur-3xl"
          animate={{ x: [0, 40, 0], y: [0, -30, 0] }}
          transition={{ duration: 18, repeat: Infinity, ease: 'easeInOut' }}
          style={{ top: '20%', left: '60%' }}
        />
        <motion.div
          className="absolute w-56 h-56 rounded-full bg-[#C9A227]/[0.03] blur-2xl"
          animate={{ x: [0, -30, 0], y: [0, 40, 0] }}
          transition={{ duration: 22, repeat: Infinity, ease: 'easeInOut', delay: 4 }}
          style={{ top: '55%', left: '15%' }}
        />
      </div>

      {/* === Persistent header === */}
      <AnimatePresence>
        {showHeader && (
          <motion.header
            key="onboarding-header"
            initial={{ opacity: 0, y: -16 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -16 }}
            transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
            className="relative z-20 flex flex-col gap-3 px-6 pt-5 pb-4 border-b border-white/[0.04]"
          >
            <div className="flex items-center justify-between max-w-2xl mx-auto w-full">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
                  <Crown className="w-3.5 h-3.5 text-black" />
                </div>
                <span className="text-white/50 text-xs font-semibold tracking-widest uppercase">
                  Yo.Salon
                </span>
              </div>

              <div className="flex items-center gap-4">
                <AnimatePresence>
                  {isSaving && (
                    <motion.div
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      className="flex items-center gap-1.5 text-white/30 text-xs"
                    >
                      <Cloud className="w-3 h-3 animate-pulse" />
                      Saving…
                    </motion.div>
                  )}
                </AnimatePresence>
                <span className="text-white/30 text-xs">
                  About {minutesLeft} min left
                </span>
              </div>
            </div>

            <div className="max-w-2xl mx-auto w-full">
              <div className="flex items-center justify-between mb-1.5">
                <span className="text-white/70 text-xs font-medium">{meta.group}</span>
                <span className="text-white/30 text-xs">{progress}%</span>
              </div>
              <div className="h-1 bg-white/[0.06] rounded-full overflow-hidden">
                <motion.div
                  className="h-full bg-gradient-to-r from-[#FFD700] to-[#C9A227] rounded-full"
                  initial={false}
                  animate={{ width: `${progress}%` }}
                  transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
                />
              </div>
            </div>
          </motion.header>
        )}
      </AnimatePresence>

      {/* === Scene area === */}
      <div className="relative z-10 flex-1 flex flex-col lg:flex-row w-full h-full overflow-hidden">
        
        {/* Left pane: Forms */}
      <div className="flex-1 flex flex-col h-full relative z-10 overflow-y-auto overflow-x-hidden relative scroll-smooth lg:items-center">
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={scene}
              initial={{ opacity: 0, x: 32, filter: 'blur(4px)' }}
              animate={{ opacity: 1, x: 0, filter: 'blur(0px)' }}
              exit={{ opacity: 0, x: -32, filter: 'blur(4px)' }}
              transition={{ duration: 0.38, ease: [0.16, 1, 0.3, 1] }}
              className="flex-1 flex flex-col min-h-full w-full lg:max-w-2xl"
            >
              {CurrentScene && <CurrentScene />}
            </motion.div>
          </AnimatePresence>
        </div>

        {/* Right pane: Desktop Preview */}
        <AnimatePresence>
          {showPreviewPane && (
            <motion.div 
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: 20 }}
              transition={{ duration: 0.5, delay: 0.1 }}
              className="hidden lg:flex w-[400px] xl:w-[500px] h-full border-l border-white/[0.04] bg-[#0A0A0C] z-20 flex-col shadow-2xl relative"
            >
              {/* Phone Frame wrapper to make it look like a mobile site preview */}
              <div className="flex-1 w-full h-full p-6 xl:p-12 flex items-center justify-center bg-black/20 backdrop-blur-3xl">
                <div className="w-[375px] h-[812px] bg-black rounded-[3rem] border-[8px] border-[#1C1C22] overflow-hidden shadow-2xl relative flex flex-col ring-1 ring-white/10 shrink-0 transform scale-90 xl:scale-100 origin-center transition-transform">
                  {/* Fake Notch */}
                  <div className="absolute top-0 inset-x-0 h-7 flex justify-center z-50">
                    <div className="w-32 h-6 bg-[#1C1C22] rounded-b-3xl"></div>
                  </div>
                  
                  <SalonProfileRenderer 
                    mode="preview"
                    salonData={salonData}
                    services={services}
                    team={staff}
                  />
                </div>
              </div>
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Mobile Preview Bottom Sheet Toggle */}
      {showPreviewPane && (
        <div className="lg:hidden absolute bottom-6 right-6 z-50">
          <button
            onClick={() => setShowMobilePreview(true)}
            className="w-14 h-14 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-black shadow-[0_8px_32px_rgba(255,215,0,0.3)] flex items-center justify-center hover:scale-105 active:scale-95 transition-transform"
          >
            <Smartphone className="w-6 h-6" />
          </button>
        </div>
      )}

      {/* Mobile Preview Bottom Sheet */}
      <AnimatePresence>
        {showMobilePreview && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setShowMobilePreview(false)}
              className="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100] lg:hidden"
            />
            <motion.div
              initial={{ y: '100%' }}
              animate={{ y: 0 }}
              exit={{ y: '100%' }}
              transition={{ type: 'spring', damping: 25, stiffness: 200 }}
              className="fixed inset-x-0 bottom-0 h-[85vh] bg-[#0A0A0C] rounded-t-3xl border-t border-white/10 z-[101] overflow-hidden flex flex-col lg:hidden"
            >
              <div className="w-full flex justify-center pt-3 pb-2">
                <div className="w-12 h-1.5 rounded-full bg-white/20" />
              </div>
              <div className="flex-1 overflow-hidden relative">
                <SalonProfileRenderer 
                  mode="preview"
                  salonData={salonData}
                  services={services}
                  team={staff}
                />
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </div>
  );
}
