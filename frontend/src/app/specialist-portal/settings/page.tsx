'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { motion } from 'framer-motion';
import { 
  Settings as SettingsIcon, 
  Bell, 
  Shield, 
  Smartphone,
  Moon,
  Sun,
  Lock,
  LogOut,
  Clock,
  Save,
  Crown,
  Sparkles,
  ArrowRight
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { specialistSettingsApi } from '@/services/specialist/api';
import { apiClient } from '@/lib/api-client';

type AvailabilityDay = {
  day: string;
  active: boolean;
  start: string;
  end: string;
  break_start: string | null;
  break_end: string | null;
};

const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default function SettingsPage() {
  const { specialist, logout } = useSpecialistAuth();
  const queryClient = useQueryClient();

  const { data: settings } = useQuery({
    queryKey: ['specialist-settings'],
    queryFn: specialistSettingsApi.getSettings,
    enabled: !!specialist,
  });

  const { data: availabilityData, isLoading: loadingAvailability } = useQuery({
    queryKey: ['specialist-availability'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/availability');
    },
    enabled: !!specialist,
  });

  const { data: capabilities } = useQuery({
    queryKey: ['specialist-capabilities'],
    queryFn: () => apiClient.getSpecialistCapabilities(),
    enabled: !!specialist,
  });

  const updateSettings = useMutation({
    mutationFn: specialistSettingsApi.updateSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-settings'] });
    },
  });

  const updateAvailability = useMutation({
    mutationFn: async (availability: AvailabilityDay[]) => {
      return await apiClient.put('/v1/specialist-portal/availability', { availability });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-availability'] });
    },
  });

  const [notifications, setNotifications] = useState(settings?.notifications_enabled ?? true);
  const [darkMode, setDarkMode] = useState(settings?.appearance === 'dark');
  const [availability, setAvailability] = useState<AvailabilityDay[]>(
    DAYS.map(day => ({ day, active: false, start: '09:00', end: '17:00', break_start: null, break_end: null }))
  );
  const [isSavingAvailability, setIsSavingAvailability] = useState(false);

  // Initialize availability from API data
  useEffect(() => {
    if (availabilityData?.schedules) {
      const mapped = DAYS.map(day => {
        const schedule = availabilityData.schedules.find((s: any) => s.day === day);
        return schedule || { day, active: false, start: '09:00', end: '17:00', break_start: null, break_end: null };
      });
      setAvailability(mapped);
    }
  }, [availabilityData?.schedules]);

  const handleNotificationChange = (value: boolean) => {
    setNotifications(value);
    updateSettings.mutate({ ...settings, notifications_enabled: value });
  };

  const handleDarkModeChange = (value: boolean) => {
    setDarkMode(value);
    updateSettings.mutate({ ...settings, appearance: value ? 'dark' : 'light' });
  };

  const handleAvailabilityChange = (index: number, field: keyof AvailabilityDay, value: any) => {
    const newAvailability = [...availability];
    newAvailability[index] = { ...newAvailability[index], [field]: value };
    setAvailability(newAvailability);
  };

  const handleSaveAvailability = async () => {
    setIsSavingAvailability(true);
    try {
      await updateAvailability.mutateAsync(availability);
    } finally {
      setIsSavingAvailability(false);
    }
  };

  const settingsSections = [
    {
      title: 'Subscription',
      icon: Crown,
      items: [],
      customContent: (
        <div className="space-y-4">
          <div className={`bg-gradient-to-br backdrop-blur-xl border rounded-2xl p-6 shadow-xl ${
            capabilities?.plan?.slug === 'specialist-pro'
              ? 'from-gold/20 to-amber-600/10 border-gold/30'
              : 'from-surface/50 to-surface/30 border-border-light/50'
          }`}>
            <div className="flex items-start justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className={`w-12 h-12 rounded-full flex items-center justify-center ${
                  capabilities?.plan?.slug === 'specialist-pro'
                    ? 'bg-gold/30'
                    : 'bg-surface border border-border-light'
                }`}>
                  {capabilities?.plan?.slug === 'specialist-pro' ? (
                    <Sparkles className="w-6 h-6 text-gold" />
                  ) : (
                    <Crown className="w-6 h-6 text-text-secondary" />
                  )}
                </div>
                <div>
                  <h3 className="text-lg font-bold text-text-primary">
                    {capabilities?.plan?.name || 'Free'}
                  </h3>
                  <p className="text-sm text-text-secondary">
                    {capabilities?.plan?.slug === 'specialist-pro' ? 'Pro Plan' : 'Free Plan'}
                  </p>
                </div>
              </div>
              {capabilities?.plan?.slug !== 'specialist-pro' && (
                <button 
                  onClick={() => {/* TODO: Open upgrade modal */}}
                  className="px-4 py-2 bg-gold text-black rounded-xl text-sm font-semibold hover:bg-amber-400 transition-colors flex items-center gap-2"
                >
                  Upgrade
                  <ArrowRight className="w-4 h-4" />
                </button>
              )}
              {capabilities?.plan?.slug === 'specialist-pro' && (
                <button 
                  onClick={() => {/* TODO: Open subscription management */}}
                  className="px-4 py-2 border border-border-light text-text-primary rounded-xl text-sm font-semibold hover:bg-surface transition-colors"
                >
                  Manage
                </button>
              )}
            </div>
            
            <div className="space-y-2">
              <p className="text-xs font-medium text-text-secondary uppercase tracking-wider mb-3">Your Features</p>
              <div className="grid grid-cols-2 gap-2">
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_WORKSPACE ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Workspace
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_CALENDAR ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Calendar
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_APPOINTMENTS ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Appointments
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_PROFILE ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Profile
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_CRAFT ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Craft
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_CLIENTS ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Clients
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_INTELLIGENCE ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Intelligence
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_FINANCE ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Finance
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_CAREER ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Career
                </div>
                <div className={`flex items-center gap-2 text-sm ${
                  capabilities?.features?.SPECIALIST_JOURNEY ? 'text-text-primary' : 'text-text-muted'
                }`}>
                  <span className="w-1.5 h-1.5 rounded-full bg-gold" />
                  Journey
                </div>
              </div>
            </div>
          </div>
        </div>
      ),
    },
    {
      title: 'Schedule',
      icon: Clock,
      items: [],
      customContent: (
        <div className="space-y-4">
          {!loadingAvailability && availabilityData?.schedule_mode !== 'CUSTOM' && (
            <div className="bg-gradient-to-br from-blue-500/10 to-blue-600/5 backdrop-blur-xl border border-blue-500/20 rounded-2xl p-5 flex gap-4 shadow-lg shadow-blue-500/5">
              <div className="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                <Clock className="w-5 h-5 text-blue-400" />
              </div>
              <div>
                <p className="text-sm font-medium text-blue-100 mb-1">Set your availability</p>
                <p className="text-xs text-blue-200/70">
                  Configure your working hours to appear available for bookings. Days without hours will be marked as closed.
                </p>
              </div>
            </div>
          )}
          <div className="bg-gradient-to-br from-surface/50 to-surface/30 backdrop-blur-xl border border-border-light/50 rounded-2xl overflow-hidden shadow-xl">
            <div className="divide-y divide-border-light/50">
              {availability.map((day, i) => (
                <motion.div
                  key={day.day}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.05 }}
                  className={`flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 p-4 transition-all duration-300 ${
                    day.active ? 'bg-gold/5 hover:bg-gold/10' : 'bg-transparent hover:bg-surface/50'
                  }`}
                >
                  <div className="w-full sm:w-32 flex items-center gap-3">
                    <div className="relative shrink-0">
                      <input
                        type="checkbox"
                        checked={day.active}
                        onChange={(e) => handleAvailabilityChange(i, 'active', e.target.checked)}
                        className="sr-only peer"
                      />
                      <div className={`w-6 h-6 rounded-full border-2 transition-all duration-300 flex items-center justify-center ${
                        day.active
                          ? 'bg-gold border-gold shadow-lg shadow-gold/30'
                          : 'border-border-light bg-surface peer-hover:border-gold/50'
                      }`}>
                        {day.active && (
                          <motion.div
                            initial={{ scale: 0 }}
                            animate={{ scale: 1 }}
                            className="w-3 h-3 rounded-full bg-black"
                          />
                        )}
                      </div>
                    </div>
                    <span className={`font-medium text-sm tracking-wide ${
                      day.active ? 'text-white' : 'text-text-secondary'
                    }`}>
                      {day.day}
                    </span>
                  </div>

                  {day.active ? (
                    <div className="flex items-center gap-3 flex-1 w-full sm:w-auto">
                      <div className="relative flex-1">
                        <input
                          type="time"
                          value={day.start || ''}
                          onChange={(e) => handleAvailabilityChange(i, 'start', e.target.value)}
                          className="w-full bg-gradient-to-br from-background/90 to-background/70 backdrop-blur-md border border-border-light/50 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 transition-all duration-300 shadow-lg shadow-black/5 hover:shadow-xl hover:shadow-black/10"
                        />
                      </div>
                      <span className="text-text-secondary/60 text-sm font-light shrink-0">to</span>
                      <div className="relative flex-1">
                        <input
                          type="time"
                          value={day.end || ''}
                          onChange={(e) => handleAvailabilityChange(i, 'end', e.target.value)}
                          className="w-full bg-gradient-to-br from-background/90 to-background/70 backdrop-blur-md border border-border-light/50 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-gold focus:ring-2 focus:ring-gold/20 transition-all duration-300 shadow-lg shadow-black/5 hover:shadow-xl hover:shadow-black/10"
                        />
                      </div>
                    </div>
                  ) : (
                    <div className="flex-1 flex items-center justify-end w-full sm:w-auto">
                      <span className="text-xs text-text-secondary/40 font-medium tracking-wider uppercase">Unavailable</span>
                    </div>
                  )}
                </motion.div>
              ))}
            </div>
          </div>
          <motion.button
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            onClick={handleSaveAvailability}
            disabled={isSavingAvailability}
            className="w-full mt-4 bg-gradient-to-r from-gold to-amber-500 hover:from-amber-400 hover:to-amber-600 text-black px-6 py-3 rounded-2xl font-semibold transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3 shadow-lg shadow-gold/25"
          >
            <Save className="w-4 h-4" />
            {isSavingAvailability ? 'Saving...' : 'Save Schedule'}
          </motion.button>
        </div>
      ),
    },
    {
      title: 'Notifications',
      icon: Bell,
      items: [
        { label: 'Push notifications', value: notifications, onChange: handleNotificationChange },
        { label: 'Email notifications', value: settings?.email_notifications ?? true, onChange: (v: boolean) => updateSettings.mutate({ ...settings, email_notifications: v }) },
        { label: 'SMS notifications', value: settings?.sms_notifications ?? false, onChange: (v: boolean) => updateSettings.mutate({ ...settings, sms_notifications: v }) },
      ],
    },
    {
      title: 'Appearance',
      icon: Moon,
      items: [
        { label: 'Dark mode', value: darkMode, onChange: handleDarkModeChange },
      ],
    },
    {
      title: 'Security',
      icon: Shield,
      items: [
        { label: 'Change password', action: 'navigate' },
        { label: 'Two-factor authentication', value: false, onChange: () => {} },
        { label: 'Active sessions', action: 'navigate' },
      ],
    },
    {
      title: 'Devices',
      icon: Smartphone,
      items: [
        { label: 'Manage devices', action: 'navigate' },
      ],
    },
  ];

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Settings</h1>
        <p className="text-text-secondary">Preferences and security</p>
      </div>

      {/* Settings Sections */}
      <div className="space-y-6">
        {settingsSections.map((section, sectionIndex) => {
          const Icon = section.icon;
          return (
            <motion.div
              key={section.title}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: sectionIndex * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-6"
            >
              <div className="flex items-center gap-2 mb-4">
                <Icon className="w-5 h-5 text-gold" />
                <h3 className="font-semibold text-text-primary">{section.title}</h3>
              </div>
              {section.customContent ? (
                section.customContent
              ) : (
                <div className="space-y-4">
                  {section.items.map((item, itemIndex) => (
                    <div
                      key={item.label}
                      className="flex items-center justify-between p-4 bg-surface rounded-xl"
                    >
                      <span className="text-text-primary">{item.label}</span>
                      {'action' in item && item.action === 'navigate' ? (
                        <button className="text-gold text-sm font-medium hover:underline">
                          Manage →
                        </button>
                      ) : 'onChange' in item && item.onChange ? (
                        <button
                          onClick={() => item.onChange(!item.value)}
                          className={`w-12 h-6 rounded-full transition-colors ${
                            item.value ? 'bg-gold' : 'bg-border-light'
                          }`}
                        >
                          <div
                            className={`w-5 h-5 rounded-full bg-white transition-transform ${
                              item.value ? 'translate-x-6' : 'translate-x-0.5'
                            }`}
                          />
                        </button>
                      ) : null}
                    </div>
                  ))}
                </div>
              )}
            </motion.div>
          );
        })}
      </div>

      {/* Logout */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
        className="bg-card border border-red-400/30 rounded-2xl p-6"
      >
        <button
          onClick={logout}
          className="flex items-center gap-3 text-red-400 hover:text-red-300 transition-colors"
        >
          <LogOut className="w-5 h-5" />
          <span className="font-medium">Logout</span>
        </button>
      </motion.div>
    </div>
  );
}
