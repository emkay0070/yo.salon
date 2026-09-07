'use client';

import { motion } from 'framer-motion';
import { Database, CheckCircle, XCircle, Clock, Play, RefreshCw, AlertTriangle, Settings } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useState } from 'react';

interface SeederStatus {
  id: string;
  seeder_class: string;
  display_name: string;
  description: string | null;
  category: string;
  is_seeded: boolean;
  last_seeded_at: string | null;
  last_seeded_by: string | null;
  records_count: number;
  is_required: boolean;
  priority: number;
  created_at: string;
  updated_at: string;
}

interface Summary {
  total: number;
  seeded: number;
  not_seeded: number;
  required_not_seeded: number;
}

interface SeedersResponse {
  data: SeederStatus[];
  summary: Summary;
}

export default function SeederManagement() {
  const [selectedCategory, setSelectedCategory] = useState<string>('all');
  const [showRequiredOnly, setShowRequiredOnly] = useState(false);
  const [showNotSeededOnly, setShowNotSeededOnly] = useState(false);
  const queryClient = useQueryClient();

  const { data: seedersData, isLoading } = useQuery<SeedersResponse>({
    queryKey: ['admin-seeders', selectedCategory, showRequiredOnly, showNotSeededOnly],
    queryFn: () => apiClient.get(`/admin/seeders?category=${selectedCategory}&required_only=${showRequiredOnly}&not_seeded_only=${showNotSeededOnly}`),
  });

  const runSeederMutation = useMutation({
    mutationFn: ({ seederClass, unseed }: { seederClass: string; unseed?: boolean }) =>
      apiClient.post(`/admin/seeders/${seederClass}/run`, { unseed }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-seeders'] });
    },
  });

  const runMultipleMutation = useMutation({
    mutationFn: (seederClasses: string[]) =>
      apiClient.post('/admin/seeders/batch-run', { seeder_classes: seederClasses }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-seeders'] });
    },
  });

  const discoverMutation = useMutation({
    mutationFn: () => apiClient.post('/admin/seeders/discover'),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['admin-seeders'] });
      alert(`Discovery completed: ${data.registered} registered, ${data.updated} updated`);
    },
  });

  const handleRunSeeder = (seederClass: string, unseed = false) => {
    if (confirm(unseed ? 'Are you sure you want to mark this seeder as unseeded? Data cleanup must be handled manually.' : 'Are you sure you want to run this seeder?')) {
      runSeederMutation.mutate({ seederClass, unseed });
    }
  };

  const handleRunAllRequired = () => {
    const requiredNotSeeded = seedersData?.data?.filter(
      (s) => s.is_required && !s.is_seeded
    ).map((s) => s.seeder_class) || [];
    
    if (requiredNotSeeded.length === 0) {
      alert('All required seeders are already seeded.');
      return;
    }

    if (confirm(`Run ${requiredNotSeeded.length} required seeders?`)) {
      runMultipleMutation.mutate(requiredNotSeeded);
    }
  };

  const categories = ['all', ...new Set(seedersData?.data?.map((s) => s.category) || [])];

  const getStatusBadge = (seeder: SeederStatus) => {
    if (seeder.is_seeded) {
      return (
        <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-green-500/20 text-green-400">
          <CheckCircle className="w-3 h-3" />
          Seeded
        </span>
      );
    }
    return (
      <span className="flex items-center gap-1 text-xs px-2 py-1 rounded-full bg-yellow-500/20 text-yellow-400">
        <XCircle className="w-3 h-3" />
        Not Seeded
      </span>
    );
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div>
          <h1 className="text-2xl font-bold text-text-primary flex items-center gap-2">
            <Database className="w-6 h-6 text-gold" />
            Seeder Management
          </h1>
          <p className="text-text-secondary mt-1">Manage platform data seeders</p>
        </div>
      </motion.div>

      {/* Summary Cards */}
      {seedersData?.summary && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="grid grid-cols-2 md:grid-cols-4 gap-4"
        >
          {[
            { label: 'Total Seeders', value: seedersData.summary.total, icon: Database, color: 'text-gold' },
            { label: 'Seeded', value: seedersData.summary.seeded, icon: CheckCircle, color: 'text-green-400' },
            { label: 'Not Seeded', value: seedersData.summary.not_seeded, icon: XCircle, color: 'text-yellow-400' },
            {
              label: 'Required & Not Seeded',
              value: seedersData.summary.required_not_seeded,
              icon: AlertTriangle,
              color: 'text-red-400',
            },
          ].map((stat, index) => (
            <motion.div
              key={stat.label}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-4 backdrop-blur-2xl"
            >
              <div className="flex items-center gap-3">
                <stat.icon className={`w-5 h-5 ${stat.color}`} />
                <div>
                  <p className="text-text-secondary text-xs">{stat.label}</p>
                  <p className="text-xl font-bold text-text-primary">{stat.value}</p>
                </div>
              </div>
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Filters */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-4 backdrop-blur-2xl"
      >
        <div className="flex flex-wrap items-center gap-4">
          <div className="flex items-center gap-2">
            <label className="text-text-secondary text-sm">Category:</label>
            <select
              value={selectedCategory}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="px-3 py-2 rounded-lg bg-surface border border-border-medium text-text-primary text-sm focus:outline-none focus:border-gold/50"
            >
              {categories.map((cat) => (
                <option key={cat} value={cat}>
                  {cat === 'all' ? 'All Categories' : cat.charAt(0).toUpperCase() + cat.slice(1)}
                </option>
              ))}
            </select>
          </div>

          <label className="flex items-center gap-2 text-text-secondary text-sm cursor-pointer">
            <input
              type="checkbox"
              checked={showRequiredOnly}
              onChange={(e) => setShowRequiredOnly(e.target.checked)}
              className="rounded"
            />
            Required Only
          </label>

          <label className="flex items-center gap-2 text-text-secondary text-sm cursor-pointer">
            <input
              type="checkbox"
              checked={showNotSeededOnly}
              onChange={(e) => setShowNotSeededOnly(e.target.checked)}
              className="rounded"
            />
            Not Seeded Only
          </label>

          {seedersData?.summary?.required_not_seeded && seedersData.summary.required_not_seeded > 0 && (
            <button
              onClick={handleRunAllRequired}
              className="ml-auto px-4 py-2 rounded-lg bg-gradient-to-r from-gold to-amber-600 text-white text-sm font-medium hover:opacity-90 transition-opacity"
            >
              Run All Required ({seedersData.summary.required_not_seeded})
            </button>
          )}

          <button
            onClick={() => {
              if (confirm('Discover and register all seeders from the seeders directory?')) {
                discoverMutation.mutate();
              }
            }}
            disabled={discoverMutation.isPending}
            className="px-4 py-2 rounded-lg bg-surface border border-border-medium text-text-primary text-sm font-medium hover:bg-surface/80 transition-colors disabled:opacity-50 flex items-center gap-2"
          >
            <Settings className="w-4 h-4" />
            Discover Seeders
          </button>
        </div>
      </motion.div>

      {/* Seeders List */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl overflow-hidden backdrop-blur-2xl"
      >
        {isLoading ? (
          <div className="p-8 text-center text-text-secondary">Loading seeders...</div>
        ) : !seedersData?.data || seedersData.data.length === 0 ? (
          <div className="p-8 text-center text-text-secondary">No seeders found</div>
        ) : (
          <div className="divide-y divide-border-medium">
            {seedersData.data.map((seeder, index) => (
              <motion.div
                key={seeder.id}
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                className="p-4 hover:bg-surface/50 transition-colors"
              >
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-3 mb-2">
                      <h3 className="font-semibold text-text-primary">{seeder.display_name}</h3>
                      {getStatusBadge(seeder)}
                      {seeder.is_required && (
                        <span className="text-xs px-2 py-1 rounded-full bg-red-500/20 text-red-400">
                          Required
                        </span>
                      )}
                      <span className="text-xs px-2 py-1 rounded-full bg-blue-500/20 text-blue-400">
                        {seeder.category}
                      </span>
                    </div>
                    <p className="text-text-secondary text-sm mb-2">{seeder.description}</p>
                    <div className="flex items-center gap-4 text-xs text-text-secondary">
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" />
                        Last seeded: {formatDate(seeder.last_seeded_at)}
                      </span>
                      {seeder.last_seeded_by && (
                        <span>by {seeder.last_seeded_by}</span>
                      )}
                      {seeder.records_count > 0 && (
                        <span>{seeder.records_count} records</span>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {seeder.is_seeded ? (
                      <button
                        onClick={() => handleRunSeeder(seeder.seeder_class, true)}
                        disabled={runSeederMutation.isPending}
                        className="p-2 rounded-lg bg-yellow-500/20 text-yellow-400 hover:bg-yellow-500/30 transition-colors disabled:opacity-50"
                        title="Mark as unseeded"
                      >
                        <RefreshCw className="w-4 h-4" />
                      </button>
                    ) : (
                      <button
                        onClick={() => handleRunSeeder(seeder.seeder_class)}
                        disabled={runSeederMutation.isPending}
                        className="p-2 rounded-lg bg-green-500/20 text-green-400 hover:bg-green-500/30 transition-colors disabled:opacity-50"
                        title="Run seeder"
                      >
                        <Play className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        )}
      </motion.div>
    </div>
  );
}
