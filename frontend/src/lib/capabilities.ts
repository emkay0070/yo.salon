/**
 * Capability Helper Functions
 * 
 * These are utility functions for checking plan capabilities.
 * They can be used outside of React components or in contexts where
 * the usePlanFeatures hook is not available.
 */

/**
 * Feature codes for plan capabilities
 */
export const FEATURE_CODES = {
  CALENDAR_WEEK: 'CALENDAR_WEEK',
  CALENDAR_MONTH: 'CALENDAR_MONTH',
  CUSTOMER_PORTAL: 'CUSTOMER_PORTAL',
  ANALYTICS_ADVANCED: 'ANALYTICS_ADVANCED',
  INTELLIGENCE_AI: 'INTELLIGENCE_AI',
  BRANDING_CUSTOM: 'BRANDING_CUSTOM',
  SMS_NOTIFICATIONS: 'SMS_NOTIFICATIONS',
} as const;

/**
 * Resource codes for quota limits
 */
export const RESOURCE_CODES = {
  STAFF_SEAT: 'STAFF_SEAT',
  BRANCH: 'BRANCH',
  STORAGE_GB: 'STORAGE_GB',
} as const;

/**
 * Credit codes for consumable credits
 */
export const CREDIT_CODES = {
  SMS: 'SMS',
  AI_REQUEST: 'AI_REQUEST',
  EMAIL: 'EMAIL',
  WHATSAPP: 'WHATSAPP',
} as const;

/**
 * Check if a feature is allowed based on capability data
 * 
 * @param capabilities - The capability summary from the API
 * @param featureCode - The feature code to check
 * @returns true if the feature is allowed, false otherwise
 */
export function canFeature(
  capabilities: any,
  featureCode: string
): boolean {
  if (!capabilities || !capabilities.features) {
    return false;
  }
  return capabilities.features[featureCode] === true;
}

/**
 * Get quota information for a resource
 * 
 * @param capabilities - The capability summary from the API
 * @param resourceCode - The resource code to check
 * @returns Quota information or null if not available
 */
export function getQuota(
  capabilities: any,
  resourceCode: string
): {
  used: number;
  limit: number;
  remaining: number;
  canAdd: boolean;
  isOverLimit: boolean;
} | null {
  if (!capabilities || !capabilities.resources) {
    return null;
  }

  const quota = capabilities.resources[resourceCode];
  if (!quota) {
    return null;
  }

  return {
    used: quota.used,
    limit: quota.effective_limit,
    remaining: quota.available,
    canAdd: quota.available > 0,
    isOverLimit: quota.is_over_limit,
  };
}

/**
 * Get credit balance for a credit type
 * 
 * @param capabilities - The capability summary from the API
 * @param creditCode - The credit code to check
 * @returns Credit balance or null if not available
 */
export function getCredits(
  capabilities: any,
  creditCode: string
): {
  available: number;
} | null {
  if (!capabilities || !capabilities.credits) {
    return null;
  }

  const credit = capabilities.credits[creditCode];
  if (!credit) {
    return null;
  }

  return {
    available: credit.available,
  };
}

/**
 * Check if the salon is over limit for any resource
 * 
 * @param capabilities - The capability summary from the API
 * @returns true if any resource is over limit
 */
export function isOverLimit(capabilities: any): boolean {
  if (!capabilities || !capabilities.resources) {
    return false;
  }

  return Object.values(capabilities.resources).some(
    (resource: any) => resource.is_over_limit === true
  );
}

/**
 * Get all over-limit resources
 * 
 * @param capabilities - The capability summary from the API
 * @returns Array of over-limit resource codes
 */
export function getOverLimitResources(capabilities: any): string[] {
  if (!capabilities || !capabilities.resources) {
    return [];
  }

  return Object.entries(capabilities.resources)
    .filter(([_, resource]: [string, any]) => resource.is_over_limit === true)
    .map(([code]) => code);
}

/**
 * Check if the salon has an active subscription
 * 
 * @param capabilities - The capability summary from the API
 * @returns true if subscription is active
 */
export function hasActiveSubscription(capabilities: any): boolean {
  if (!capabilities || !capabilities.has_subscription) {
    return false;
  }

  return capabilities.subscription?.status === 'active';
}

/**
 * Check if the salon is in trial period
 * 
 * @param capabilities - The capability summary from the API
 * @returns true if in trial period
 */
export function isTrialing(capabilities: any): boolean {
  if (!capabilities || !capabilities.subscription) {
    return false;
  }

  return capabilities.subscription.is_trialing === true;
}
