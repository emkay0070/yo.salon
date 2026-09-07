'use client';

export function LoadingSkeleton({ className }: { className?: string }) {
  return (
    <div className={`animate-pulse bg-gray-200 rounded ${className}`} />
  );
}

export function CardSkeleton() {
  return (
    <div className="bg-surface border border-border-light p-6 rounded-xl">
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-4">
          <LoadingSkeleton className="w-12 h-12 rounded-xl" />
          <div className="space-y-2">
            <LoadingSkeleton className="h-4 w-32" />
            <LoadingSkeleton className="h-3 w-24" />
          </div>
        </div>
        <LoadingSkeleton className="h-6 w-16 rounded-full" />
      </div>
      <div className="flex items-center gap-6">
        <LoadingSkeleton className="h-4 w-20" />
        <LoadingSkeleton className="h-4 w-16" />
        <LoadingSkeleton className="h-4 w-12" />
      </div>
    </div>
  );
}

export function ServiceCardSkeleton() {
  return (
    <div className="bg-surface border border-border-light p-4 rounded-xl">
      <LoadingSkeleton className="w-full h-24 rounded-xl mb-3" />
      <LoadingSkeleton className="h-4 w-24 mb-1" />
      <LoadingSkeleton className="h-3 w-16" />
    </div>
  );
}

export function QuickActionSkeleton() {
  return (
    <div className="bg-surface border border-border-light p-4 rounded-xl">
      <LoadingSkeleton className="w-12 h-12 rounded-xl mb-3" />
      <LoadingSkeleton className="h-4 w-20" />
    </div>
  );
}

export function StatCardSkeleton() {
  return (
    <div className="bg-surface border border-border-light p-4 rounded-xl">
      <LoadingSkeleton className="h-4 w-16 mb-2" />
      <LoadingSkeleton className="h-8 w-20" />
    </div>
  );
}
