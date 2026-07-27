'use client';

import { useEffect, useState, useRef } from 'react';
import { 
  Scissors, Search, Plus, Clock, X, Edit, MoreVertical, 
  Sparkles, Upload, ChevronLeft, ChevronRight, LayoutGrid, List
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion, AnimatePresence, useScroll, useTransform } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useQuery } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';

interface Service {
  id: string;
  name: string;
  description: string;
  price: number;
  duration: number;
  category: string;
  active: boolean;
  image_path?: string;
  image_url?: string;
  bookings_count?: number;
}

// Cinematic photo pool for drawer headers
const HEADER_PHOTOS = [
  '/images/salon-styling.jpg',
  '/images/salon-luxury.png',
  '/images/salon-mirror.jpg',
  '/images/salon-hero.jpg',
  '/images/salon-auth.jpg',
  '/images/salon-station.jpg',
];

function getHeaderPhoto(id: string) {
  const hash = id.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  return HEADER_PHOTOS[hash % HEADER_PHOTOS.length];
}

function ServiceDetailsDrawer({ service, onClose, onEdit }: { service: Service, onClose: () => void, onEdit: () => void }) {
  const scrollRef = useRef<HTMLDivElement>(null);
  
  // Disable background scrolling when drawer is open
  useEffect(() => {
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = 'auto';
    };
  }, []);
  
  const { scrollY } = useScroll({ container: scrollRef });
  
  // Fade in sticky navbar and fade out main header text
  const stickyNavOpacity = useTransform(scrollY, [150, 200], [0, 1]);
  const mainHeaderOpacity = useTransform(scrollY, [50, 150], [1, 0]);
  
  return (
    <>
      {/* Backdrop */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
        onClick={onClose}
        className="fixed inset-0 bg-[var(--color-overlay)] backdrop-blur-sm z-50"
      />

      {/* Drawer */}
      <motion.div
        initial={{ x: '100%' }}
        animate={{ x: 0 }}
        exit={{ x: '100%' }}
        transition={{ type: 'spring', damping: 32, stiffness: 280 }}
        className="fixed top-0 right-0 h-full w-full max-w-[520px] bg-[var(--color-surface)] border-l border-[var(--color-border-medium)] z-[60] shadow-2xl flex flex-col"
      >
        {/* ── Sticky Navbar (Fades in on scroll) ──────────────── */}
        <motion.div 
          style={{ opacity: stickyNavOpacity }}
          className="absolute top-0 left-0 right-0 h-[72px] bg-[var(--color-surface)]/95 backdrop-blur-md border-b border-[var(--color-border-light)] z-30 flex items-center px-5 gap-3 pointer-events-none"
        >
          <div className="w-9 h-9 rounded-xl bg-[var(--color-gold)]/10 flex items-center justify-center text-sm font-bold text-[var(--color-text-primary)] shadow-sm border border-[var(--color-gold)]/20">
            <Scissors className="w-4 h-4 text-[var(--color-gold)]" />
          </div>
          <h2 className="text-base font-bold text-[var(--color-text-primary)] truncate">{service.name}</h2>
        </motion.div>

        {/* Close Button - always visible */}
        <button
          onClick={onClose}
          className="absolute top-5 right-5 p-2 bg-[var(--color-surface)]/50 backdrop-blur-md border border-[var(--color-border-light)] rounded-full text-[var(--color-text-primary)]/80 hover:text-[var(--color-text-primary)] hover:bg-[var(--color-card)] transition-all z-40"
        >
          <X className="w-4 h-4" />
        </button>

        {/* ── Scrollable Body ───────────────────────────────────── */}
        <div ref={scrollRef} className="flex-1 overflow-y-auto custom-scrollbar pb-24">
          
          {/* ── Cinematic Header ────────────────────────────────── */}
          <div className="relative h-[280px] shrink-0 overflow-hidden">
            {/* Photo */}
            {service.image_url ? (
              <img
                src={service.image_url}
                alt=""
                className="absolute inset-0 w-full h-full object-cover"
                style={{ objectPosition: 'center 30%' }}
              />
            ) : (
              <img
                src={getHeaderPhoto(service.id)}
                alt=""
                className="absolute inset-0 w-full h-full object-cover"
                style={{ objectPosition: 'center 30%' }}
              />
            )}
            {/* Scrim */}
            <div className="absolute inset-0 bg-gradient-to-b from-black/20 via-black/40 to-[var(--color-surface)]" />
            
            <motion.div style={{ opacity: mainHeaderOpacity }} className="absolute inset-0 pointer-events-none">
              {/* Status chip */}
              <div className="absolute top-5 left-5 pointer-events-auto">
                <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md border ${
                  service.active
                    ? 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400'
                    : 'bg-red-500/20 border-red-500/30 text-red-400'
                }`}>
                  {service.active ? '● Active' : '○ Inactive'}
                </span>
              </div>

              {/* Avatar + info */}
              <div className="absolute bottom-0 left-0 right-0 px-6 pb-6 pointer-events-auto">
                <div className="relative w-fit mb-3">
                  <div className="w-[72px] h-[72px] rounded-2xl bg-[var(--color-gold)]/10 flex items-center justify-center text-2xl font-bold text-[var(--color-text-primary)] shadow-2xl border-2 border-[var(--color-gold)]/20">
                    <Scissors className="w-8 h-8 text-[var(--color-gold)]" />
                  </div>
                </div>

                <div className="flex items-start justify-between">
                  <div>
                    <h2 className="text-[22px] font-bold text-[var(--color-text-primary)] tracking-tight mb-0.5 drop-shadow-md">
                      {service.name}
                    </h2>
                    <p className="text-[var(--color-text-secondary)] text-sm">
                      {service.category} · {service.duration} min
                    </p>
                  </div>
                  <span className="text-[var(--color-gold)] font-bold text-xl">
                    UGX {service.price.toLocaleString()}
                  </span>
                </div>
              </div>
            </motion.div>
          </div>

          {/* ── Quick Actions ───────────────────────────────────── */}
          <div className="px-5 py-4 flex gap-2 shrink-0">
            <button 
              onClick={onEdit}
              className="flex-1 py-2.5 bg-gradient-to-r from-[var(--color-gold)] to-[var(--color-dark-gold)] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[var(--color-gold)]/10"
            >
              Edit Service
            </button>
          </div>

          {/* ── Description ───────────────────────────────────── */}
          <div className="px-5 py-4">
            <h3 className="text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-3 flex items-center gap-2">
              Description
            </h3>
            <p className="text-[var(--color-text-primary)]/80 text-sm leading-relaxed">
              {service.description}
            </p>
          </div>

          {/* ── Analytics ────────────────────────────────────── */}
          <div className="px-5 py-4 space-y-6">
            <h3 className="text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-3 flex items-center gap-2">
              <Sparkles className="w-3.5 h-3.5 text-[var(--color-gold)]" /> Performance Overview
            </h3>
            
            <div className="grid grid-cols-2 gap-3">
              <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-4 text-center">
                <p className="text-[var(--color-text-primary)] font-bold text-lg">{service.bookings_count || 0}</p>
                <p className="text-[var(--color-text-secondary)] text-xs mt-0.5">Total Bookings</p>
              </div>
              <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-4 text-center">
                <p className="text-[var(--color-gold)] font-bold text-lg">UGX {((service.bookings_count || 0) * service.price).toLocaleString()}</p>
                <p className="text-[var(--color-text-secondary)] text-xs mt-0.5">Est. Revenue</p>
              </div>
            </div>

            {/* Recent Bookings Visualization */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-medium)] rounded-xl p-5">
              <h5 className="text-[var(--color-text-primary)] text-sm font-medium mb-4">Booking Activity (Past 7 Days)</h5>
              <div className="flex items-end justify-between h-24 gap-2">
                {/* Mock activity bars based on overall booking scale */}
                {[40, 20, 60, 80, 100, 30, 50].map((height, i) => {
                  const activeHeight = service.bookings_count ? height : 0;
                  return (
                    <div key={i} className="w-full bg-[var(--color-card)] rounded-t-sm relative group hover:bg-white/10 transition-colors h-full flex items-end">
                      <div 
                        className="w-full bg-[var(--color-gold)] rounded-t-sm transition-all duration-500"
                        style={{ height: `${activeHeight}%` }}
                      ></div>
                    </div>
                  );
                })}
              </div>
              <div className="flex justify-between text-[var(--color-text-secondary)] text-[10px] mt-2">
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
                <span>Sun</span>
              </div>
            </div>
          </div>
        </div>
      </motion.div>
    </>
  );
}

export default function ServicesPage() {
  const { salonId } = useRole();
  const [services, setServices] = useState<Service[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [mounted, setMounted] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [activeCategory, setActiveCategory] = useState('All Services');
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('grid');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 7;
  
  const [formData, setFormData] = useState({
    name: '',
    description: '',
    price: '',
    duration: '',
    category: '',
    active: true,
    image: null as File | null,
    image_preview: '',
  });
  const [editingServiceId, setEditingServiceId] = useState<string | null>(null);
  const [selectedService, setSelectedService] = useState<Service | null>(null);
  const [isDetailsOpen, setIsDetailsOpen] = useState(false);

  // Fetch bookings to calculate real booking counts for services
  const { data: bookings = [] } = useQuery({
    queryKey: ['bookings', salonId],
    queryFn: () => apiClient.getBookings({ salon_id: salonId }),
    enabled: !!salonId,
  });

  useEffect(() => {
    setMounted(true);
  }, []);

  const { data, isLoading } = useQuery({
    queryKey: ['services', salonId],
    queryFn: () => apiClient.getServices({ salon_id: salonId }),
    enabled: !!salonId,
  });

  useEffect(() => {
    if (data) {
      // Calculate real booking counts from bookings data
      const servicesWithCounts = data.map((service: any) => {
        const bookingCount = bookings.filter((b: any) => b.service_id === service.id).length;
        return { ...service, bookings_count: bookingCount };
      });
      setServices(servicesWithCounts);
    }
  }, [data, bookings]);

  // Compute dynamic categories
  const categoriesMap = services.reduce((acc, service) => {
    acc[service.category] = (acc[service.category] || 0) + 1;
    return acc;
  }, {} as Record<string, number>);
  
  const categoryList = [
    { name: 'All Services', count: services.length },
    ...Object.entries(categoriesMap).map(([name, count]) => ({ name, count }))
  ];

  const filteredServices = services.filter(service => {
    const matchesSearch = service.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
                          service.category.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesCategory = activeCategory === 'All Services' || service.category === activeCategory;
    return matchesSearch && matchesCategory;
  });

  const totalPages = Math.max(1, Math.ceil(filteredServices.length / itemsPerPage));
  const paginatedServices = filteredServices.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

  // Reset to page 1 if filter changes
  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, activeCategory]);

  const openEditModal = (service: Service) => {
    setFormData({
      name: service.name ?? '',
      description: service.description ?? '',
      price: service.price.toString(),
      duration: service.duration.toString(),
      category: service.category,
      active: service.active,
      image: null,
      image_preview: service.image_url || '',
    });
    setEditingServiceId(service.id);
    setIsModalOpen(true);
  };

  const openDetails = (service: Service) => {
    setSelectedService(service);
    setIsDetailsOpen(true);
  };

  const handleAddService = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const data = new FormData();
      data.append('name', formData.name);
      data.append('description', formData.description);
      data.append('price', formData.price);
      data.append('duration', formData.duration);
      data.append('category', formData.category || 'Uncategorized');
      data.append('active', formData.active ? '1' : '0');
      
      if (formData.image) {
        data.append('image', formData.image);
      }

      let returnedService: Service;
      if (editingServiceId) {
        returnedService = await apiClient.updateService(editingServiceId, data);
        setServices(services.map(s => s.id === editingServiceId ? { ...s, ...returnedService, bookings_count: s.bookings_count } : s));
      } else {
        returnedService = await apiClient.createService(data);
        setServices([...services, { ...returnedService, bookings_count: 0 }]);
      }

      setIsModalOpen(false);
      setEditingServiceId(null);
      setFormData({ name: '', description: '', price: '', duration: '', category: '', active: true, image: null, image_preview: '' });
    } catch (err) {
      console.error('Failed to save service:', err);
    }
  };

  if (!mounted) return null;

  return (
    <DashboardLayout>
      <div className="max-w-[1600px] mx-auto font-sans pb-12 overflow-x-hidden">
        {/* Page Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="font-serif text-3xl font-semibold text-[var(--color-text-primary)] tracking-tight">Services</h1>
                        <p className="text-[var(--color-text-secondary)] text-sm mt-1">Manage the services you offer in your salon</p>
          </div>
          <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <button className="flex items-center gap-2 px-3 sm:px-4 py-2.5 bg-[var(--color-card)] text-[var(--color-text-primary)] border border-[var(--color-border-light)] rounded-xl font-medium hover:bg-white/10 transition-colors text-sm">
              <Upload className="w-4 h-4" />
              <span className="hidden sm:inline">Export</span>
            </button>
            <button onClick={() => {
              setEditingServiceId(null);
              setFormData({ name: '', description: '', price: '', duration: '', category: '', active: true, image: null, image_preview: '' });
              setIsModalOpen(true);
            }} className="flex items-center gap-2 px-3 sm:px-4 py-2.5 bg-gradient-to-r from-[var(--color-gold)] to-[var(--color-dark-gold)] text-black rounded-xl font-medium hover:opacity-90 transition-opacity shadow-lg shadow-[var(--color-gold)]/10 text-sm">
              <Plus className="w-4 sm:w-5 h-4 sm:h-5" />
              <span className="hidden sm:inline">Add New Service</span>
              <span className="sm:hidden">Add</span>
            </button>
          </div>
        </div>

        {/* Main Content Area: Master/Detail Layout */}
        <div className="flex flex-col lg:flex-row gap-6 items-start">
                
                {/* Left Column: Categories */}
                <div className="w-full lg:w-[280px] xl:w-[320px] flex flex-col shrink-0 space-y-4 lg:space-y-6">
                    <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl p-4 backdrop-blur-md">
                        <div className="flex items-center justify-between mb-3 lg:mb-4 px-2">
                            <h3 className="text-[var(--color-text-primary)] font-medium text-sm">Categories</h3>
                            <button className="hidden lg:flex w-6 h-6 rounded bg-[var(--color-gold)]/10 text-[var(--color-gold)] items-center justify-center hover:bg-[var(--color-gold)]/20 transition-colors">
                                <Plus className="w-3.5 h-3.5" />
                            </button>
                        </div>
                        {/* Horizontal on mobile, vertical on desktop */}
                        <div className="flex flex-row lg:flex-col gap-2 lg:gap-1 overflow-x-auto lg:overflow-visible pb-2 lg:pb-0 scrollbar-hide">
                            {categoryList.map((category) => (
                                <button
                                    key={category.name}
                                    onClick={() => setActiveCategory(category.name)}
                                    className={`shrink-0 lg:w-full flex items-center justify-between px-4 lg:px-3 py-2 lg:py-2.5 rounded-full lg:rounded-xl text-sm transition-colors ${
                                        activeCategory === category.name 
                                          ? 'bg-[var(--color-gold)] text-black lg:bg-[var(--color-gold)]/10 lg:border-l-2 lg:border-[var(--color-gold)] lg:text-[var(--color-text-primary)] font-medium' 
                                          : 'bg-[var(--color-card)] lg:bg-transparent text-[var(--color-text-secondary)] hover:bg-white/10 lg:hover:bg-[var(--color-card)] hover:text-[var(--color-text-primary)] lg:border-l-2 lg:border-transparent'
                                    }`}
                                >
                    <span>{category.name}</span>
                    <span className={`hidden lg:inline text-xs ${activeCategory === category.name ? 'text-[var(--color-gold)]' : 'text-[var(--color-text-secondary)]'}`}>
                      {category.count}
                    </span>
                  </button>
                ))}
              </div>
            </div>

            {/* Promo Card (Hidden on Mobile) */}
                    <div className="hidden lg:block bg-gradient-to-b from-[var(--color-gold)]/5 to-transparent border border-[var(--color-gold)]/10 rounded-2xl p-6 text-center">
                        <div className="w-12 h-12 mx-auto bg-[var(--color-gold)]/10 rounded-xl flex items-center justify-center mb-4">
                            <Sparkles className="w-6 h-6 text-[var(--color-gold)]" />
                        </div>
                        <h4 className="text-[var(--color-text-primary)] font-medium mb-2">Organize your services</h4>
                        <p className="text-[var(--color-text-secondary)] text-xs leading-relaxed mb-6">
                            Create categories to keep your services organized and make booking easier for your clients.
                        </p>
                        <button className="w-full py-2.5 bg-[var(--color-gold)]/10 text-[var(--color-gold)] hover:bg-[var(--color-gold)]/20 transition-colors rounded-xl text-sm font-medium border border-[var(--color-gold)]/20 flex items-center justify-center gap-2">
                            <Plus className="w-4 h-4" /> Add Category
                        </button>
                    </div>
                </div>

                {/* Right Column: Services List */}
                <div className="flex-1 min-w-0 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl backdrop-blur-md flex flex-col overflow-hidden">
            
            {/* Toolbar */}
            <div className="p-3 sm:p-4 border-b border-[var(--color-border-light)] flex flex-col sm:flex-row gap-3 sm:gap-4 items-start sm:items-center justify-between">
              <div className="relative w-full sm:w-64 md:w-80">
                <Search className="w-4 h-4 text-[var(--color-text-secondary)] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  placeholder="Search services..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-9 pr-4 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-lg text-[var(--color-text-primary)] placeholder-[#A0A0A0] focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors text-sm"
                />
              </div>
              <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto flex-wrap">
                <select className="bg-black/20 border border-[var(--color-border-light)] rounded-lg text-[var(--color-text-primary)] text-sm px-3 py-2 outline-none focus:border-[var(--color-gold)]/50">
                  <option value="all">All Categories</option>
                  {Object.keys(categoriesMap).map(cat => <option key={cat} value={cat}>{cat}</option>)}
                </select>
                <select className="bg-black/20 border border-[var(--color-border-light)] rounded-lg text-[var(--color-text-primary)] text-sm px-3 py-2 outline-none focus:border-[var(--color-gold)]/50">
                  <option value="all">All Status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <div className="flex items-center bg-black/20 border border-[var(--color-border-light)] rounded-lg p-1">
                  <button 
                    onClick={() => setViewMode('list')}
                    className={`p-1.5 rounded ${viewMode === 'list' ? 'bg-[var(--color-gold)] text-black' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'}`}
                  >
                    <List className="w-4 h-4" />
                  </button>
                  <button 
                    onClick={() => setViewMode('grid')}
                    className={`p-1.5 rounded ${viewMode === 'grid' ? 'bg-[var(--color-gold)] text-black' : 'text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'}`}
                  >
                    <LayoutGrid className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            {/* Services Content */}
            <div className="flex-1 overflow-x-auto overflow-y-auto">
              {paginatedServices.length === 0 ? (
                <div className="relative w-full h-[400px] rounded-2xl overflow-hidden border border-[var(--color-border-light)] flex flex-col items-center justify-center mt-4">
                  <div className="absolute inset-0 z-0">
                    <img src="/images/salon_tools.png" alt="Empty Services" className="w-full h-full object-cover opacity-30" />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#121212] via-[#121212]/80 to-transparent"></div>
                  </div>
                  <div className="relative z-10 flex flex-col items-center text-center p-6 backdrop-blur-md bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl shadow-2xl">
                    <div className="w-16 h-16 rounded-2xl bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center mb-4">
                      <Scissors className="w-8 h-8 text-[var(--color-gold)]" />
                    </div>
                    <h3 className="text-xl font-medium text-[var(--color-text-primary)] mb-2">No services found</h3>
                    <p className="text-[var(--color-text-secondary)] max-w-[280px] mb-6 text-sm leading-relaxed">Build your salon's menu by adding your first premium service.</p>
                    <button onClick={() => {
                      setEditingServiceId(null);
                      setFormData({ name: '', description: '', price: '', duration: '', category: '', active: true, image: null, image_preview: '' });
                      setIsModalOpen(true);
                    }} className="px-6 py-2.5 bg-gradient-to-r from-[var(--color-gold)] to-[var(--color-dark-gold)] text-black font-medium rounded-xl hover:opacity-90 transition-opacity shadow-lg shadow-[var(--color-gold)]/10 text-sm">
                      Add First Service
                    </button>
                  </div>
                </div>
              ) : viewMode === 'list' ? (
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-white/5 text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">
                      <th className="font-medium p-4 pl-6">Service</th>
                      <th className="font-medium p-4">Category</th>
                      <th className="font-medium p-4">Duration</th>
                      <th className="font-medium p-4">Price</th>
                      <th className="font-medium p-4">Popularity</th>
                      <th className="font-medium p-4">Status</th>
                      <th className="font-medium p-4 text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-white/5">
                    {paginatedServices.map((service) => (
                      <tr key={service.id} className="hover:bg-white/[0.02] transition-colors group">
                        <td className="p-4 pl-6">
                    <div className="flex items-center gap-4">
                      <div className="w-20 h-14 rounded-lg bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center shrink-0 overflow-hidden">
                        {service.image_url ? (
                          <img src={service.image_url} alt={service.name} className="w-full h-full object-cover" />
                        ) : (
                          <div className="w-full h-full bg-gradient-to-br from-[var(--color-gold)]/20 to-[var(--color-gold)]/5 flex items-center justify-center">
                            <Scissors className="w-5 h-5 text-[var(--color-gold)]" />
                          </div>
                        )}
                      </div>
                      <div className="min-w-0">
                        <h3 className="text-[var(--color-text-primary)] font-medium text-sm truncate">{service.name}</h3>
                        <p className="text-[var(--color-text-secondary)] text-xs mt-0.5 line-clamp-1 max-w-[200px] xl:max-w-[300px]">{service.description}</p>
                      </div>
                    </div>
                  </td>
                        <td className="p-4">
                          <span className="bg-[var(--color-gold)]/10 text-[var(--color-gold)] text-[10px] px-2 py-1 rounded font-medium border border-[var(--color-gold)]/20 whitespace-nowrap">
                            {service.category}
                          </span>
                        </td>
                        <td className="p-4">
                          <div className="flex items-center gap-1.5 text-[var(--color-text-primary)] text-sm">
                            <Clock className="w-3.5 h-3.5 text-[var(--color-text-secondary)]" /> {service.duration} min
                          </div>
                        </td>
                        <td className="p-4">
                          <span className="text-[var(--color-text-primary)] text-sm font-medium">UGX {service.price.toLocaleString()}</span>
                        </td>
                        <td className="p-4 w-40">
                          <div className="w-full bg-white/10 rounded-full h-1.5 mb-1.5 overflow-hidden">
                            <div 
                              className="bg-[var(--color-gold)] h-1.5 rounded-full" 
                              style={{ width: `${Math.min(100, (service.bookings_count || 0) / 4)}%` }}
                            ></div>
                          </div>
                          <p className="text-[var(--color-text-secondary)] text-[10px]">{service.bookings_count} bookings</p>
                        </td>
                        <td className="p-4">
                          <span className={`text-[10px] px-2 py-1 rounded font-medium border ${
                            service.active 
                              ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                              : 'bg-red-500/10 text-red-400 border-red-500/20'
                          }`}>
                            {service.active ? 'Active' : 'Inactive'}
                          </span>
                        </td>
                        <td className="p-4 text-center">
                          <div className="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onClick={() => openEditModal(service)} className="p-1.5 text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] border border-transparent hover:border-[var(--color-border-light)] hover:bg-[var(--color-card)] rounded-md transition-all">
                              <Edit className="w-4 h-4" />
                            </button>
                            <button onClick={() => openDetails(service)} className="p-1.5 text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] border border-transparent hover:border-[var(--color-border-light)] hover:bg-[var(--color-card)] rounded-md transition-all">
                              <MoreVertical className="w-4 h-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <div className="p-4 sm:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                  {paginatedServices.map((service) => {
                    // Pick a subtle accent color per category for the top stripe
                    const categoryColors: Record<string, string> = {
                      Hair:     'from-violet-500/60 to-violet-700/40',
                      Beard:    'from-amber-500/60 to-amber-700/40',
                      Nails:    'from-rose-500/60 to-rose-700/40',
                      Skin:     'from-cyan-500/60 to-cyan-700/40',
                      Makeup:   'from-pink-500/60 to-pink-700/40',
                      Massage:  'from-teal-500/60 to-teal-700/40',
                      Waxing:   'from-orange-500/60 to-orange-700/40',
                    };
                    const stripeGradient = categoryColors[service.category] ?? 'from-[var(--color-gold)]/50 to-[var(--color-dark-gold)]/30';

                    return (
                      <motion.div
                        key={service.id}
                        whileHover={{ y: -2, boxShadow: '0 16px 48px rgba(0,0,0,0.5)' }}
                        transition={{ duration: 0.18 }}
                        className="relative bg-[var(--color-card)] border border-[var(--color-border-light)] hover:border-[var(--color-border-medium)] rounded-2xl overflow-hidden cursor-pointer group flex flex-col"
                      >
                        {/* Category accent stripe — thin, colourful, meaningful */}
                        <div className={`h-[3px] w-full bg-gradient-to-r ${stripeGradient} opacity-90 shrink-0`} />

                        <div className="p-4 flex flex-col gap-3 flex-1">
                          {/* Top row: icon + status dot */}
                          <div className="flex items-start justify-between">
                            <div className="w-9 h-9 rounded-xl bg-[var(--color-card)] border border-[var(--color-border-light)] flex items-center justify-center shrink-0">
                              {service.image_url ? (
                                <img
                                  src={service.image_url}
                                  alt={service.name}
                                  className="w-full h-full object-cover rounded-xl"
                                />
                              ) : (
                                <Scissors className="w-4 h-4 text-[var(--color-text-primary)]/30" />
                              )}
                            </div>
                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium border ${
                              service.active
                                ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'
                                : 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20'
                            }`}>
                              {service.active ? 'Active' : 'Inactive'}
                            </span>
                          </div>

                          {/* Service name */}
                          <h3 className="text-[var(--color-text-primary)] font-semibold text-sm leading-snug">
                            {service.name}
                          </h3>

                          {/* Meta chips: category + duration */}
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-[10px] px-2 py-0.5 rounded-md bg-[var(--color-card)] border border-[var(--color-border-light)] text-[var(--color-text-secondary)] font-medium">
                              {service.category}
                            </span>
                            <span className="flex items-center gap-1 text-[10px] text-[var(--color-text-secondary)]">
                              <Clock className="w-3 h-3" />
                              {service.duration} min
                            </span>
                          </div>

                          {/* Bottom: price + bookings */}
                          <div className="flex items-center justify-between mt-auto pt-3 border-t border-[var(--color-border-light)]">
                            <span className="text-[var(--color-gold)] font-semibold text-sm">
                              UGX {service.price.toLocaleString()}
                            </span>
                            <span className="text-[var(--color-text-secondary)] text-[10px]">
                              {service.bookings_count ?? 0} bookings
                            </span>
                          </div>
                        </div>

                        {/* Hover: inline actions slide in */}
                        <div className="absolute bottom-0 inset-x-0 flex gap-2 p-3 bg-gradient-to-t from-[var(--color-card)] via-[var(--color-card)]/90 to-transparent opacity-0 group-hover:opacity-100 translate-y-1 group-hover:translate-y-0 transition-all duration-200">
                          <button
                            onClick={() => openEditModal(service)}
                            className="flex-1 flex items-center justify-center gap-1.5 py-1.5 text-xs font-medium text-[var(--color-text-primary)]/80 bg-[var(--color-card)] hover:bg-white/10 border border-[var(--color-border-light)] rounded-lg transition"
                          >
                            <Edit className="w-3.5 h-3.5" /> Edit
                          </button>
                          <button
                            onClick={() => openDetails(service)}
                            className="flex-1 flex items-center justify-center gap-1.5 py-1.5 text-xs font-medium text-black bg-[var(--color-gold)] hover:bg-[var(--color-gold)]/90 rounded-lg transition"
                          >
                            Details
                          </button>
                        </div>
                      </motion.div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Pagination Controls */}
            {totalPages > 1 && (
              <div className="p-3 sm:p-4 border-t border-[var(--color-border-light)] flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-0 bg-black/10">
                <p className="text-[var(--color-text-secondary)] text-xs">
                  Showing {(currentPage - 1) * itemsPerPage + 1} to {Math.min(currentPage * itemsPerPage, filteredServices.length)} of {filteredServices.length} services
                </p>
                <div className="flex items-center gap-1">
                  <button 
                    onClick={() => setCurrentPage(p => Math.max(1, p - 1))}
                    disabled={currentPage === 1}
                    className="p-1.5 border border-[var(--color-border-light)] rounded bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] disabled:opacity-30 transition-colors"
                  >
                    <ChevronLeft className="w-4 h-4" />
                  </button>
                  {Array.from({ length: totalPages }).map((_, i) => (
                    <button
                      key={i}
                      onClick={() => setCurrentPage(i + 1)}
                      className={`w-7 h-7 flex items-center justify-center rounded text-xs font-medium transition-colors ${
                        currentPage === i + 1 
                          ? 'bg-[var(--color-gold)]/20 text-[var(--color-gold)] border border-[var(--color-gold)]/30' 
                          : 'text-[var(--color-text-secondary)] hover:bg-white/10 border border-transparent hover:border-[var(--color-border-light)]'
                      }`}
                    >
                      {i + 1}
                    </button>
                  ))}
                  <button 
                    onClick={() => setCurrentPage(p => Math.min(totalPages, p + 1))}
                    disabled={currentPage === totalPages}
                    className="p-1.5 border border-[var(--color-border-light)] rounded bg-[var(--color-card)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] disabled:opacity-30 transition-colors"
                  >
                    <ChevronRight className="w-4 h-4" />
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Add Service Modal */}
      <AnimatePresence>
        {isModalOpen && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4"
            onClick={() => setIsModalOpen(false)}
          >
            <motion.div
              initial={{ opacity: 0, scale: 0.95, y: 10 }}
              animate={{ opacity: 1, scale: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95, y: 10 }}
              onClick={(e) => e.stopPropagation()}
              className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl p-6 w-full max-w-md shadow-2xl max-h-[90vh] overflow-y-auto [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-white/10 [&::-webkit-scrollbar-thumb]:rounded-full"
            >
              <div className="flex items-center justify-between mb-5">
                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">{editingServiceId ? 'Edit Service' : 'New Service'}</h2>
                <button onClick={() => setIsModalOpen(false)} className="p-2 rounded-lg bg-black/20 hover:bg-white/10 border border-[var(--color-border-light)] transition-colors">
                  <X className="w-4 h-4 text-[var(--color-text-secondary)]" />
                </button>
              </div>

              <form onSubmit={handleAddService} className="space-y-4">
                {/* Image Upload Area */}
                <div>
                  <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-2">Service Image</label>
                  <div className="flex items-center gap-4">
                    <div className="w-16 h-16 rounded-xl bg-black/20 border border-[var(--color-border-light)] overflow-hidden flex items-center justify-center shrink-0">
                      {formData.image_preview ? (
                        <img src={formData.image_preview} alt="Preview" className="w-full h-full object-cover" />
                      ) : (
                        <Upload className="w-5 h-5 text-[var(--color-text-secondary)]" />
                      )}
                    </div>
                    <div className="flex-1">
                      <label className="cursor-pointer inline-flex items-center justify-center px-4 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-lg text-[var(--color-text-primary)] hover:bg-white/10 transition-colors text-sm font-medium">
                        <span>Choose Image</span>
                        <input 
                          type="file" 
                          accept="image/*" 
                          className="hidden"
                          onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                              setFormData({
                                ...formData,
                                image: file,
                                image_preview: URL.createObjectURL(file)
                              });
                            }
                          }}
                        />
                      </label>
                      <p className="text-[11px] text-[var(--color-text-secondary)] mt-1.5">Max 2MB. Optional.</p>
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="col-span-2">
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Service Name</label>
                    <input
                      type="text"
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      className="w-full px-3 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors text-sm"
                    />
                  </div>

                  <div className="col-span-2">
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Description</label>
                    <textarea
                      required
                      value={formData.description ?? ''}
                      onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                      rows={2}
                      className="w-full px-3 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors resize-none text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Price (UGX)</label>
                    <input
                      type="number"
                      required
                      min="0"
                      value={formData.price}
                      onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                      className="w-full px-3 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors text-sm"
                    />
                  </div>

                  <div>
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Duration (min)</label>
                    <input
                      type="number"
                      required
                      min="1"
                      value={formData.duration}
                      onChange={(e) => setFormData({ ...formData, duration: e.target.value })}
                      className="w-full px-3 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors text-sm"
                    />
                  </div>

                  <div className="col-span-2">
                    <label className="block text-[var(--color-text-secondary)] text-xs font-semibold uppercase tracking-wider mb-1.5">Category</label>
                    <input
                      type="text"
                      required
                      value={formData.category}
                      onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                      className="w-full px-3 py-2 bg-black/20 border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] placeholder-white/20 focus:outline-none focus:border-[var(--color-gold)]/50 transition-colors text-sm"
                    />
                  </div>
                </div>

                <div className="flex items-center justify-between pt-1 pb-1">
                  <label className="flex items-center gap-3 cursor-pointer group">
                    <div className="relative flex items-center">
                      <input
                        type="checkbox"
                        checked={formData.active}
                        onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                        className="peer appearance-none w-5 h-5 border border-border-medium rounded bg-black/20 checked:bg-[var(--color-gold)] checked:border-[var(--color-gold)] transition-colors cursor-pointer"
                      />
                      <svg className="absolute w-3.5 h-3.5 pointer-events-none hidden peer-checked:block text-black left-[3px] top-[3px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                      </svg>
                    </div>
                    <span className="text-[var(--color-text-primary)] text-sm font-medium group-hover:text-white transition-colors">Active Service</span>
                  </label>
                </div>

                <div className="flex gap-3 pt-3">
                  <button
                    type="button"
                    onClick={() => setIsModalOpen(false)}
                    className="flex-1 px-4 py-2.5 bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-xl text-[var(--color-text-primary)] hover:bg-white/5 transition-colors font-medium text-sm"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="flex-1 px-4 py-2.5 bg-gradient-to-r from-[var(--color-gold)] to-[var(--color-dark-gold)] text-black rounded-xl font-bold hover:opacity-90 transition-opacity shadow-lg shadow-[var(--color-gold)]/20 text-sm"
                  >
                    {editingServiceId ? 'Update Service' : 'Save Service'}
                  </button>
                </div>
              </form>
            </motion.div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Service Details Drawer */}
      <AnimatePresence>
        {isDetailsOpen && selectedService && (
          <ServiceDetailsDrawer 
            service={selectedService} 
            onClose={() => setIsDetailsOpen(false)} 
            onEdit={() => { setIsDetailsOpen(false); openEditModal(selectedService); }} 
          />
        )}
      </AnimatePresence>
    </DashboardLayout>
  );
}
