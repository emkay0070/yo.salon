'use client';

import { useState, useEffect } from 'react';
import { Clock, CheckCircle, XCircle, AlertCircle, User } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

interface Activity {
  id: number;
  type: string;
  title: string;
  description: string | null;
  data: any;
  actor_type: string | null;
  created_at: string;
}

interface ActivityTimelineProps {
  bookingId: string;
}

export function ActivityTimeline({ bookingId }: ActivityTimelineProps) {
  const [activities, setActivities] = useState<Activity[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchActivities = async () => {
    try {
      const response = await apiClient.get(`/bookings/${bookingId}/activities`);
      setActivities(response.activities);
    } catch (error) {
      console.error('Failed to fetch activities:', error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchActivities();
  }, [bookingId]);

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'created':
        return <Clock className="w-4 h-4 text-blue-400" />;
      case 'payment_confirmed':
        return <CheckCircle className="w-4 h-4 text-green-400" />;
      case 'confirmed':
        return <CheckCircle className="w-4 h-4 text-gold" />;
      case 'cancelled':
        return <XCircle className="w-4 h-4 text-red-400" />;
      case 'staff_assigned':
        return <User className="w-4 h-4 text-purple-400" />;
      default:
        return <AlertCircle className="w-4 h-4 text-gray-400" />;
    }
  };

  const getActivityColor = (type: string) => {
    switch (type) {
      case 'created':
        return 'border-blue-400';
      case 'payment_confirmed':
        return 'border-green-400';
      case 'confirmed':
        return 'border-gold';
      case 'cancelled':
        return 'border-red-400';
      case 'staff_assigned':
        return 'border-purple-400';
      default:
        return 'border-gray-400';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="animate-spin w-6 h-6 border-2 border-gold border-t-transparent rounded-full" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <h3 className="text-lg font-semibold text-white mb-4">Booking Timeline</h3>
      
      <div className="relative">
        <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-white/10" />
        
        {activities.map((activity, index) => (
          <div key={activity.id} className="relative pl-10 pb-6">
            <div className={`absolute left-2.5 w-3 h-3 rounded-full border-2 bg-[#1C1C22] ${getActivityColor(activity.type)}`} />
            
            <div className="bg-white/5 border border-white/10 rounded-lg p-4">
              <div className="flex items-center gap-2 mb-2">
                {getActivityIcon(activity.type)}
                <h4 className="font-medium text-white">{activity.title}</h4>
              </div>
              
              {activity.description && (
                <p className="text-sm text-gray-400 mb-2">{activity.description}</p>
              )}
              
              <p className="text-xs text-gray-500">
                {new Date(activity.created_at).toLocaleString()}
              </p>
              
              {activity.actor_type && (
                <p className="text-xs text-gray-500 mt-1">
                  by {activity.actor_type}
                </p>
              )}
            </div>
          </div>
        ))}
        
        {activities.length === 0 && (
          <div className="pl-10 py-4 text-gray-400 text-sm">
            No activity recorded yet
          </div>
        )}
      </div>
    </div>
  );
}
