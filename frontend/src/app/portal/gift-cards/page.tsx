'use client';

import { motion } from 'framer-motion';
import { Gift, Plus, Check, Send, Clock, CreditCard } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

export default function GiftCardsPage() {
  const { customer, salon } = usePortalAuth();
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = useState<'purchased' | 'redeemed'>('purchased');

  const { data: purchasedGiftCards, isLoading: purchasedLoading, isError: purchasedError } = useQuery({
    queryKey: ['purchased-gift-cards'],
    queryFn: () => portalApiClient.get('/portal/gift-cards/purchased'),
    enabled: !!customer,
  });

  const { data: redeemedGiftCards, isLoading: redeemedLoading, isError: redeemedError } = useQuery({
    queryKey: ['redeemed-gift-cards'],
    queryFn: () => portalApiClient.get('/portal/gift-cards/redeemed'),
    enabled: !!customer,
  });

  const purchaseMutation = useMutation({
    mutationFn: (data: { amount: number; message?: string }) =>
      portalApiClient.post('/portal/gift-cards/purchase', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchased-gift-cards'] });
      queryClient.invalidateQueries({ queryKey: ['portal-wallet'] });
    },
  });

  const redeemMutation = useMutation({
    mutationFn: (code: string) =>
      portalApiClient.post('/portal/gift-cards/redeem', { code }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchased-gift-cards'] });
      queryClient.invalidateQueries({ queryKey: ['redeemed-gift-cards'] });
      queryClient.invalidateQueries({ queryKey: ['portal-wallet'] });
    },
  });

  if (purchasedLoading || redeemedLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  if (purchasedError || redeemedError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary text-center">
          <p className="text-red-500 mb-2">Failed to load gift cards.</p>
          <button onClick={() => window.location.reload()} className="text-gold underline hover:text-dark-gold">Try again</button>
        </div>
      </div>
    );
  }

  return (
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-3xl font-bold text-text-primary mb-2">Gift Cards</h1>
          <p className="text-text-secondary">Purchase and redeem gift cards</p>
        </motion.div>

        {/* Purchase Gift Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bg-gradient-to-br from-red-500/10 to-orange-500/10 border border-red-500/20 rounded-2xl p-6"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Purchase Gift Card</h2>
          <PurchaseForm 
            onPurchase={(data: any) => purchaseMutation.mutate(data)}
            isPurchasing={purchaseMutation.isPending}
          />
        </motion.div>

        {/* Redeem Gift Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="bg-surface border border-border-light rounded-2xl p-6"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Redeem Gift Card</h2>
          <RedeemForm 
            onRedeem={(code: string) => redeemMutation.mutate(code)}
            isRedeeming={redeemMutation.isPending}
          />
        </motion.div>

        {/* Tabs */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
        >
          <div className="flex gap-2 mb-4">
            <button
              onClick={() => setActiveTab('purchased')}
              className={`px-4 py-2 rounded-full font-medium transition-colors ${
                activeTab === 'purchased'
                  ? 'bg-gold text-obsidian'
                  : 'bg-surface border border-border-light text-text-primary hover:border-gold/30'
              }`}
            >
              Purchased
            </button>
            <button
              onClick={() => setActiveTab('redeemed')}
              className={`px-4 py-2 rounded-full font-medium transition-colors ${
                activeTab === 'redeemed'
                  ? 'bg-gold text-obsidian'
                  : 'bg-surface border border-border-light text-text-primary hover:border-gold/30'
              }`}
            >
              Redeemed
            </button>
          </div>

          {activeTab === 'purchased' ? (
            <div className="space-y-3">
              {purchasedGiftCards && purchasedGiftCards.length > 0 ? (
                purchasedGiftCards.map((giftCard: any) => (
                  <PurchasedGiftCard key={giftCard.id} giftCard={giftCard} />
                ))
              ) : (
                <div className="text-center py-8">
                  <Gift className="w-12 h-12 text-text-secondary mx-auto mb-3" />
                  <p className="text-text-secondary">No purchased gift cards</p>
                </div>
              )}
            </div>
          ) : (
            <div className="space-y-3">
              {redeemedGiftCards && redeemedGiftCards.length > 0 ? (
                redeemedGiftCards.map((giftCard: any) => (
                  <RedeemedGiftCard key={giftCard.id} giftCard={giftCard} />
                ))
              ) : (
                <div className="text-center py-8">
                  <Check className="w-12 h-12 text-text-secondary mx-auto mb-3" />
                  <p className="text-text-secondary">No redeemed gift cards</p>
                </div>
              )}
            </div>
          )}
        </motion.div>
      </div>
  );
}

function PurchaseForm({ onPurchase, isPurchasing }: any) {
  const [amount, setAmount] = useState('');
  const [message, setMessage] = useState('');

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-text-primary mb-2">Amount</label>
        <input
          type="number"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          placeholder="Enter amount"
          className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl focus:border-gold focus:outline-none transition-colors"
        />
      </div>
      <div>
        <label className="block text-sm font-medium text-text-primary mb-2">Message (optional)</label>
        <textarea
          value={message}
          onChange={(e) => setMessage(e.target.value)}
          placeholder="Add a personal message"
          rows={3}
          className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl focus:border-gold focus:outline-none transition-colors resize-none"
        />
      </div>
      <button
        onClick={() => onPurchase({ amount: parseFloat(amount), message })}
        disabled={!amount || isPurchasing}
        className="w-full px-6 py-3 bg-gold text-obsidian rounded-full font-semiboldhover:bg-dark-gold transition-colors disabled:opacity-50"
      >
        {isPurchasing ? 'Processing...' : 'Purchase Gift Card'}
      </button>
    </div>
  );
}

function RedeemForm({ onRedeem, isRedeeming }: any) {
  const [code, setCode] = useState('');

  return (
    <div className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-text-primary mb-2">Gift Card Code</label>
        <input
          type="text"
          value={code}
          onChange={(e) => setCode(e.target.value.toUpperCase())}
          placeholder="Enter 12-character code"
          className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl focus:border-gold focus:outline-none transition-colors uppercase"
        />
      </div>
      <button
        onClick={() => onRedeem(code)}
        disabled={!code || isRedeeming}
        className="w-full px-6 py-3 bg-emerald-500 text-white rounded-full font-semibold hover:bg-emerald-600 transition-colors disabled:opacity-50"
      >
        {isRedeeming ? 'Redeeming...' : 'Redeem Gift Card'}
      </button>
    </div>
  );
}

function PurchasedGiftCard({ giftCard }: any) {
  return (
    <div className="bg-surface border border-border-light rounded-xl p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
            <Gift className="w-5 h-5 text-red-500" />
          </div>
          <div>
            <p className="font-mono font-semibold text-text-primary">{giftCard.code}</p>
            <p className="text-xs text-text-secondary">
              {giftCard.expires_at ? `Expires ${new Date(giftCard.expires_at).toLocaleDateString()}` : 'No expiration'}
            </p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-lg font-bold text-red-500">${giftCard.amount}</p>
          <p className="text-xs text-text-secondary">
            {giftCard.redeemed ? 'Redeemed' : 'Available'}
          </p>
        </div>
      </div>
      {giftCard.message && (
        <p className="text-sm text-text-secondary italic">"{giftCard.message}"</p>
      )}
    </div>
  );
}

function RedeemedGiftCard({ giftCard }: any) {
  return (
    <div className="bg-gradient-to-r from-emerald-500/10 to-emerald-600/10 border border-emerald-500/20 rounded-xl p-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
            <Check className="w-5 h-5 text-emerald-500" />
          </div>
          <div>
            <p className="font-mono font-semibold text-text-primary">{giftCard.code}</p>
            <p className="text-xs text-text-secondary">
              Redeemed {new Date(giftCard.redeemed_at).toLocaleDateString()}
            </p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-lg font-bold text-emerald-500">${giftCard.amount}</p>
          <p className="text-xs text-text-secondary">Redeemed</p>
        </div>
      </div>
    </div>
  );
}
