'use client';

import { useState, useEffect } from 'react';
import { apiClient } from '@/lib/api-client';

interface FeatureStatus {
  feature_key: string;
  enabled: boolean;
  enabled_at: string | null;
  enabled_by: string | null;
  reason: string | null;
  available: boolean;
}

interface Salon {
  id: string;
  name: string;
}

interface SuggestedFeature {
  feature: string;
  reason: string;
  confidence: 'high' | 'medium' | 'low';
}

export default function FeatureFlagsPage() {
  const [salons, setSalons] = useState<Salon[]>([]);
  const [selectedSalonId, setSelectedSalonId] = useState<string>('');
  const [features, setFeatures] = useState<FeatureStatus[]>([]);
  const [availableFeatures, setAvailableFeatures] = useState<string[]>([]);
  const [suggestedFeatures, setSuggestedFeatures] = useState<SuggestedFeature[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadSalons();
  }, []);

  useEffect(() => {
    if (selectedSalonId) {
      loadFeatureStatus();
    }
  }, [selectedSalonId]);

  const loadSalons = async () => {
    try {
      const data = await apiClient.get('/api/v1/salons');
      setSalons(data);
      if (data.length > 0) {
        setSelectedSalonId(data[0].id);
      }
    } catch (error) {
      console.error('Failed to load salons:', error);
    }
  };

  const loadFeatureStatus = async () => {
    if (!selectedSalonId) return;

    setLoading(true);
    try {
      const data = await apiClient.get(`/api/v1/features?salon_id=${selectedSalonId}`);
      setFeatures(data.features);
      setAvailableFeatures(data.available_features);
      setSuggestedFeatures(data.suggested_features);
    } catch (error) {
      console.error('Failed to load feature status:', error);
    } finally {
      setLoading(false);
    }
  };

  const toggleFeature = async (featureKey: string, enable: boolean) => {
    try {
      if (enable) {
        await apiClient.post('/api/v1/features/enable', {
          salon_id: selectedSalonId,
          feature_key: featureKey,
          reason: 'Manually enabled via admin panel',
        });
      } else {
        await apiClient.post('/api/v1/features/disable', {
          salon_id: selectedSalonId,
          feature_key: featureKey,
        });
      }
      await loadFeatureStatus();
    } catch (error) {
      console.error('Failed to toggle feature:', error);
      alert('Failed to toggle feature. Please try again.');
    }
  };

  const seedPolicies = async () => {
    try {
      await apiClient.post('/api/v1/features/seed-policies');
      alert('Default policies seeded successfully');
    } catch (error) {
      console.error('Failed to seed policies:', error);
      alert('Failed to seed policies. Please try again.');
    }
  };

  const getFeatureDisplayName = (key: string) => {
    return key.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
  };

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Feature Flags Management</h1>
        <p className="text-gray-600">Manage feature availability for salons</p>
      </div>

      <div className="bg-white rounded-lg shadow p-6 mb-6">
        <div className="flex items-center gap-4 mb-4">
          <label className="text-sm font-medium text-gray-700">Select Salon:</label>
          <select
            value={selectedSalonId}
            onChange={(e) => setSelectedSalonId(e.target.value)}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            {salons.map((salon) => (
              <option key={salon.id} value={salon.id}>
                {salon.name}
              </option>
            ))}
          </select>
        </div>

        <button
          onClick={seedPolicies}
          className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
        >
          Seed Default Policies
        </button>
      </div>

      {loading ? (
        <div className="text-center py-8">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      ) : (
        <>
          <div className="bg-white rounded-lg shadow p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Available Features</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {features.map((feature) => (
                <div
                  key={feature.feature_key}
                  className={`p-4 rounded-lg border-2 ${
                    feature.enabled
                      ? 'border-green-500 bg-green-50'
                      : feature.available
                      ? 'border-blue-500 bg-blue-50'
                      : 'border-gray-300 bg-gray-50'
                  }`}
                >
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-medium text-gray-900">
                      {getFeatureDisplayName(feature.feature_key)}
                    </h3>
                    <button
                      onClick={() => toggleFeature(feature.feature_key, !feature.enabled)}
                      className={`px-3 py-1 rounded text-sm font-medium ${
                        feature.enabled
                          ? 'bg-green-600 text-white hover:bg-green-700'
                          : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                    >
                      {feature.enabled ? 'Enabled' : 'Enable'}
                    </button>
                  </div>
                  <div className="text-sm text-gray-600 space-y-1">
                    <p>Status: {feature.enabled ? 'Active' : feature.available ? 'Available' : 'Not Available'}</p>
                    {feature.enabled && feature.enabled_at && (
                      <p>Enabled: {new Date(feature.enabled_at).toLocaleDateString()}</p>
                    )}
                    {feature.enabled_by && <p>By: {feature.enabled_by}</p>}
                    {feature.reason && <p>Reason: {feature.reason}</p>}
                  </div>
                </div>
              ))}
            </div>
          </div>

          {suggestedFeatures.length > 0 && (
            <div className="bg-white rounded-lg shadow p-6 mb-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Suggested Features</h2>
              <div className="space-y-3">
                {suggestedFeatures.map((suggestion, index) => (
                  <div
                    key={index}
                    className="p-4 bg-yellow-50 border border-yellow-200 rounded-lg"
                  >
                    <div className="flex items-start justify-between">
                      <div>
                        <h3 className="font-medium text-gray-900 mb-1">
                          {getFeatureDisplayName(suggestion.feature)}
                        </h3>
                        <p className="text-sm text-gray-600">{suggestion.reason}</p>
                      </div>
                      <span
                        className={`px-2 py-1 rounded text-xs font-medium ${
                          suggestion.confidence === 'high'
                            ? 'bg-green-100 text-green-800'
                            : suggestion.confidence === 'medium'
                            ? 'bg-yellow-100 text-yellow-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}
                      >
                        {suggestion.confidence}
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Feature Summary</h2>
            <div className="grid grid-cols-3 gap-4 text-center">
              <div className="p-4 bg-green-50 rounded-lg">
                <div className="text-2xl font-bold text-green-600">
                  {features.filter((f) => f.enabled).length}
                </div>
                <div className="text-sm text-gray-600">Enabled</div>
              </div>
              <div className="p-4 bg-blue-50 rounded-lg">
                <div className="text-2xl font-bold text-blue-600">
                  {features.filter((f) => f.available && !f.enabled).length}
                </div>
                <div className="text-sm text-gray-600">Available</div>
              </div>
              <div className="p-4 bg-gray-50 rounded-lg">
                <div className="text-2xl font-bold text-gray-600">
                  {features.filter((f) => !f.available).length}
                </div>
                <div className="text-sm text-gray-600">Not Available</div>
              </div>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
