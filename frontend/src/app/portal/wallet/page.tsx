'use client';

import { motion } from 'framer-motion';
import { CreditCard, Smartphone, Gift, History, Plus, ArrowRight, CheckCircle, Clock, AlertCircle, Star, Award } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

export default function WalletPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const [activeTab, setActiveTab] = useState<'loyalty' | 'history' | 'promo' | 'gift'>('loyalty');

  const { data: walletData, isLoading } = useQuery({
    queryKey: ['portal-wallet'],
    queryFn: () => portalApiClient.get('/portal/wallet'),
    enabled: !!customer,
  });

  const queryClient = useQueryClient();

  const { data: loyaltyHistory } = useQuery({
    queryKey: ['loyalty-history'],
    queryFn: () => portalApiClient.get('/portal/wallet/transactions'),
    enabled: !!customer && activeTab === 'history',
  });

  const applyPromoMutation = useMutation({
    mutationFn: (code: string) => portalApiClient.post('/portal/wallet/promo/apply', { code }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-wallet'] });
      alert('Promo code applied successfully!');
    },
    onError: (error: any) => {
      alert(error.response?.data?.message || 'Failed to apply promo code');
    },
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const loyaltyPoints = walletData?.data?.loyalty_points || 0;
  const tier = walletData?.data?.tier || 'Bronze';

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Loyalty & Rewards</h1>
        <p className="text-text-secondary">Earn points and unlock exclusive benefits</p>
      </motion.div>

      {/* Loyalty Points Card */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="relative overflow-hidden rounded-2xl p-8"
        style={{
          background: `linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)20)`,
          border: `1px solid var(--brand-primary, #FFD700)30`,
          borderRadius: 'var(--brand-border-radius, 16px)',
          boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
        }}
      >
        <div className="relative z-10">
          <div className="flex items-center gap-3 mb-4">
            <Star className="w-6 h-6" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            <span className="text-text-secondary">Loyalty Points</span>
          </div>
          <div className="text-4xl font-bold mb-2" style={{ color: 'var(--brand-primary, #FFD700)' }}>
            {loyaltyPoints.toLocaleString()} pts
          </div>
          <div className="flex items-center gap-2">
            <Award className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            <span className="text-text-secondary text-sm font-medium">{tier} Member</span>
          </div>
        </div>
        <div className="absolute right-0 top-0 w-1/3 h-full opacity-10">
          <Star className="w-full h-full" />
        </div>
      </motion.div>

      {/* Quick Actions */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="grid grid-cols-2 sm:grid-cols-3 gap-4"
      >
        <WalletAction
          icon={History}
          label="Point History"
          onClick={() => setActiveTab('history')}
        />
        <WalletAction
          icon={Gift}
          label="Promo Codes"
          onClick={() => setActiveTab('promo')}
        />
        <WalletAction
          icon={Gift}
          label="Gift Cards"
          onClick={() => setActiveTab('gift')}
        />
      </motion.div>

      {/* Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'loyalty', label: 'Loyalty' },
          { id: 'history', label: 'Point History' },
          { id: 'promo', label: 'Promo Codes' },
          { id: 'gift', label: 'Gift Cards' },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            className={`flex-shrink-0 px-6 py-3 rounded-2xl text-sm font-medium transition-all ${
              activeTab === tab.id
                ? 'text-white'
                : 'bg-surface border border-border-light text-text-secondary hover:border-gold/30'
            }`}
            style={{
              ...(activeTab === tab.id
                ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                : { borderRadius: 'var(--brand-border-radius, 16px)' })
            }}
          >
            {tab.label}
          </button>
        ))}
      </motion.div>

      {/* Tab Content */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
      >
        {activeTab === 'loyalty' && <LoyaltyContent loyaltyPoints={loyaltyPoints} tier={tier} />}
        {activeTab === 'history' && <LoyaltyHistoryContent loyaltyHistory={loyaltyHistory} />}
        {activeTab === 'promo' && <PromoCodesContent applyPromoMutation={applyPromoMutation} />}
        {activeTab === 'gift' && <GiftCardsContent />}
      </motion.div>
    </div>
  );
}

function WalletAction({ icon: Icon, label, onClick }: { icon: any, label: string, onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className="bg-surface border border-border-light rounded-2xl p-4 hover:border-gold/30 transition-all group text-left"
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
    >
      <div className="flex flex-col items-center gap-2">
        <div
          className="w-10 h-10 rounded-xl flex items-center justify-center group-hover:opacity-80 transition-colors"
          style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}
        >
          <Icon className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
        </div>
        <span className="text-sm font-medium text-text-primary">{label}</span>
      </div>
    </button>
  );
}

function LoyaltyContent({ loyaltyPoints, tier }: { loyaltyPoints: number, tier: string }) {
  const tierBenefits: Record<string, string[]> = {
    Bronze: ['Earn 1 point per UGX 1,000 spent', 'Birthday discounts', 'Priority booking'],
    Silver: ['Earn 2 points per UGX 1,000 spent', 'Free rescheduling', 'Exclusive offers'],
    Gold: ['Earn 3 points per UGX 1,000 spent', 'VIP treatment', 'Complimentary services'],
    Platinum: ['Earn 5 points per UGX 1,000 spent', 'Personal stylist', 'Exclusive events'],
  };

  const nextTier = tier === 'Bronze' ? 'Silver' : tier === 'Silver' ? 'Gold' : tier === 'Gold' ? 'Platinum' : null;
  const pointsNeeded = tier === 'Bronze' ? 100 : tier === 'Silver' ? 500 : tier === 'Gold' ? 1000 : null;

  return (
    <div className="space-y-4">
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h2 className="text-lg font-semibold text-text-primary mb-4">Current Benefits</h2>
        <div className="space-y-3">
          {tierBenefits[tier]?.map((benefit, index) => (
            <div key={index} className="flex items-center gap-3 p-3 bg-surface border border-border-light rounded-xl">
              <CheckCircle className="w-5 h-5 text-emerald-500" />
              <span className="text-text-primary">{benefit}</span>
            </div>
          ))}
        </div>
      </div>

      {nextTier && pointsNeeded && (
        <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
          <h2 className="text-lg font-semibold text-text-primary mb-4">Next Tier: {nextTier}</h2>
          <div className="space-y-3">
            <div className="flex justify-between text-sm">
              <span className="text-text-secondary">Points needed</span>
              <span className="font-semibold text-text-primary">{pointsNeeded - loyaltyPoints} pts</span>
            </div>
            <div className="w-full bg-surface border border-border-light rounded-full h-2">
              <div
                className="h-2 rounded-full transition-all"
                style={{
                  width: `${Math.min((loyaltyPoints / pointsNeeded) * 100, 100)}%`,
                  backgroundColor: 'var(--brand-primary, #FFD700)',
                }}
              />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

function LoyaltyHistoryContent({ loyaltyHistory }: { loyaltyHistory: any }) {
  const history = loyaltyHistory?.data?.loyalty_history || [];

  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <h2 className="text-lg font-semibold text-text-primary mb-4">Point History</h2>
      {history.length === 0 ? (
        <div className="text-center py-8">
          <History className="w-16 h-16 text-text-secondary mx-auto mb-4" />
          <p className="text-text-secondary">No point history yet</p>
        </div>
      ) : (
        <div className="space-y-3">
          {history.map((item: any) => (
            <LoyaltyHistoryItem
              key={item.id}
              description={item.description || 'Points earned'}
              points={item.points || 0}
              date={new Date(item.created_at).toLocaleString()}
              type={item.type || 'booking'}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function LoyaltyHistoryItem({ description, points, date, type }: { description: string, points: number, date: string, type: string }) {
  const isPositive = points > 0;

  return (
    <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
      <div className="flex items-center gap-4">
        <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: isPositive ? 'var(--brand-primary, #FFD700)20' : 'rgba(239, 68, 68, 0.1)' }}>
          <Star className={`w-5 h-5 ${isPositive ? '' : 'text-red-500'}`} style={{ color: isPositive ? 'var(--brand-primary, #FFD700)' : undefined }} />
        </div>
        <div>
          <p className="font-medium text-text-primary">{description}</p>
          <p className="text-sm text-text-secondary">{date}</p>
        </div>
      </div>
      <div className="text-right">
        <p className={`font-semibold ${isPositive ? 'text-emerald-500' : 'text-red-500'}`}>
          {isPositive ? '+' : ''}{points} pts
        </p>
      </div>
    </div>
  );
}

function PromoCodesContent({ applyPromoMutation }: { applyPromoMutation: any }) {
  const [code, setCode] = useState('');

  const handleApply = () => {
    if (code.trim()) {
      applyPromoMutation.mutate(code.trim());
      setCode('');
    }
  };

  return (
    <div className="space-y-4">
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h2 className="text-lg font-semibold text-text-primary mb-4">Apply Promo Code</h2>
        <div className="flex gap-2">
          <input
            type="text"
            placeholder="Enter promo code"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            className="flex-1 px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold/50"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          />
          <button
            onClick={handleApply}
            disabled={applyPromoMutation.isPending}
            className="px-6 py-3 text-white font-medium rounded-xl"
            style={{ backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            {applyPromoMutation.isPending ? 'Applying...' : 'Apply'}
          </button>
        </div>
      </div>

      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h2 className="text-lg font-semibold text-text-primary mb-4">Available Offers</h2>
        <div className="text-center py-8">
          <Gift className="w-16 h-16 text-text-secondary mx-auto mb-4" />
          <p className="text-text-secondary mb-2">No active offers</p>
          <p className="text-sm text-text-secondary">Check back later for promotions</p>
        </div>
      </div>
    </div>
  );
}

function PromoCard({ code, description, expiry }: { code: string, description: string, expiry: string }) {
  return (
    <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
      <div>
        <p className="font-semibold text-text-primary">{code}</p>
        <p className="text-sm text-text-secondary">{description}</p>
        <p className="text-xs text-text-secondary">{expiry}</p>
      </div>
      <button className="px-4 py-2 text-sm font-medium rounded-lg" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}>
        Apply
      </button>
    </div>
  );
}

function GiftCardsContent() {
  return (
    <div className="space-y-4">
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-text-primary">Your Gift Cards</h2>
          <button className="flex items-center gap-2 text-sm" style={{ color: 'var(--brand-primary, #FFD700)' }}>
            <Plus className="w-4 h-4" />
            Redeem
          </button>
        </div>
        <div className="text-center py-8">
          <Gift className="w-16 h-16 text-text-secondary mx-auto mb-4" />
          <p className="text-text-secondary mb-2">No gift cards yet</p>
          <p className="text-sm text-text-secondary">Redeem a gift card to get started</p>
        </div>
      </div>

      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <h2 className="text-lg font-semibold text-text-primary mb-4">Send a Gift Card</h2>
        <p className="text-text-secondary mb-4">Share the gift of grooming with friends and family</p>
        <button className="w-full px-6 py-3 text-white font-medium rounded-xl" style={{ backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}>
          Send Gift Card
        </button>
      </div>
    </div>
  );
}
