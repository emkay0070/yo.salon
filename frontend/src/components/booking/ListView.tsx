'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter } from 'lucide-react';
import AppointmentCard from './AppointmentCard';
import { Booking } from './TimelineView';

interface ListViewProps {
  bookings: Booking[];
  onBookingClick: (booking: Booking) => void;
  currentUserRole?: string;
  currentUserName?: string;
}

export default function ListView({ 
  bookings, 
  onBookingClick,
  currentUserRole,
  currentUserName 
}: ListViewProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');

  // Filter bookings based on role - stylists only see their own bookings
  const filteredBookings = bookings.filter(booking => {
    const matchesRole = currentUserRole === 'employee' 
      ? booking.staffName === currentUserName 
      : true;
    
    const matchesSearch = searchQuery === '' || 
      booking.customerName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      booking.service.toLowerCase().includes(searchQuery.toLowerCase()) ||
      booking.staffName.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesStatus = statusFilter === 'all' || booking.status === statusFilter;

    return matchesRole && matchesSearch && matchesStatus;
  });

  const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'checked_in', label: 'Checked In' },
    { value: 'in_service', label: 'In Service' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
  ];

  return (
    <div className="space-y-4">
      {/* Search and Filters */}
      <div className="flex flex-col md:flex-row gap-4">
        <div className="relative flex-1">
          <Search className="w-5 h-5 text-text-secondary absolute left-4 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search customer, service, or staff..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-12 pr-4 py-3 bg-card border border-border-light rounded-xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
          />
        </div>

        <div className="flex items-center gap-2">
          <Filter className="w-5 h-5 text-text-secondary" />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="px-4 py-3 bg-card border border-border-light rounded-xl text-text-primary focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
          >
            {statusOptions.map(option => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Bookings List */}
      <div className="space-y-3">
        {filteredBookings.length === 0 ? (
          <div className="relative flex flex-col items-center justify-center py-16 text-center rounded-2xl overflow-hidden border border-border-light mt-2">
            <div className="absolute inset-0 z-0">
              <img src="/images/salon_empty_chair.png" alt="Empty List" className="w-full h-full object-cover opacity-20" />
              <div className="absolute inset-0 bg-gradient-to-t from-[#121212] via-[#121212]/80 to-transparent"></div>
            </div>
            <div className="relative z-10">
              <div className="w-14 h-14 mx-auto rounded-2xl bg-card border border-border-light flex items-center justify-center mb-4 shadow-xl backdrop-blur-md">
                <Filter className="w-6 h-6 text-text-secondary" />
              </div>
              <p className="text-text-primary font-medium mb-1">No bookings found</p>
              <p className="text-text-secondary text-sm">Try adjusting your filters or search query.</p>
            </div>
          </div>
        ) : (
          filteredBookings.map((booking, index) => (
            <motion.div
              key={booking.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.05 }}
            >
              <AppointmentCard
                booking={booking}
                onClick={() => onBookingClick(booking)}
              />
            </motion.div>
          ))
        )}
      </div>

      {/* Results Count */}
      {filteredBookings.length > 0 && (
        <p className="text-text-secondary text-sm text-center">
          Showing {filteredBookings.length} of {bookings.length} bookings
        </p>
      )}
    </div>
  );
}
