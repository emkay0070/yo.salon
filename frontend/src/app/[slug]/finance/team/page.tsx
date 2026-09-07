'use client';

import { useEffect, useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';
import DashboardLayout from '@/components/DashboardLayout';
import { salonRoutes } from '@/lib/routes';

interface PageProps {
  params: Promise<{ slug: string }>;
}

export default function TeamPaymentsPage(props: PageProps) {
  const params = use(props.params);
  const router = useRouter();
  
  const [salon, setSalon] = useState<any>(null);
  const [teamList, setTeamList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchData() {
      try {
        const salonData = await apiClient.getSalonBySlug(params.slug);
        setSalon(salonData);

        const teamRes = await apiClient.getSalonTeam(salonData.id);
        setTeamList(teamRes.team || []);
      } catch (err) {
        console.error('Failed to load team payments data', err);
      } finally {
        setLoading(false);
      }
    }
    fetchData();
  }, [params.slug]);

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex h-screen items-center justify-center">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-gray-900 border-t-transparent" />
        </div>
      </DashboardLayout>
    );
  }

  // Calculate top-level metrics from the unified payload
  const totalOutstanding = teamList.reduce((sum, member) => sum + (member.payable?.amount || 0), 0);

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX' }).format(amount);
  };

  return (
    <DashboardLayout>
      <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">Team Payments</h1>

        {/* Dashboard Metrics */}
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-3 mb-8">
          <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6">
            <dt className="truncate text-sm font-medium text-gray-500">Outstanding Payables</dt>
            <dd className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
              {formatCurrency(totalOutstanding)}
            </dd>
          </div>
          <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 opacity-75">
            <dt className="truncate text-sm font-medium text-gray-500">Open Periods Gross</dt>
            <dd className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
              -
            </dd>
            <p className="text-xs text-gray-400 mt-2">Computing...</p>
          </div>
          <div className="overflow-hidden rounded-lg bg-white px-4 py-5 shadow sm:p-6 opacity-75">
            <dt className="truncate text-sm font-medium text-gray-500">Paid This Month</dt>
            <dd className="mt-1 text-3xl font-semibold tracking-tight text-gray-900">
              -
            </dd>
            <p className="text-xs text-gray-400 mt-2">Computing...</p>
          </div>
        </div>

        {/* Team List */}
        <div className="overflow-hidden bg-white shadow sm:rounded-md">
          <ul role="list" className="divide-y divide-gray-200">
            {teamList.map((member) => {
              const policyDescription = member.compensation?.summary || 'No active policy';
              const payableAmount = member.payable?.amount || 0;
              const settlementCount = member.payable?.settlement_count || 0;

              return (
                <li key={`${member.type}-${member.id}`}>
                  <div className="flex items-center justify-between px-4 py-4 sm:px-6">
                    <div className="flex items-center gap-4">
                      {member.photo_url ? (
                        <img className="h-10 w-10 rounded-full object-cover" src={member.photo_url} alt="" />
                      ) : (
                        <div className="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-medium">
                          {member.name?.charAt(0) || '?'}
                        </div>
                      )}
                      <div>
                        <div className="flex items-center gap-2">
                          <p className="truncate text-sm font-medium text-indigo-600">{member.name || 'Unknown'}</p>
                          <span className="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                            {member.role || 'team'}
                          </span>
                          {member.type === 'independent_specialist' && (
                            <span className="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                              Independent
                            </span>
                          )}
                        </div>
                        <p className="mt-1 text-sm text-gray-500">{policyDescription}</p>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-6">
                      <div className="text-right">
                        <p className="text-sm font-medium text-gray-900">Payable: {formatCurrency(payableAmount)}</p>
                        {settlementCount > 0 && (
                          <p className="text-xs text-orange-600">{settlementCount} outstanding settlement(s)</p>
                        )}
                      </div>
                      
                      <button
                        onClick={() => router.push(`/${params.slug}/finance/team/${member.id}?type=${member.type}`)}
                        className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                      >
                        View / Pay
                      </button>
                    </div>
                  </div>
                </li>
              );
            })}
            
            {teamList.length === 0 && (
              <li className="px-4 py-8 text-center text-gray-500">
                No team members found.
              </li>
            )}
          </ul>
        </div>
      </div>
    </DashboardLayout>
  );
}
