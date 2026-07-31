'use client';

import { motion } from 'framer-motion';
import { Wallet, Plus, History, ArrowUp, ArrowDown } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';

export default function WalletPage() {
  const { customer, salon } = usePortalAuth();

  const { data: walletData, isLoading } = useQuery({
    queryKey: ['portal-wallet'],
    queryFn: () => portalApiClient.get('/portal/wallet'),
    enabled: !!customer,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const wallet = walletData?.wallet || {};
  const transactions = walletData?.recent_transactions || [];

  return (
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-3xl font-bold text-text-primary mb-2">My Wallet</h1>
          <p className="text-text-secondary">Manage your balance and credits</p>
        </motion.div>

        {/* Balance Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bg-gradient-to-br from-gold to-dark-gold rounded-2xl p-8 text-obsidian"
        >
          <div className="flex items-center gap-3 mb-4">
            <Wallet className="w-8 h-8" />
            <span className="text-lg font-medium">Current Balance</span>
          </div>
          <p className="text-4xl font-bold mb-2">
            {wallet.currency === 'UGX' ? 'UGX ' : '$'}{wallet.balance?.toFixed(2) || '0.00'}
          </p>
          <p className="text-sm opacity-80">Available for bookings</p>
        </motion.div>

        {/* Quick Actions */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="grid grid-cols-2 gap-4"
        >
          <ActionButton
            icon={Plus}
            label="Add Funds"
            description="Coming Soon"
            onClick={() => alert('Coming Soon!')}
          />
          <ActionButton
            icon={History}
            label="Transaction History"
            description="View past transactions"
            onClick={() => document.getElementById('recent-transactions')?.scrollIntoView({ behavior: 'smooth' })}
          />
        </motion.div>

        {/* Recent Transactions */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="bg-surface border border-border-light rounded-2xl p-6"
          id="recent-transactions"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Recent Transactions</h2>
          {transactions.length > 0 ? (
            <div className="space-y-3">
              {transactions.map((transaction: any) => (
                <TransactionCard key={transaction.id} transaction={transaction} />
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <History className="w-12 h-12 text-text-secondary mx-auto mb-3" />
              <p className="text-text-secondary">No transactions yet</p>
            </div>
          )}
        </motion.div>
      </div>
  );
}

function TransactionCard({ transaction }: { transaction: any }) {
  const isCredit = transaction.type === 'credit';

  return (
    <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
      <div className="flex items-center gap-3">
        <div className={`w-10 h-10 rounded-xl flex items-center justify-center ${
          isCredit ? 'bg-emerald-500/10' : 'bg-red-500/10'
        }`}>
          {isCredit ? (
            <ArrowDown className="w-5 h-5 text-emerald-500" />
          ) : (
            <ArrowUp className="w-5 h-5 text-red-500" />
          )}
        </div>
        <div>
          <p className="font-medium text-text-primary text-sm">{transaction.notes || 'Transaction'}</p>
          <p className="text-xs text-text-secondary">
            {new Date(transaction.created_at).toLocaleDateString()}
          </p>
        </div>
      </div>
      <div className={`font-semibold ${isCredit ? 'text-emerald-500' : 'text-red-500'}`}>
        {isCredit ? '+' : '-'}{transaction.currency === 'UGX' ? 'UGX ' : '$'}{transaction.amount?.toFixed(2)}
      </div>
    </div>
  );
}

function ActionButton({ icon: Icon, label, description, onClick }: { icon: any, label: string, description: string, onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className="bg-surface border border-border-light rounded-2xl p-6 hover:border-gold/30 transition-all group text-left"
    >
      <div className="flex items-center gap-3 mb-3">
        <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center group-hover:bg-gold/20 transition-colors">
          <Icon className="w-5 h-5 text-gold" />
        </div>
        <span className="font-semibold text-text-primary">{label}</span>
      </div>
      <p className="text-sm text-text-secondary">{description}</p>
    </button>
  );
}
