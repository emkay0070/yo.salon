'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { 
  Shield, 
  CheckCircle2, 
  Clock, 
  XCircle,
  Upload,
  FileText,
  Award,
  AlertCircle,
  Send
} from 'lucide-react';
import { useState } from 'react';

type VerificationStatus = 'unverified' | 'submitted' | 'under_review' | 'approved' | 'verified' | 'expired' | 'revoked' | 'rejected';

export default function VerificationPage() {
  const { specialist } = useSpecialistAuth();
  const queryClient = useQueryClient();
  const [formData, setFormData] = useState({
    idDocument: null as File | null,
    certifications: [] as File[],
    portfolioLink: '',
    socialLinks: '',
  });

  const { data: verificationStatus } = useQuery({
    queryKey: ['specialist-verification'],
    queryFn: async () => {
      // Mock data - replace with actual API call
      return {
        status: 'unverified' as VerificationStatus,
        is_verified: false,
        latest_verification: null,
      };
    },
    enabled: !!specialist,
  });

  const submitVerificationMutation = useMutation({
    mutationFn: async (data: any) => {
      // Mock API call - replace with actual implementation
      await new Promise(resolve => setTimeout(resolve, 1000));
      return { success: true };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-verification'] });
    },
  });

  const handleSubmit = () => {
    submitVerificationMutation.mutate(formData);
  };

  const getStatusIcon = (status: VerificationStatus) => {
    switch (status) {
      case 'verified':
      case 'approved': return <CheckCircle2 className="w-8 h-8 text-green-400" />;
      case 'submitted':
      case 'under_review': return <Clock className="w-8 h-8 text-amber-400" />;
      case 'rejected':
      case 'revoked': return <XCircle className="w-8 h-8 text-red-400" />;
      case 'expired': return <AlertCircle className="w-8 h-8 text-orange-400" />;
      default: return <Shield className="w-8 h-8 text-text-secondary" />;
    }
  };

  const getStatusText = (status: VerificationStatus) => {
    switch (status) {
      case 'verified': return 'Verified';
      case 'approved': return 'Approved';
      case 'submitted': return 'Submitted';
      case 'under_review': return 'Under Review';
      case 'rejected': return 'Verification Rejected';
      case 'revoked': return 'Verification Revoked';
      case 'expired': return 'Verification Expired';
      default: return 'Not Verified';
    }
  };

  const getStatusDescription = (status: VerificationStatus) => {
    switch (status) {
      case 'verified': return 'Your account has been verified. You now have the verified badge on your profile.';
      case 'approved': return 'Your verification has been approved. The verified badge will appear on your profile shortly.';
      case 'submitted': return 'Your verification has been submitted. It is now under review.';
      case 'under_review': return 'Your verification is under review. This typically takes 1-2 business days.';
      case 'rejected': return (verificationStatus as any)?.latest_verification?.reason || 'Your verification was rejected. Please review and resubmit.';
      case 'revoked': return 'Your verification has been revoked. Please contact support for more information.';
      case 'expired': return 'Your verification has expired. Please resubmit to maintain your verified status.';
      default: return 'Get verified to build trust with customers and unlock premium features.';
    }
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Verification</h1>
        <p className="text-text-secondary">Get verified to build trust with customers</p>
      </div>

      {/* Status Card */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className={`bg-card border rounded-2xl p-6 ${
          verificationStatus?.status === 'verified' ? 'border-green-400/30' :
          verificationStatus?.status === 'under_review' ? 'border-amber-400/30' :
          verificationStatus?.status === 'rejected' ? 'border-red-400/30' :
          'border-border-light'
        }`}
      >
        <div className="flex items-start gap-4">
          <div className="p-3 rounded-full bg-surface">
            {getStatusIcon(verificationStatus?.status || 'unverified')}
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-text-primary mb-2">
              {getStatusText(verificationStatus?.status || 'unverified')}
            </h3>
            <p className="text-text-secondary">{getStatusDescription((verificationStatus?.status ?? 'unverified') as VerificationStatus)}</p>
            {verificationStatus?.status === 'verified' && (
              <div className="mt-4 flex items-center gap-2 text-green-400">
                <CheckCircle2 className="w-5 h-5" />
                <span className="font-medium">Verified badge active on your profile</span>
              </div>
            )}
          </div>
        </div>
      </motion.div>

      {/* Verification Form */}
      {verificationStatus?.status === 'unverified' || verificationStatus?.status === 'rejected' ? (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="space-y-6"
        >
          <div className="bg-card border border-border-light rounded-2xl p-6">
            <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
              <FileText className="w-5 h-5 text-gold" />
              Identity Verification
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">
                  Government ID
                </label>
                <div className="border-2 border-dashed border-border-light rounded-xl p-8 text-center hover:border-gold/50 transition-colors cursor-pointer">
                  <Upload className="w-8 h-8 text-text-secondary mx-auto mb-2" />
                  <p className="text-sm text-text-secondary">
                    {formData.idDocument ? formData.idDocument.name : 'Upload your ID (passport, driver\'s license, or national ID)'}
                  </p>
                  <input
                    type="file"
                    accept="image/*,.pdf"
                    onChange={(e) => setFormData({ ...formData, idDocument: e.target.files?.[0] || null })}
                    className="hidden"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-card border border-border-light rounded-2xl p-6">
            <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
              <Award className="w-5 h-5 text-gold" />
              Professional Certifications
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">
                  Upload Certifications (Optional)
                </label>
                <div className="border-2 border-dashed border-border-light rounded-xl p-8 text-center hover:border-gold/50 transition-colors cursor-pointer">
                  <Upload className="w-8 h-8 text-text-secondary mx-auto mb-2" />
                  <p className="text-sm text-text-secondary">
                    Upload barber certifications, training certificates, or awards
                  </p>
                  <input
                    type="file"
                    accept="image/*,.pdf"
                    multiple
                    onChange={(e) => setFormData({ ...formData, certifications: Array.from(e.target.files || []) })}
                    className="hidden"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-card border border-border-light rounded-2xl p-6">
            <h3 className="font-semibold text-text-primary mb-4 flex items-center gap-2">
              <Shield className="w-5 h-5 text-gold" />
              Portfolio & Social Proof
            </h3>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">
                  Portfolio Link (Optional)
                </label>
                <input
                  type="url"
                  placeholder="https://instagram.com/yourhandle"
                  value={formData.portfolioLink}
                  onChange={(e) => setFormData({ ...formData, portfolioLink: e.target.value })}
                  className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold transition-colors"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">
                  Social Media Links (Optional)
                </label>
                <textarea
                  placeholder="Instagram, Twitter, Facebook links..."
                  value={formData.socialLinks}
                  onChange={(e) => setFormData({ ...formData, socialLinks: e.target.value })}
                  className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold transition-colors resize-none"
                  rows={3}
                />
              </div>
            </div>
          </div>

          <div className="bg-amber-400/10 border border-amber-400/30 rounded-2xl p-6">
            <div className="flex items-start gap-3">
              <AlertCircle className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" />
              <div>
                <p className="font-medium text-text-primary mb-2">Verification Requirements</p>
                <ul className="text-sm text-text-secondary space-y-1">
                  <li>• Government ID is required for identity verification</li>
                  <li>• Certifications help verify your professional credentials</li>
                  <li>• Portfolio and social links provide additional proof of expertise</li>
                  <li>• Review typically takes 1-2 business days</li>
                </ul>
              </div>
            </div>
          </div>

          <button
            onClick={handleSubmit}
            disabled={submitVerificationMutation.isPending || !formData.idDocument}
            className="w-full flex items-center justify-center gap-2 px-6 py-4 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {submitVerificationMutation.isPending ? (
              'Submitting...'
            ) : (
              <>
                <Send className="w-4 h-4" />
                Submit for Verification
              </>
            )}
          </button>
        </motion.div>
      ) : null}

      {(verificationStatus?.status === 'submitted' || verificationStatus?.status === 'under_review') && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-center gap-3 mb-4">
            <Clock className="w-5 h-5 text-amber-400" />
            <h3 className="font-semibold text-text-primary">What Happens Next?</h3>
          </div>
          <ul className="space-y-3 text-text-secondary">
            <li className="flex items-start gap-2">
              <CheckCircle2 className="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" />
              <span>Our team will review your submitted documents</span>
            </li>
            <li className="flex items-start gap-2">
              <CheckCircle2 className="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" />
              <span>Verification typically takes 1-2 business days</span>
            </li>
            <li className="flex items-start gap-2">
              <CheckCircle2 className="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" />
              <span>You'll receive a notification once reviewed</span>
            </li>
            <li className="flex items-start gap-2">
              <CheckCircle2 className="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" />
              <span>If approved, the verified badge will appear on your profile</span>
            </li>
          </ul>
        </motion.div>
      )}
    </div>
  );
}
