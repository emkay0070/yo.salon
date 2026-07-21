'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  Bell,
  Search,
  User,
  Plus,
  Clock,
  DollarSign,
  Users,
  Calendar as CalendarIcon,
  XCircle,
  CheckCircle,
  X
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useRole } from '@/contexts/RoleContext';
import OwnerDashboard from '@/components/dashboard/OwnerDashboard';
import ManagerDashboard from '@/components/dashboard/ManagerDashboard';
import EmployeeDashboard from '@/components/dashboard/EmployeeDashboard';
import ReceptionistDashboard from '@/components/dashboard/ReceptionistDashboard';
import PlatformAdminDashboard from '@/components/dashboard/PlatformAdminDashboard';
import OnboardingChecklist from '@/components/dashboard/OnboardingChecklist';

interface Salon {
  id: string;
  name: string;
  slug: string;
  description?: string;
  city?: string;
}

interface Booking {
  id: string;
  date: string;
  time: string;
  status: string;
  customer: {
    name: string;
    phone: string;
  };
  service: {
    name: string;
    price: number;
  };
  staff?: {
    name: string;
  };
}

interface Staff {
  id: string;
  name: string;
  specializations?: string[];
}


export default function DashboardPage() {
  const router = useRouter();
  const { role, userName } = useRole();
  const [salons, setSalons] = useState<Salon[]>([]);
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [staff, setStaff] = useState<Staff[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);
  const [isWalkInModalOpen, setIsWalkInModalOpen] = useState(false);
  const [walkInFormData, setWalkInFormData] = useState({
    customerName: '',
    customerPhone: '',
    service: '',
    staffId: '',
  });

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    async function loadData() {
      try {
        const [salonsData, bookingsData, staffData] = await Promise.all([
          apiClient.getSalons(),
          apiClient.getBookings(),
          apiClient.getStaff(),
        ]);
        setSalons(salonsData);
        setBookings(Array.isArray(bookingsData) ? bookingsData : []);
        setStaff(Array.isArray(staffData) ? staffData : []);
      } catch (err: any) {
        if (err.response?.status === 401) {
          window.location.href = '/login';
          return;
        }
        setError('Failed to load data from API');
        console.error(err);
      }
    }

    const token = localStorage.getItem('auth_token');
    if (!token) {
      window.location.href = '/login';
      return;
    }

    loadData();
  }, []);

  // Get today's date
  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-US', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long' 
  });

  // Filter today's bookings
  const todayBookings = bookings.filter(booking => {
    const bookingDate = new Date(booking.date);
    return bookingDate.toDateString() === today.toDateString();
  });

  // Calculate stats
  const todayRevenue = todayBookings.reduce((sum, b) => {
    const price = b.service?.price ?? 0;
    return sum + (typeof price === 'number' ? price : 0);
  }, 0);

  const completedBookings = todayBookings.filter(b => b.status === 'completed').length;
  const pendingBookings = todayBookings.filter(b => b.status === 'pending').length;
  const cancelledBookings = todayBookings.filter(b => b.status === 'cancelled').length;

  const handleWalkInSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const newBooking = await apiClient.createBooking({
        customer: {
          name: walkInFormData.customerName,
          phone: walkInFormData.customerPhone,
        },
        service: {
          name: walkInFormData.service,
          price: 0, // Will be determined by service selection
        },
        staff: staff.find(s => s.id === walkInFormData.staffId),
        date: new Date().toISOString(),
        time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
        status: 'pending',
      });
      setBookings([...bookings, newBooking]);
      setIsWalkInModalOpen(false);
      setWalkInFormData({
        customerName: '',
        customerPhone: '',
        service: '',
        staffId: '',
      });
    } catch (err) {
      console.error('Failed to create walk-in booking:', err);
    }
  };

  if (error) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full">
          <div className="text-gold">{error}</div>
        </div>
      </DashboardLayout>
    );
  }

  // Render appropriate dashboard based on user role
  const renderDashboard = () => {
    switch (role) {
      case 'owner':
        return <OwnerDashboard userName={userName} />;
      case 'manager':
        return <ManagerDashboard userName={userName} />;
      case 'employee':
        return <EmployeeDashboard userName={userName} />;
      case 'receptionist':
        return <ReceptionistDashboard userName={userName} />;
      case 'platform_admin':
        return <PlatformAdminDashboard userName={userName} />;
      default:
        return <OwnerDashboard userName={userName} />;
    }
  };

  return (
    <DashboardLayout>
      <OnboardingChecklist />
      {renderDashboard()}
    </DashboardLayout>
  );
}
