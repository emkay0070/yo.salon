'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { apiClient } from '@/lib/api-client';

interface BusinessCategory {
  id: string;
  slug: string;
  name: string;
  short_name?: string;
  description?: string;
  icon?: string;
  default_specialist_label?: string;
  default_specialist_plural?: string;
  default_appointment_label?: string;
  default_customer_term?: string;
}

interface CategoryContextType {
  category: BusinessCategory | null;
  categorySlug: string | null;
  setCategorySlug: (slug: string | null) => void;
  terminology: {
    specialist: string;
    specialistPlural: string;
    appointment: string;
    appointmentPlural: string;
    customer: string;
    customerPlural: string;
    business: string;
  };
  isLoading: boolean;
}

const CategoryContext = createContext<CategoryContextType | undefined>(undefined);

const DEFAULT_TERMINOLOGY = {
  specialist: 'Specialist',
  specialistPlural: 'Specialists',
  appointment: 'Appointment',
  appointmentPlural: 'Appointments',
  customer: 'Client',
  customerPlural: 'Clients',
  business: 'Salon',
};

export function CategoryProvider({ children }: { children: ReactNode }) {
  const [category, setCategory] = useState<BusinessCategory | null>(null);
  const [categorySlug, setCategorySlug] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  const terminology = {
    specialist: category?.default_specialist_label || DEFAULT_TERMINOLOGY.specialist,
    specialistPlural: category?.default_specialist_plural || DEFAULT_TERMINOLOGY.specialistPlural,
    appointment: category?.default_appointment_label || DEFAULT_TERMINOLOGY.appointment,
    appointmentPlural: DEFAULT_TERMINOLOGY.appointmentPlural, // Derived from singular
    customer: category?.default_customer_term || DEFAULT_TERMINOLOGY.customer,
    customerPlural: DEFAULT_TERMINOLOGY.customerPlural, // Derived from singular
    business: category?.short_name || category?.name || DEFAULT_TERMINOLOGY.business,
  };

  useEffect(() => {
    const fetchCategory = async () => {
      if (!categorySlug) {
        setCategory(null);
        return;
      }

      setIsLoading(true);
      try {
        const data = await apiClient.get(`/v1/business-categories/${categorySlug}`);
        if (data.data) {
          setCategory(data.data);
        }
      } catch (error) {
        console.error('Failed to fetch business category:', error);
      } finally {
        setIsLoading(false);
      }
    };

    fetchCategory();
  }, [categorySlug]);

  return (
    <CategoryContext.Provider
      value={{
        category,
        categorySlug,
        setCategorySlug,
        terminology,
        isLoading,
      }}
    >
      {children}
    </CategoryContext.Provider>
  );
}

export function useCategory() {
  const context = useContext(CategoryContext);
  if (context === undefined) {
    throw new Error('useCategory must be used within a CategoryProvider');
  }
  return context;
}
