'use client';

import { motion } from 'framer-motion';
import { Target, Calendar, TrendingUp, Award, CheckCircle2 } from 'lucide-react';
import SideDrawer from '../shared/SideDrawer';

interface GoalDetailsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  goal: any;
}

export default function GoalDetailsDrawer({ isOpen, onClose, goal }: GoalDetailsDrawerProps) {
  if (!goal) return null;

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose} title="Goal Details" position="right">
      <div className="p-6 space-y-6">
        <div>
          <h3 className="text-xl font-bold text-text-primary mb-2">{goal.title}</h3>
          <div className="flex items-center gap-2">
            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
              goal.status === 'ACTIVE' ? 'bg-green-400/20 text-green-400' : 
              goal.status === 'PAUSED' ? 'bg-amber-400/20 text-amber-400' : 
              'bg-blue-400/20 text-blue-400'
            }`}>
              {goal.status}
            </span>
            <span className={`px-2 py-1 rounded-full text-xs font-medium capitalize ${
              goal.priority === 'HIGH' || goal.priority === 'URGENT' ? 'bg-red-400/20 text-red-400' :
              goal.priority === 'MEDIUM' ? 'bg-amber-400/20 text-amber-400' :
              'bg-blue-400/20 text-blue-400'
            }`}>
              {goal.priority} Priority
            </span>
            <span className="px-2 py-1 rounded-full text-xs font-medium bg-surface text-text-secondary capitalize border border-border-light">
              {goal.scope?.toLowerCase()}
            </span>
          </div>
        </div>

        {goal.description && (
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <h4 className="text-sm font-medium text-text-primary mb-2">Description</h4>
            <p className="text-sm text-text-secondary whitespace-pre-line">{goal.description}</p>
          </div>
        )}

        <div className="grid grid-cols-2 gap-4">
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              <Calendar className="w-4 h-4 text-gold" />
              <span className="text-sm font-medium text-text-primary">Deadline</span>
            </div>
            <p className="text-sm text-text-secondary">
              {goal.deadline ? new Date(goal.deadline).toLocaleDateString() : 'No deadline'}
            </p>
          </div>

          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <div className="flex items-center gap-2 mb-2">
              {goal.goal_type === 'NUMERIC' ? (
                <TrendingUp className="w-4 h-4 text-gold" />
              ) : (
                <Award className="w-4 h-4 text-gold" />
              )}
              <span className="text-sm font-medium text-text-primary">Type</span>
            </div>
            <p className="text-sm text-text-secondary capitalize">
              {goal.goal_type?.toLowerCase()}
            </p>
          </div>
        </div>

        {goal.goal_type === 'NUMERIC' && (
          <div className="bg-surface rounded-xl p-4 border border-border-light">
            <h4 className="text-sm font-medium text-text-primary mb-3">Progress</h4>
            <div className="flex justify-between mb-2">
              <span className="text-sm text-text-secondary">
                {goal.current_value || 0} / {goal.target_value} {goal.unit || ''}
              </span>
              <span className="text-sm font-medium text-text-primary">
                {goal.progress_percent?.toFixed(0) || 0}%
              </span>
            </div>
            <div className="h-2 bg-card rounded-full overflow-hidden border border-border-light">
              <div
                className="h-full bg-gradient-to-r from-gold to-amber-600 transition-all"
                style={{ width: `${Math.min(goal.progress_percent || 0, 100)}%` }}
              />
            </div>
          </div>
        )}

        {goal.goal_type === 'QUALITATIVE' && goal.milestones && goal.milestones.length > 0 && (
          <div className="space-y-3">
            <h4 className="text-sm font-medium text-text-primary">Milestones</h4>
            {goal.milestones.map((milestone: any, index: number) => (
              <div key={index} className="bg-surface rounded-xl p-4 border border-border-light">
                <div className="flex items-start gap-3">
                  <div className={`mt-0.5 w-5 h-5 rounded-full flex items-center justify-center shrink-0 ${
                    milestone.status === 'COMPLETED' ? 'bg-green-400/20 text-green-400' : 'bg-card border border-border-light text-text-secondary'
                  }`}>
                    {milestone.status === 'COMPLETED' ? (
                      <CheckCircle2 className="w-3 h-3" />
                    ) : (
                      <span className="text-xs">{index + 1}</span>
                    )}
                  </div>
                  <div>
                    <h5 className="text-sm font-medium text-text-primary">{milestone.title}</h5>
                    {milestone.criteria && (
                      <p className="text-xs text-text-secondary mt-1">{milestone.criteria}</p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </SideDrawer>
  );
}
