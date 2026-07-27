import { useState } from 'react';
import { format } from 'date-fns';
import { Download, Filter, Search } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

const formatCurrency = (amount: number) =>
  new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', minimumFractionDigits: 0 }).format(Math.abs(amount));

interface LedgerTableProps {
  transactions: any[];
}

export default function LedgerTable({ transactions }: LedgerTableProps) {
  const [searchTerm, setSearchTerm] = useState('');
  
  const filteredTransactions = transactions.filter(tx => 
    tx.internal_reference?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    (tx.customer?.first_name || '').toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="bg-card border border-border-light rounded-3xl overflow-hidden flex flex-col">
      {/* Header & Controls */}
      <div className="p-6 border-b border-border-light flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-xl font-bold text-text-primary tracking-tight">Ledger</h2>
          <p className="text-text-secondary text-sm mt-1">Source of truth for all financial movements.</p>
        </div>
        
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="relative flex-1 sm:min-w-[250px]">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
            <input
              type="text"
              placeholder="Search reference or customer..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full bg-surface border border-border-light text-text-primary text-sm rounded-xl pl-10 pr-4 py-2.5 focus:outline-none focus:border-[#6C5CE7] transition-colors"
            />
          </div>
          <button className="flex items-center gap-2 px-4 py-2.5 bg-surface border border-border-light rounded-xl text-text-secondary hover:text-text-primary transition-colors text-sm font-medium">
            <Filter className="w-4 h-4" />
            <span className="hidden sm:inline">Filter</span>
          </button>
          <button className="flex items-center gap-2 px-4 py-2.5 bg-surface border border-border-light rounded-xl text-text-secondary hover:text-text-primary transition-colors text-sm font-medium">
            <Download className="w-4 h-4" />
            <span className="hidden sm:inline">Export</span>
          </button>
        </div>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-left text-sm text-text-secondary">
          <thead className="bg-surface/50 text-xs uppercase tracking-wider text-text-primary font-semibold border-b border-border-light">
            <tr>
              <th className="px-6 py-4">Date & Ref</th>
              <th className="px-6 py-4">Customer / Details</th>
              <th className="px-6 py-4 text-right">Gross</th>
              <th className="px-6 py-4 text-right">Fees</th>
              <th className="px-6 py-4 text-right">Net Amount</th>
              <th className="px-6 py-4 text-center">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border-light">
            <AnimatePresence>
              {filteredTransactions.map((tx, index) => {
                const totalFees = Number(tx.gateway_fee) + Number(tx.platform_fee) + Number(tx.tax_amount);
                const isRefund = tx.type === 'refund';
                
                return (
                  <motion.tr 
                    key={tx.id}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: index * 0.03 }}
                    className="hover:bg-surface/30 transition-colors"
                  >
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="font-medium text-text-primary">
                        {format(new Date(tx.created_at), 'MMM d, yyyy')}
                      </div>
                      <div className="text-xs font-mono text-text-secondary mt-1 tracking-wider">
                        {tx.internal_reference}
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="font-medium text-text-primary">
                        {tx.customer?.first_name ? `${tx.customer.first_name} ${tx.customer.last_name || ''}` : 'Walk-in / System'}
                      </div>
                      <div className="text-xs text-text-secondary mt-1 flex items-center gap-2">
                        <span className="px-1.5 py-0.5 bg-white/5 rounded text-[10px] uppercase border border-white/10">
                          {tx.paymentMethod?.display_name || tx.type}
                        </span>
                        {tx.notes && <span>{tx.notes}</span>}
                      </div>
                    </td>
                    <td className={`px-6 py-4 text-right font-medium ${isRefund ? 'text-red-400' : 'text-text-primary'}`}>
                       {isRefund ? '-' : ''}{formatCurrency(Number(tx.gross_amount))}
                    </td>
                    <td className="px-6 py-4 text-right text-orange-400 font-medium">
                       -{formatCurrency(totalFees)}
                       <div className="text-[10px] text-text-secondary mt-1">
                         (GW: {formatCurrency(Number(tx.gateway_fee))} | PF: {formatCurrency(Number(tx.platform_fee))})
                       </div>
                    </td>
                    <td className={`px-6 py-4 text-right font-bold ${isRefund ? 'text-red-400' : 'text-emerald-400'}`}>
                       {isRefund ? '-' : ''}{formatCurrency(Number(tx.net_amount))}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={`inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider ${
                        tx.status === 'completed' || tx.status === 'paid' ? 'bg-emerald-500/10 text-emerald-400' :
                        tx.status === 'refunded' ? 'bg-red-500/10 text-red-400' :
                        'bg-yellow-500/10 text-yellow-400'
                      }`}>
                        {tx.status}
                      </span>
                    </td>
                  </motion.tr>
                );
              })}
            </AnimatePresence>
            
            {filteredTransactions.length === 0 && (
              <tr>
                <td colSpan={6} className="px-6 py-12 text-center text-text-secondary">
                  No transactions found matching your criteria.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
