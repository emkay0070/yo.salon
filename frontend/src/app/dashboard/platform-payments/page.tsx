'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { CreditCard, Phone, Building2, DollarSign, Shield, Edit2, Save, X, Check, AlertCircle } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface PlatformPaymentMethod {
  id: string;
  name: string;
  type: string;
  verification_mode: string;
  is_active: boolean;
  description: string;
  sort_order: number;
  details?: any;
}

export default function PlatformPaymentsPage() {
  const queryClient = useQueryClient();
  const [editingMethod, setEditingMethod] = useState<string | null>(null);
  const [editForm, setEditForm] = useState<any>({});

  const { data: paymentMethods, isLoading } = useQuery({
    queryKey: ['platform-payment-methods'],
    queryFn: () => apiClient.getPlatformPaymentMethods(),
  });

  const { data: pendingInvoices } = useQuery({
    queryKey: ['pending-invoices'],
    queryFn: () => apiClient.getPendingInvoices(),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) =>
      apiClient.updatePlatformPaymentMethod(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform-payment-methods'] });
      setEditingMethod(null);
      setEditForm({});
    },
  });

  const handleEdit = (method: PlatformPaymentMethod) => {
    setEditingMethod(method.id);
    setEditForm({
      is_active: method.is_active,
      details: method.details || {},
    });
  };

  const handleSave = (id: string) => {
    updateMutation.mutate({ id, data: editForm });
  };

  const handleCancel = () => {
    setEditingMethod(null);
    setEditForm({});
  };

  const getVerificationModeBadge = (mode: string) => {
    switch (mode) {
      case 'manual':
        return <span className="px-2 py-1 text-xs rounded-full bg-yellow-500/20 text-yellow-400">Manual</span>;
      case 'automatic':
        return <span className="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-400">Automatic</span>;
      case 'webhook':
        return <span className="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-400">Webhook</span>;
      default:
        return <span className="px-2 py-1 text-xs rounded-full bg-gray-500/20 text-gray-400">Unknown</span>;
    }
  };

  const getMethodIcon = (type: string) => {
    switch (type) {
      case 'mtn_personal':
      case 'mtn_merchant':
        return Phone;
      case 'airtel_personal':
        return Phone;
      case 'dfcu_bank':
        return Building2;
      case 'cash':
        return DollarSign;
      case 'flutterwave':
        return CreditCard;
      default:
        return Shield;
    }
  };

  const renderDetailFields = (type: string) => {
    switch (type) {
      case 'mtn_personal':
      case 'airtel_personal':
        return (
          <>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Phone Number</label>
              <input
                type="text"
                value={editForm.details?.phone_number || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, phone_number: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="e.g., +256 700 000 000"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Account Name</label>
              <input
                type="text"
                value={editForm.details?.account_name || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, account_name: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="Your account name"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Instructions</label>
              <textarea
                value={editForm.details?.instructions || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, instructions: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                rows={2}
                placeholder="Payment instructions for providers"
              />
            </div>
          </>
        );
      case 'dfcu_bank':
        return (
          <>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Account Number</label>
              <input
                type="text"
                value={editForm.details?.account_number || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, account_number: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="DFCU account number"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Account Name</label>
              <input
                type="text"
                value={editForm.details?.account_name || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, account_name: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="Account holder name"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Branch</label>
              <input
                type="text"
                value={editForm.details?.branch || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, branch: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="Branch name"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Instructions</label>
              <textarea
                value={editForm.details?.instructions || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, instructions: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                rows={2}
                placeholder="Transfer instructions"
              />
            </div>
          </>
        );
      case 'cash':
        return (
          <>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Office Address</label>
              <input
                type="text"
                value={editForm.details?.location || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, location: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="Your office address"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Contact Person</label>
              <input
                type="text"
                value={editForm.details?.contact_person || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, contact_person: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                placeholder="Contact person name"
              />
            </div>
            <div>
              <label className="block text-sm text-text-secondary mb-1">Instructions</label>
              <textarea
                value={editForm.details?.instructions || ''}
                onChange={(e) => setEditForm({ ...editForm, details: { ...editForm.details, instructions: e.target.value } })}
                className="w-full px-3 py-2 bg-surface border border-border-medium rounded-lg text-text-primary"
                rows={2}
                placeholder="Cash payment instructions"
              />
            </div>
          </>
        );
      default:
        return <p className="text-text-secondary text-sm">No editable fields for this payment method</p>;
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-text-secondary">Loading payment methods...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Platform Payment Methods</h1>
          <p className="text-text-secondary mt-1">Manage payment methods for platform subscriptions</p>
        </div>
        {pendingInvoices && pendingInvoices.count > 0 && (
          <div className="flex items-center gap-2 px-4 py-2 bg-yellow-500/20 border border-yellow-500/30 rounded-lg">
            <AlertCircle className="w-5 h-5 text-yellow-400" />
            <span className="text-yellow-400 font-medium">{pendingInvoices.count} pending invoices</span>
          </div>
        )}
      </motion.div>

      {/* Payment Methods */}
      <div className="space-y-4">
        {paymentMethods?.payment_methods?.map((method: PlatformPaymentMethod, index: number) => {
          const Icon = getMethodIcon(method.type);
          const isEditing = editingMethod === method.id;

          return (
            <motion.div
              key={method.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
            >
              <div className="flex items-start justify-between">
                <div className="flex items-start gap-4 flex-1">
                  <div className="p-3 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]">
                    <Icon className="w-6 h-6 text-gold" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="text-lg font-semibold text-text-primary">{method.name}</h3>
                      {getVerificationModeBadge(method.verification_mode)}
                      {method.is_active ? (
                        <span className="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-400 flex items-center gap-1">
                          <Check className="w-3 h-3" /> Active
                        </span>
                      ) : (
                        <span className="px-2 py-1 text-xs rounded-full bg-gray-500/20 text-gray-400">Disabled</span>
                      )}
                    </div>
                    <p className="text-text-secondary text-sm mb-3">{method.description}</p>
                    
                    {isEditing ? (
                      <div className="space-y-4 p-4 bg-surface border border-border-medium rounded-xl">
                        <div className="flex items-center gap-3 mb-4">
                          <input
                            type="checkbox"
                            id={`active-${method.id}`}
                            checked={editForm.is_active}
                            onChange={(e) => setEditForm({ ...editForm, is_active: e.target.checked })}
                            className="w-4 h-4 rounded border-border-medium"
                          />
                          <label htmlFor={`active-${method.id}`} className="text-sm text-text-primary">
                            Enable this payment method
                          </label>
                        </div>
                        {renderDetailFields(method.type)}
                      </div>
                    ) : (
                      <div className="text-text-secondary text-sm">
                        <p className="font-medium text-text-primary mb-1">Payment Details:</p>
                        {method.details?.phone_number && <p>Phone: {method.details.phone_number}</p>}
                        {method.details?.account_number && <p>Account: {method.details.account_number}</p>}
                        {method.details?.location && <p>Location: {method.details.location}</p>}
                        {!method.details || Object.keys(method.details).length === 0 && (
                          <p className="text-gray-500 italic">No payment details configured</p>
                        )}
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-2 ml-4">
                  {isEditing ? (
                    <>
                      <button
                        onClick={() => handleSave(method.id)}
                        disabled={updateMutation.isPending}
                        className="p-2 rounded-lg bg-green-500/20 text-green-400 hover:bg-green-500/30 transition-colors"
                      >
                        <Save className="w-5 h-5" />
                      </button>
                      <button
                        onClick={handleCancel}
                        className="p-2 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500/30 transition-colors"
                      >
                        <X className="w-5 h-5" />
                      </button>
                    </>
                  ) : (
                    <button
                      onClick={() => handleEdit(method)}
                      className="p-2 rounded-lg bg-gold/20 text-gold hover:bg-gold/30 transition-colors"
                    >
                      <Edit2 className="w-5 h-5" />
                    </button>
                  )}
                </div>
              </div>
            </motion.div>
          );
        })}
      </div>

      {/* Info Box */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-6"
      >
        <div className="flex items-start gap-4">
          <Shield className="w-6 h-6 text-blue-400 mt-1" />
          <div>
            <h3 className="text-lg font-semibold text-text-primary mb-2">Security Notice</h3>
            <p className="text-text-secondary text-sm">
              All payment details are encrypted in the database. Only admin users can view and edit these details.
              Personal mobile money numbers and bank accounts are suitable for launch but should be migrated to business-owned accounts as Yo.Salon grows.
            </p>
          </div>
        </div>
      </motion.div>
    </div>
  );
}
