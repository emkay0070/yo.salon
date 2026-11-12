'use client';

import { motion } from 'framer-motion';
import { Heart, Plus, Folder, Clock, Star, Scissors, User, Store, Trash2 } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function FavoritesPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<'all' | 'providers' | 'specialists' | 'services' | 'collections'>('all');

  const queryClient = useQueryClient();
  const [showNewCollection, setShowNewCollection] = useState(false);
  const [newCollectionName, setNewCollectionName] = useState('');

  const { data: favoritesResponse, isLoading: favoritesLoading } = useQuery({
    queryKey: ['portal-favorites', activeTab],
    queryFn: () => portalApiClient.get(`/portal/favorites?type=${activeTab === 'all' ? '' : activeTab}`),
    enabled: !!customer && activeTab !== 'collections',
  });

  const { data: collectionsResponse, isLoading: collectionsLoading } = useQuery({
    queryKey: ['portal-collections'],
    queryFn: () => portalApiClient.get('/portal/collections'),
    enabled: !!customer,
  });

  const createCollectionMutation = useMutation({
    mutationFn: (data: { name: string; description?: string }) => 
      portalApiClient.post('/portal/collections', data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-collections'] });
      setShowNewCollection(false);
      setNewCollectionName('');
    },
  });

  const removeFavoriteMutation = useMutation({
    mutationFn: (id: string) => portalApiClient.delete(`/portal/favorites/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-favorites'] });
    },
  });

  if (favoritesLoading || collectionsLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const favorites = favoritesResponse?.data?.favorites?.data || [];
  const collections = collectionsResponse?.data?.collections || [];

  // Group favorites by type
  const providers = favorites.filter((f: any) => f.favoritable_type === 'App\\Models\\Provider').map((f: any) => ({
    id: f.favoritable_id,
    ...f.favoritable,
    favoriteId: f.id,
  }));
  const specialists = favorites.filter((f: any) => f.favoritable_type === 'App\\Models\\Specialist').map((f: any) => ({
    id: f.favoritable_id,
    ...f.favoritable,
    favoriteId: f.id,
  }));
  const services = favorites.filter((f: any) => f.favoritable_type === 'App\\Models\\Service').map((f: any) => ({
    id: f.favoritable_id,
    ...f.favoritable,
    favoriteId: f.id,
  }));

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Favorites</h1>
        <p className="text-text-secondary">Your personal collection of saved items</p>
      </motion.div>

      {/* Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'all', label: 'All' },
          { id: 'providers', label: 'Providers' },
          { id: 'specialists', label: 'Specialists' },
          { id: 'services', label: 'Services' },
          { id: 'collections', label: 'Collections' },
        ].map((tab) => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id as any)}
            className={`flex-shrink-0 px-6 py-3 rounded-2xl text-sm font-medium transition-all ${
              activeTab === tab.id
                ? 'text-white'
                : 'bg-surface border border-border-light text-text-secondary hover:border-gold/30'
            }`}
            style={{
              ...(activeTab === tab.id
                ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                : { borderRadius: 'var(--brand-border-radius, 16px)' })
            }}
          >
            {tab.label}
          </button>
        ))}
      </motion.div>

      {/* Tab Content */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
      >
        {activeTab === 'all' && (
          <div className="space-y-6">
            {providers.length > 0 && <FavoritesSection title="Providers" items={providers} type="provider" onRemove={removeFavoriteMutation.mutate} />}
            {specialists.length > 0 && <FavoritesSection title="Specialists" items={specialists} type="specialist" onRemove={removeFavoriteMutation.mutate} />}
            {services.length > 0 && <FavoritesSection title="Services" items={services} type="service" onRemove={removeFavoriteMutation.mutate} />}
            {providers.length === 0 && specialists.length === 0 && services.length === 0 && (
              <div className="bg-surface border border-border-light rounded-2xl p-12 text-center" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
                <Heart className="w-16 h-16 text-text-secondary mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-text-primary mb-2">No favorites yet</h3>
                <p className="text-text-secondary">Start saving your favorite items</p>
              </div>
            )}
          </div>
        )}
        {activeTab === 'providers' && <FavoritesSection title="Saved Providers" items={providers} type="provider" onRemove={removeFavoriteMutation.mutate} />}
        {activeTab === 'specialists' && <FavoritesSection title="Saved Specialists" items={specialists} type="specialist" onRemove={removeFavoriteMutation.mutate} />}
        {activeTab === 'services' && <FavoritesSection title="Saved Services" items={services} type="service" onRemove={removeFavoriteMutation.mutate} />}
        {activeTab === 'collections' && <CollectionsContent collections={collections} onCreate={createCollectionMutation.mutate} showNewCollection={showNewCollection} setShowNewCollection={setShowNewCollection} newCollectionName={newCollectionName} setNewCollectionName={setNewCollectionName} />}
      </motion.div>
    </div>
  );
}

function FavoritesSection({ title, items, type, onRemove }: { title: string, items: any[], type: 'provider' | 'specialist' | 'service', onRemove: (id: string) => void }) {
  const router = useRouter();

  if (items.length === 0) {
    return null;
  }

  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <h2 className="text-lg font-semibold text-text-primary mb-4">{title}</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {items.map((item) => (
          <FavoriteItem key={item.id} item={item} type={type} onClick={() => {
            if (type === 'provider') router.push(`/providers/${item.id}`);
            if (type === 'specialist') router.push(`/specialists/${item.id}`);
            if (type === 'service') router.push(`/services/${item.id}`);
          }} onRemove={() => onRemove(item.favoriteId)} />
        ))}
      </div>
    </div>
  );
}

function FavoriteItem({ item, type, onClick, onRemove }: { item: any, type: 'provider' | 'specialist' | 'service', onClick: () => void, onRemove: () => void }) {
  return (
    <div
      onClick={onClick}
      className="bg-surface border border-border-light rounded-2xl p-4 hover:border-gold/30 transition-all cursor-pointer"
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
    >
      <div className="relative mb-3">
        <div className="w-full h-32 bg-surface border border-border-light rounded-xl flex items-center justify-center" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
          {type === 'provider' && <Store className="w-12 h-12 text-text-secondary" />}
          {type === 'specialist' && <User className="w-12 h-12 text-text-secondary" />}
          {type === 'service' && <Scissors className="w-12 h-12 text-text-secondary" />}
        </div>
        <button 
          onClick={(e) => { e.stopPropagation(); onRemove(); }}
          className="absolute top-2 right-2 w-8 h-8 rounded-full bg-surface border border-border-light flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors" 
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <Trash2 className="w-4 h-4" style={{ color: '#EF4444' }} />
        </button>
      </div>
      <h3 className="font-semibold text-text-primary mb-1">{item.name || item.title}</h3>
      {type === 'provider' && (
        <>
          <p className="text-sm text-text-secondary mb-2">{item.type || 'Provider'}</p>
          <div className="flex items-center gap-1">
            <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
            <span className="text-sm text-text-secondary">{item.rating || 0} ({item.reviews_count || 0})</span>
          </div>
        </>
      )}
      {type === 'specialist' && (
        <>
          <p className="text-sm text-text-secondary mb-1">{item.role || 'Specialist'}</p>
          <p className="text-xs text-text-secondary mb-2">{item.provider_name || 'Provider'}</p>
          <div className="flex items-center gap-1">
            <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
            <span className="text-sm text-text-secondary">{item.rating || 0} ({item.reviews_count || 0})</span>
          </div>
        </>
      )}
      {type === 'service' && (
        <>
          <p className="text-sm text-text-secondary mb-2">{item.category} • {item.duration} min</p>
          <div className="flex items-center justify-between">
            <span className="font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>UGX {(item.price || 0).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
            <div className="flex items-center gap-1">
              <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
              <span className="text-sm text-text-secondary">{item.rating || 0}</span>
            </div>
          </div>
        </>
      )}
    </div>
  );
}

function CollectionsContent({ collections, onCreate, showNewCollection, setShowNewCollection, newCollectionName, setNewCollectionName }: { 
  collections: any[], 
  onCreate: (data: { name: string; description?: string }) => void,
  showNewCollection: boolean,
  setShowNewCollection: (show: boolean) => void,
  newCollectionName: string,
  setNewCollectionName: (name: string) => void,
}) {
  return (
    <div className="space-y-4">
      {/* Create New Collection */}
      <div className="bg-surface border border-border-light rounded-2xl p-6" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <button
          onClick={() => setShowNewCollection(!showNewCollection)}
          className="w-full flex items-center justify-center gap-2 p-4 border-2 border-dashed rounded-xl hover:border-gold/30 transition-colors"
          style={{ borderColor: 'var(--brand-primary, #FFD700)30', borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <Plus className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          <span className="font-medium" style={{ color: 'var(--brand-primary, #FFD700)' }}>Create New Collection</span>
        </button>
        {showNewCollection && (
          <div className="mt-4 flex gap-2">
            <input
              type="text"
              placeholder="Collection name"
              value={newCollectionName}
              onChange={(e) => setNewCollectionName(e.target.value)}
              className="flex-1 px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary placeholder-text-secondary focus:outline-none focus:border-gold/50"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            />
            <button
              onClick={() => {
                if (newCollectionName) {
                  onCreate({ name: newCollectionName });
                }
              }}
              className="px-6 py-3 text-white font-medium rounded-xl"
              style={{ backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              Create
            </button>
          </div>
        )}
      </div>

      {/* Collections Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {collections.map((collection) => (
          <CollectionCard key={collection.id} collection={collection} />
        ))}
      </div>
    </div>
  );
}

function CollectionCard({ collection }: { collection: any }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-6 hover:border-gold/30 transition-all cursor-pointer" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <div className="flex items-center gap-4 mb-4">
        <div className="w-12 h-12 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20' }}>
          <Folder className="w-6 h-6" style={{ color: 'var(--brand-primary, #FFD700)' }} />
        </div>
        <div>
          <h3 className="font-semibold text-text-primary">{collection.name}</h3>
          <p className="text-sm text-text-secondary">{collection.favorites_count || 0} items</p>
        </div>
      </div>
      <button className="w-full px-4 py-2 text-sm font-medium rounded-lg" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}>
        View Collection
      </button>
    </div>
  );
}
