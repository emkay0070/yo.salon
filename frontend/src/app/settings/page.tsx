'use client';

import { useState } from 'react';
import { Settings, User, Bell, Lock, Globe, Palette, Save, CheckCircle, Crown, ChevronRight } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useTheme as useNextTheme } from 'next-themes';
import Link from 'next/link';
import { useRole } from '@/contexts/RoleContext';
import { Avatar } from '@/components/ui/Avatar';
import ThemeSwitcher from '@/components/ThemeSwitcher';

export default function SettingsPage() {
  const { user } = useRole();
  const [activeTab, setActiveTab] = useState('profile');
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [accentColor, setAccentColor] = useState('#FFD700');
  const [notificationSettings, setNotificationSettings] = useState({
    emailBookings: true,
    smsReminders: true,
    pushUpdates: false,
    weeklyReports: true,
  });
  const { theme: nextTheme, setTheme: setNextTheme } = useNextTheme();

  const tabs = [
    { id: 'profile', label: 'Profile', icon: User },
    { id: 'notifications', label: 'Notifications', icon: Bell },
    { id: 'security', label: 'Security', icon: Lock },
    { id: 'appearance', label: 'Appearance', icon: Palette },
    { id: 'branding', label: 'Branding', icon: Globe },
    { id: 'membership', label: 'Membership', icon: Crown },
  ];

  const SettingRow = ({ label, value, onClick }: { label: string, value?: string, onClick?: () => void }) => (
    <div 
      onClick={onClick}
      className={`flex items-center justify-between py-4 border-b border-white/[0.04] last:border-0 group ${onClick ? 'cursor-pointer hover:bg-white/[0.02] -mx-4 px-4 transition-colors' : ''}`}
    >
      <span className="text-sm font-medium text-text-primary/90">{label}</span>
      <div className="flex items-center gap-3">
        {value && <span className="text-sm text-text-secondary">{value}</span>}
        {onClick && <ChevronRight className="w-4 h-4 text-text-secondary/50 group-hover:text-text-primary/70 transition-colors" />}
      </div>
    </div>
  );

  return (
    <DashboardLayout>
      <div className="max-w-[1000px] mx-auto overflow-x-hidden font-sans">
        <div className="mb-10">
          <h1 className="text-3xl font-semibold text-text-primary tracking-tight">Settings</h1>
        </div>

        <div className="flex flex-col md:flex-row gap-10 items-start">
          {/* Sidebar */}
          <div className="w-full md:w-[220px] flex-shrink-0">
            <div className="flex flex-col gap-1">
              {tabs.map((tab) => (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors text-sm ${
                    activeTab === tab.id
                      ? 'bg-white/[0.06] text-text-primary font-medium'
                      : 'text-text-secondary hover:bg-white/[0.02] hover:text-text-primary'
                  }`}
                >
                  <tab.icon className={`w-4 h-4 shrink-0 ${activeTab === tab.id ? 'text-[#FFD700]' : 'text-text-secondary/70'}`} />
                  <span>{tab.label}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0 w-full">
            {activeTab === 'profile' && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                className="space-y-8"
              >
                <div>
                  <h2 className="text-lg font-medium text-text-primary mb-1">Your Profile</h2>
                  <p className="text-sm text-text-secondary mb-6">Manage your personal information and identity.</p>
                  
                  <div className="flex items-center gap-6 py-6 border-b border-white/[0.04]">
                    <Avatar 
                      name={user?.name || 'User'} 
                      size="2xl" 
                      shape="circle" 
                    />
                    <div>
                      <div className="flex gap-3 mt-1">
                        <button className="px-4 py-2 bg-white/[0.06] hover:bg-white/10 border border-white/10 rounded-lg text-sm font-medium text-text-primary transition-colors">
                          Upload new picture
                        </button>
                        <button className="px-4 py-2 text-text-secondary hover:text-red-400 text-sm font-medium transition-colors">
                          Remove
                        </button>
                      </div>
                    </div>
                  </div>

                  <div className="mt-2">
                    <SettingRow label="Full Name" value={user?.name || 'Not provided'} onClick={() => {}} />
                    <SettingRow label="Email Address" value={user?.email || 'Not provided'} onClick={() => {}} />
                    <SettingRow label="Phone Number" value={user?.phone || 'Add phone number'} onClick={() => {}} />
                    <SettingRow label="Role" value={user?.role || 'Owner'} />
                  </div>
                </div>
              </motion.div>
            )}

            {activeTab === 'notifications' && (
              <motion.div
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                className="space-y-6"
              >
                <h2 className="text-xl font-semibold text-text-primary mb-6">Notification Settings</h2>
                
                {[
                  { label: 'Email notifications for new bookings', key: 'emailBookings' },
                  { label: 'SMS notifications for reminders', key: 'smsReminders' },
                  { label: 'Push notifications for updates', key: 'pushUpdates' },
                  { label: 'Weekly summary reports', key: 'weeklyReports' },
                ].map((item) => (
                  <div key={item.key} className="flex items-center justify-between py-3 border-b border-border-light">
                    <span className="text-text-primary">{item.label}</span>
                    <button
                      onClick={() => setNotificationSettings({ ...notificationSettings, [item.key]: !notificationSettings[item.key as keyof typeof notificationSettings] })}
                      className={`w-12 h-6 rounded-full transition-colors flex items-center p-0.5 ${
                        notificationSettings[item.key as keyof typeof notificationSettings] ? 'bg-[#FFD700]' : 'bg-[#1a1a1a] border border-white/10'
                      }`}
                    >
                        <div
                          className={`w-5 h-5 rounded-full bg-[#0A0A0A] transition-transform ${
                            notificationSettings[item.key as keyof typeof notificationSettings] ? 'translate-x-6' : 'translate-x-0.5'
                          }`}
                        />
                      </button>
                    </div>
                  ))}
                </motion.div>
              )}

              {activeTab === 'security' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <h2 className="text-xl font-semibold text-text-primary mb-6">Security Settings</h2>
                  
                  <div>
                    <label className="block text-text-primary/70 font-medium mb-2">Current Password</label>
                    <input
                      type="password"
                      className="w-full px-4 py-3 bg-card border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
                    />
                  </div>

                  <div>
                    <label className="block text-text-primary/70 font-medium mb-2">New Password</label>
                    <input
                      type="password"
                      className="w-full px-4 py-3 bg-card border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
                    />
                  </div>

                  <div>
                    <label className="block text-text-primary/70 font-medium mb-2">Confirm New Password</label>
                    <input
                      type="password"
                      className="w-full px-4 py-3 bg-card border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
                    />
                  </div>

                  <button 
                    onClick={() => {
                      setSaveSuccess(true);
                      setTimeout(() => setSaveSuccess(false), 2000);
                    }}
                    className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian rounded-xl font-medium hover:opacity-90 transition-opacity"
                  >
                    <Save className="w-5 h-5" />
                    Update Password
                  </button>
                </motion.div>
              )}

              {activeTab === 'appearance' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <h2 className="text-xl font-semibold text-text-primary mb-6">Appearance Settings</h2>

                  <div>
                    <label className="block text-text-secondary font-medium mb-3">Theme Preset</label>
                    <ThemeSwitcher />
                  </div>

                  <div>
                    <label className="block text-text-primary/70 font-medium mb-3">Accent Color</label>
                    <div className="flex gap-4">
                      {['#FFD700', '#FF622B', '#2F7A5C', '#6366F1'].map((color) => (
                        <button
                          key={color}
                          onClick={() => setAccentColor(color)}
                          className="w-10 h-10 rounded-full border-2 transition-all hover:scale-110"
                          style={{
                            backgroundColor: color,
                            borderColor: accentColor === color ? 'white' : 'transparent',
                          }}
                        />
                      ))}
                    </div>
                  </div>
                </motion.div>
              )}

              {activeTab === 'branding' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <h2 className="text-xl font-semibold text-text-primary mb-6">Salon Branding</h2>

                  <div className="bg-gradient-to-br from-[#FF622B]/10 via-[#FF622B]/5 to-transparent border border-[#FF622B]/25 rounded-xl p-6">
                    <div className="flex items-start gap-4">
                      <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FF622B] to-[#FF8C5A] flex items-center justify-center flex-shrink-0">
                        <Globe className="w-6 h-6 text-white" />
                      </div>
                      <div className="flex-1">
                        <h3 className="text-text-primary font-semibold mb-1">Brand Experience</h3>
                        <p className="text-text-secondary text-sm mb-4">Customize your salon's visual identity with colors, fonts, and experience families.</p>
                        <Link
                          href="/settings/branding"
                          className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FF622B] to-[#FF8C5A] text-white rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                        >
                          Customize Branding
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                          </svg>
                        </Link>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Experience Family</p>
                        <p className="text-text-secondary text-xs">Choose your design language</p>
                      </div>
                      <Link href="/settings/branding" className="text-[#FF622B] text-sm font-medium hover:underline">
                        Customize →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Brand Colors</p>
                        <p className="text-text-secondary text-xs">Primary, secondary, and accent colors</p>
                      </div>
                      <Link href="/settings/branding" className="text-[#FF622B] text-sm font-medium hover:underline">
                        Customize →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Typography</p>
                        <p className="text-text-secondary text-xs">Heading and body fonts</p>
                      </div>
                      <Link href="/settings/branding" className="text-[#FF622B] text-sm font-medium hover:underline">
                        Customize →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Logo & Images</p>
                        <p className="text-text-secondary text-xs">Upload your salon logo and background</p>
                      </div>
                      <Link href="/settings/branding" className="text-[#FF622B] text-sm font-medium hover:underline">
                        Customize →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3">
                      <div>
                        <p className="text-text-primary font-medium">White Label</p>
                        <p className="text-text-secondary text-xs">Custom domain and white-label mode</p>
                      </div>
                      <Link href="/settings/branding" className="text-[#FF622B] text-sm font-medium hover:underline">
                        Customize →
                      </Link>
                    </div>
                  </div>
                </motion.div>
              )}

              {activeTab === 'membership' && (
                <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="space-y-6"
                >
                  <h2 className="text-xl font-semibold text-text-primary mb-6">Membership & Billing</h2>

                  <div className="bg-gradient-to-br from-[#FFD700]/10 via-[#FFD700]/5 to-transparent border border-[#FFD700]/25 rounded-xl p-6">
                    <div className="flex items-start gap-4">
                      <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center flex-shrink-0">
                        <Crown className="w-6 h-6 text-black" />
                      </div>
                      <div className="flex-1">
                        <h3 className="text-text-primary font-semibold mb-1">Your Workspace</h3>
                        <p className="text-text-secondary text-sm mb-4">Manage your subscription, view usage, and upgrade your plan.</p>
                        <Link
                          href="/settings/membership"
                          className="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity"
                        >
                          Manage Membership
                          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                          </svg>
                        </Link>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Current Plan</p>
                        <p className="text-text-secondary text-xs">View your current subscription details</p>
                      </div>
                      <Link href="/settings/membership" className="text-[#FFD700] text-sm font-medium hover:underline">
                        View →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Usage & Limits</p>
                        <p className="text-text-secondary text-xs">Monitor your resource usage</p>
                      </div>
                      <Link href="/settings/membership" className="text-[#FFD700] text-sm font-medium hover:underline">
                        View →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3 border-b border-border-light">
                      <div>
                        <p className="text-text-primary font-medium">Billing History</p>
                        <p className="text-text-secondary text-xs">View past invoices and payments</p>
                      </div>
                      <Link href="/settings/membership" className="text-[#FFD700] text-sm font-medium hover:underline">
                        View →
                      </Link>
                    </div>

                    <div className="flex items-center justify-between py-3">
                      <div>
                        <p className="text-text-primary font-medium">Payment Methods</p>
                        <p className="text-text-secondary text-xs">Manage your payment options</p>
                      </div>
                      <Link href="/settings/membership" className="text-[#FFD700] text-sm font-medium hover:underline">
                        View →
                      </Link>
                    </div>
                  </div>
                </motion.div>
              )}
          </div>
        </div>
      </div>

      {/* Success Toast */}
      <AnimatePresence>
        {saveSuccess && (
          <motion.div
            initial={{ opacity: 0, y: 50 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 50 }}
            className="fixed bottom-8 right-8 bg-green-500/20 border border-green-500/30 rounded-xl p-4 flex items-center gap-3"
          >
            <CheckCircle className="w-5 h-5 text-green-400" />
            <span className="text-green-400 font-medium">Settings saved successfully!</span>
          </motion.div>
        )}
      </AnimatePresence>
    </DashboardLayout>
  );
}
