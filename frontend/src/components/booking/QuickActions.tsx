'use client';

import { Plus, UserPlus, RotateCcw, X, UserCheck } from 'lucide-react';

interface QuickActionsProps {
  onNewBooking: () => void;
  onWalkIn: () => void;
  onReschedule: () => void;
  onCancel: () => void;
  onAssignStaff: () => void;
  currentUserRole?: string;
}

export default function QuickActions({
  onNewBooking,
  onWalkIn,
  onReschedule,
  onCancel,
  onAssignStaff,
  currentUserRole
}: QuickActionsProps) {
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

  return (
    <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-hide w-full sm:w-auto">
      {actions.map((action) => (
        <button
          key={action.label}
          onClick={action.onClick}
          className="flex items-center gap-2 px-3 sm:px-4 py-2 rounded-xl bg-gradient-to-r from-white/5 to-white/10 border border-border-light hover:border-[rgba(255,215,0,0.4)] transition-all group shrink-0"
        >
          <div className={`p-2 rounded-lg bg-gradient-to-br ${action.color}`}>
            <action.icon className="w-4 h-4 text-text-primary" />
          </div>
          <span className="text-text-primary font-medium text-sm hidden sm:inline">{action.label}</span>
        </button>
      ))}
    </div>
  );
}
