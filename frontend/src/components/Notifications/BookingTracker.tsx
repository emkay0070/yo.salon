'use client';

import { useState, useEffect } from 'react';
import { Calendar, Clock, CheckCircle, User, Scissors, Star } from 'lucide-react';
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

interface BookingTrackerProps {
  bookingId: string;
  bookingDate: string;
  bookingTime: string;
  staffName?: string;
  staffRole?: string;
}

export function BookingTracker({ 
  bookingId, 
  bookingDate, 
  bookingTime, 
  staffName, 
  staffRole 
}: BookingTrackerProps) {
  const [activities, setActivities] = useState<Activity[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchActivities = async () => {
    try {
      const response = await apiClient.get(`/portal/bookings/${bookingId}/activities`);
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

  const getStepStatus = (stepType: string) => {
    const activity = activities.find(a => a.type === stepType);
    if (activity) return 'completed';
    
    // Determine if this is the current step
    const stepOrder = ['created', 'payment_requested', 'payment_confirmed', 'confirmed', 'staff_assigned'];
    const currentIndex = stepOrder.findIndex(type => activities.some(a => a.type === type));
    const stepIndex = stepOrder.indexOf(stepType);
    
    if (stepIndex === currentIndex + 1) return 'current';
    return 'pending';
  };

  const getStepIcon = (stepType: string) => {
    switch (stepType) {
      case 'created':
        return <Calendar className="w-5 h-5" />;
      case 'payment_requested':
        return <Clock className="w-5 h-5" />;
      case 'payment_confirmed':
        return <CheckCircle className="w-5 h-5" />;
      case 'confirmed':
        return <CheckCircle className="w-5 h-5" />;
      case 'staff_assigned':
        return <User className="w-5 h-5" />;
      default:
        return <CheckCircle className="w-5 h-5" />;
    }
  };

  const getStepLabel = (stepType: string) => {
    switch (stepType) {
      case 'created':
        return 'Booking Submitted';
      case 'payment_requested':
        return 'Deposit Requested';
      case 'payment_confirmed':
        return 'Deposit Paid';
      case 'confirmed':
        return 'Booking Confirmed';
      case 'staff_assigned':
        return 'Stylist Assigned';
      default:
        return 'Completed';
    }
  };

  const steps = ['created', 'payment_requested', 'payment_confirmed', 'confirmed', 'staff_assigned'];

  if (loading) {
    return (
      <div className="bg-white/5 border border-white/10 rounded-2xl p-6">
        <div className="flex items-center justify-center h-32">
          <div className="animate-spin w-6 h-6 border-2 border-gold border-t-transparent rounded-full" />
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white/5 border border-white/10 rounded-2xl p-6">
      <h3 className="text-lg font-semibold text-white mb-6">Your Appointment</h3>
      
      {/* Appointment Details */}
      <div className="mb-8 p-4 bg-white/5 rounded-xl border border-white/10">
        <div className="flex items-center gap-4">
          <div className="w-12 h-12 rounded-full bg-gold/20 flex items-center justify-center">
            <Calendar className="w-6 h-6 text-gold" />
          </div>
          <div>
            <p className="text-white font-medium">
              {new Date(bookingDate).toLocaleDateString('en-US', { 
                weekday: 'long', 
                month: 'long', 
                day: 'numeric' 
              })}
            </p>
            <p className="text-gray-400 text-sm">{bookingTime}</p>
          </div>
        </div>
        
        {staffName && (
          <div className="flex items-center gap-4 mt-4 pt-4 border-t border-white/10">
            <div className="w-12 h-12 rounded-full bg-purple-500/20 flex items-center justify-center">
              <User className="w-6 h-6 text-purple-400" />
            </div>
            <div>
              <p className="text-white font-medium">{staffName}</p>
              <p className="text-gray-400 text-sm">{staffRole || 'Stylist'}</p>
            </div>
          </div>
        )}
      </div>

      {/* Timeline */}
      <div className="space-y-0">
        {steps.map((step, index) => {
          const status = getStepStatus(step);
          const isLast = index === steps.length - 1;
          
          return (
            <div key={step} className="relative">
              {/* Connector Line */}
              {!isLast && (
                <div className="absolute left-4 top-8 bottom-0 w-0.5 bg-white/10" />
              )}
              
              <div className="flex items-start gap-4 pb-8">
                {/* Status Icon */}
                <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${
                  status === 'completed' ? 'bg-green-500' :
                  status === 'current' ? 'bg-gold' :
                  'bg-white/10'
                }`}>
                  {status === 'completed' ? (
                    <CheckCircle className="w-4 h-4 text-white" />
                  ) : status === 'current' ? (
                    <Clock className="w-4 h-4 text-white animate-pulse" />
                  ) : (
                    <div className="w-3 h-3 rounded-full bg-white/30" />
                  )}
                </div>
                
                {/* Content */}
                <div className="flex-1 pt-1">
                  <p className={`font-medium ${
                    status === 'completed' ? 'text-green-400' :
                    status === 'current' ? 'text-gold' :
                    'text-gray-500'
                  }`}>
                    {getStepLabel(step)}
                  </p>
                  
                  {status === 'current' && (
                    <p className="text-sm text-gray-400 mt-1">In progress...</p>
                  )}
                  
                  {status === 'completed' && (
                    <p className="text-sm text-gray-400 mt-1">Completed</p>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Footer */}
      <div className="mt-6 pt-6 border-t border-white/10 text-center">
        <p className="text-gray-400 text-sm">
          We'll notify you before your appointment
        </p>
      </div>
    </div>
  );
}
