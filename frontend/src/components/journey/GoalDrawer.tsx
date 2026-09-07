'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Target, X, Plus, Trash2, Calendar, TrendingUp, Award, CheckCircle } from 'lucide-react';
import SideDrawer from '../shared/SideDrawer';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

interface GoalDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  goal?: {
    id: string;
    title: string;
    description?: string;
    goal_type: string;
    metric_key?: string;
    target_value?: number;
    unit?: string;
    deadline?: string;
    priority: string;
    scope: string;
    milestones?: Array<{
      id: string;
      title: string;
      description?: string;
      criteria: string;
      status: string;
    }>;
  };
}

const goalTypes = [
  { value: 'NUMERIC', label: 'Numeric Target', icon: TrendingUp },
  { value: 'QUALITATIVE', label: 'Qualitative Milestone', icon: Award },
];

const priorities = [
  { value: 'LOW', label: 'Low', color: 'bg-blue-400/20 text-blue-400' },
  { value: 'MEDIUM', label: 'Medium', color: 'bg-amber-400/20 text-amber-400' },
  { value: 'HIGH', label: 'High', color: 'bg-red-400/20 text-red-400' },
  { value: 'URGENT', label: 'Urgent', color: 'bg-red-500/20 text-red-500' },
];

const scopes = [
  { value: 'SPECIALIST', label: 'Personal' },
  { value: 'WORKPLACE', label: 'Workplace' },
];

const metricKeys = [
  { value: 'clients_served', label: 'Clients Served' },
  { value: 'rating', label: 'Rating' },
  { value: 'repeat_client_rate', label: 'Repeat Client Rate' },
  { value: 'services_performed', label: 'Services Performed' },
  { value: 'revenue', label: 'Revenue' },
];

export default function GoalDrawer({ isOpen, onClose, goal }: GoalDrawerProps) {
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(!!goal);
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    goal_type: 'NUMERIC',
    metric_key: '',
    target_value: '',
    unit: '',
    deadline: '',
    priority: 'MEDIUM',
    scope: 'SPECIALIST',
    milestones: [] as any[],
  });

  const [newMilestone, setNewMilestone] = useState({
    title: '',
    description: '',
    criteria: '',
  });

  useEffect(() => {
    if (isOpen) {
      setIsEditing(!!goal);
      setFormData({
        title: goal?.title || '',
        description: goal?.description || '',
        goal_type: goal?.goal_type || 'NUMERIC',
        metric_key: goal?.metric_key || '',
        target_value: goal?.target_value?.toString() || '',
        unit: goal?.unit || '',
        deadline: goal?.deadline ? new Date(goal.deadline).toISOString().split('T')[0] : '',
        priority: goal?.priority || 'MEDIUM',
        scope: goal?.scope || 'SPECIALIST',
        milestones: goal?.milestones || [],
      });
      setNewMilestone({ title: '', description: '', criteria: '' });
    }
  }, [isOpen, goal]);

  const createMutation = useMutation({
    mutationFn: (data: any) => apiClient.post('/v1/specialist-portal/goals', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
      onClose();
    },
    onError: (error: any) => {
      console.error('Create goal error:', error);
      alert(error.response?.data?.message || 'Failed to create goal');
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: any }) => 
      apiClient.put(`/v1/specialist-portal/goals/${id}`, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
      onClose();
    },
    onError: (error: any) => {
      console.error('Update goal error:', error);
      alert(error.response?.data?.message || 'Failed to update goal');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: string) => apiClient.delete(`/v1/specialist-portal/goals/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-journey'] });
      onClose();
    },
    onError: (error: any) => {
      console.error('Delete goal error:', error);
      alert(error.response?.data?.message || 'Failed to delete goal');
    },
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    const data = {
      ...formData,
      target_value: formData.goal_type === 'NUMERIC' ? parseFloat(formData.target_value) : null,
      milestones: formData.goal_type === 'QUALITATIVE' ? formData.milestones : undefined,
    };

    if (goal?.id) {
      updateMutation.mutate({ id: goal.id, data });
    } else {
      createMutation.mutate(data);
    }
  };

  const addMilestone = () => {
    if (newMilestone.title && newMilestone.criteria) {
      setFormData({
        ...formData,
        milestones: [...formData.milestones, { ...newMilestone, id: Date.now().toString() }],
      });
      setNewMilestone({ title: '', description: '', criteria: '' });
    }
  };

  const removeMilestone = (id: string) => {
    setFormData({
      ...formData,
      milestones: formData.milestones.filter(m => m.id !== id),
    });
  };

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose} title={goal ? 'Edit Goal' : 'Create Goal'} position="top">
      <form onSubmit={handleSubmit} className="space-y-6 p-6">
        {/* Title */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Goal Title</label>
          <input
            type="text"
            value={formData.title}
            onChange={(e) => setFormData({ ...formData, title: e.target.value })}
            className="w-full px-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold"
            placeholder="e.g., Reach 1000 Clients"
            required
          />
        </div>

        {/* Description */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Description</label>
          <textarea
            value={formData.description}
            onChange={(e) => setFormData({ ...formData, description: e.target.value })}
            className="w-full px-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold resize-none"
            rows={3}
            placeholder="Describe your goal..."
          />
        </div>

        {/* Goal Type */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Goal Type</label>
          <div className="grid grid-cols-2 gap-3">
            {goalTypes.map((type) => {
              const Icon = type.icon;
              return (
                <button
                  key={type.value}
                  type="button"
                  onClick={() => setFormData({ ...formData, goal_type: type.value })}
                  className={`flex items-center gap-2 p-3 rounded-lg border transition-all ${
                    formData.goal_type === type.value
                      ? 'border-gold bg-gold/20 text-gold'
                      : 'border-border-light bg-surface text-text-secondary hover:border-gold/50'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                  <span className="text-sm">{type.label}</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Numeric Goal Fields */}
        {formData.goal_type === 'NUMERIC' && (
          <>
            <div>
              <label className="block text-sm font-medium text-text-primary mb-2">Metric</label>
              <select
                value={formData.metric_key}
                onChange={(e) => setFormData({ ...formData, metric_key: e.target.value })}
                className="w-full px-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold"
              >
                <option value="">Select a metric...</option>
                {metricKeys.map((metric) => (
                  <option key={metric.value} value={metric.value}>
                    {metric.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">Target Value</label>
                <input
                  type="number"
                  value={formData.target_value}
                  onChange={(e) => setFormData({ ...formData, target_value: e.target.value })}
                  className="w-full px-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold"
                  placeholder="1000"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-text-primary mb-2">Unit</label>
                <input
                  type="text"
                  value={formData.unit}
                  onChange={(e) => setFormData({ ...formData, unit: e.target.value })}
                  className="w-full px-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold"
                  placeholder="clients"
                />
              </div>
            </div>
          </>
        )}

        {/* Qualitative Goal Milestones */}
        {formData.goal_type === 'QUALITATIVE' && (
          <div>
            <label className="block text-sm font-medium text-text-primary mb-2">Milestones</label>
            <div className="space-y-3">
              {formData.milestones.map((milestone) => (
                <div key={milestone.id} className="p-3 bg-surface border border-border-light rounded-lg">
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1">
                      <p className="font-medium text-text-primary">{milestone.title}</p>
                      {milestone.description && (
                        <p className="text-sm text-text-secondary mt-1">{milestone.description}</p>
                      )}
                      <p className="text-xs text-text-secondary mt-1">Criteria: {milestone.criteria}</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => removeMilestone(milestone.id)}
                      className="p-1 text-red-400 hover:text-red-300"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              ))}
              
              <div className="p-3 bg-surface border border-border-light rounded-lg space-y-2">
                <input
                  type="text"
                  value={newMilestone.title}
                  onChange={(e) => setNewMilestone({ ...newMilestone, title: e.target.value })}
                  className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-gold"
                  placeholder="Milestone title"
                />
                <input
                  type="text"
                  value={newMilestone.description}
                  onChange={(e) => setNewMilestone({ ...newMilestone, description: e.target.value })}
                  className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-gold"
                  placeholder="Description (optional)"
                />
                <input
                  type="text"
                  value={newMilestone.criteria}
                  onChange={(e) => setNewMilestone({ ...newMilestone, criteria: e.target.value })}
                  className="w-full px-3 py-2 bg-card border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-gold"
                  placeholder="Success criteria"
                />
                <button
                  type="button"
                  onClick={addMilestone}
                  className="w-full flex items-center justify-center gap-2 px-3 py-2 bg-gold/20 text-gold rounded-lg text-sm hover:bg-gold/30 transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  Add Milestone
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Deadline */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Deadline</label>
          <div className="relative">
            <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
            <input
              type="date"
              value={formData.deadline}
              onChange={(e) => setFormData({ ...formData, deadline: e.target.value })}
              className="w-full pl-10 pr-4 py-2 bg-surface border border-border-light rounded-lg text-text-primary focus:outline-none focus:ring-2 focus:ring-gold"
            />
          </div>
        </div>

        {/* Priority */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Priority</label>
          <div className="grid grid-cols-4 gap-2">
            {priorities.map((priority) => (
              <button
                key={priority.value}
                type="button"
                onClick={() => setFormData({ ...formData, priority: priority.value })}
                className={`px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                  formData.priority === priority.value
                    ? priority.color
                    : 'bg-surface text-text-secondary border border-border-light hover:border-gold/50'
                }`}
              >
                {priority.label}
              </button>
            ))}
          </div>
        </div>

        {/* Scope */}
        <div>
          <label className="block text-sm font-medium text-text-primary mb-2">Scope</label>
          <div className="grid grid-cols-2 gap-3">
            {scopes.map((scope) => (
              <button
                key={scope.value}
                type="button"
                onClick={() => setFormData({ ...formData, scope: scope.value })}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  formData.scope === scope.value
                    ? 'bg-gold/20 text-gold border-gold'
                    : 'bg-surface text-text-secondary border border-border-light hover:border-gold/50'
                }`}
              >
                {scope.label}
              </button>
            ))}
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center gap-3 pt-4 border-t border-border-light">
          {goal && (
            <button
              type="button"
              onClick={() => {
                if (confirm('Are you sure you want to delete this goal?')) {
                  deleteMutation.mutate(goal.id);
                }
              }}
              className="px-4 py-2 bg-red-400/20 text-red-400 rounded-lg text-sm font-medium hover:bg-red-400/30 transition-colors"
            >
              Delete
            </button>
          )}
          <button
            type="button"
            onClick={onClose}
            className="flex-1 px-4 py-2 bg-surface text-text-secondary rounded-lg text-sm font-medium hover:bg-surface/80 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMutation.isPending || updateMutation.isPending}
            className="flex-1 px-4 py-2 bg-gradient-to-r from-gold to-amber-600 text-white rounded-lg text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            {createMutation.isPending || updateMutation.isPending ? 'Saving...' : goal ? 'Update' : 'Create'}
          </button>
        </div>
      </form>
    </SideDrawer>
  );
}
