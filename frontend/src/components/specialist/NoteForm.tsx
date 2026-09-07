'use client';

import { motion } from 'framer-motion';
import { MessageSquare, Save, X, Tag, Eye, EyeOff, Star } from 'lucide-react';
import { useState } from 'react';

interface Customer {
  id: string;
  name: string;
  email: string;
  phone: string;
}

interface NoteFormProps {
  customer: Customer;
  salonId: string;
  specialistId: string;
  bookingId?: string;
  onCancel: () => void;
  onSuccess: () => void;
}

export default function NoteForm({ customer, salonId, specialistId, bookingId, onCancel, onSuccess }: NoteFormProps) {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formData, setFormData] = useState({
    note: '',
    note_type: 'general',
    tags: [] as string[],
    is_private: false,
    is_important: false,
    customer_visible: true,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      const token = localStorage.getItem('specialist_auth_token');
      const response = await fetch(`${process.env.NEXT_PUBLIC_API_URL}/v1/notes`, {
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
        throw new Error('Failed to create note');
      }

      onSuccess();
    } catch (error) {
      console.error('Error creating note:', error);
      alert('Failed to create note. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleTagToggle = (tag: string) => {
    setFormData(prev => ({
      ...prev,
      tags: prev.tags.includes(tag)
        ? prev.tags.filter(t => t !== tag)
        : [...prev.tags, tag],
    }));
  };

  const availableTags = ['preference', 'allergy', 'style', 'behavior', 'payment', 'feedback'];

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6"
    >
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-xl font-bold text-text-primary flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-gold" />
            Specialist Note
          </h2>
          <p className="text-sm text-text-secondary mt-1">
            For: {customer.name}
          </p>
        </div>
        <button
          onClick={onCancel}
          className="p-2 rounded-lg hover:bg-surface text-text-secondary transition-colors"
        >
          <X className="w-5 h-5" />
        </button>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Note Content */}
        <div>
          <label className="block text-sm font-medium text-text-secondary mb-2">
            Note
          </label>
          <textarea
            value={formData.note}
            onChange={(e) => setFormData({ ...formData, note: e.target.value })}
            className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold/50 resize-none"
            rows={5}
            placeholder="Write your note here..."
            required
          />
        </div>

        {/* Note Type */}
        <div>
          <label className="block text-sm font-medium text-text-secondary mb-2">
            Note Type
          </label>
          <select
            value={formData.note_type}
            onChange={(e) => setFormData({ ...formData, note_type: e.target.value })}
            className="w-full px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-gold/50"
          >
            <option value="general">General</option>
            <option value="preference">Preference</option>
            <option value="observation">Observation</option>
            <option value="recommendation">Recommendation</option>
          </select>
        </div>

        {/* Tags */}
        <div>
          <label className="block text-sm font-medium text-text-secondary mb-2">
            Tags
          </label>
          <div className="flex flex-wrap gap-2">
            {availableTags.map((tag) => (
              <button
                key={tag}
                type="button"
                onClick={() => handleTagToggle(tag)}
                className={`px-4 py-2 rounded-lg border transition-colors ${
                  formData.tags.includes(tag)
                    ? 'border-gold bg-gold/20 text-gold'
                    : 'border-border-light bg-surface text-text-secondary hover:border-gold/50'
                }`}
              >
                <Tag className="w-4 h-4 inline mr-1" />
                {tag}
              </button>
            ))}
          </div>
        </div>

        {/* Visibility Options */}
        <div className="space-y-4 pt-4 border-t border-border-medium">
          <h3 className="text-sm font-medium text-text-secondary">Visibility Options</h3>
          
          <div className="space-y-3">
            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.is_private}
                onChange={(e) => setFormData({ ...formData, is_private: e.target.checked })}
                className="w-5 h-5 rounded border-border-light text-gold focus:ring-gold"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <EyeOff className="w-4 h-4 text-text-secondary" />
                  <span className="text-text-primary font-medium">Private Note</span>
                </div>
                <p className="text-xs text-text-secondary mt-1">Only visible to you</p>
              </div>
            </label>

            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.is_important}
                onChange={(e) => setFormData({ ...formData, is_important: e.target.checked })}
                className="w-5 h-5 rounded border-border-light text-gold focus:ring-gold"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <Star className="w-4 h-4 text-gold" />
                  <span className="text-text-primary font-medium">Important</span>
                </div>
                <p className="text-xs text-text-secondary mt-1">Highlight this note</p>
              </div>
            </label>

            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={formData.customer_visible}
                onChange={(e) => setFormData({ ...formData, customer_visible: e.target.checked })}
                className="w-5 h-5 rounded border-border-light text-gold focus:ring-gold"
              />
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <Eye className="w-4 h-4 text-text-secondary" />
                  <span className="text-text-primary font-medium">Customer Visible</span>
                </div>
                <p className="text-xs text-text-secondary mt-1">Customer can see this note</p>
              </div>
            </label>
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 pt-4">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 px-4 py-3 rounded-xl bg-surface border border-border-medium text-text-primary font-medium hover:bg-surface/80 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting}
            className="flex-1 px-4 py-3 rounded-xl bg-gradient-to-r from-gold to-amber-600 text-white font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <Save className="w-5 h-5" />
            {isSubmitting ? 'Saving...' : 'Save Note'}
          </button>
        </div>
      </form>
    </motion.div>
  );
}
