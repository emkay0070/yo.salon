'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Clock, DollarSign, Star, Edit, Trash2, Eye, Scissors, Building2, Award, X, Upload } from 'lucide-react';
import SideDrawer from '../shared/SideDrawer';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface ServiceDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  service: {
    id: string;
    name: string;
    description?: string;
    price: number;
    duration: number;
    category?: string;
    craft_taxonomy_name?: string;
    craft_taxonomy_id?: string;
    craft_taxonomy_slug?: string;
    skill_level: string;
    image_url?: string;
    image_media_id?: string;
    provider_id?: string;
    provider_name: string;
    role: string;
    employment_type: string;
    provider_type: string;
    can_manage_provider: boolean;
    can_create_services: boolean;
    reputation?: {
      average_rating: number;
      review_count: number;
    };
  };
  onRemoveFromMyServices?: () => void;
}

export default function ServiceDrawer({
  isOpen,
  onClose,
  service,
  onRemoveFromMyServices,
}: ServiceDrawerProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editForm, setEditForm] = useState({
    name: '',
    price: '',
    duration: '',
    category: '',
    description: '',
    craft_taxonomy_id: '',
  });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [showTaxonomySelector, setShowTaxonomySelector] = useState(false);
  const [taxonomySearch, setTaxonomySearch] = useState('');
  const queryClient = useQueryClient();

  const { data: taxonomyData } = useQuery({
    queryKey: ['craft-taxonomy'],
    queryFn: () => apiClient.get('/v1/specialist-portal/craft/taxonomy'),
  });

  const taxonomy = taxonomyData?.taxonomy ?? [];

  const filteredTaxonomy = taxonomy.flatMap((root: any) =>
    root.children.flatMap((category: any) =>
      category.children.map((item: any) => ({
        ...item,
        categoryName: category.name,
        rootName: root.name,
      }))
    )
  ).filter((item: any) =>
    item.name.toLowerCase().includes(taxonomySearch.toLowerCase()) ||
    item.categoryName.toLowerCase().includes(taxonomySearch.toLowerCase())
  );

  const canEditService = service?.can_manage_provider || service?.can_create_services;

  if (!service) {
    return null;
  }

  const handleEdit = () => {
    setEditForm({
      name: service.name,
      price: service.price.toString(),
      duration: service.duration.toString(),
      category: service.category || '',
      description: service.description || '',
      craft_taxonomy_id: service.craft_taxonomy_id || '',
    });
    setIsEditing(true);
  };

  const handleSaveEdit = async () => {
    setIsSubmitting(true);
    try {
      await apiClient.put(`/v1/specialist-portal/craft/services/${service.id}`, {
        name: editForm.name,
        price: parseFloat(editForm.price),
        duration: parseInt(editForm.duration),
        category: editForm.category || null,
        description: editForm.description || null,
        craft_taxonomy_id: editForm.craft_taxonomy_id || null,
      });
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      setIsEditing(false);
      onClose();
    } catch (e) {
      console.error('Failed to update service', e);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose}>
      <AnimatePresence>
        {!isEditing ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="p-6 space-y-6"
          >
            {/* Service Image - Spatial Card */}
            {service.image_url ? (
              <motion.div 
                whileHover={{ scale: 1.02, y: -4 }}
                transition={{ type: "spring", stiffness: 300, damping: 20 }}
                className="relative h-52 rounded-2xl overflow-hidden border border-white/10 backdrop-blur-xl shadow-2xl"
              >
                <img
                  src={service.image_url}
                  alt={service.name}
                  className="w-full h-full object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
              </motion.div>
            ) : (
              <motion.div 
                whileHover={{ scale: 1.02, y: -4 }}
                transition={{ type: "spring", stiffness: 300, damping: 20 }}
                className="h-52 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-xl flex items-center justify-center"
              >
                <Scissors className="w-14 h-14 text-white/30" />
              </motion.div>
            )}

            {/* Service Name & Craft Taxonomy */}
            <div>
              <h3 className="text-xl font-bold text-text-primary mb-2">{service.name}</h3>
              {service.craft_taxonomy_name && (
                <div className="flex items-center gap-2 text-sm text-gold">
                  <Award className="w-4 h-4" />
                  <span>{service.craft_taxonomy_name}</span>
                </div>
              )}
              {service.description && (
                <p className="text-sm text-text-secondary mt-2">{service.description}</p>
              )}
            </div>

            {/* Price & Duration - Spatial Cards */}
            <div className="grid grid-cols-2 gap-4">
              <motion.div 
                whileHover={{ scale: 1.05, y: -2 }}
                className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-2xl p-5 shadow-lg"
              >
                <div className="flex items-center gap-2 text-white/50 mb-2">
                  <DollarSign className="w-4 h-4" />
                  <span className="text-xs uppercase tracking-wider">Price</span>
                </div>
                <p className="text-xl font-bold text-white">
                  UGX {service.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                </p>
              </motion.div>
              <motion.div 
                whileHover={{ scale: 1.05, y: -2 }}
                className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-2xl p-5 shadow-lg"
              >
                <div className="flex items-center gap-2 text-white/50 mb-2">
                  <Clock className="w-4 h-4" />
                  <span className="text-xs uppercase tracking-wider">Duration</span>
                </div>
                <p className="text-xl font-bold text-white">
                  {service.duration} min
                </p>
              </motion.div>
            </div>

            {/* Divider */}
            <div className="border-t border-border-light" />

            {/* Your Expertise - Spatial Pill */}
            <div>
              <h4 className="text-sm font-medium text-white/50 mb-3">Your Expertise</h4>
              <motion.div 
                whileHover={{ scale: 1.05 }}
                className={`inline-flex px-4 py-2 rounded-full text-sm font-medium backdrop-blur-xl border ${
                  service.skill_level === 'expert' ? 'bg-[#FFD700]/20 border-[#FFD700]/30 text-[#FFD700]' :
                  service.skill_level === 'advanced' ? 'bg-purple-500/20 border-purple-500/30 text-purple-400' :
                  service.skill_level === 'intermediate' ? 'bg-blue-500/20 border-blue-500/30 text-blue-400' :
                  'bg-green-500/20 border-green-500/30 text-green-400'
                }`}>
                {service.skill_level.charAt(0).toUpperCase() + service.skill_level.slice(1)}
              </motion.div>
            </div>

            {/* Customer Evidence (Reputation) */}
            {service.reputation && service.reputation.review_count > 0 && (
              <>
                <div className="border-t border-border-light" />
                <div>
                  <h4 className="text-sm font-medium text-text-secondary mb-3">Customer Evidence</h4>
                  <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1">
                      <Star className="w-4 h-4 text-gold fill-gold" />
                      <span className="font-semibold text-text-primary">
                        {service.reputation.average_rating.toFixed(1)}
                      </span>
                    </div>
                    <span className="text-text-muted">
                      · {service.reputation.review_count} reviews
                    </span>
                  </div>
                </div>
              </>
            )}

            {/* Divider */}
            <div className="border-t border-border-light" />

            {/* Provider Context */}
            <div>
              <h4 className="text-sm font-medium text-text-secondary mb-3">Provider</h4>
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-gold/10 flex items-center justify-center">
                  <Building2 className="w-5 h-5 text-gold" />
                </div>
                <div>
                  <p className="font-medium text-text-primary">{service.provider_name}</p>
                  <p className="text-xs text-text-muted capitalize">
                    {service.provider_type === 'independent_specialist' ? 'Independent' : `${service.role} · ${service.employment_type}`}
                  </p>
                </div>
              </div>
            </div>

            {/* Actions - Spatial Buttons */}
            <div className="space-y-3 pt-4">
              {canEditService && (
                <motion.button
                  whileHover={{ scale: 1.02, y: -2 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleEdit}
                  className="w-full flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl hover:border-[#FFD700]/50 hover:text-[#FFD700] transition-all text-white shadow-lg"
                >
                  <Edit className="w-4 h-4" />
                  Edit Service
                </motion.button>
              )}

              {!canEditService && onRemoveFromMyServices && (
                <motion.button
                  whileHover={{ scale: 1.02, y: -2 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => {
                    onRemoveFromMyServices();
                    onClose();
                  }}
                  className="w-full flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl hover:border-red-500/50 hover:text-red-400 transition-all text-white shadow-lg"
                >
                  <Trash2 className="w-4 h-4" />
                  Remove from My Services
                </motion.button>
              )}
            </div>
          </motion.div>
        ) : (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            transition={{ type: "spring", stiffness: 300, damping: 30 }}
            className="p-6 space-y-5"
          >
            <div className="flex items-center justify-between">
              <h2 className="text-lg font-bold text-text-primary">Edit Service</h2>
              <button onClick={() => setIsEditing(false)} className="text-text-secondary hover:text-text-primary">
                <X className="w-5 h-5" />
              </button>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">Service Name</label>
              <motion.input
                whileFocus={{ scale: 1.01 }}
                type="text"
                value={editForm.name}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all placeholder-white/30"
              />
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">What kind of service is this?</label>
              {!showTaxonomySelector ? (
                <motion.button
                  whileHover={{ scale: 1.02, y: -2 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => setShowTaxonomySelector(true)}
                  className="w-full p-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-left text-white/60 hover:border-[#FFD700]/50 hover:text-white transition-all"
                >
                  {editForm.craft_taxonomy_id 
                    ? (() => {
                      const selected = filteredTaxonomy.find((t: any) => t.id === editForm.craft_taxonomy_id);
                      return selected ? `${selected.rootName} → ${selected.categoryName} → ${selected.name}` : 'Selected';
                    })()
                    : '+ Select craft type'
                  }
                </motion.button>
              ) : (
                <div className="space-y-3">
                  <motion.input
                    whileFocus={{ scale: 1.01 }}
                    type="text"
                    value={taxonomySearch}
                    onChange={(e) => setTaxonomySearch(e.target.value)}
                    placeholder="Search craft type..."
                    className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:border-[#FFD700]/50 transition-all"
                  />
                  <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                    {filteredTaxonomy.slice(0, 10).map((item: any, index: any) => (
                      <motion.button
                        key={item.id}
                        initial={{ opacity: 0, x: -10 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: index * 0.05 }}
                        whileHover={{ scale: 1.02, x: 4 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={() => {
                          setEditForm({ ...editForm, craft_taxonomy_id: item.id });
                          setShowTaxonomySelector(false);
                          setTaxonomySearch('');
                        }}
                        className={`w-full text-left p-3 rounded-xl text-sm transition-all backdrop-blur-xl ${
                          editForm.craft_taxonomy_id === item.id
                            ? 'bg-[#FFD700]/20 border border-[#FFD700]/40 text-white shadow-lg shadow-[#FFD700]/10'
                            : 'bg-white/5 border border-white/10 hover:border-[#FFD700]/30 text-white/60'
                        }`}
                      >
                        <p className="font-medium">{item.name}</p>
                        <p className="text-xs text-white/40">{item.rootName} → {item.categoryName}</p>
                      </motion.button>
                    ))}
                  </div>
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    onClick={() => {
                      setShowTaxonomySelector(false);
                      setEditForm({ ...editForm, craft_taxonomy_id: '' });
                    }}
                    className="text-xs text-white/40 hover:text-white/60 transition-colors"
                  >
                    Clear selection
                  </motion.button>
                </div>
              )}
            </motion.div>

            <motion.div 
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
              className="grid grid-cols-2 gap-4"
            >
              <div>
                <label className="block text-sm font-medium text-white/50 mb-2">Price (UGX)</label>
                <motion.input
                  whileFocus={{ scale: 1.01 }}
                  type="number"
                  value={editForm.price}
                  onChange={(e) => setEditForm({ ...editForm, price: e.target.value })}
                  className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all placeholder-white/30"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-white/50 mb-2">Duration (mins)</label>
                <motion.input
                  whileFocus={{ scale: 1.01 }}
                  type="number"
                  value={editForm.duration}
                  onChange={(e) => setEditForm({ ...editForm, duration: e.target.value })}
                  className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all placeholder-white/30"
                />
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">Description</label>
              <motion.textarea
                whileFocus={{ scale: 1.01 }}
                value={editForm.description}
                onChange={(e) => setEditForm({ ...editForm, description: e.target.value })}
                rows={2}
                className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all resize-none placeholder-white/30"
              />
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.5 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">Menu Category (Optional)</label>
              <motion.input
                whileFocus={{ scale: 1.01 }}
                type="text"
                value={editForm.category}
                onChange={(e) => setEditForm({ ...editForm, category: e.target.value })}
                className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all placeholder-white/30"
              />
            </motion.div>

            <motion.button
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.6 }}
              whileHover={{ scale: 1.02, y: -2 }}
              whileTap={{ scale: 0.98 }}
              onClick={handleSaveEdit}
              disabled={isSubmitting}
              className="w-full py-3.5 rounded-xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold text-sm hover:opacity-90 transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-lg shadow-[#FFD700]/20"
            >
              {isSubmitting ? 'Saving...' : 'Save Changes'}
            </motion.button>
          </motion.div>
        )}
      </AnimatePresence>
    </SideDrawer>
  );
}
