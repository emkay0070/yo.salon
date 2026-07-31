'use client';

import { useState, useRef, useEffect } from 'react';
import { Plus, UserPlus, RotateCcw, X, UserCheck, MoreVertical, GripVertical } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

interface QuickActionsProps {
  onNewBooking: () => void;
  onWalkIn: () => void;
  onReschedule: () => void;
  onCancel: () => void;
  onAssignStaff: () => void;
  currentUserRole?: string;
}

export default function FloatingQuickActions({
  onNewBooking,
  onWalkIn,
  onReschedule,
  onCancel,
  onAssignStaff,
  currentUserRole
}: QuickActionsProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [position, setPosition] = useState({ x: 20, y: typeof window !== 'undefined' ? window.innerHeight - 100 : 500 });
  const [isDragging, setIsDragging] = useState(false);
  const dragRef = useRef<HTMLDivElement>(null);
  const dragOffset = useRef({ x: 0, y: 0 });

  const canCreateBooking = currentUserRole === 'manager' || currentUserRole === 'receptionist' || currentUserRole === 'owner';
  const canAddWalkIn = currentUserRole === 'manager' || currentUserRole === 'receptionist';
  const canReschedule = currentUserRole === 'manager' || currentUserRole === 'receptionist' || currentUserRole === 'owner';
  const canCancel = currentUserRole === 'manager' || currentUserRole === 'receptionist' || currentUserRole === 'owner';
  const canAssignStaff = currentUserRole === 'manager' || currentUserRole === 'owner';

  const actions = [
    { 
      icon: Plus, 
      label: 'New Booking', 
      color: 'from-[#FFD700] to-[#C9A227]',
      onClick: onNewBooking,
      visible: canCreateBooking
    },
    { 
      icon: UserPlus, 
      label: 'Walk-in', 
      color: 'from-[#2F7A5C] to-[#1E523D]',
      onClick: onWalkIn,
      visible: canAddWalkIn
    },
    { 
      icon: RotateCcw, 
      label: 'Reschedule', 
      color: 'from-[#FF622B] to-[#CC4E22]',
      onClick: onReschedule,
      visible: canReschedule
    },
    { 
      icon: X, 
      label: 'Cancel', 
      color: 'from-[#FF4444] to-[#CC3333]',
      onClick: onCancel,
      visible: canCancel
    },
    { 
      icon: UserCheck, 
      label: 'Assign Staff', 
      color: 'from-[#6366F1] to-[#4F46E5]',
      onClick: onAssignStaff,
      visible: canAssignStaff
    },
  ].filter(action => action.visible);

  const handleMouseDown = (e: React.MouseEvent) => {
    setIsDragging(true);
    dragOffset.current = {
      x: e.clientX - position.x,
      y: e.clientY - position.y
    };
  };

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      if (!isDragging) return;

      const newX = e.clientX - dragOffset.current.x;
      const newY = e.clientY - dragOffset.current.y;

      // Keep within viewport bounds
      const maxX = (typeof window !== 'undefined' ? window.innerWidth : 1200) - 60;
      const maxY = (typeof window !== 'undefined' ? window.innerHeight : 800) - 60;

      setPosition({
        x: Math.max(0, Math.min(newX, maxX)),
        y: Math.max(0, Math.min(newY, maxY))
      });
    };

    const handleMouseUp = () => {
      setIsDragging(false);
    };

    if (isDragging && typeof window !== 'undefined') {
      window.addEventListener('mousemove', handleMouseMove);
      window.addEventListener('mouseup', handleMouseUp);
    }

    return () => {
      if (typeof window !== 'undefined') {
        window.removeEventListener('mousemove', handleMouseMove);
        window.removeEventListener('mouseup', handleMouseUp);
      }
    };
  }, [isDragging]);

  // Calculate menu position based on button position
  const menuPosition = typeof window !== 'undefined' && position.y > window.innerHeight / 2 ? 'bottom' : 'top';
  const menuY = menuPosition === 'bottom' ? position.y - 10 : position.y + 60;

  return (
    <>
      <motion.div
        ref={dragRef}
        onMouseDown={handleMouseDown}
        animate={{ x: position.x, y: position.y }}
        style={{ position: 'fixed', zIndex: 9999 }}
        className="cursor-grab active:cursor-grabbing"
      >
        <div className="relative">
          <motion.button
            onClick={() => setIsOpen(!isOpen)}
            whileHover={{ scale: 1.1 }}
            whileTap={{ scale: 0.95 }}
            className={`w-14 h-14 rounded-full bg-gradient-to-r from-[#FFD700] to-[#C9A227] shadow-lg shadow-[rgba(255,215,0,0.3)] flex items-center justify-center transition-all ${isOpen ? 'rotate-45' : ''}`}
          >
            <MoreVertical className="w-6 h-6 text-obsidian" />
          </motion.button>

          <div className="absolute -top-1 -left-1 w-4 h-4 rounded-full bg-white/20 flex items-center justify-center">
            <GripVertical className="w-3 h-3 text-white/60" />
          </div>
        </div>
      </motion.div>

      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.8 }}
            style={{
              position: 'fixed',
              left: position.x + 20,
              top: menuY,
              zIndex: 999
            }}
            className="bg-card border border-border-light rounded-2xl shadow-2xl p-2 min-w-[200px]"
          >
            <div className={`flex flex-col ${menuPosition === 'bottom' ? 'flex-col-reverse' : 'flex-col'} gap-1`}>
              {actions.map((action, index) => (
                <motion.button
                  key={action.label}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05 }}
                  onClick={() => {
                    action.onClick();
                    setIsOpen(false);
                  }}
                  className="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 transition-colors group"
                >
                  <div className={`p-2 rounded-lg bg-gradient-to-br ${action.color} group-hover:scale-110 transition-transform`}>
                    <action.icon className="w-4 h-4 text-text-primary" />
                  </div>
                  <span className="text-text-primary font-medium text-sm">{action.label}</span>
                </motion.button>
              ))}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </>
  );
}
