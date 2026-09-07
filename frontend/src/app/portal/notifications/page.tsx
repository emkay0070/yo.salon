'use client';

import { motion } from 'framer-motion';
import { Bell, CheckCircle, Clock, AlertCircle, Calendar, CreditCard, Gift, MessageSquare, Settings, Check, Star } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';

export default function NotificationsPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const queryClient = useQueryClient();
  const [filter, setFilter] = useState<'all' | 'unread' | 'bookings' | 'payments' | 'promotions' | 'messages' | 'system'>('all');

  const { data: notificationsResponse, isLoading } = useQuery({
    queryKey: ['portal-notifications'],
    queryFn: () => portalApiClient.get('/portal/notifications'),
    enabled: !!customer,
  });

  const markAsReadMutation = useMutation({
    mutationFn: (id: string) => portalApiClient.post(`/portal/notifications/${id}/read`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-notifications'] });
    },
  });

  const markAllAsReadMutation = useMutation({
    mutationFn: () => portalApiClient.post('/portal/notifications/read-all'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-notifications'] });
    },
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const notifications = notificationsResponse?.data?.notifications?.data || [];
  const unreadCount = notificationsResponse?.data?.unread_count || 0;

  // Map notification types to icons and colors
  const getNotificationIcon = (type: string) => {
    const iconMap: Record<string, any> = {
      booking_confirmed: CheckCircle,
      reminder: Calendar,
      payment: CreditCard,
      payment_received: CreditCard,
      review: Star,
      review_reminder: Star,
      promotion: Gift,
      message: MessageSquare,
      system: Settings,
    };
    return iconMap[type] || Bell;
  };

  const getNotificationColor = (type: string) => {
    const colorMap: Record<string, string> = {
      booking_confirmed: '#10B981',
      reminder: '#8B5CF6',
      payment: '#3B82F6',
      payment_received: '#3B82F6',
      review: 'var(--brand-primary, #FFD700)',
      review_reminder: 'var(--brand-primary, #FFD700)',
      promotion: '#EF4444',
      message: '#F59E0B',
      system: '#6B7280',
    };
    return colorMap[type] || '#6B7280';
  };

  const filteredNotifications = filter === 'all'
    ? notifications
    : filter === 'unread'
    ? notifications.filter((n: any) => !n.read_at)
    : notifications.filter((n: any) => n.type?.includes(filter));

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-text-primary mb-2">Notifications</h1>
            <p className="text-text-secondary">Stay updated with your bookings and offers</p>
          </div>
          {unreadCount > 0 && (
            <button
              onClick={() => markAllAsReadMutation.mutate()}
              disabled={markAllAsReadMutation.isPending}
              className="flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl"
              style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <Check className="w-4 h-4" />
              Mark All Read
            </button>
          )}
        </div>
      </motion.div>

      {/* Filter Pills */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'all', label: 'All' },
          { id: 'unread', label: 'Unread', badge: unreadCount },
          { id: 'bookings', label: 'Bookings' },
          { id: 'payments', label: 'Payments' },
          { id: 'promotions', label: 'Promotions' },
          { id: 'messages', label: 'Messages' },
          { id: 'system', label: 'System' },
        ].map((f) => (
          <button
            key={f.id}
            onClick={() => setFilter(f.id as any)}
            className={`flex-shrink-0 flex items-center gap-2 px-5 py-2 rounded-2xl text-sm font-medium transition-all ${
              filter === f.id
                ? 'text-white'
                : 'bg-surface border border-border-light text-text-secondary hover:border-gold/30'
            }`}
            style={{
              ...(filter === f.id
                ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                : { borderRadius: 'var(--brand-border-radius, 16px)' })
            }}
          >
            {f.label}
            {f.badge && f.badge > 0 && (
              <span className="w-5 h-5 rounded-full bg-red-500 text-white text-xs flex items-center justify-center">
                {f.badge}
              </span>
            )}
          </button>
        ))}
      </motion.div>

      {/* Notifications List */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="space-y-3"
      >
        {filteredNotifications.length === 0 ? (
          <div className="bg-surface border border-border-light rounded-2xl p-12 text-center" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
            <Bell className="w-16 h-16 text-text-secondary mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-text-primary mb-2">No notifications</h3>
            <p className="text-text-secondary">You're all caught up!</p>
          </div>
        ) : (
          filteredNotifications.map((notification: any, index: number) => (
            <NotificationCard
              key={notification.id}
              notification={notification}
              delay={index * 0.05}
              onMarkAsRead={() => markAsReadMutation.mutate(notification.id.toString())}
              getIcon={getNotificationIcon}
              getColor={getNotificationColor}
            />
          ))
        )}
      </motion.div>
    </div>
  );
}

function NotificationCard({ notification, delay, onMarkAsRead, getIcon, getColor }: { 
  notification: any, 
  delay: number, 
  onMarkAsRead: () => void,
  getIcon: (type: string) => any,
  getColor: (type: string) => string,
}) {
  const Icon = getIcon(notification.type);
  const color = getColor(notification.type);
  const isRead = !!notification.read_at;
  const timestamp = new Date(notification.created_at).toLocaleString();

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay }}
      className={`bg-surface border rounded-2xl p-5 hover:border-gold/30 transition-all cursor-pointer ${
        !isRead ? 'border-gold/30' : 'border-border-light'
      }`}
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      onClick={onMarkAsRead}
    >
      <div className="flex items-start gap-4">
        <div className="relative">
          <div className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style={{ backgroundColor: `${color}20` }}>
            <Icon className="w-6 h-6" style={{ color }} />
          </div>
          {!isRead && (
            <div className="absolute -top-1 -right-1 w-3 h-3 rounded-full bg-red-500" />
          )}
        </div>
        <div className="flex-1">
          <div className="flex items-start justify-between mb-1">
            <h3 className={`font-semibold ${!isRead ? 'text-text-primary' : 'text-text-secondary'}`}>{notification.title}</h3>
            <span className="text-xs text-text-secondary whitespace-nowrap ml-2">{timestamp}</span>
          </div>
          <p className="text-sm text-text-secondary">{notification.message}</p>
        </div>
      </div>
    </motion.div>
  );
}
