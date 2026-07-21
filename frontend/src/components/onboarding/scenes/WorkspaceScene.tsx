'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion } from 'framer-motion';
import { Check } from 'lucide-react';
import SceneLayout from './SceneLayout';

const days = [
  { id: 'monday', label: 'Mon' },
  { id: 'tuesday', label: 'Tue' },
  { id: 'wednesday', label: 'Wed' },
  { id: 'thursday', label: 'Thu' },
  { id: 'friday', label: 'Fri' },
  { id: 'saturday', label: 'Sat' },
  { id: 'sunday', label: 'Sun' },
];

const paymentOptions = [
  { id: 'cash',   name: 'Cash' },
  { id: 'mtn',    name: 'MTN Mobile Money' },
  { id: 'airtel', name: 'Airtel Money' },
  { id: 'card',   name: 'Card' },
];

const durations = [
  { value: 15, label: '15 min' },
  { value: 30, label: '30 min' },
  { value: 45, label: '45 min' },
  { value: 60, label: '1 hour' },
];

export default function WorkspaceScene() {
  const { workspaceData, setWorkspaceData } = useOnboarding();

  const toggleDay = (id: string) => {
    const days = workspaceData.workingDays.includes(id)
      ? workspaceData.workingDays.filter((d) => d !== id)
      : [...workspaceData.workingDays, id];
    setWorkspaceData({ ...workspaceData, workingDays: days });
  };

  const togglePayment = (id: string) => {
    const methods = workspaceData.paymentMethods.includes(id)
      ? workspaceData.paymentMethods.filter((m) => m !== id)
      : [...workspaceData.paymentMethods, id];
    setWorkspaceData({ ...workspaceData, paymentMethods: methods });
  };

  const updateHours = (dayId: string, field: 'open' | 'close', value: string) => {
    setWorkspaceData({
      ...workspaceData,
      openingHours: {
        ...workspaceData.openingHours,
        [dayId]: { ...workspaceData.openingHours[dayId], [field]: value },
      },
    });
  };

  const selectClass =
    'px-3 py-2 rounded-xl bg-white/[0.04] border border-white/[0.08] text-white/70 text-sm focus:outline-none focus:border-[#FFD700]/30 transition-all cursor-pointer appearance-none';

  return (
    <SceneLayout nextDisabled={workspaceData.workingDays.length === 0 || workspaceData.paymentMethods.length === 0}>
      <div className="text-center mb-8">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Workspace</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Set your schedule
        </h2>
        <p className="text-white/40 text-base">
          We'll use this to manage bookings. You can adjust anytime.
        </p>
      </div>

      <div className="space-y-6">
        {/* Working Days */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-3">
            Working Days
          </label>
          <div className="flex gap-2 flex-wrap">
            {days.map((d) => {
              const active = workspaceData.workingDays.includes(d.id);
              return (
                <button
                  key={d.id}
                  onClick={() => toggleDay(d.id)}
                  className={`w-12 h-12 rounded-xl text-xs font-semibold transition-all duration-200 ${
                    active
                      ? 'bg-[#FFD700] text-black shadow-lg shadow-[#FFD700]/10'
                      : 'bg-white/[0.04] border border-white/[0.08] text-white/40 hover:border-white/20'
                  }`}
                >
                  {d.label}
                </button>
              );
            })}
          </div>
        </div>

        {/* Opening hours for active days */}
        {workspaceData.workingDays.length > 0 && (
          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-3">
              Opening Hours
            </label>
            <div className="space-y-2">
              {workspaceData.workingDays.map((dayId) => {
                const d = days.find((d) => d.id === dayId);
                const hours = workspaceData.openingHours[dayId];
                return (
                  <motion.div
                    key={dayId}
                    initial={{ opacity: 0, x: -8 }}
                    animate={{ opacity: 1, x: 0 }}
                    className="flex items-center justify-between gap-3 p-3 rounded-xl bg-white/[0.02] border border-white/[0.04]"
                  >
                    <span className="text-sm text-white/60 w-10">{d?.label}</span>
                    <div className="flex items-center gap-2 flex-1">
                      <select value={hours?.open || '09:00'} onChange={(e) => updateHours(dayId, 'open', e.target.value)} className={selectClass}>
                        {Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, '0')}:00`).map((t) => (
                          <option key={t} value={t} className="bg-[#0e0e12]">{t}</option>
                        ))}
                      </select>
                      <span className="text-white/20 text-sm">—</span>
                      <select value={hours?.close || '18:00'} onChange={(e) => updateHours(dayId, 'close', e.target.value)} className={selectClass}>
                        {Array.from({ length: 24 }, (_, i) => `${String(i).padStart(2, '0')}:00`).map((t) => (
                          <option key={t} value={t} className="bg-[#0e0e12]">{t}</option>
                        ))}
                      </select>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          </div>
        )}

        {/* Slot Duration */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-3">
            Default Appointment Slot
          </label>
          <div className="flex gap-2">
            {durations.map((d) => (
              <button
                key={d.value}
                onClick={() => setWorkspaceData({ ...workspaceData, appointmentDuration: d.value })}
                className={`flex-1 py-3 rounded-xl text-sm font-semibold transition-all duration-200 ${
                  workspaceData.appointmentDuration === d.value
                    ? 'bg-[#FFD700] text-black shadow-lg shadow-[#FFD700]/10'
                    : 'bg-white/[0.04] border border-white/[0.08] text-white/40 hover:border-white/20'
                }`}
              >
                {d.label}
              </button>
            ))}
          </div>
        </div>

        {/* Payment Methods */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-3">
            Payment Methods
          </label>
          <div className="grid grid-cols-2 gap-2">
            {paymentOptions.map((p) => {
              const active = workspaceData.paymentMethods.includes(p.id);
              return (
                <button
                  key={p.id}
                  onClick={() => togglePayment(p.id)}
                  className={`flex items-center gap-2 px-4 py-3 rounded-xl text-sm transition-all duration-200 ${
                    active
                      ? 'border-2 border-[#FFD700]/60 bg-[#FFD700]/[0.07] text-white'
                      : 'border border-white/[0.08] bg-white/[0.02] text-white/40 hover:border-white/20'
                  }`}
                >
                  <div className={`w-4 h-4 rounded border flex items-center justify-center flex-shrink-0 ${
                    active ? 'bg-[#FFD700] border-[#FFD700]' : 'border-white/20'
                  }`}>
                    {active && <Check className="w-2.5 h-2.5 text-black" />}
                  </div>
                  {p.name}
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </SceneLayout>
  );
}
