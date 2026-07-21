'use client';

import { useState } from 'react';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Plus, X, User, Phone, Briefcase, Upload } from 'lucide-react';
import SceneLayout from './SceneLayout';

const roles = ['Owner', 'Manager', 'Stylist', 'Barber', 'Beautician', 'Nail Technician', 'Receptionist', 'Other'];

export default function TeamScene() {
  const { staff, setStaff } = useOnboarding();
  const [showForm, setShowForm] = useState(false);
  const [draft, setDraft] = useState({ name: '', role: '', phone: '', photo: '' });

  const addMember = () => {
    if (draft.name && draft.role) {
      setStaff([...staff, { id: Date.now().toString(), ...draft }]);
      setDraft({ name: '', role: '', phone: '', photo: '' });
      setShowForm(false);
    }
  };

  const removeMember = (id: string) => setStaff(staff.filter((m) => m.id !== id));

  const handlePhoto = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => setDraft({ ...draft, photo: reader.result as string });
      reader.readAsDataURL(file);
    }
  };

  const inputClass =
    'w-full px-4 py-3 rounded-xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 text-sm focus:outline-none focus:border-[#FFD700]/40 transition-all duration-300';

  return (
    <SceneLayout nextLabel={staff.length > 0 ? 'Continue' : 'Skip for now'}>
      <div className="text-center mb-8">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Team</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Who's on your team?
        </h2>
        <p className="text-white/40 text-base">
          Don't worry — you can always invite more people later.
        </p>
      </div>

      {/* Team grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-4">
        <AnimatePresence>
          {staff.map((m) => (
            <motion.div
              key={m.id}
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.9 }}
              className="relative bg-white/[0.03] border border-white/[0.06] rounded-2xl p-4 text-center group"
            >
              <button
                onClick={() => removeMember(m.id)}
                className="absolute top-2 right-2 w-6 h-6 rounded-full bg-red-500/10 text-red-400/50 hover:text-red-400 hover:bg-red-500/20 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center"
              >
                <X className="w-3 h-3" />
              </button>
              {m.photo ? (
                <img src={m.photo} alt={m.name} className="w-14 h-14 rounded-xl mx-auto mb-2 object-cover" />
              ) : (
                <div className="w-14 h-14 rounded-xl bg-gradient-to-br from-[#FFD700]/20 to-[#C9A227]/20 mx-auto mb-2 flex items-center justify-center">
                  <User className="w-7 h-7 text-[#FFD700]/60" />
                </div>
              )}
              <div className="text-sm font-semibold text-white truncate">{m.name}</div>
              <div className="text-xs text-white/30 truncate">{m.role}</div>
            </motion.div>
          ))}
        </AnimatePresence>

        {/* Add card */}
        <button
          onClick={() => setShowForm(true)}
          className="border-2 border-dashed border-white/[0.08] rounded-2xl p-4 flex flex-col items-center justify-center gap-2 hover:border-[#FFD700]/30 hover:bg-[#FFD700]/[0.03] transition-all duration-300 min-h-[120px]"
        >
          <div className="w-10 h-10 rounded-full bg-white/[0.04] flex items-center justify-center">
            <Plus className="w-5 h-5 text-white/30" />
          </div>
          <span className="text-xs text-white/30">Add member</span>
        </button>
      </div>

      {/* Add member modal */}
      <AnimatePresence>
        {showForm && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-4"
          >
            <motion.div
              initial={{ opacity: 0, y: 40 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 40 }}
              className="bg-[#0e0e12] border border-white/[0.08] rounded-3xl p-6 w-full max-w-sm"
            >
              <div className="flex items-center justify-between mb-5">
                <h3 className="text-lg font-bold text-white">Add Team Member</h3>
                <button onClick={() => setShowForm(false)} className="p-2 rounded-xl text-white/30 hover:text-white hover:bg-white/[0.06] transition-all">
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="space-y-3">
                {/* Photo */}
                <label className="flex items-center gap-3 cursor-pointer group">
                  <input type="file" accept="image/*" onChange={handlePhoto} className="hidden" />
                  <div className="w-14 h-14 rounded-xl border-2 border-dashed border-white/10 group-hover:border-[#FFD700]/30 transition-colors flex items-center justify-center overflow-hidden flex-shrink-0">
                    {draft.photo ? (
                      <img src={draft.photo} alt="preview" className="w-full h-full object-cover" />
                    ) : (
                      <Upload className="w-5 h-5 text-white/20" />
                    )}
                  </div>
                  <span className="text-xs text-white/30">Add photo (optional)</span>
                </label>

                {/* Name */}
                <div className="relative">
                  <User className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/20 pointer-events-none" />
                  <input type="text" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} placeholder="Full name" className={`${inputClass} pl-10`} />
                </div>

                {/* Role */}
                <div className="relative">
                  <Briefcase className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/20 pointer-events-none" />
                  <select value={draft.role} onChange={(e) => setDraft({ ...draft, role: e.target.value })} className={`${inputClass} pl-10 appearance-none cursor-pointer`}>
                    <option value="" className="bg-[#0e0e12]">Select role…</option>
                    {roles.map((r) => <option key={r} value={r} className="bg-[#0e0e12]">{r}</option>)}
                  </select>
                </div>

                {/* Phone */}
                <div className="relative">
                  <Phone className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/20 pointer-events-none" />
                  <input type="tel" value={draft.phone} onChange={(e) => setDraft({ ...draft, phone: e.target.value })} placeholder="Phone (optional)" className={`${inputClass} pl-10`} />
                </div>
              </div>

              <button
                onClick={addMember}
                disabled={!draft.name || !draft.role}
                className="w-full mt-5 py-3 rounded-2xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold text-sm hover:brightness-110 transition-all disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Add to Team
              </button>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
    </SceneLayout>
  );
}
