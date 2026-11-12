'use client';

import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { Loader2, Save, ShieldAlert, Plus, Trash2, Clock, UserCheck, CheckCircle } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

const generateUUID = () => {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  // Fallback for older browsers
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = Math.random() * 16 | 0;
    const v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
};

interface ReminderRule {
  id: string;
  label: string;
  type: string;
  lead_minutes: number;
  channel: 'sms' | 'email' | 'in_app';
  enabled: boolean;
}

export default function BookingRulesTab() {
  const { providerId, isLoading: roleLoading } = useRole();
  const queryClient = useQueryClient();
  const [config, setConfig] = useState<any>({
    availability_policy: {
      max_advance_booking_days: 30,
      min_notice_hours: 2,
      allow_same_day: true,
      buffer_minutes: 15,
      allow_walk_ins: false,
    },
    assignment_policy: {
      strategy: 'customer_choice',
      auto_assign_criteria: 'availability',
      show_specialist_photos: true,
      show_specialist_ratings: true,
      allow_specialist_change: true,
    },
    approval_policy: {
      require_approval: false,
      approval_type: 'instant',
      consultation_required: false,
      consultation_duration_minutes: 15,
    },
    cancellation_policy: {
      free_cancellation_hours: 24,
    },
    booking_policy: {
      reminders: [] as ReminderRule[],
    },
  });

  const validProviderId = providerId && providerId !== 'null' && providerId !== 'undefined' ? providerId : null;
  const canSave = !!validProviderId && !roleLoading;

  const { data: configData, isLoading } = useQuery({
    queryKey: ['booking-config', validProviderId],
    queryFn: () => apiClient.getBookingConfig(validProviderId!),
    enabled: !!validProviderId && !roleLoading,
  });

  useEffect(() => {
    if (configData?.configuration) {
      setConfig((prev: any) => {
        const merged = { ...prev };
        // Merge each policy section separately to preserve defaults
        if (configData.configuration.availability_policy) {
          merged.availability_policy = { ...prev.availability_policy, ...configData.configuration.availability_policy };
        }
        if (configData.configuration.assignment_policy) {
          merged.assignment_policy = { ...prev.assignment_policy, ...configData.configuration.assignment_policy };
        }
        if (configData.configuration.approval_policy) {
          merged.approval_policy = { ...prev.approval_policy, ...configData.configuration.approval_policy };
        }
        if (configData.configuration.cancellation_policy) {
          merged.cancellation_policy = { ...prev.cancellation_policy, ...configData.configuration.cancellation_policy };
        }
        if (configData.configuration.booking_policy) {
          merged.booking_policy = { ...prev.booking_policy, ...configData.configuration.booking_policy };
        }
        return merged;
      });
    }
  }, [configData]);

  const updateMutation = useMutation({
    mutationFn: (newConfig: any) => apiClient.updateBookingConfig(validProviderId!, newConfig),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['booking-config', validProviderId] });
    }
  });

  const handleChange = (category: string, field: string, value: any) => {
    setConfig((prev: any) => ({
      ...prev,
      [category]: {
        ...(prev[category] || {}),
        [field]: value
      }
    }));
  };

  const addReminder = () => {
    const newReminder: ReminderRule = {
      id: generateUUID(),
      label: '30 minutes before',
      type: 'appointment_reminder',
      lead_minutes: 30,
      channel: 'sms',
      enabled: true,
    };
    setConfig((prev: any) => ({
      ...prev,
      booking_policy: {
        ...prev.booking_policy,
        reminders: [...(prev.booking_policy.reminders || []), newReminder],
      },
    }));
  };

  const updateReminder = (id: string, field: keyof ReminderRule, value: any) => {
    setConfig((prev: any) => ({
      ...prev,
      booking_policy: {
        ...prev.booking_policy,
        reminders: prev.booking_policy.reminders.map((r: ReminderRule) =>
          r.id === id ? { ...r, [field]: value } : r
        ),
      },
    }));
  };

  const removeReminder = (id: string) => {
    setConfig((prev: any) => ({
      ...prev,
      booking_policy: {
        ...prev.booking_policy,
        reminders: prev.booking_policy.reminders.filter((r: ReminderRule) => r.id !== id),
      },
    }));
  };

  const toggleReminder = (id: string) => {
    setConfig((prev: any) => ({
      ...prev,
      booking_policy: {
        ...prev.booking_policy,
        reminders: prev.booking_policy.reminders.map((r: ReminderRule) =>
          r.id === id ? { ...r, enabled: !r.enabled } : r
        ),
      },
    }));
  };

  const getReminderLabel = (minutes: number) => {
    if (minutes < 60) return `${minutes} minutes before`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} before`;
    const days = Math.floor(hours / 24);
    return `${days} day${days > 1 ? 's' : ''} before`;
  };

  const handleSave = () => {
    if (canSave) updateMutation.mutate(config);
  };

  if (roleLoading || isLoading) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 text-[#FFD700] animate-spin" />
      </div>
    );
  }

  if (!validProviderId) {
    return (
      <div className="flex flex-col justify-center items-center py-20 text-center space-y-3">
        <ShieldAlert className="w-10 h-10 text-amber-400" />
        <h3 className="text-lg font-semibold text-text-primary">No salon selected</h3>
        <p className="text-sm text-text-secondary max-w-md">
          Salon context is not available. Please sign out and back in, or complete onboarding.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-semibold text-text-primary mb-1 flex items-center gap-2">
            <ShieldAlert className="w-5 h-5 text-[#FFD700]" />
            Booking Rules & Policies
          </h2>
          <p className="text-sm text-text-secondary">Configure how clients book appointments with your business.</p>
        </div>
        <button
          onClick={handleSave}
          disabled={!canSave || updateMutation.isPending}
          className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity"
        >
          {updateMutation.isPending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
          Save Changes
        </button>
      </div>

      {/* Booking Availability */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4">Booking Availability</h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">How far ahead can clients book?</label>
              <p className="text-xs text-text-secondary">Maximum days in advance clients can schedule appointments.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="1"
                max="365"
                value={config.availability_policy?.max_advance_booking_days || 30}
                onChange={(e) => handleChange('availability_policy', 'max_advance_booking_days', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">days</span>
            </div>
          </div>
          
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Minimum notice before appointment</label>
              <p className="text-xs text-text-secondary">How many hours in advance must clients book?</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                max="168"
                value={config.availability_policy?.min_notice_hours || 2}
                onChange={(e) => handleChange('availability_policy', 'min_notice_hours', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">hours</span>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Allow same-day bookings</label>
              <p className="text-xs text-text-secondary">Whether clients can book appointments for today.</p>
            </div>
            <button
              onClick={() => handleChange('availability_policy', 'allow_same_day', !config.availability_policy?.allow_same_day)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.availability_policy?.allow_same_day ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.availability_policy?.allow_same_day ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Time between appointments</label>
              <p className="text-xs text-text-secondary">Buffer time between consecutive bookings.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                max="120"
                value={config.availability_policy?.buffer_minutes || 15}
                onChange={(e) => handleChange('availability_policy', 'buffer_minutes', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">minutes</span>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Allow walk-ins</label>
              <p className="text-xs text-text-secondary">Whether clients can book without advance notice.</p>
            </div>
            <button
              onClick={() => handleChange('availability_policy', 'allow_walk_ins', !config.availability_policy?.allow_walk_ins)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.availability_policy?.allow_walk_ins ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.availability_policy?.allow_walk_ins ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>
        </div>
      </div>

      {/* Specialist Selection */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4 flex items-center gap-2">
          <UserCheck className="w-5 h-5 text-[#FFD700]" />
          Specialist Selection
        </h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Who chooses the specialist?</label>
              <p className="text-xs text-text-secondary">How clients select their service provider.</p>
            </div>
            <select
              value={config.assignment_policy?.strategy || 'customer_choice'}
              onChange={(e) => handleChange('assignment_policy', 'strategy', e.target.value)}
              className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-48 focus:outline-none focus:border-[#FFD700]"
            >
              <option value="customer_choice">Customer chooses</option>
              <option value="auto_assign">Automatically assign</option>
              <option value="reception_assign">Reception assigns</option>
              <option value="none">No specialist selection</option>
            </select>
          </div>

          {config.assignment_policy?.strategy === 'auto_assign' && (
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-1">How should we choose?</label>
                <p className="text-xs text-text-secondary">Criteria for automatic assignment.</p>
              </div>
              <select
                value={config.assignment_policy?.auto_assign_criteria || 'availability'}
                onChange={(e) => handleChange('assignment_policy', 'auto_assign_criteria', e.target.value)}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-48 focus:outline-none focus:border-[#FFD700]"
              >
                <option value="availability">Availability</option>
                <option value="rating">Rating</option>
                <option value="load_balance">Workload balance</option>
              </select>
            </div>
          )}

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Show specialist photos</label>
              <p className="text-xs text-text-secondary">Display specialist photos to clients.</p>
            </div>
            <button
              onClick={() => handleChange('assignment_policy', 'show_specialist_photos', !config.assignment_policy?.show_specialist_photos)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.assignment_policy?.show_specialist_photos ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.assignment_policy?.show_specialist_photos ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Show specialist ratings</label>
              <p className="text-xs text-text-secondary">Display specialist ratings to clients.</p>
            </div>
            <button
              onClick={() => handleChange('assignment_policy', 'show_specialist_ratings', !config.assignment_policy?.show_specialist_ratings)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.assignment_policy?.show_specialist_ratings ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.assignment_policy?.show_specialist_ratings ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>

          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Allow customer to change specialist</label>
              <p className="text-xs text-text-secondary">Whether clients can modify their specialist choice.</p>
            </div>
            <button
              onClick={() => handleChange('assignment_policy', 'allow_specialist_change', !config.assignment_policy?.allow_specialist_change)}
              className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                config.assignment_policy?.allow_specialist_change ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
              }`}
            >
              <div
                className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                  config.assignment_policy?.allow_specialist_change ? 'translate-x-6' : 'translate-x-0.5'
                }`}
              />
            </button>
          </div>
        </div>
      </div>

      {/* Booking Approval */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4 flex items-center gap-2">
          <CheckCircle className="w-5 h-5 text-[#FFD700]" />
          Booking Confirmation
        </h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Booking confirmation</label>
              <p className="text-xs text-text-secondary">How appointments are confirmed.</p>
            </div>
            <select
              value={config.approval_policy?.approval_type || 'instant'}
              onChange={(e) => {
                const value = e.target.value;
                handleChange('approval_policy', 'approval_type', value);
                handleChange('approval_policy', 'require_approval', value !== 'instant');
                handleChange('approval_policy', 'consultation_required', value === 'consultation');
              }}
              className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-48 focus:outline-none focus:border-[#FFD700]"
            >
              <option value="instant">Confirm immediately</option>
              <option value="manual">Require approval</option>
              <option value="consultation">Consultation required</option>
            </select>
          </div>

          {config.approval_policy?.consultation_required && (
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Consultation duration</label>
              <p className="text-xs text-text-secondary">Length of consultation appointments.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="5"
                max="120"
                value={config.approval_policy?.consultation_duration_minutes || 15}
                onChange={(e) => handleChange('approval_policy', 'consultation_duration_minutes', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">minutes</span>
            </div>
          </div>
          )}
        </div>
      </div>

      {/* Booking Reminders */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4 flex items-center gap-2">
          <Clock className="w-5 h-5 text-[#FFD700]" />
          Booking Reminders
        </h3>
        <p className="text-sm text-text-secondary mb-4">Choose when customers should be reminded about their upcoming appointments.</p>
        
        <div className="space-y-3">
          {config.booking_policy?.reminders?.map((reminder: ReminderRule) => (
            <div key={reminder.id} className="bg-black/30 border border-white/5 rounded-lg p-4 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <button
                    onClick={() => toggleReminder(reminder.id)}
                    className={`w-10 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                      reminder.enabled ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
                    }`}
                  >
                    <div
                      className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                        reminder.enabled ? 'translate-x-4' : 'translate-x-0.5'
                      }`}
                    />
                  </button>
                  <span className="text-sm font-medium text-text-primary">{reminder.label}</span>
                </div>
                <button
                  onClick={() => removeReminder(reminder.id)}
                  className="text-text-secondary hover:text-red-400 transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
              
              {reminder.enabled && (
                <div className="flex flex-col sm:flex-row sm:items-center gap-4 pt-3 border-t border-white/5">
                  <div className="flex-1">
                    <label className="block text-xs font-medium text-text-secondary mb-1">When should we remind customers?</label>
                    <div className="flex items-center gap-2">
                      <input 
                        type="number"
                        min="5"
                        max="10080"
                        value={reminder.lead_minutes}
                        onChange={(e) => updateReminder(reminder.id, 'lead_minutes', parseInt(e.target.value))}
                        className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-20 text-center focus:outline-none focus:border-[#FFD700]"
                      />
                      <select
                        value={reminder.lead_minutes >= 60 ? (reminder.lead_minutes >= 1440 ? 'days' : 'hours') : 'minutes'}
                        onChange={(e) => {
                          const unit = e.target.value;
                          const current = reminder.lead_minutes;
                          let newMinutes = current;
                          if (unit === 'minutes') newMinutes = Math.min(current, 59);
                          else if (unit === 'hours') newMinutes = current >= 60 ? current : 60;
                          else if (unit === 'days') newMinutes = current >= 1440 ? current : 1440;
                          updateReminder(reminder.id, 'lead_minutes', newMinutes);
                        }}
                        className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-28 focus:outline-none focus:border-[#FFD700]"
                      >
                        <option value="minutes">minutes</option>
                        <option value="hours">hours</option>
                        <option value="days">days</option>
                      </select>
                      <span className="text-xs text-text-secondary">before</span>
                    </div>
                  </div>
                  <div className="flex-1">
                    <label className="block text-xs font-medium text-text-secondary mb-1">Send via</label>
                    <select
                      value={reminder.channel}
                      onChange={(e) => updateReminder(reminder.id, 'channel', e.target.value)}
                      className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-full focus:outline-none focus:border-[#FFD700]"
                    >
                      <option value="sms">SMS</option>
                      <option value="email">Email</option>
                      <option value="in_app">In-app notification</option>
                    </select>
                  </div>
                </div>
              )}
            </div>
          ))}
          
          <button
            onClick={addReminder}
            className="flex items-center gap-2 px-4 py-3 bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 rounded-lg text-text-primary transition-colors"
          >
            <Plus className="w-4 h-4" />
            <span className="text-sm font-medium">Add reminder</span>
          </button>
        </div>
      </div>

      {/* Cancellation Policy */}
      <div className="bg-white/[0.02] border border-white/5 rounded-xl p-6">
        <h3 className="text-lg font-medium text-text-primary mb-4">Cancellation Policy</h3>
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <label className="block text-sm font-medium text-text-primary mb-1">Free cancellation window</label>
              <p className="text-xs text-text-secondary">Hours before appointment clients can cancel for free.</p>
            </div>
            <div className="flex items-center gap-2">
              <input 
                type="number"
                min="0"
                max="168"
                value={config.cancellation_policy?.free_cancellation_hours || 24}
                onChange={(e) => handleChange('cancellation_policy', 'free_cancellation_hours', parseInt(e.target.value))}
                className="bg-black border border-white/10 rounded-lg px-3 py-2 text-text-primary w-24 text-center focus:outline-none focus:border-[#FFD700]"
              />
              <span className="text-sm text-text-secondary">hours</span>
            </div>
          </div>
        </div>
      </div>
      
      {updateMutation.isSuccess && (
        <div className="text-green-400 text-sm text-right mt-2">
          Booking rules successfully updated!
        </div>
      )}
      {updateMutation.isError && (
        <div className="text-red-400 text-sm text-right mt-2">
          Failed to update booking rules. Please try again.
        </div>
      )}
    </div>
  );
}
