'use client';

import { useEffect, useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';
import DashboardLayout from '@/components/DashboardLayout';
import { salonRoutes } from '@/lib/routes';
import { ArrowLeft, Edit, Wallet } from 'lucide-react';
import PolicyEditorModal from '@/components/compensation/PolicyEditorModal';
import PayoutReviewModal from '@/components/compensation/PayoutReviewModal';

interface PageProps {
  params: Promise<{ slug: string; staffId: string }>;
}

export default function StaffCompensationPage(props: PageProps) {
  const params = use(props.params);
  const router = useRouter();
  
  const [salon, setSalon] = useState<any>(null);
  const [staff, setStaff] = useState<any>(null);
  const [policies, setPolicies] = useState<any[]>([]);
  const [payables, setPayables] = useState<any[]>([]);
  const [periods, setPeriods] = useState<any[]>([]);
  const [paymentProfile, setPaymentProfile] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  // Modals
  const [isPolicyModalOpen, setIsPolicyModalOpen] = useState(false);
  const [selectedSettlement, setSelectedSettlement] = useState<any>(null);

  useEffect(() => {
    fetchData();
  }, [params.slug, params.staffId]);

  async function fetchData() {
    try {
      const salonData = await apiClient.getSalonBySlug(params.slug);
      setSalon(salonData);

      const [teamRes, policiesRes, payablesRes, periodsRes, profileRes] = await Promise.all([
        apiClient.getSalonTeam(salonData.id),
        apiClient.getCompensationPolicies(salonData.id),
        apiClient.getCompensationPayables(salonData.id),
        apiClient.getCompensationPeriods(salonData.id, { compensatable_id: params.staffId }),
        apiClient.getTeamMemberPaymentProfile(salonData.id, params.staffId).catch(() => null)
      ]);
      
      const memberData = teamRes.team?.find((m: any) => m.id === params.staffId);
      // Normalize to look like what the UI expects, or we just pass the member data directly
      // The UI expects staff.user.name and staff.user.profile_photo_url
      setStaff({
        ...memberData,
        user: {
          name: memberData?.name,
          profile_photo_url: memberData?.photo_url
        },
        role: memberData?.role
      });
      
      // Filter policies for this staff member
      setPolicies(policiesRes?.filter((p: any) => p.compensatable_id === params.staffId) || []);
      
      // Filter payables for this staff member
      setPayables(payablesRes?.filter((p: any) => p.recipient_id === params.staffId) || []);

      setPeriods(periodsRes || []);
      setPaymentProfile(profileRes);

    } catch (err) {
      console.error('Failed to load staff compensation data', err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex h-screen items-center justify-center">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-gray-900 border-t-transparent" />
        </div>
      </DashboardLayout>
    );
  }

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX' }).format(amount);
  };

  const activePolicy = policies.find(p => p.is_active);

  return (
    <DashboardLayout>
      <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        
        {/* Header */}
        <div className="mb-8">
          <button 
            onClick={() => router.push(`/${params.slug}/finance/team`)}
            className="flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500 mb-4"
          >
            <ArrowLeft className="mr-1 h-4 w-4" /> Back to Team
          </button>
          
          <div className="md:flex md:items-center md:justify-between">
            <div className="min-w-0 flex-1 flex items-center gap-4">
              {staff?.user?.profile_photo_url ? (
                <img className="h-16 w-16 rounded-full" src={staff.user.profile_photo_url} alt="" />
              ) : (
                <div className="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center text-xl text-gray-500">
                  {staff?.user?.name?.charAt(0) || '?'}
                </div>
              )}
              <div>
                <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                  {staff?.user?.name}
                </h2>
                <p className="mt-1 text-sm text-gray-500">
                  {staff?.role || 'Staff Member'}
                </p>
              </div>
            </div>
            <div className="mt-4 flex md:ml-4 md:mt-0">
              <button
                onClick={() => setIsPolicyModalOpen(true)}
                type="button"
                className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
              >
                <Edit className="-ml-0.5 mr-1.5 h-4 w-4" />
                Edit Terms
              </button>
            </div>
          </div>
        </div>

        {/* Current Terms */}
        <div className="overflow-hidden bg-white shadow sm:rounded-lg mb-8">
          <div className="px-4 py-5 sm:px-6">
            <h3 className="text-base font-semibold leading-6 text-gray-900">Compensation Terms</h3>
            <p className="mt-1 max-w-2xl text-sm text-gray-500">Active policy governing earnings.</p>
          </div>
          <div className="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl className="sm:divide-y sm:divide-gray-200">
              <div className="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt className="text-sm font-medium text-gray-500">Type</dt>
                <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0 capitalize">{activePolicy?.type || 'None'}</dd>
              </div>
              {activePolicy?.type === 'commission' && (
                <div className="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt className="text-sm font-medium text-gray-500">Rate</dt>
                  <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{activePolicy.rules.rate}%</dd>
                </div>
              )}
              {activePolicy?.type === 'salary' && (
                <div className="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                  <dt className="text-sm font-medium text-gray-500">Base Salary</dt>
                  <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">{formatCurrency(activePolicy.rules.amount)}</dd>
                </div>
              )}
              <div className="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt className="text-sm font-medium text-gray-500">Frequency</dt>
                <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0 capitalize">{activePolicy?.settlement_frequency || 'N/A'}</dd>
              </div>
              <div className="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt className="text-sm font-medium text-gray-500">Effective From</dt>
                <dd className="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                  {activePolicy?.effective_from ? new Date(activePolicy.effective_from).toLocaleDateString() : 'N/A'}
                </dd>
              </div>
            </dl>
          </div>
        </div>

        {/* Payment Destination (Manager Read-Only) */}
        <div className="overflow-hidden bg-white shadow sm:rounded-lg mb-8">
          <div className="px-4 py-5 sm:px-6">
            <h3 className="text-base font-semibold leading-6 text-gray-900">Payment Destination</h3>
            <p className="mt-1 max-w-2xl text-sm text-gray-500">
              Where this team member receives automated payouts. (Configured by the member in their portal).
            </p>
          </div>
          <div className="border-t border-gray-200 px-4 py-5 sm:p-6">
            {paymentProfile ? (
              <div className="flex items-center gap-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                  <Wallet className="h-6 w-6" />
                </div>
                <div>
                  <p className="font-medium text-gray-900">
                    {paymentProfile.method === 'mtn' ? 'MTN Mobile Money' : paymentProfile.method === 'airtel' ? 'Airtel Money' : 'Bank Account'}
                    {paymentProfile.label ? ` • ${paymentProfile.label}` : ''}
                  </p>
                  <p className="text-sm text-gray-500">{paymentProfile.destination}</p>
                  {paymentProfile.is_verified ? (
                    <span className="mt-1 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">
                      Verified
                    </span>
                  ) : (
                    <span className="mt-1 inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-800">
                      Pending verification
                    </span>
                  )}
                </div>
              </div>
            ) : (
              <div className="rounded-md bg-gray-50 p-4">
                <p className="text-sm text-gray-600">No electronic payment profile configured. Automated payouts will not be available.</p>
              </div>
            )}
          </div>
        </div>

        {/* Outstanding Payables */}
        <div className="mb-8">
          <h3 className="text-lg font-medium leading-6 text-gray-900 mb-4">Outstanding Payables</h3>
          {payables.length === 0 ? (
            <div className="rounded-md bg-green-50 p-4">
              <div className="flex">
                <div className="ml-3">
                  <p className="text-sm font-medium text-green-800">All caught up! No outstanding payments.</p>
                </div>
              </div>
            </div>
          ) : (
            <div className="overflow-hidden bg-white shadow sm:rounded-md">
              <ul role="list" className="divide-y divide-gray-200">
                {payables.map((settlement) => (
                  <li key={settlement.id}>
                    <div className="flex items-center justify-between px-4 py-4 sm:px-6">
                      <div>
                        <p className="text-sm font-medium text-indigo-600">
                          {formatCurrency(Number(settlement.amount))}
                        </p>
                        <p className="mt-1 text-sm text-gray-500">
                          Period ending {new Date(settlement.created_at).toLocaleDateString()}
                        </p>
                      </div>
                      <div>
                        <button
                          onClick={() => setSelectedSettlement(settlement)}
                          className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                          Pay Now
                        </button>
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>

      </div>

      {/* Modals */}
      {isPolicyModalOpen && (
        <PolicyEditorModal
          salonId={salon.id}
          staff={staff}
          currentPolicy={activePolicy}
          onClose={() => setIsPolicyModalOpen(false)}
          onSaved={() => {
            setIsPolicyModalOpen(false);
            fetchData();
          }}
        />
      )}

      {selectedSettlement && (
        <PayoutReviewModal
          salonId={salon.id}
          staff={staff}
          settlement={selectedSettlement}
          onClose={() => setSelectedSettlement(null)}
          onPaid={() => {
            setSelectedSettlement(null);
            fetchData();
          }}
        />
      )}
    </DashboardLayout>
  );
}
