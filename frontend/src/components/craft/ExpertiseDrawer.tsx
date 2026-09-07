'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Star, Edit, Trash2, Award, X, TrendingUp } from 'lucide-react';
import SideDrawer from '../shared/SideDrawer';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface ExpertiseDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  expertise: {
    id: string;
    name: string;
    skill_level: string;
    craft_taxonomy_name?: string;
    craft_taxonomy_id?: string;
    craft_taxonomy_slug?: string;
    reputation?: {
      average_rating: number;
      review_count: number;
    };
    related_services?: Array<{
      id: string;
      name: string;
      provider_name: string;
    }>;
  };
}

const skillLevels = [
  { value: 'beginner', label: 'Beginner' },
  { value: 'intermediate', label: 'Intermediate' },
  { value: 'advanced', label: 'Advanced' },
  { value: 'expert', label: 'Expert' },
];

export default function ExpertiseDrawer({
  isOpen,
  onClose,
  expertise,
}: ExpertiseDrawerProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [editForm, setEditForm] = useState({
    name: '',
    skill_level: '',
    craft_taxonomy_id: '',
  });
  const [isCustom, setIsCustom] = useState(false);
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

  if (!expertise) {
    return null;
  }

  const handleEdit = () => {
    setEditForm({
      name: expertise.name,
      skill_level: expertise.skill_level,
      craft_taxonomy_id: expertise.craft_taxonomy_id || '',
    });
    setIsCustom(!expertise.craft_taxonomy_id);
    setIsEditing(true);
  };

  const handleSaveEdit = async () => {
    setIsSubmitting(true);
    try {
      const payload: any = {
        name: editForm.name,
        skill_level: editForm.skill_level,
      };
      if (!isCustom && editForm.craft_taxonomy_id) {
        payload.craft_taxonomy_id = editForm.craft_taxonomy_id;
      }
      await apiClient.put(`/v1/specialist-portal/craft/expertise/${expertise.id}`, payload);
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      setIsEditing(false);
      onClose();
    } catch (e) {
      console.error('Failed to update expertise', e);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm('Are you sure you want to remove this expertise?')) return;
    
    try {
      await apiClient.delete(`/v1/specialist-portal/craft/expertise/${expertise.id}`);
      queryClient.invalidateQueries({ queryKey: ['specialist-craft'] });
      onClose();
    } catch (e) {
      console.error('Failed to delete expertise', e);
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
            {/* Expertise Header - Spatial Card */}
            <motion.div 
              whileHover={{ scale: 1.02, y: -2 }}
              className="flex items-center gap-4 p-4 bg-white/5 border border-white/10 backdrop-blur-xl rounded-2xl shadow-lg"
            >
              <div className="w-14 h-14 rounded-full bg-[#FFD700]/10 border border-[#FFD700]/20 flex items-center justify-center">
                <Award className="w-7 h-7 text-[#FFD700]" />
              </div>
              <div>
                <h3 className="text-xl font-bold text-white">{expertise.name}</h3>
                {expertise.craft_taxonomy_name && (
                  <p className="text-sm text-[#FFD700]">{expertise.craft_taxonomy_name}</p>
                )}
              </div>
            </motion.div>

            {/* Skill Level - Spatial Pill */}
            <div>
              <h4 className="text-sm font-medium text-white/50 mb-3">Your Level</h4>
              <motion.div 
                whileHover={{ scale: 1.05 }}
                className={`inline-flex px-5 py-2.5 rounded-full text-sm font-medium backdrop-blur-xl border ${
                  expertise.skill_level === 'expert' ? 'bg-[#FFD700]/20 border-[#FFD700]/30 text-[#FFD700]' :
                  expertise.skill_level === 'advanced' ? 'bg-purple-500/20 border-purple-500/30 text-purple-400' :
                  expertise.skill_level === 'intermediate' ? 'bg-blue-500/20 border-blue-500/30 text-blue-400' :
                  'bg-green-500/20 border-green-500/30 text-green-400'
                }`}>
                {expertise.skill_level ? expertise.skill_level.charAt(0).toUpperCase() + expertise.skill_level.slice(1) : 'Unknown'}
              </motion.div>
            </div>

            {/* Customer Evidence (Reputation) */}
            {expertise.reputation && expertise.reputation.review_count > 0 && (
              <>
                <div className="border-t border-border-light" />
                <div>
                  <h4 className="text-sm font-medium text-text-secondary mb-3">Customer Evidence</h4>
                  <div className="flex items-center gap-3">
                    <div className="flex items-center gap-1">
                      <Star className="w-4 h-4 text-gold fill-gold" />
                      <span className="font-semibold text-text-primary">
                        {expertise.reputation.average_rating.toFixed(1)}
                      </span>
                    </div>
                    <span className="text-text-muted">
                      · {expertise.reputation.review_count} reviews
                    </span>
                  </div>
                </div>
              </>
            )}

            {/* Related Services - Spatial Cards */}
            {expertise.related_services && expertise.related_services.length > 0 && (
              <>
                <div className="border-t border-white/10" />
                <div>
                  <h4 className="text-sm font-medium text-white/50 mb-3">Related Services</h4>
                  <div className="space-y-2">
                    {expertise.related_services.map((service, index: any) => (
                      <motion.div 
                        key={service.id}
                        initial={{ opacity: 0, x: -10 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: index * 0.1 }}
                        whileHover={{ scale: 1.02, x: 4 }}
                        className="bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl p-4 cursor-pointer"
                      >
                        <p className="font-medium text-white text-sm">{service.name}</p>
                        <p className="text-xs text-white/40">{service.provider_name}</p>
                      </motion.div>
                    ))}
                  </div>
                </div>
              </>
            )}

            {/* Actions - Spatial Buttons */}
            <div className="space-y-3 pt-4 border-t border-white/10">
              <motion.button
                whileHover={{ scale: 1.02, y: -2 }}
                whileTap={{ scale: 0.98 }}
                onClick={handleEdit}
                className="w-full flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl hover:border-[#FFD700]/50 hover:text-[#FFD700] transition-all text-white shadow-lg"
              >
                <Edit className="w-4 h-4" />
                Edit Expertise
              </motion.button>
              <motion.button
                whileHover={{ scale: 1.02, y: -2 }}
                whileTap={{ scale: 0.98 }}
                onClick={handleDelete}
                className="w-full flex items-center justify-center gap-2 px-4 py-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl hover:border-red-500/50 hover:text-red-400 transition-all text-white shadow-lg"
              >
                <Trash2 className="w-4 h-4" />
                Remove Expertise
              </motion.button>
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
              <h2 className="text-lg font-bold text-text-primary">Edit Expertise</h2>
              <button onClick={() => setIsEditing(false)} className="text-text-secondary hover:text-text-primary">
                <X className="w-5 h-5" />
              </button>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">Expertise Name</label>
              {!isCustom ? (
                <motion.button
                  whileHover={{ scale: 1.02, y: -2 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => setIsCustom(true)}
                  className="w-full p-3 bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl text-left text-white/60 hover:border-[#FFD700]/50 hover:text-white transition-all"
                >
                  + Create custom expertise
                </motion.button>
              ) : (
                <motion.input
                  whileFocus={{ scale: 1.01 }}
                  type="text"
                  value={editForm.name}
                  onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                  placeholder="e.g. Bohemian Knotless Braiding"
                  className="w-full bg-white/5 border border-white/10 backdrop-blur-xl rounded-xl px-4 py-3 text-white focus:outline-none focus:border-[#FFD700]/50 transition-all placeholder-white/30"
                />
              )}
            </motion.div>

            {!isCustom && (
              <motion.div
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2 }}
              >
                <label className="block text-sm font-medium text-white/50 mb-2">Select from Craft Taxonomy</label>
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
                      : '+ Select from craft taxonomy'
                    }
                  </motion.button>
                ) : (
                  <div className="space-y-3">
                    <motion.input
                      whileFocus={{ scale: 1.01 }}
                      type="text"
                      value={taxonomySearch}
                      onChange={(e) => setTaxonomySearch(e.target.value)}
                      placeholder="Search expertise..."
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
                            setEditForm({ ...editForm, craft_taxonomy_id: item.id, name: item.name });
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
            )}

            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
            >
              <label className="block text-sm font-medium text-white/50 mb-2">Skill Level</label>
              <div className="grid grid-cols-2 gap-2">
                {skillLevels.map((level, index: any) => (
                  <motion.button
                    key={level.value}
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ delay: 0.3 + index * 0.05 }}
                    whileHover={{ scale: 1.05, y: -2 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={() => setEditForm({ ...editForm, skill_level: level.value })}
                    className={`px-4 py-2.5 rounded-xl text-sm font-medium transition-all backdrop-blur-xl border ${
                      editForm.skill_level === level.value
                        ? 'bg-[#FFD700] border-[#FFD700] text-black shadow-lg shadow-[#FFD700]/20'
                        : 'bg-white/5 border-white/10 text-white/60 hover:border-[#FFD700]/30 hover:text-white'
                    }`}
                  >
                    {level.label}
                  </motion.button>
                ))}
              </div>
            </motion.div>

            <motion.button
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              whileHover={{ scale: 1.02, y: -2 }}
              whileTap={{ scale: 0.98 }}
              onClick={handleSaveEdit}
              disabled={isSubmitting || !editForm.name.trim()}
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
