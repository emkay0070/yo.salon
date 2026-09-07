'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import Link from 'next/link';
import {
  Users,
  Search,
  Star,
  Scissors,
  MessageSquare,
  Calendar,
  Phone,
  Mail,
  ChevronRight,
  Loader2,
  AlertCircle,
  Heart,
  TrendingUp,
  Building2,
  Link as LinkIcon,
  CalendarCheck,
  UserPlus,
  Plus,
  X,
} from 'lucide-react';
import { useState } from 'react';
import { apiClient } from '@/lib/api-client';
import { useMutation } from '@tanstack/react-query';

type Client = {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  pivot: {
    is_favorite: boolean;
    is_following: boolean;
    total_bookings: number;
    total_spent: number;
    last_interaction_at: string | null;
    provider_id: string | null;
    relationship_origin: string | null;
    acquisition_source: string | null;
  };
};

export default function ClientsPage() {
  const { specialist, currentWorkplace } = useSpecialistAuth();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedClient, setSelectedClient] = useState<Client | null>(null);
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');

  const { data, isLoading, isError } = useQuery({
    queryKey: ['specialist-clients', currentWorkplace?.provider_slug],
    queryFn: async () => {
      if (!currentWorkplace?.provider_slug) {
        throw new Error('No workspace context available');
      }
      const json = await apiClient.get(`/v1/specialist-portal/customers?provider_slug=${currentWorkplace.provider_slug}`);
      return json.customers as Client[];
    },
    enabled: !!specialist && !!currentWorkplace?.provider_slug,
  });

  const inviteMutation = useMutation({
    mutationFn: async (customerId: string) => {
      return await apiClient.post('/v1/specialist-portal/customers/invitations', {
        customer_id: customerId,
        target_provider_id: currentWorkplace?.provider_id,
        channel: 'specialist_direct_invite',
      });
    },
    onSuccess: () => {
      setShowInviteModal(false);
      setInviteEmail('');
    },
  });

  const clients = data ?? [];

  const filteredClients = clients.filter(
    (c) =>
      c.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      c.phone?.includes(searchQuery) ||
      c.email?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const formatCurrency = (amount: number | null) => {
    if (!amount) return 'UGX 0';
    return `UGX ${Number(amount).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'No visits yet';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    });
  };

  const getRelationshipOriginBadge = (origin: string | null) => {
    if (!origin) return null;
    
    const badges = {
      'SALON': { label: 'Salon', icon: Building2, color: 'bg-blue-500/20 text-blue-400' },
      'INDEPENDENT': { label: 'Independent', icon: UserPlus, color: 'bg-purple-500/20 text-purple-400' },
      'PRE_EXISTING': { label: 'Pre-existing', icon: CalendarCheck, color: 'bg-green-500/20 text-green-400' },
    };
    
    const badge = badges[origin as keyof typeof badges];
    if (!badge) return null;
    
    const Icon = badge.icon;
    return (
      <span className={`flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium ${badge.color}`}>
        <Icon className="w-3 h-3" /> {badge.label}
      </span>
    );
  };

  const getAcquisitionSourceBadge = (source: string | null) => {
    if (!source) return null;
    
    const badges = {
      'BOOKING': { label: 'Booking', icon: Calendar, color: 'bg-amber-500/20 text-amber-400' },
      'PROFILE': { label: 'Profile', icon: UserPlus, color: 'bg-pink-500/20 text-pink-400' },
      'INVITATION': { label: 'Invitation', icon: LinkIcon, color: 'bg-cyan-500/20 text-cyan-400' },
      'DIRECT_LINK': { label: 'Direct Link', icon: LinkIcon, color: 'bg-orange-500/20 text-orange-400' },
      'REFERRAL': { label: 'Referral', icon: UserPlus, color: 'bg-emerald-500/20 text-emerald-400' },
    };
    
    const badge = badges[source as keyof typeof badges];
    if (!badge) return null;
    
    const Icon = badge.icon;
    return (
      <span className={`flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium ${badge.color}`}>
        <Icon className="w-3 h-3" /> {badge.label}
      </span>
    );
  };

  // ─── Client Detail View ───────────────────────────────────────────────────
  if (selectedClient) {
    const p = selectedClient.pivot;
    
    // Fetch detailed client data with assessments and notes
    const { data: clientDetails } = useQuery({
      queryKey: ['customer-details', selectedClient.id],
      queryFn: async () => {
        return await apiClient.get(`/v1/specialist-portal/customers/${selectedClient.id}/details`);
      },
      enabled: !!selectedClient,
    });

    return (
      <div className="p-4 sm:p-6 lg:p-8 space-y-6">
        {/* Back */}
        <button
          onClick={() => setSelectedClient(null)}
          className="flex items-center gap-2 text-sm text-text-secondary hover:text-text-primary transition-colors"
        >
          ← Back to clients
        </button>

        {/* Header card */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-start gap-5">
            <div className="w-20 h-20 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white text-2xl font-bold flex-shrink-0">
              {selectedClient.name?.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-2 flex-wrap">
                <h2 className="text-2xl font-bold text-text-primary">{selectedClient.name}</h2>
                {p.is_favorite && <Star className="w-5 h-5 text-gold fill-gold" />}
                {p.is_following && <Heart className="w-5 h-5 text-pink-400 fill-pink-400" />}
              </div>
              <div className="flex flex-wrap gap-4 text-sm text-text-secondary">
                {selectedClient.phone && (
                  <span className="flex items-center gap-1">
                    <Phone className="w-4 h-4" />
                    {selectedClient.phone}
                  </span>
                )}
                {selectedClient.email && (
                  <span className="flex items-center gap-1">
                    <Mail className="w-4 h-4" />
                    {selectedClient.email}
                  </span>
                )}
              </div>
            </div>
          </div>
        </motion.div>

        {/* Stats */}
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
          {[
            { label: 'Total Bookings', value: p.total_bookings ?? 0, icon: Calendar },
            { label: 'Total Spent',    value: formatCurrency(p.total_spent), icon: TrendingUp },
            { label: 'Last Visit',     value: formatDate(p.last_interaction_at), icon: Calendar },
          ].map((stat, i) => {
            const Icon = stat.icon;
            return (
              <motion.div
                key={stat.label}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.08 }}
                className="bg-card border border-border-light rounded-2xl p-4"
              >
                <Icon className="w-5 h-5 text-gold mb-2" />
                <p className="text-xl font-bold text-text-primary truncate">{stat.value}</p>
                <p className="text-sm text-text-secondary">{stat.label}</p>
              </motion.div>
            );
          })}
        </div>

        {/* Relationship badges */}
        {(p.is_favorite || p.is_following || p.relationship_origin || p.acquisition_source) && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="bg-card border border-border-light rounded-2xl p-6"
          >
            <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
              <Star className="w-5 h-5 text-gold" />
              Relationship
            </h3>
            <div className="flex gap-3 flex-wrap">
              {p.is_favorite && (
                <span className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gold/20 text-gold text-sm font-medium">
                  <Star className="w-3.5 h-3.5 fill-gold" /> Favourite
                </span>
              )}
              {p.is_following && (
                <span className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-pink-400/20 text-pink-400 text-sm font-medium">
                  <Heart className="w-3.5 h-3.5 fill-pink-400" /> Following
                </span>
              )}
              {getRelationshipOriginBadge(p.relationship_origin)}
              {getAcquisitionSourceBadge(p.acquisition_source)}
            </div>
          </motion.div>
        )}

        {/* Assessments */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
            <Scissors className="w-5 h-5 text-gold" />
            Assessments
          </h3>
          {clientDetails?.assessments && clientDetails.assessments.length > 0 ? (
            <div className="space-y-3">
              {clientDetails.assessments.map((assessment: any) => (
                <div key={assessment.id} className="bg-surface rounded-xl p-4">
                  <p className="text-sm text-text-secondary">{new Date(assessment.created_at).toLocaleDateString()}</p>
                  {assessment.notes && <p className="text-text-primary mt-2">{assessment.notes}</p>}
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-surface rounded-xl p-4 mb-4">
              <p className="text-text-secondary italic text-sm">No assessments yet for this client.</p>
            </div>
          )}
          <button className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white text-sm font-medium hover:opacity-90 transition-opacity">
            <Scissors className="w-4 h-4" />
            Add Assessment
          </button>
        </motion.div>

        {/* Notes */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-gold" />
            Notes
          </h3>
          {clientDetails?.notes && clientDetails.notes.length > 0 ? (
            <div className="space-y-3">
              {clientDetails.notes.map((note: any) => (
                <div key={note.id} className="bg-surface rounded-xl p-4">
                  <p className="text-sm text-text-secondary">{new Date(note.created_at).toLocaleString()}</p>
                  <p className="text-text-primary mt-2">{note.content}</p>
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-surface rounded-xl p-4 mb-4">
              <p className="text-text-secondary italic text-sm">No notes yet for this client.</p>
            </div>
          )}
          <button className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white text-sm font-medium hover:opacity-90 transition-opacity">
            <MessageSquare className="w-4 h-4" />
            Add Note
          </button>
        </motion.div>

        {/* Book Client Action */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          className="bg-gradient-to-br from-gold/20 to-amber-600/20 border border-gold/30 rounded-2xl p-6"
        >
          <div className="flex items-center justify-between">
            <div>
              <h3 className="font-semibold text-text-primary mb-1">Book an Appointment</h3>
              <p className="text-sm text-text-secondary">Schedule a new appointment for {selectedClient.name}</p>
            </div>
            <Link 
              href={`/bookings/new?customer_id=${selectedClient.id}&specialist_id=${specialist?.id}`}
              className="flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity"
            >
              <Calendar className="w-4 h-4" />
              Book Now
            </Link>
          </div>
        </motion.div>
      </div>
    );
  }

  // ─── Clients List View ────────────────────────────────────────────────────
  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Clients</h1>
          <p className="text-text-secondary">Your customer relationships</p>
        </div>
        {currentWorkplace?.provider_type === 'independent_specialist' && (
          <button
            onClick={() => setShowInviteModal(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity"
          >
            <Plus className="w-4 h-4" />
            Invite Customer
          </button>
        )}
      </div>

      {/* Search */}
      <div className="relative">
        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-text-secondary" />
        <input
          type="text"
          placeholder="Search clients by name, phone, or email..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          className="w-full pl-12 pr-4 py-3 bg-card border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold transition-colors"
        />
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex items-center justify-center py-20">
          <Loader2 className="w-8 h-8 text-gold animate-spin" />
        </div>
      )}

      {/* Error */}
      {isError && (
        <div className="bg-card border border-border-light rounded-2xl p-12 text-center">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-4" />
          <p className="text-text-secondary">Failed to load clients. Please try again.</p>
        </div>
      )}

      {/* List */}
      {!isLoading && !isError && (
        <div className="bg-card border border-border-light rounded-2xl p-6">
          <div className="flex items-center justify-between mb-6">
            <h3 className="font-semibold text-text-primary">
              {filteredClients.length} {filteredClients.length === 1 ? 'Client' : 'Clients'}
            </h3>
          </div>

          <div className="space-y-3">
            {filteredClients.map((client, index) => {
              const p = client.pivot;
              return (
                <motion.div
                  key={client.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.04 }}
                  onClick={() => setSelectedClient(client)}
                  className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light hover:border-gold/30 transition-colors cursor-pointer"
                >
                  <div className="flex items-center gap-4 min-w-0">
                    <div className="w-12 h-12 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold flex-shrink-0">
                      {client.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2 mb-0.5 flex-wrap">
                        <p className="font-medium text-text-primary truncate">{client.name}</p>
                        {p.is_favorite && <Star className="w-3.5 h-3.5 text-gold fill-gold flex-shrink-0" />}
                        {p.is_following && <Heart className="w-3.5 h-3.5 text-pink-400 fill-pink-400 flex-shrink-0" />}
                      </div>
                      <div className="flex items-center gap-2 flex-wrap">
                        <p className="text-sm text-text-secondary truncate">
                          {p.total_bookings ?? 0} bookings • {formatCurrency(p.total_spent)}
                        </p>
                        {getRelationshipOriginBadge(p.relationship_origin)}
                        {getAcquisitionSourceBadge(p.acquisition_source)}
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center gap-3 flex-shrink-0 ml-2">
                    <span className="text-xs text-text-secondary hidden sm:block">
                      {formatDate(p.last_interaction_at)}
                    </span>
                    <ChevronRight className="w-5 h-5 text-text-secondary" />
                  </div>
                </motion.div>
              );
            })}

            {filteredClients.length === 0 && clients.length > 0 && (
              <div className="text-center py-12">
                <Search className="w-12 h-12 text-text-secondary mx-auto mb-4" />
                <p className="text-text-secondary">No clients match your search</p>
              </div>
            )}

            {clients.length === 0 && (
              <div className="text-center py-12">
                <Users className="w-12 h-12 text-text-secondary mx-auto mb-4" />
                <p className="text-text-secondary font-medium">No clients yet</p>
                <p className="text-sm text-text-secondary mt-1">Clients will appear here after their first booking with you</p>
                {currentWorkplace?.provider_type === 'independent_specialist' && (
                  <button
                    onClick={() => setShowInviteModal(true)}
                    className="mt-4 flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity mx-auto"
                  >
                    <Plus className="w-4 h-4" />
                    Invite Your First Customer
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Invitation Modal */}
      {showInviteModal && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-card border border-border-light rounded-2xl p-6 w-full max-w-md">
            <div className="flex items-center justify-between mb-6">
              <h3 className="text-lg font-semibold text-text-primary">Invite Customer to Workspace</h3>
              <button
                onClick={() => setShowInviteModal(false)}
                className="p-2 rounded-lg hover:bg-surface text-text-secondary"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">
                  Customer Email
                </label>
                <input
                  type="email"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  placeholder="customer@example.com"
                  className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold transition-colors"
                />
              </div>

              <div className="bg-surface/50 rounded-xl p-4">
                <p className="text-sm text-text-secondary">
                  This will send an invitation to join your independent workspace. The customer can accept or decline the invitation.
                </p>
              </div>

              <div className="flex gap-3">
                <button
                  onClick={() => setShowInviteModal(false)}
                  className="flex-1 px-4 py-3 rounded-xl border border-border-light text-text-primary font-medium hover:bg-surface transition-colors"
                >
                  Cancel
                </button>
                <button
                  onClick={() => {
                    // For now, we'll just close the modal
                    // In a real implementation, you'd search for the customer by email first
                    setShowInviteModal(false);
                  }}
                  className="flex-1 px-4 py-3 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity"
                >
                  Send Invitation
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
