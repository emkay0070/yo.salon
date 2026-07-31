'use client';

import { motion } from 'framer-motion';
import { CreditCard, ShoppingBag, Check, Star } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import ClientLayout from '@/components/ClientLayout';

export default function PackagesPage() {
  const { customer, salon } = usePortalAuth();
  const queryClient = useQueryClient();

  const { data: packages, isLoading: packagesLoading, isError: packagesError } = useQuery({
    queryKey: ['packages'],
    queryFn: () => portalApiClient.get('/portal/packages'),
    enabled: !!customer,
  });

  const { data: myPackages, isLoading: myPackagesLoading, isError: myPackagesError } = useQuery({
    queryKey: ['my-packages'],
    queryFn: () => portalApiClient.get('/portal/packages/my'),
    enabled: !!customer,
  });

  const purchaseMutation = useMutation({
    mutationFn: (packageId: string) => 
      portalApiClient.post('/portal/packages/purchase', { package_id: packageId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-packages'] });
      queryClient.invalidateQueries({ queryKey: ['portal-wallet'] });
    },
  });

  if (packagesLoading || myPackagesLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  if (packagesError || myPackagesError) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary text-center">
          <p className="text-red-500 mb-2">Failed to load packages.</p>
          <button onClick={() => window.location.reload()} className="text-gold underline hover:text-dark-gold">Try again</button>
        </div>
      </div>
    );
  }

  return (
    <ClientLayout>
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="text-3xl font-bold text-text-primary mb-2">Service Packages</h1>
          <p className="text-text-secondary">Save money with bundled services</p>
        </motion.div>

        {/* My Packages */}
        {myPackages && myPackages.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="bg-surface border border-border-light rounded-2xl p-6"
          >
            <h2 className="text-lg font-semibold text-text-primary mb-4">My Packages</h2>
            <div className="space-y-3">
              {myPackages.map((pkg: any) => (
                <MyPackageCard key={pkg.id} package={pkg} />
              ))}
            </div>
          </motion.div>
        )}

        {/* Available Packages */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Available Packages</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {packages?.map((pkg: any) => (
              <PackageCard 
                key={pkg.id} 
                package={pkg} 
                onPurchase={() => purchaseMutation.mutate(pkg.id)}
                isPurchasing={purchaseMutation.isPending}
              />
            ))}
          </div>
        </motion.div>
      </div>
    </ClientLayout>
  );
}

function PackageCard({ package: pkg, onPurchase, isPurchasing }: any) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6 hover:border-gold/30 transition-all">
      <div className="flex items-center gap-2 mb-4">
        <ShoppingBag className="w-5 h-5 text-gold" />
        <h3 className="font-semibold text-text-primary">{pkg.name}</h3>
      </div>
      
      <p className="text-sm text-text-secondary mb-4">{pkg.description}</p>
      
      <div className="space-y-2 mb-4">
        <div className="flex items-center gap-2 text-sm text-text-primary">
          <Check className="w-4 h-4 text-emerald-500" />
          <span>{pkg.services_included} services included</span>
        </div>
        {pkg.validity_days && (
          <div className="flex items-center gap-2 text-sm text-text-primary">
            <Check className="w-4 h-4 text-emerald-500" />
            <span>Valid for {pkg.validity_days} days</span>
          </div>
        )}
      </div>

      <div className="flex items-center justify-between pt-4 border-t border-border-light">
        <div>
          <p className="text-2xl font-bold text-gold">${pkg.price}</p>
          <p className="text-xs text-text-secondary">One-time payment</p>
        </div>
        <button
          onClick={onPurchase}
          disabled={isPurchasing}
          className="px-4 py-2 bg-gold text-obsidian rounded-full font-medium hover:bg-dark-gold transition-colors disabled:opacity-50"
        >
          {isPurchasing ? 'Purchasing...' : 'Purchase'}
        </button>
      </div>
    </div>
  );
}

function MyPackageCard({ package: pkg }: any) {
  const progress = (pkg.services_remaining / pkg.services_included) * 100;

  return (
    <div className="bg-gradient-to-r from-purple-500/10 to-purple-600/10 border border-purple-500/20 rounded-xl p-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center">
            <CreditCard className="w-5 h-5 text-purple-500" />
          </div>
          <div>
            <h4 className="font-semibold text-text-primary">{pkg.package_name}</h4>
            <p className="text-xs text-text-secondary">
              Purchased {new Date(pkg.purchased_at).toLocaleDateString()}
            </p>
          </div>
        </div>
        <div className="text-right">
          <p className="text-lg font-bold text-purple-500">{pkg.services_remaining}</p>
          <p className="text-xs text-text-secondary">remaining</p>
        </div>
      </div>

      <div className="w-full bg-gray-200 rounded-full h-2">
        <div
          className="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all"
          style={{ width: `${progress}%` }}
        />
      </div>

      {pkg.expires_at && (
        <p className="text-xs text-text-secondary mt-2">
          Expires {new Date(pkg.expires_at).toLocaleDateString()}
        </p>
      )}
    </div>
  );
}
