'use client';

import { useEffect, useState } from 'react';
import { useRef } from 'react';
import {
  Users, Search, Plus, Phone, Mail, Calendar, X, Star, Scissors,
  Clock, DollarSign, MessageSquare, Edit, ArrowUpRight, Activity,
  Sparkles, ChevronRight, ChevronLeft, MoreVertical, Upload, Crown, Link2, Loader2
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { CopyLinkModal } from '@/components/ui/CopyLinkModal';
import { motion, AnimatePresence, useScroll, useTransform } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useQuery } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Customer {
  id: string;
  name: string;
  phone: string;
  email?: string;
  visits: number;
  notes?: string;
  isVip?: boolean;
  lastVisit?: string;
  totalSpent?: number;
  favoriteService?: string;
  preferredStylist?: string;
  customerSince?: string;
  status?: 'active' | 'inactive' | 'new';
  hasOutstandingBalance?: boolean;
  photo?: string | null;
  photo_url?: string | null;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function getInitials(name: string) {
  return name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase();
}

function getAvatarGradient(id: string) {
  const palettes = [
    'from-violet-500 to-purple-700',
    'from-rose-500 to-pink-700',
    'from-amber-500 to-orange-600',
    'from-cyan-500 to-blue-600',
    'from-emerald-500 to-teal-700',
    'from-fuchsia-500 to-purple-700',
  ];
  const hash = id.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return palettes[hash % palettes.length];
}

// Cinematic photo pool for drawer headers
const HEADER_PHOTOS = [
  '/images/salon-styling.jpg',
  '/images/salon-luxury.png',
  '/images/salon-mirror.jpg',
  '/images/salon-hero.jpg',
  '/images/salon-auth.jpg',
  '/images/salon-station.jpg',
];

function getHeaderPhoto(id: string) {
  const hash = id.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return HEADER_PHOTOS[hash % HEADER_PHOTOS.length];
}

// ─── Customer Drawer ──────────────────────────────────────────────────────────

function CustomerDrawer({
  customer,
  onClose,
  onSendInvitation,
}: {
  customer: Customer;
  onClose: () => void;
  onSendInvitation: (customerId: string) => void;
}) {
  const [activeTab, setActiveTab] = useState<'overview' | 'history' | 'notes'>('overview');
  const [inviting, setInviting] = useState(false);
  const [inviteError, setInviteError] = useState('');
  const gradient = getAvatarGradient(customer.id);
  const headerPhoto = getHeaderPhoto(customer.id);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Disable background scrolling when drawer is open
  useEffect(() => {
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = 'auto';
    };
  }, []);

  const { scrollY } = useScroll({ container: scrollRef });
  
  // Fade in sticky navbar and fade out main header text
  const stickyNavOpacity = useTransform(scrollY, [150, 200], [0, 1]);
  const mainHeaderOpacity = useTransform(scrollY, [50, 150], [1, 0]);

  const { data: customerBookings = [] } = useQuery({
    queryKey: ['customer-bookings', customer.id],
    queryFn: () => apiClient.getCustomerBookings(customer.id),
    enabled: !!customer.id,
  });

  const TABS = [
    { key: 'overview', label: 'Overview' },
    { key: 'history',  label: 'History'  },
    { key: 'notes',    label: 'Notes'    },
  ] as const;

  return (
    <>
      {/* Backdrop */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        onClick={onClose}
        className="fixed inset-0 bg-[var(--color-overlay)] backdrop-blur-sm z-50"
      />

      {/* Drawer */}
      <motion.div
        initial={{ x: '100%' }}
        animate={{ x: 0 }}
        exit={{ x: '100%' }}
        transition={{ type: 'spring', damping: 32, stiffness: 280 }}
        className="fixed top-0 right-0 h-full w-full max-w-[520px] bg-[var(--color-surface)] border-l border-[var(--color-border-medium)] z-[60] shadow-2xl flex flex-col"
      >
        {/* ── Sticky Navbar (Fades in on scroll) ──────────────── */}
        <motion.div 
          style={{ opacity: stickyNavOpacity }}
          className="absolute top-0 left-0 right-0 h-[72px] bg-[var(--color-surface)]/95 backdrop-blur-md border-b border-[var(--color-border-light)] z-30 flex items-center px-5 gap-3 pointer-events-none"
        >
          <div className={`w-9 h-9 rounded-full bg-gradient-to-br ${gradient} flex items-center justify-center text-sm font-bold text-[var(--color-text-primary)] shadow-sm border border-white/15`}>
            {getInitials(customer.name)}
          </div>
          <h2 className="text-base font-bold text-[var(--color-text-primary)] truncate">{customer.name}</h2>
          {customer.isVip && <Crown className="w-3.5 h-3.5 text-[var(--color-gold)] shrink-0" />}
        </motion.div>

        {/* Close Button - always visible */}
        <button
          onClick={onClose}
          className="absolute top-5 right-5 p-2 bg-[var(--color-surface)]/50 backdrop-blur-md border border-[var(--color-border-light)] rounded-full text-[var(--color-text-primary)]/80 hover:text-[var(--color-text-primary)] hover:bg-[var(--color-card)] transition-all z-40"
        >
          <X className="w-4 h-4" />
        </button>

        {/* ── Scrollable Body ───────────────────────────────────── */}
        <div ref={scrollRef} className="flex-1 overflow-y-auto custom-scrollbar pb-24">
          
          {/* ── Cinematic Header ────────────────────────────────── */}
          <div className="relative h-[280px] shrink-0 overflow-hidden">
            {/* Photo */}
            <img
              src={headerPhoto}
              alt=""
              className="absolute inset-0 w-full h-full object-cover"
              style={{ objectPosition: 'center 30%' }}
            />
            {/* Scrim */}
            <div className="absolute inset-0 bg-gradient-to-b from-black/20 via-black/40 to-surface" />
            {/* Gold tint for VIP */}
            {customer.isVip && (
              <div className="absolute inset-0 bg-gradient-to-br from-[#FFD700]/10 to-transparent" />
            )}
            
            <motion.div style={{ opacity: mainHeaderOpacity }} className="absolute inset-0 pointer-events-none">
              {/* Status chip */}
              {customer.status && (
                <div className="absolute top-5 left-5 pointer-events-auto">
                  <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border ${
                    customer.status === 'active'   ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400' :
                    customer.status === 'new'      ? 'bg-blue-500/20 border-blue-500/30 text-blue-400' :
                                                     'bg-white/10 border-[var(--color-border-light)] text-[var(--color-text-primary)]/60'
                  }`}>
                    {customer.status === 'active' ? '● Active' : customer.status === 'new' ? '✦ New' : '○ Inactive'}
                  </div>
                </div>
              )}

              {/* Avatar + info */}
              <div className="absolute bottom-0 left-0 right-0 px-6 pb-6 pointer-events-auto">
                <div className="relative w-fit mb-3">
                  <div className={`w-[72px] h-[72px] rounded-2xl bg-gradient-to-br ${gradient} flex items-center justify-center text-2xl font-bold text-[var(--color-text-primary)] shadow-2xl border-2 border-white/15`}>
                    {getInitials(customer.name)}
                  </div>
                  {/* VIP crown */}
                  {customer.isVip && (
                    <div className="absolute -top-2 -right-2 w-6 h-6 bg-[#FFD700] rounded-full flex items-center justify-center shadow-lg">
                      <Crown className="w-3 h-3 text-black" />
                    </div>
                  )}
                </div>

                <div className="flex items-start justify-between">
                  <div>
                    <h2 className="text-[22px] font-bold text-[var(--color-text-primary)] tracking-tight mb-0.5 drop-shadow-md">
                      {customer.name}
                    </h2>
                    <p className="text-[var(--color-text-secondary)] text-sm">
                      Client since {customer.customerSince || '2024'}
                      {customer.lastVisit && <> · Last visit {customer.lastVisit.toLowerCase()}</>}
                    </p>
                  </div>
                  {customer.isVip && (
                    <span className="shrink-0 flex items-center gap-1 px-2.5 py-1 bg-[#FFD700]/15 border border-[#FFD700]/30 rounded-full text-[var(--color-gold)] text-xs font-bold">
                      <Star className="w-3 h-3 fill-current" /> VIP
                    </span>
                  )}
                </div>
              </div>
            </motion.div>
          </div>

          {/* ── Quick Actions ───────────────────────────────────── */}
        <div className="px-5 py-4 flex gap-2 shrink-0">
          <button className="flex-1 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/10">
            Book Appointment
          </button>
          <button className="p-2.5 bg-[var(--color-surface)] text-[var(--color-text-secondary)] rounded-xl hover:bg-[var(--color-card)] hover:text-[var(--color-text-primary)] transition-colors border border-[var(--color-border-medium)]">
            <Phone className="w-5 h-5" />
          </button>
          <button className="p-2.5 bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 rounded-xl hover:bg-emerald-500/20 transition-colors">
            <MessageSquare className="w-5 h-5" />
          </button>
          <button className="p-2.5 bg-[var(--color-surface)] text-[var(--color-text-secondary)] rounded-xl hover:bg-[var(--color-card)] hover:text-[var(--color-text-primary)] transition-colors border border-[var(--color-border-medium)]">
            <MoreVertical className="w-5 h-5" />
          </button>
        </div>

        {/* ── Portal Invite Banner ─────────────────────────────── */}
        <div className="mx-5 mb-2 p-4 rounded-2xl border border-blue-500/25 bg-blue-500/5 flex items-center gap-3">
          <div className="p-2 rounded-full bg-blue-500/15 flex-shrink-0">
            <Link2 className="w-4 h-4 text-blue-400" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-semibold text-[var(--color-text-primary)]">Invite to Client Portal</p>
            <p className="text-xs text-[var(--color-text-secondary)] mt-0.5">Send a secure one-time link for them to create their account</p>
            {inviteError && <p className="text-xs text-red-400 mt-1">{inviteError}</p>}
          </div>
          <button
            onClick={async () => {
              setInviting(true);
              setInviteError('');
              try {
                await onSendInvitation(customer.id);
              } catch {
                setInviteError('Failed to generate link. Try again.');
              } finally {
                setInviting(false);
              }
            }}
            disabled={inviting}
            className="flex-shrink-0 flex items-center gap-1.5 px-3.5 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-60"
          >
            {inviting ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Link2 className="w-3.5 h-3.5" />}
            {inviting ? 'Generating…' : 'Generate Link'}
          </button>
        </div>

        {/* ── Contact strip ───────────────────────────────────── */}
        <div className="mx-5 bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl px-4 py-3 flex items-center gap-6 shrink-0">
          <span className="flex items-center gap-2 text-sm text-[var(--color-text-primary)]/70">
            <Phone className="w-3.5 h-3.5 text-[var(--color-gold)]" /> {customer.phone}
          </span>
          {customer.email && (
            <span className="flex items-center gap-2 text-sm text-[var(--color-text-primary)]/70 truncate">
              <Mail className="w-3.5 h-3.5 text-[var(--color-gold)]" />
              <span className="truncate">{customer.email}</span>
            </span>
          )}
        </div>

        {/* ── Tabs ────────────────────────────────────────────── */}
        <div className="px-5 mt-4 flex gap-1 border-b border-[var(--color-border-medium)] shrink-0">
          {TABS.map(tab => (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
                activeTab === tab.key
                  ? 'border-[#FFD700] text-[var(--color-text-primary)]'
                  : 'border-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:border-white/20'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {/* ── Tab Body ────────────────────────────────────────── */}
        <div className="flex-1 px-5 py-6 space-y-6">
          <AnimatePresence mode="wait">
            {activeTab === 'overview' && (
              <motion.div
                key="overview"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.2 }}
                className="space-y-6"
              >
                {/* Stats row */}
                <div className="grid grid-cols-3 gap-3">
                  {[
                    { label: 'Visits',    value: customer.visits },
                    { label: 'Spent',     value: customer.totalSpent ? `UGX ${(customer.totalSpent/1000).toFixed(0)}K` : '—' },
                    { label: 'Since',     value: customer.customerSince || '2024' },
                  ].map(({ label, value }) => (
                    <div key={label} className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-3.5 text-center">
                      <p className="text-[var(--color-text-primary)] font-bold text-lg">{value}</p>
                      <p className="text-[var(--color-text-secondary)] text-xs mt-0.5">{label}</p>
                    </div>
                  ))}
                </div>

                {/* Recent visits */}
                <div>
                  <h3 className="text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
                    <Clock className="w-3.5 h-3.5 text-[var(--color-gold)]" /> Recent Visits
                  </h3>
                  {customerBookings.length > 0 ? (
                    <div className="relative pl-4 border-l border-[var(--color-border-light)] space-y-4">
                      {customerBookings.slice(0, 4).map((booking: any, index: number) => (
                        <div key={booking.id} className="relative">
                          <div className={`absolute -left-[21px] top-1.5 w-2.5 h-2.5 rounded-full border-2 border-[#111] ${index === 0 ? 'bg-[#FFD700] shadow-[0_0_8px_rgba(255,215,0,0.5)]' : 'bg-white/20'}`} />
                          <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-3">
                            <div className="flex justify-between items-start mb-1">
                              <p className="text-[var(--color-text-primary)] text-sm font-medium">{booking.service?.name || 'Service'}</p>
                              <span className={`text-[10px] px-1.5 py-0.5 rounded border font-medium ${
                                booking.payment_status === 'paid'
                                  ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                  : 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'
                              }`}>
                                {booking.payment_status === 'paid' ? 'Paid' : 'Pending'}
                              </span>
                            </div>
                            <p className="text-[var(--color-text-secondary)] text-xs">{booking.date ? new Date(booking.date).toLocaleDateString() : 'N/A'} · {booking.staff?.name || 'Staff'}</p>
                          </div>
                        </div>
                      ))}
                      {customerBookings.length > 4 && (
                        <button className="text-[var(--color-gold)] text-xs font-medium flex items-center gap-1 hover:opacity-80 ml-1">
                          View all {customerBookings.length} visits <ArrowUpRight className="w-3 h-3" />
                        </button>
                      )}
                    </div>
                  ) : (
                    <div className="bg-[var(--color-surface)] border border-[var(--color-border-medium)] rounded-xl p-6 flex flex-col items-center gap-3 text-center">
                      <div className="w-10 h-10 rounded-full bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center">
                        <Scissors className="w-4 h-4 text-[var(--color-text-primary)]/20" />
                      </div>
                      <div>
                        <p className="text-[var(--color-text-primary)]/60 text-sm font-medium">No visits yet</p>
                        <p className="text-[var(--color-text-secondary)] text-xs mt-1">Book their first appointment</p>
                      </div>
                    </div>
                  )}
                </div>

                {/* Insights */}
                {customerBookings.length > 0 && (
                  <div>
                    <h3 className="text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-2">
                      <Sparkles className="w-3.5 h-3.5 text-[var(--color-gold)]" /> Insights
                    </h3>
                    <div className="bg-[#FFD700]/5 border border-[#FFD700]/20 rounded-xl p-4 space-y-3">
                      <div className="flex justify-between items-center text-sm">
                        <span className="text-[var(--color-text-secondary)]">Total visits</span>
                        <span className="text-[var(--color-text-primary)] font-semibold">{customerBookings.length}</span>
                      </div>
                      <div className="flex justify-between items-center text-sm">
                        <span className="text-[var(--color-text-secondary)]">Total spent</span>
                        <span className="text-[var(--color-text-primary)] font-semibold">UGX {customerBookings.reduce((sum: number, b: any) => sum + (b.service?.price || 0), 0).toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between items-center text-sm">
                        <span className="text-[var(--color-text-secondary)]">Avg per visit</span>
                        <span className="text-[var(--color-text-primary)] font-semibold">
                          UGX {Math.round(customerBookings.reduce((sum: number, b: any) => sum + (b.service?.price || 0), 0) / customerBookings.length).toLocaleString()}
                        </span>
                      </div>
                      {customer.preferredStylist && (
                        <div className="flex justify-between items-center text-sm">
                          <span className="text-[var(--color-text-secondary)]">Preferred stylist</span>
                          <span className="text-[var(--color-text-primary)] font-semibold">{customer.preferredStylist}</span>
                        </div>
                      )}
                    </div>
                  </div>
                )}
              </motion.div>
            )}

            {activeTab === 'history' && (
              <motion.div
                key="history"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.2 }}
                className="space-y-3"
              >
                {customerBookings.length === 0 ? (
                  <div className="bg-[var(--color-surface)] border border-[var(--color-border-medium)] rounded-xl p-8 flex flex-col items-center gap-3 text-center">
                    <div className="w-12 h-12 rounded-full bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center">
                      <Calendar className="w-5 h-5 text-[var(--color-text-primary)]/20" />
                    </div>
                    <div>
                      <p className="text-[var(--color-text-primary)]/60 font-medium text-sm">No booking history</p>
                      <p className="text-[var(--color-text-secondary)] text-xs mt-1">History will appear here after their first visit</p>
                    </div>
                  </div>
                ) : (
                  customerBookings.map((booking: any) => (
                    <div key={booking.id} className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-4">
                      <div className="flex justify-between items-start mb-2">
                        <p className="text-[var(--color-text-primary)] font-medium text-sm">{booking.service?.name || 'Service'}</p>
                        <span className={`text-[10px] px-1.5 py-0.5 rounded border font-medium ${
                          booking.payment_status === 'paid'
                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                            : 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'
                        }`}>
                          {booking.payment_status === 'paid' ? 'Paid' : 'Pending'}
                        </span>
                      </div>
                      <div className="flex items-center gap-3 text-xs text-[var(--color-text-secondary)]">
                        <span className="flex items-center gap-1"><Calendar className="w-3 h-3" /> {booking.date ? new Date(booking.date).toLocaleDateString() : 'N/A'}</span>
                        <span>·</span>
                        <span>{booking.staff?.name || 'Staff'}</span>
                        {booking.service?.price && <><span>·</span><span className="text-[var(--color-gold)]">UGX {booking.service.price.toLocaleString()}</span></>}
                      </div>
                    </div>
                  ))
                )}
              </motion.div>
            )}

            {activeTab === 'notes' && (
              <motion.div
                key="notes"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.2 }}
              >
                <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-5">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-[var(--color-gold)] text-sm font-semibold">Client Notes</h3>
                    <button className="text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] transition-colors">
                      <Edit className="w-4 h-4" />
                    </button>
                  </div>
                    <p className="text-[var(--color-text-primary)]/80 text-sm leading-relaxed whitespace-pre-line">
                      {customer.notes || 'No notes for this client yet. Click edit to add notes.'}
                    </p>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        </div>
      </motion.div>
    </>
  );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function CustomersPage() {
  const { salonId } = useRole();
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [mounted, setMounted] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  
  // State for invite modal
  const [inviteModalOpen, setInviteModalOpen] = useState(false);
  const [inviteLink, setInviteLink] = useState('');

  const [selectedFilter, setSelectedFilter] = useState('all');
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 8;
  const [formData, setFormData] = useState({ name: '', phone: '', email: '', notes: '', photo: null as File | null, photo_preview: '' });

  useEffect(() => { setMounted(true); }, []);

  const { data, isLoading } = useQuery({
    queryKey: ['customers', salonId],
    queryFn: () => apiClient.getCustomers({ salon_id: salonId }),
    enabled: !!salonId,
  });

  useEffect(() => {
    if (data) setCustomers(data);
  }, [data]);

  const filteredCustomers = customers.filter(customer => {
    const matchesSearch =
      customer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      customer.phone.includes(searchTerm) ||
      (customer.email && customer.email.toLowerCase().includes(searchTerm.toLowerCase()));
    const matchesFilter =
      selectedFilter === 'all' ||
      (selectedFilter === 'vip' && customer.isVip) ||
      (selectedFilter === 'new' && customer.status === 'new') ||
      (selectedFilter === 'inactive' && customer.status === 'inactive');
    return matchesSearch && matchesFilter;
  });

  const totalPages = Math.max(1, Math.ceil(filteredCustomers.length / itemsPerPage));
  const paginatedCustomers = filteredCustomers.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  useEffect(() => { setCurrentPage(1); }, [searchTerm, selectedFilter]);

  const stats = {
    total: customers.length,
    active: customers.filter(c => c.status === 'active' || !c.status).length,
    new: customers.filter(c => c.status === 'new').length,
    vip: customers.filter((c: any) => c.isVip || c.is_vip).length,
  };

  const handleAddCustomer = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const data = new FormData();
      data.append('name', formData.name);
      data.append('phone', formData.phone);
      if (formData.email) data.append('email', formData.email);
      if (formData.notes) data.append('notes', formData.notes);
      if (salonId) data.append('salon_id', salonId);
      if (formData.photo) data.append('photo', formData.photo);
      
      const newCustomer = await apiClient.createCustomer(data);
      setCustomers([newCustomer, ...customers]);
      setIsModalOpen(false);
      setFormData({ name: '', phone: '', email: '', notes: '', photo: null, photo_preview: '' });
    } catch (err) {
      console.error('Failed to create customer:', err);
    }
  };

  const handleSendInvitation = async (customerId: string) => {
    try {
      const result = await apiClient.createInvitation({ role: 'customer', target_id: customerId });
      const inviteUrl = `${window.location.origin}/portal/invite/${result.invitation.token}`;
      setInviteLink(inviteUrl);
      setInviteModalOpen(true);
    } catch (err) {
      console.error('Failed to create invitation:', err);
      alert('Failed to create invitation. Please try again.');
    }
  };

  const handleGenerateGeneralInvite = async () => {
    try {
      const result = await apiClient.createInvitation({ role: 'customer' });
      const inviteUrl = `${window.location.origin}/portal/invite/${result.invitation.token}`;
      setInviteLink(inviteUrl);
      setInviteModalOpen(true);
    } catch (err) {
      console.error('Failed to create invitation:', err);
      alert('Failed to create invitation. Please try again.');
    }
  };

  if (!mounted) return null;

  return (
    <DashboardLayout>
      <div className="max-w-[1400px] mx-auto font-sans pb-12 overflow-x-hidden">

        {/* ── Page Header ─────────────────────────────────────── */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-[var(--color-text-primary)] tracking-tight">Customers</h1>
            <p className="text-[var(--color-text-secondary)] text-sm mt-1">
              <span className="text-[var(--color-text-primary)] font-medium">{stats.total}</span> total ·{' '}
              <span className="text-[var(--color-gold)] font-medium">{stats.vip}</span> VIP ·{' '}
              <span className="text-emerald-400 font-medium">{stats.new}</span> new
            </p>
          </div>
          <div className="flex items-center gap-2 w-full sm:w-auto">
            <button
              onClick={handleGenerateGeneralInvite}
              className="flex items-center gap-2 px-4 py-2.5 bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-xl font-medium hover:bg-blue-500/25 transition-colors text-sm"
            >
              <Link2 className="w-4 h-4" />
              <span>Invite via Link</span>
            </button>
            <button className="flex items-center gap-2 px-4 py-2.5 bg-[var(--color-card)] text-[var(--color-text-primary)] border border-[var(--color-border-light)] rounded-xl font-medium hover:bg-white/8 transition-colors text-sm">
              <Upload className="w-4 h-4" />
              <span className="hidden sm:inline">Export</span>
            </button>
            <button
              onClick={() => setIsModalOpen(true)}
              className="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/10 text-sm"
            >
              <Plus className="w-4 h-4" /> New Customer
            </button>
          </div>
        </div>

        {/* ── Stats Row ──────────────────────────────────────── */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
          {[
            { label: 'Total Clients',  value: stats.total,  color: 'text-[var(--color-text-primary)]'        },
            { label: 'Active',         value: stats.active, color: 'text-emerald-400'  },
            { label: 'New This Month', value: stats.new,    color: 'text-blue-400'     },
            { label: 'VIP Members',    value: stats.vip,    color: 'text-[var(--color-gold)]'    },
          ].map(({ label, value, color }) => (
            <div key={label} className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-4">
              <p className={`text-2xl font-bold ${color}`}>{value}</p>
              <p className="text-[var(--color-text-secondary)] text-xs mt-0.5">{label}</p>
            </div>
          ))}
        </div>

        {/* ── Search + Filters ───────────────────────────────── */}
        <div className="flex flex-col sm:flex-row gap-3 mb-5">
          <div className="relative flex-1">
            <Search className="w-4 h-4 text-[var(--color-text-secondary)] absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder="Search clients by name, phone or email..."
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl text-[var(--color-text-primary)] placeholder-[#A0A0A0] focus:outline-none focus:border-[#FFD700]/40 transition-colors text-sm"
            />
          </div>
          <div className="flex gap-2">
            {['all', 'vip', 'new', 'inactive'].map(filter => (
              <button
                key={filter}
                onClick={() => setSelectedFilter(filter)}
                className={`px-3.5 py-2 rounded-xl font-medium text-xs capitalize transition-all ${
                  selectedFilter === filter
                    ? 'bg-[#FFD700]/20 text-[var(--color-gold)] border border-[#FFD700]/30'
                    : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:bg-white/8 hover:text-[var(--color-text-primary)] border border-[var(--color-border-medium)]'
                }`}
              >
                {filter}
              </button>
            ))}
          </div>
        </div>

        {/* ── Customer List ──────────────────────────────────── */}
        <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-2xl overflow-hidden">
          {paginatedCustomers.length === 0 ? (
            /* ── Luxury empty state ── */
            <div className="relative overflow-hidden">
              <img
                src="/images/salon-dark.jpg"
                alt=""
                className="absolute inset-0 w-full h-full object-cover opacity-[0.06]"
              />
              <div className="relative flex flex-col items-center justify-center py-20 px-8 text-center gap-5">
                <div className="w-16 h-16 rounded-2xl bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center">
                  <Users className="w-7 h-7 text-[var(--color-text-primary)]/20" />
                </div>
                <div>
                  <h3 className="text-[var(--color-text-primary)]/70 font-semibold text-lg">Your client book is empty</h3>
                  <p className="text-[var(--color-text-secondary)] text-sm mt-1 max-w-xs">
                    Add your first client to start building relationships that last.
                  </p>
                </div>
                <button
                  onClick={() => setIsModalOpen(true)}
                  className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold text-sm hover:opacity-90 transition-opacity"
                >
                  <Plus className="w-4 h-4" /> Add First Client
                </button>
              </div>
            </div>
          ) : (
            /* ── List rows ── */
            <div className="divide-y divide-white/5">
              {paginatedCustomers.map(customer => (
                <motion.div
                  key={customer.id}
                  whileHover={{ backgroundColor: 'rgba(255,255,255,0.04)' }}
                  onClick={() => setSelectedCustomer(customer)}
                  className="px-5 py-4 flex items-center justify-between gap-3 cursor-pointer transition-colors group"
                >
                  {/* Avatar + Name */}
                  <div className="flex items-center gap-3.5 min-w-0 flex-1">
                    <div className="relative shrink-0">
                      <div className={`w-10 h-10 rounded-full bg-gradient-to-br ${getAvatarGradient(customer.id)} flex items-center justify-center text-sm font-bold text-[var(--color-text-primary)]`}>
                        {getInitials(customer.name)}
                      </div>
                      {customer.status === 'active' && (
                        <div className="absolute bottom-0 right-0 w-2.5 h-2.5 bg-emerald-500 border-2 border-[#141414] rounded-full" />
                      )}
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-[var(--color-text-primary)] font-medium text-sm truncate">{customer.name}</span>
                        {customer.isVip && (
                          <span className="shrink-0 inline-flex items-center gap-0.5 bg-[#FFD700]/10 text-[var(--color-gold)] text-[10px] px-1.5 py-0.5 rounded font-semibold border border-[#FFD700]/20">
                            <Crown className="w-2.5 h-2.5" /> VIP
                          </span>
                        )}
                        {customer.status === 'new' && (
                          <span className="shrink-0 bg-blue-500/10 text-blue-400 text-[10px] px-1.5 py-0.5 rounded font-semibold border border-blue-500/20">New</span>
                        )}
                      </div>
                      <p className="text-[var(--color-text-secondary)] text-xs mt-0.5 truncate">{customer.phone}</p>
                    </div>
                  </div>

                  {/* Stats */}
                  <div className="flex items-center gap-5 sm:gap-8 shrink-0 text-right">
                    <div className="hidden sm:block">
                      <p className="text-[var(--color-text-primary)] font-medium text-sm">{customer.visits}</p>
                      <p className="text-[var(--color-text-secondary)] text-xs">Visits</p>
                    </div>
                    <div>
                      <p className="text-[var(--color-text-primary)] font-medium text-sm">
                        {customer.totalSpent ? `UGX ${(customer.totalSpent / 1000).toFixed(0)}K` : '—'}
                      </p>
                      <p className="text-[var(--color-text-secondary)] text-xs">Spent</p>
                    </div>
                    <p className="text-[var(--color-text-secondary)] text-xs hidden md:block">{customer.lastVisit || '—'}</p>
                    <ChevronRight className="w-4 h-4 text-[var(--color-text-secondary)] group-hover:text-[var(--color-text-primary)] transition-colors" />
                  </div>
                </motion.div>
              ))}
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-4 border-t border-[var(--color-border-medium)] flex items-center justify-between">
              <p className="text-[var(--color-text-secondary)] text-xs">
                {(currentPage - 1) * itemsPerPage + 1}–{Math.min(currentPage * itemsPerPage, filteredCustomers.length)} of {filteredCustomers.length}
              </p>
              <div className="flex items-center gap-1">
                <button onClick={() => setCurrentPage(p => Math.max(1, p - 1))} disabled={currentPage === 1} className="p-1.5 text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] disabled:opacity-30 transition-colors">
                  <ChevronLeft className="w-4 h-4" />
                </button>
                {Array.from({ length: Math.min(totalPages, 5) }).map((_, i) => (
                  <button
                    key={i}
                    onClick={() => setCurrentPage(i + 1)}
                    className={`w-7 h-7 flex items-center justify-center rounded-lg text-xs font-medium transition-colors ${
                      currentPage === i + 1
                        ? 'bg-[#FFD700]/20 text-[var(--color-gold)] border border-[#FFD700]/30'
                        : 'text-[var(--color-text-secondary)] hover:bg-white/8'
                    }`}
                  >
                    {i + 1}
                  </button>
                ))}
                <button onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))} disabled={currentPage === totalPages} className="p-1.5 text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] disabled:opacity-30 transition-colors">
                  <ChevronRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ── Customer Drawer ──────────────────────────────────── */}
      <AnimatePresence>
        {selectedCustomer && (
          <CustomerDrawer
            customer={selectedCustomer}
            onClose={() => setSelectedCustomer(null)}
            onSendInvitation={handleSendInvitation}
          />
        )}
      </AnimatePresence>

      {/* ── Add Customer Modal (Two-panel Experience Screen) ─── */}
      <AnimatePresence>
        {isModalOpen && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-[var(--color-overlay)] backdrop-blur-md z-50 flex items-center justify-center p-4"
            onClick={() => setIsModalOpen(false)}
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.96, y: 12 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              transition={{ type: 'spring', damping: 28, stiffness: 280 }}
              onClick={e => e.stopPropagation()}
              className="bg-[#161616] border border-[var(--color-border-light)] rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden flex flex-col sm:flex-row"
            >
              {/* ── Left: Cinematic photo ── */}
              <div className="relative hidden sm:block w-[240px] shrink-0 overflow-hidden">
                <img
                  src="/images/salon-mirror.jpg"
                  alt=""
                  className="absolute inset-0 w-full h-full object-cover"
                  style={{ objectPosition: 'center 20%' }}
                />
                <div className="absolute inset-0 bg-gradient-to-r from-transparent to-[#161616]" />
                <div className="absolute inset-0 bg-gradient-to-b from-black/30 to-black/50" />
                <div className="absolute bottom-8 left-5 right-5">
                  <p className="text-[var(--color-gold)] text-xs font-semibold uppercase tracking-widest mb-2">New Client</p>
                  <p className="text-[var(--color-text-primary)] text-lg font-semibold leading-snug">
                    Every great story starts with a name.
                  </p>
                </div>
              </div>

              {/* ── Right: Form ── */}
              <div className="flex-1 p-6">
                <div className="flex items-center justify-between mb-5">
                  <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">Add New Client</h2>
                  <button
                    onClick={() => setIsModalOpen(false)}
                    className="p-2 bg-[var(--color-card)] hover:bg-white/10 border border-[var(--color-border-light)] rounded-lg transition-colors"
                  >
                    <X className="w-4 h-4 text-[var(--color-text-secondary)]" />
                  </button>
                </div>

                <form onSubmit={handleAddCustomer} className="space-y-4">
                  {/* Photo Upload */}
                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-2">Client Photo</label>
                    <div className="flex items-center gap-4">
                      <div className="w-16 h-16 rounded-xl bg-black/20 border border-[var(--color-border-light)] overflow-hidden flex items-center justify-center shrink-0">
                        {formData.photo_preview ? (
                          <img src={formData.photo_preview} alt="Preview" className="w-full h-full object-cover" />
                        ) : (
                          <Upload className="w-5 h-5 text-[var(--color-text-secondary)]" />
                        )}
                      </div>
                      <div className="flex-1">
                        <label className="cursor-pointer inline-flex items-center justify-center px-4 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-lg text-[var(--color-text-primary)] hover:bg-white/10 transition-colors text-sm font-medium">
                          <span>Choose Photo</span>
                          <input
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => {
                              const file = e.target.files?.[0];
                              if (file) {
                                setFormData({
                                  ...formData,
                                  photo: file,
                                  photo_preview: URL.createObjectURL(file)
                                });
                              }
                            }}
                          />
                        </label>
                        <p className="text-[11px] text-[var(--color-text-secondary)] mt-1.5">Max 2MB. Optional.</p>
                      </div>
                    </div>
                  </div>
                  {/* Full Name */}
                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Full Name</label>
                    <input
                      type="text"
                      required
                      value={formData.name}
                      onChange={e => setFormData({ ...formData, name: e.target.value })}
                      className="w-full px-4 py-3 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all text-sm"
                      placeholder="Jane Smith"
                    />
                  </div>

                  {/* Phone */}
                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Phone Number</label>
                    <input
                      type="tel"
                      required
                      value={formData.phone}
                      onChange={e => setFormData({ ...formData, phone: e.target.value })}
                      className="w-full px-4 py-3 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all text-sm"
                      placeholder="+256 700 000 000"
                    />
                  </div>

                  {/* Email */}
                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">
                      Email <span className="text-[var(--color-text-primary)]/30 normal-case font-normal">(optional)</span>
                    </label>
                    <input
                      type="email"
                      value={formData.email}
                      onChange={e => setFormData({ ...formData, email: e.target.value })}
                      className="w-full px-4 py-3 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all text-sm"
                      placeholder="jane@example.com"
                    />
                  </div>

                  {/* Notes */}
                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">
                      Notes <span className="text-[var(--color-text-primary)]/30 normal-case font-normal">(optional)</span>
                    </label>
                    <textarea
                      value={formData.notes}
                      onChange={e => setFormData({ ...formData, notes: e.target.value })}
                      rows={2}
                      className="w-full px-4 py-3 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[#FFD700]/50 focus:bg-white/8 transition-all resize-none text-sm"
                      placeholder="Allergies, preferences..."
                    />
                  </div>

                  <div className="flex gap-3 pt-2">
                    <button
                      type="button"
                      onClick={() => setIsModalOpen(false)}
                      className="flex-1 px-4 py-3 bg-transparent border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] hover:bg-[var(--color-card)] transition-colors font-medium text-sm"
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="flex-1 px-4 py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/20 text-sm"
                    >
                      Add Client
                    </button>
                  </div>
                </form>
              </div>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>
      
      <CopyLinkModal 
        isOpen={inviteModalOpen}
        onClose={() => setInviteModalOpen(false)}
        title="Invite Client to Portal"
        description="Share this secure invitation link with your client. When they sign up, their portal account will be automatically linked to their historical profile."
        link={inviteLink}
      />
    </DashboardLayout>
  );
}
