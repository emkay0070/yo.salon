import { motion } from 'framer-motion';
import { MapPin, Phone, Clock, Star, Users, Check } from 'lucide-react';

export interface SalonProfileProps {
  mode: 'preview' | 'public';
  salonData: {
    name: string;
    logo?: string;
    description?: string;
    category?: string;
    vibe?: string;
    phone?: string;
    address?: string;
  };
  services: { id: string; name: string; category: string; price: number; duration: number; enabled?: boolean }[];
  team: { id: string; name: string; role: string; photo?: string }[];
}

export default function SalonProfileRenderer({ mode, salonData, services, team }: SalonProfileProps) {
  const isPreview = mode === 'preview';

  const activeServices = services.filter(s => s.enabled !== false);

  // Fallback defaults for empty states during preview
  const displayName = salonData.name || 'Your Salon Name';
  const displayCategory = salonData.category || 'Beauty & Wellness';
  const displayVibe = salonData.vibe || 'Premium Experience';

  return (
    <div className="w-full h-full bg-[#0A0A0C] text-white overflow-y-auto flex flex-col font-sans">
      {/* Header / Cover */}
      <div className="relative w-full pt-[40%] bg-gradient-to-br from-[#1C1C22] to-[#0A0A0C] border-b border-white/[0.04]">
        {/* Abstract Pattern overlay */}
        <div className="absolute inset-0 opacity-20 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-[#FFD700]/10 via-transparent to-transparent" />
        
        {/* Logo overlapping the bottom edge */}
        <div className="absolute -bottom-10 left-6">
          <div className="w-24 h-24 rounded-2xl bg-[#1C1C22] border border-white/10 flex items-center justify-center overflow-hidden shadow-2xl">
            {salonData.logo ? (
              <img src={salonData.logo} alt="Logo" className="w-full h-full object-cover" />
            ) : (
              <span className="text-3xl font-light text-white/30">
                {displayName.charAt(0).toUpperCase()}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="px-6 pt-14 pb-8 flex-1 flex flex-col gap-8">
        {/* Title & Info */}
        <div className="flex flex-col gap-3">
          <h1 className="text-3xl font-semibold tracking-tight text-white">{displayName}</h1>
          <div className="flex flex-wrap items-center gap-3 text-sm text-white/60">
            <span className="px-2 py-0.5 rounded-md bg-white/[0.04] border border-white/[0.04] text-white/80">
              {displayCategory}
            </span>
            <span className="flex items-center gap-1.5">
              <Star className="w-3.5 h-3.5 text-[#FFD700]" />
              {displayVibe}
            </span>
          </div>

          {salonData.description && (
            <p className="text-white/70 text-sm leading-relaxed mt-2 max-w-lg">
              {salonData.description}
            </p>
          )}

          <div className="flex flex-col gap-2 mt-2 text-sm text-white/50">
            {salonData.address && (
              <div className="flex items-center gap-2">
                <MapPin className="w-4 h-4" />
                {salonData.address}
              </div>
            )}
            {salonData.phone && (
              <div className="flex items-center gap-2">
                <Phone className="w-4 h-4" />
                {salonData.phone}
              </div>
            )}
          </div>
        </div>

        {/* CTA (Disabled in preview) */}
        <div className="mt-2">
          <button 
            className="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-xl hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed"
            disabled={isPreview}
          >
            Book Appointment
          </button>
        </div>

        <div className="h-px w-full bg-white/[0.04]" />

        {/* Services */}
        <div className="flex flex-col gap-4">
          <h2 className="text-lg font-medium text-white/90">Services</h2>
          {activeServices.length > 0 ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {activeServices.map(service => (
                <div key={service.id} className="p-4 rounded-xl bg-white/[0.02] border border-white/[0.04] flex justify-between items-start">
                  <div className="flex flex-col gap-1">
                    <span className="font-medium text-white/90">{service.name}</span>
                    <span className="text-xs text-white/50">{service.category} • {service.duration} min</span>
                  </div>
                  <span className="font-semibold text-[#FFD700]">
                    {service.price === 0 ? 'Free' : `$${service.price}`}
                  </span>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-sm text-white/40 italic p-4 rounded-xl border border-dashed border-white/10 text-center">
              No services added yet.
            </div>
          )}
        </div>

        {/* Team */}
        {team.length > 0 && (
          <div className="flex flex-col gap-4 mt-4">
            <h2 className="text-lg font-medium text-white/90 flex items-center gap-2">
              <Users className="w-5 h-5 text-white/50" />
              Our Team
            </h2>
            <div className="flex overflow-x-auto gap-4 pb-4 snap-x">
              {team.map(member => (
                <div key={member.id} className="flex flex-col items-center gap-2 min-w-[80px] snap-start">
                  <div className="w-16 h-16 rounded-full bg-[#1C1C22] border border-white/10 flex items-center justify-center overflow-hidden">
                    {member.photo ? (
                      <img src={member.photo} alt={member.name} className="w-full h-full object-cover" />
                    ) : (
                      <span className="text-lg font-medium text-white/30">{member.name.charAt(0).toUpperCase()}</span>
                    )}
                  </div>
                  <div className="flex flex-col items-center text-center">
                    <span className="text-sm font-medium text-white/90">{member.name.split(' ')[0]}</span>
                    <span className="text-[10px] text-white/50">{member.role}</span>
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
