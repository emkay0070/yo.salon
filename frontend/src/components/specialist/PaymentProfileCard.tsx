import React from 'react';
import { CreditCard, Smartphone, Landmark, CheckCircle, Clock, Trash2, Edit } from 'lucide-react';
import { motion } from 'framer-motion';

export interface PaymentProfile {
  id: string;
  method: 'mtn' | 'airtel' | 'bank';
  label?: string;
  phone_number?: string;
  account_name?: string;
  account_number?: string;
  bank_name?: string;
  is_default: boolean;
  is_verified: boolean;
  verification_status: 'verified' | 'pending' | 'none';
  created_at: string;
}

interface PaymentProfileCardProps {
  profile: PaymentProfile;
  onEdit: (profile: PaymentProfile) => void;
  onRemove: (profile: PaymentProfile) => void;
  onMakeDefault: (profile: PaymentProfile) => void;
}

export default function PaymentProfileCard({ profile, onEdit, onRemove, onMakeDefault }: PaymentProfileCardProps) {
  const getIcon = () => {
    switch (profile.method) {
      case 'mtn':
      case 'airtel':
        return <Smartphone className="h-6 w-6 text-indigo-500" />;
      case 'bank':
        return <Landmark className="h-6 w-6 text-indigo-500" />;
      default:
        return <CreditCard className="h-6 w-6 text-indigo-500" />;
    }
  };

  const getTitle = () => {
    if (profile.method === 'mtn') return 'MTN Mobile Money';
    if (profile.method === 'airtel') return 'Airtel Money';
    if (profile.method === 'bank') return profile.bank_name || 'Bank Account';
    return 'Payment Method';
  };

  const getDestination = () => {
    if (profile.method === 'mtn' || profile.method === 'airtel') {
      return profile.phone_number;
    }
    return `${profile.account_number} (${profile.account_name})`;
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 5 }}
      animate={{ opacity: 1, y: 0 }}
      className={`relative flex items-center justify-between rounded-xl border p-4 shadow-sm transition-colors ${
        profile.is_default ? 'border-indigo-500 bg-indigo-50/30' : 'border-border-light bg-card'
      }`}
    >
      <div className="flex items-center gap-4">
        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100">
          {getIcon()}
        </div>
        <div>
          <div className="flex items-center gap-2">
            <h3 className="font-medium text-text-primary">
              {profile.label || getTitle()}
            </h3>
            {profile.is_default && (
              <span className="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">
                Default
              </span>
            )}
          </div>
          <p className="text-sm text-text-secondary">{getDestination()}</p>
          
          <div className="mt-1 flex items-center gap-1.5">
            {profile.verification_status === 'verified' ? (
              <span className="flex items-center text-xs font-medium text-green-600">
                <CheckCircle className="mr-1 h-3.5 w-3.5" />
                Verified
              </span>
            ) : (
              <span className="flex items-center text-xs font-medium text-amber-600">
                <Clock className="mr-1 h-3.5 w-3.5" />
                Pending verification
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="flex flex-col items-end gap-2">
        <div className="flex items-center gap-2">
          <button
            onClick={() => onEdit(profile)}
            className="rounded p-1.5 text-text-secondary hover:bg-surface hover:text-text-primary"
            title="Edit"
          >
            <Edit className="h-4 w-4" />
          </button>
          <button
            onClick={() => onRemove(profile)}
            className="rounded p-1.5 text-text-secondary hover:bg-red-50 hover:text-red-600"
            title="Remove"
          >
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
        {!profile.is_default && (
          <button
            onClick={() => onMakeDefault(profile)}
            className="text-xs font-medium text-indigo-600 hover:text-indigo-700"
          >
            Set as default
          </button>
        )}
      </div>
    </motion.div>
  );
}
