# Yo.Salon Product Language Dictionary Proposal

## Overview

This document proposes a centralized product language dictionary for Yo.Salon to ensure consistent, customer-facing terminology across the application. The dictionary separates **customer concepts** from **implementation concepts**, ensuring the UI speaks in language users understand rather than internal system language.

## Why This Matters

### Current Problem
- Inconsistent terminology across screens
- Implementation language leaking to customers (e.g., "Minimum booking notice" vs "Book ahead")
- No single source of truth for product language
- Difficult to maintain consistency as the product grows

### The Goal
Every screen in Yo.Salon should speak the same language. The dictionary serves as both:
1. A **canonical reference** for customer-facing terms
2. A **guardrail** for AI development - implementation concepts must be translated through the dictionary

## Structure

The dictionary has **3 layers**:

### Layer 1: Terms
Canonical customer-facing terms used throughout the application.

### Layer 2: Short Explanations
Reusable, parameterizable explanations for individual concepts.

### Layer 3: Contextual Messages
Full sentences that combine multiple concepts for specific scenarios.

## Proposed Dictionary Structure

```typescript
// frontend/src/lib/product-language.ts

export const productLanguage = {
  // ============================================
  // LAYER 1: TERMS
  // ============================================
  terms: {
    // Booking concepts
    appointment: 'Appointment',
    availableTimes: 'Available times',
    bookAhead: 'Book ahead',
    serviceDuration: 'Service duration',
    bookingInformation: 'Booking information',
    
    // Status concepts
    inSession: 'In session',
    nextAvailable: 'Next available',
    currentlyUnavailable: 'Currently unavailable',
    
    // People concepts
    specialist: 'Specialist',
    customer: 'Customer',
    provider: 'Provider',
    
    // Navigation concepts
    backToServices: 'Back to services',
    backToStylist: 'Back to stylist',
    backToTime: 'Back to time',
  },

  // ============================================
  // LAYER 2: SHORT EXPLANATIONS
  // ============================================
  explanations: {
    bookAhead: (hours: number) => 
      `Please book at least ${hours} hours before your appointment.`,
    
    serviceDuration: (duration: number) => 
      `This service takes approximately ${duration} minutes.`,
    
    inSession: (name: string) => 
      `${name} is currently in a session.`,
    
    nextAvailable: (date: string, time: string) => 
      `Next available: ${date} at ${time}.`,
    
    specialistUnavailable: (name: string) => 
      `${name} isn't available today.`,
  },

  // ============================================
  // LAYER 3: CONTEXTUAL MESSAGES
  // ============================================
  messages: {
    // No availability scenarios
    noTimesToday: (duration: number, notice: number) => 
      `This service takes ${duration} minutes and requires ${notice} hours' advance booking. There isn't enough time remaining today to fit it.`,
    
    noTimesTodayDurationOnly: (duration: number) => 
      `This service takes ${duration} minutes. There isn't enough remaining time today to fit it.`,
    
    noTimesTodayNoticeOnly: (notice: number) => 
      `Appointments require ${notice} hours' notice. The earliest available time has passed for today.`,
    
    noAvailability: 'No appointments are available on this date.',
    
    // Configuration failure scenarios
    bookingNotConfigured: (providerName: string) => 
      `Online booking isn't currently available. Please contact ${providerName} directly or try again later.`,
    
    // Specialist status scenarios
    currentlyInSession: (name: string, nextTime: string) => 
      `${name} is currently in a session. The next available appointment starts at ${nextTime}.`,
    
    // Closed scenarios
    closedToday: 'No more appointments are available today.',
  },
} as const;
```

## Implementation Example: Reserve Slot Screen

### Before (Implementation Language)
```tsx
<p>Minimum booking notice: 2 hours</p>
<p>Service duration: 180 minutes</p>
<p>No times available today</p>
```

### After (Customer Language via Dictionary)
```tsx
<h3>{productLanguage.terms.bookingInformation}</h3>
<ul>
  <li>{productLanguage.explanations.bookAhead(2)}</li>
  <li>{productLanguage.explanations.serviceDuration(180)}</li>
  <li>Available times depend on the specialist's working hours and existing appointments.</li>
</ul>

{/* When no slots */}
<p>{productLanguage.messages.noTimesToday(180, 2)}</p>
```

## Why Code-Owned, Not Database-Driven

### Current Approach: TypeScript File
- **Pros:**
  - Version-controlled alongside code
  - Immediate feedback during development
  - Type-safe
  - No additional infrastructure
  - Easy to refactor
  - Fast iteration

- **Cons:**
  - Requires deployment for changes
  - Not multi-lingual by default

### Future Database Approach (When Needed)
A `product_terms` table would be useful when we need:
- Multiple languages (i18n)
- Provider-customizable terminology
- CMS-managed copy
- AI-generated/localized experiences
- A/B testing different language

**But we're not there yet.** A database table would introduce unnecessary complexity at this stage.

## Migration Strategy

### Phase 1: Create Dictionary
- Create `frontend/src/lib/product-language.ts`
- Define initial terms based on current Reserve Slot screen
- Add explanations and messages for booking flow

### Phase 2: Migrate Reserve Slot
- Replace hardcoded strings with dictionary references
- Test the Reserve Slot screen with new language

### Phase 3: Expand to Other Screens
- Audit other booking flow screens for implementation language
- Migrate systematically screen by screen
- Add new terms/explanations as needed

### Phase 4: Establish Development Rule
When building new screens or AI generates copy, ask:
**"Is this a customer concept or an implementation concept?"**

If it's implementation language, it must go through the dictionary.

## Development Rule for AI

When Devin (or any AI assistant) generates UI copy, it should:

1. **Identify implementation concepts** (e.g., "min_booking_notice", "slot_locked_until")
2. **Translate to customer concepts** (e.g., "Book ahead", "Slot reserved")
3. **Check the dictionary** for existing terms
4. **Add new terms** to the dictionary if they don't exist
5. **Use dictionary references** in the code, not hardcoded strings

This ensures the dictionary becomes a living guardrail that improves with each feature.

## Future Evolution

### Multi-Language Support
When Yo.Salon becomes multilingual, the dictionary can evolve:

```typescript
export const productLanguage = {
  en: { /* current structure */ },
  sw: { /* Swahili translations */ },
  lg: { /* Luganda translations */ },
};
```

### Provider Customization
Providers could override specific terms:

```typescript
const providerLanguage = {
  ...productLanguage,
  terms: {
    ...productLanguage.terms,
    specialist: 'Stylist', // Some providers prefer "Stylist"
  },
};
```

## Next Steps

1. ✅ Create this proposal document
2. ⏳ Create `frontend/src/lib/product-language.ts` with initial structure
3. ⏳ Migrate Reserve Slot screen to use the dictionary
4. ⏳ Test the Reserve Slot screen with new language
5. ⏳ Audit other screens for implementation language
6. ⏳ Establish the development rule as part of AI prompts

## Conclusion

The product language dictionary is a simple but powerful tool for ensuring Yo.Salon speaks consistently to customers. By starting with a code-owned TypeScript file, we get immediate benefits without infrastructure complexity. The dictionary can evolve into a more sophisticated i18n system when the product requires it, but the foundational concepts will remain the same.

**Technology hidden behind design.** The system does complex schedule reasoning in the background, while the customer simply understands: *"Oh. I can't book today because this treatment takes three hours and there's not enough time left. Tomorrow works."*
