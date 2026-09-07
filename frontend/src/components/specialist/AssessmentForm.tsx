'use client';

import { motion } from 'framer-motion';
import { Scissors, User, Save, X, AlertTriangle } from 'lucide-react';
import { useState } from 'react';

interface Customer {
  id: string;
  name: string;
  email: string;
  phone: string;
}

interface AssessmentFormProps {
  customer: Customer;
  salonId: string;
  specialistId: string;
  bookingId?: string;
  onCancel: () => void;
  onSuccess: () => void;
}

export default function AssessmentForm({ customer, salonId, specialistId, bookingId, onCancel, onSuccess }: AssessmentFormProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    // Hair Assessment
    observed_hair_type: '',
    hair_density: '',
    scalp_condition: '',
    hairline: '',
    hair_observations: '',
    
    // Beard Assessment
    observed_beard_style: '',
    beard_growth_pattern: '',
    beard_observations: '',
    
    // Skin Assessment
    observed_skin_type: '',
    skin_observations: '',
    skin_conditions: [] as string[],
    
    // Metadata
    confidence_level: 'Medium',
    notes: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      const token = localStorage.getItem('specialist_auth_token');
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/v1/assessments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          customer_id: customer.id,
          specialist_id: specialistId,
          salon_id: salonId,
          booking_id: bookingId,
          ...formData,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to create assessment');
      }

      onSuccess();
    } catch (error) {
      console.error('Error creating assessment:', error);
      alert('Failed to create assessment. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleSkinConditionToggle = (condition: string) => {
    setFormData(prev => ({
      ...prev,
      skin_conditions: prev.skin_conditions.includes(condition)
        ? prev.skin_conditions.filter(c => c !== condition)
        : [...prev.skin_conditions, condition],
    }));
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-3xl p-6 shadow-2xl"
    >
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-xl font-bold text-white flex items-center gap-2">
            <Scissors className="w-5 h-5 text-[#FFD700]" />
            Professional Assessment
          </h2>
          <p className="text-sm text-white/70 mt-1">
            For: {customer.name}
          </p>
        </div>
        <motion.button
          whileHover={{ scale: 1.1 }}
          whileTap={{ scale: 0.9 }}
          onClick={onCancel}
          className="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white/50 hover:text-white transition-colors"
        >
          <X className="w-5 h-5" />
        </motion.button>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Hair Assessment */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="space-y-4"
        >
          <h3 className="text-lg font-semibold text-white">Hair Assessment</h3>
          
          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.15 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Observed Hair Type
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.observed_hair_type}
              onChange={(e) => setFormData({ ...formData, observed_hair_type: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select hair type</option>
              <option value="straight">Straight</option>
              <option value="wavy">Wavy</option>
              <option value="curly">Curly</option>
              <option value="coily">Coily</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.2 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Hair Density
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.hair_density}
              onChange={(e) => setFormData({ ...formData, hair_density: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select density</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.25 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Scalp Condition
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.scalp_condition}
              onChange={(e) => setFormData({ ...formData, scalp_condition: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select condition</option>
              <option value="Healthy">Healthy</option>
              <option value="Dry">Dry</option>
              <option value="Oily">Oily</option>
              <option value="Inflamed">Inflamed</option>
              <option value="Sensitive">Sensitive</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.3 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Hairline
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.hairline}
              onChange={(e) => setFormData({ ...formData, hairline: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select hairline</option>
              <option value="Normal">Normal</option>
              <option value="Receding">Receding</option>
              <option value="Widow's Peak">Widow's Peak</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.35 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Hair Observations
            </label>
            <motion.textarea
              whileFocus={{ scale: 1.01 }}
              value={formData.hair_observations}
              onChange={(e) => setFormData({ ...formData, hair_observations: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 resize-none transition-all"
              rows={3}
              placeholder="Additional observations about hair..."
            />
          </motion.div>
        </motion.div>

        {/* Beard Assessment */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="space-y-4 pt-4 border-t border-white/10"
        >
          <h3 className="text-lg font-semibold text-white">Beard Assessment</h3>
          
          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.45 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Observed Beard Style
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.observed_beard_style}
              onChange={(e) => setFormData({ ...formData, observed_beard_style: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select beard style</option>
              <option value="Clean Shaven">Clean Shaven</option>
              <option value="Stubble">Stubble</option>
              <option value="Short Beard">Short Beard</option>
              <option value="Full Beard">Full Beard</option>
              <option value="Goatee">Goatee</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.5 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Beard Growth Pattern
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.beard_growth_pattern}
              onChange={(e) => setFormData({ ...formData, beard_growth_pattern: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select pattern</option>
              <option value="Patchy">Patchy</option>
              <option value="Full">Full</option>
              <option value="Sparse">Sparse</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.55 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Beard Observations
            </label>
            <motion.textarea
              whileFocus={{ scale: 1.01 }}
              value={formData.beard_observations}
              onChange={(e) => setFormData({ ...formData, beard_observations: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 resize-none transition-all"
              rows={3}
              placeholder="Additional observations about beard..."
            />
          </motion.div>
        </motion.div>

        {/* Skin Assessment */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          className="space-y-4 pt-4 border-t border-white/10"
        >
          <h3 className="text-lg font-semibold text-white">Skin Assessment</h3>
          
          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.65 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Observed Skin Type
            </label>
            <motion.select
              whileFocus={{ scale: 1.01 }}
              value={formData.observed_skin_type}
              onChange={(e) => setFormData({ ...formData, observed_skin_type: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 transition-all"
            >
              <option value="">Select skin type</option>
              <option value="Normal">Normal</option>
              <option value="Dry">Dry</option>
              <option value="Oily">Oily</option>
              <option value="Combination">Combination</option>
              <option value="Sensitive">Sensitive</option>
            </motion.select>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.7 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Skin Conditions
            </label>
            <div className="flex flex-wrap gap-2">
              {['Acne', 'Pigmentation', 'Sensitive', 'Scarring'].map((condition) => (
                <motion.button
                  key={condition}
                  type="button"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => handleSkinConditionToggle(condition)}
                  className={`px-4 py-2 rounded-lg border transition-colors backdrop-blur-xl ${
                    formData.skin_conditions.includes(condition)
                      ? 'border-[#FFD700] bg-[#FFD700]/20 text-[#FFD700]'
                      : 'border-white/10 bg-white/5 text-white/60 hover:border-[#FFD700]/30'
                  }`}
                >
                  {condition}
                </motion.button>
              ))}
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, x: -10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.75 }}
          >
            <label className="block text-sm font-medium text-white/50 mb-2">
              Skin Observations
            </label>
            <motion.textarea
              whileFocus={{ scale: 1.01 }}
              value={formData.skin_observations}
              onChange={(e) => setFormData({ ...formData, skin_observations: e.target.value })}
              className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 resize-none transition-all"
              rows={3}
              placeholder="Additional observations about skin..."
            />
          </motion.div>
        </motion.div>

        {/* Confidence Level */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.8 }}
          className="pt-4 border-t border-white/10"
        >
          <label className="block text-sm font-medium text-white/50 mb-2">
            Confidence Level
          </label>
          <div className="flex gap-2">
            {['Low', 'Medium', 'High'].map((level) => (
              <motion.button
                key={level}
                type="button"
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setFormData({ ...formData, confidence_level: level })}
                className={`flex-1 px-4 py-3 rounded-lg border transition-colors backdrop-blur-xl ${
                  formData.confidence_level === level
                    ? 'border-[#FFD700] bg-[#FFD700]/20 text-[#FFD700]'
                    : 'border-white/10 bg-white/5 text-white/60 hover:border-[#FFD700]/30'
                }`}
              >
                {level}
              </motion.button>
            ))}
          </div>
        </motion.div>

        {/* Notes */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.85 }}
          className="pt-4 border-t border-white/10"
        >
          <label className="block text-sm font-medium text-white/50 mb-2">
            Additional Notes
          </label>
          <motion.textarea
            whileFocus={{ scale: 1.01 }}
            value={formData.notes}
            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
            className="w-full px-4 py-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-white focus:outline-none focus:border-[#FFD700]/50 resize-none transition-all"
            rows={3}
            placeholder="Any additional notes or recommendations..."
          />
        </motion.div>

        {/* Warning */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.9 }}
          className="bg-blue-500/10 border border-blue-500/20 backdrop-blur-xl rounded-xl p-4 flex items-start gap-3"
        >
          <AlertTriangle className="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" />
          <div className="text-sm text-white/70">
            <p className="font-medium text-blue-400 mb-1">Important</p>
            <p>
              This assessment will generate profile recommendations for the customer. 
              They will be able to review and accept or reject these recommendations.
            </p>
          </div>
        </motion.div>

        {/* Actions */}
        <motion.div 
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.95 }}
          className="flex gap-3 pt-4"
        >
          <motion.button
            type="button"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            onClick={onCancel}
            className="flex-1 px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white font-medium hover:bg-white/10 transition-colors backdrop-blur-xl"
          >
            Cancel
          </motion.button>
          <motion.button
            type="submit"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
            disabled={isSubmitting}
            className="flex-1 px-4 py-3 rounded-xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-white font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <Save className="w-5 h-5" />
            {isSubmitting ? 'Saving...' : 'Save Assessment'}
          </motion.button>
        </motion.div>
      </form>
    </motion.div>
  );
}
