'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { AlertTriangle, Rocket, Loader2, CheckCircle, X } from 'lucide-react';
import { useRole } from '@/contexts/RoleContext';

export default function ScheduleMigrationBanner() {
  const { providerId, isLoading: roleLoading } = useRole();
  const queryClient = useQueryClient();
  const [dismissed, setDismissed] = useState(false);

  const validProviderId = providerId && providerId !== 'null' && providerId !== 'undefined' ? providerId : null;

  const { data: migrationStatus, isLoading } = useQuery({
    queryKey: ['schedule-migration-status', validProviderId],
    queryFn: () => apiClient.checkScheduleMigration(),
    enabled: !!validProviderId && !roleLoading && !dismissed,
  });

  const migrateMutation = useMutation({
    mutationFn: () => apiClient.migrateSchedule(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['schedule-migration-status', validProviderId] });
      queryClient.invalidateQueries({ queryKey: ['provider-schedules', validProviderId] });
    },
  });

  if (roleLoading || isLoading) {
    return null;
  }

  if (!migrationStatus?.needs_migration || dismissed) {
    return null;
  }

  return (
    <div className="rounded-xl px-4 py-4 bg-gradient-to-r from-amber-500/10 to-orange-500/10 border border-amber-500/20 mb-6">
      <div className="flex items-start gap-4">
        <div className="flex-shrink-0">
          <AlertTriangle className="w-6 h-6 text-amber-400" />
        </div>
        <div className="flex-1 min-w-0">
          <h3 className="text-base font-semibold text-amber-200 mb-1">
            Schedule Update Required
          </h3>
          <p className="text-sm text-amber-300/80 mb-3">
            Your business hours need to be updated to our new scheduling system. This one-time update will improve booking accuracy and enable new features.
          </p>
          <div className="flex items-center gap-3">
            <button
              onClick={() => migrateMutation.mutate()}
              disabled={migrateMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-black font-semibold rounded-lg hover:opacity-90 disabled:opacity-50 transition-opacity text-sm"
            >
              {migrateMutation.isPending ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Updating...
                </>
              ) : (
                <>
                  <Rocket className="w-4 h-4" />
                  Update Now
                </>
              )}
            </button>
            <button
              onClick={() => setDismissed(true)}
              className="text-sm text-amber-400/60 hover:text-amber-400 transition-colors"
            >
              Remind me later
            </button>
          </div>
          {migrateMutation.isSuccess && (
            <div className="mt-3 flex items-center gap-2 text-sm text-emerald-400">
              <CheckCircle className="w-4 h-4" />
              Schedule updated successfully!
            </div>
          )}
          {migrateMutation.isError && (
            <div className="mt-3 text-sm text-red-400">
              Failed to update schedule. Please try again or contact support.
            </div>
          )}
        </div>
        <button
          onClick={() => setDismissed(true)}
          className="flex-shrink-0 text-amber-400/60 hover:text-amber-400 transition-colors"
        >
          <X className="w-5 h-5" />
        </button>
      </div>
    </div>
  );
}
