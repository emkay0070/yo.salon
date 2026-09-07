# Specialist Assessment System Documentation

## Overview

The Specialist Assessment System provides a professional layer for specialists to record observations about customers without directly editing customer profiles. This system maintains data ownership separation while enabling expert guidance through recommendations that customers can accept or reject.

## Design Philosophy

**Three Sources of Truth:**

1. **Customer Profile (Customer-owned)** - Only customers can edit
   - Allergies, preferred hairstyle, preferred fragrance, hair type, contact details

2. **Professional Assessment (Specialist-owned)** - Only specialists can create/update
   - Hair density, scalp condition, beard growth pattern, hairline assessment, skin observations

3. **AI Intelligence (System-owned)** - Automatically generated
   - Recommended specialist, best products, rebooking prediction, loyalty score

**Key Principle:** Specialists never directly edit customer profiles. Instead, they record observations which generate recommendations that customers can accept or reject.

## Database Schema

### professional_assessments

Stores specialist observations about customers.

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| customer_id | uuid | Foreign key to customers |
| specialist_id | uuid | Foreign key to staff |
| salon_id | uuid | Foreign key to salons |
| booking_id | uuid | Nullable foreign key to bookings |
| observed_hair_type | string | Specialist's observation of hair type |
| hair_density | string | Low, Medium, High |
| scalp_condition | string | Healthy, Dry, Oily, Inflamed, Sensitive |
| hairline | string | Normal, Receding, Widow's Peak |
| hair_observations | text | Free-form hair observations |
| observed_beard_style | string | Specialist's observation of beard style |
| beard_growth_pattern | string | Patchy, Full, Sparse |
| beard_observations | text | Free-form beard observations |
| observed_skin_type | string | Specialist's observation of skin type |
| skin_observations | text | Free-form skin observations |
| skin_conditions | jsonb | Array: Acne, Pigmentation, Sensitive, Scarring |
| confidence_level | string | Low, Medium, High |
| notes | text | Additional notes |
| metadata | jsonb | Additional data |
| customer_reviewed | boolean | Whether customer has reviewed |
| customer_accepted | boolean | Whether customer accepted |
| customer_reviewed_at | timestamp | When customer reviewed |
| customer_feedback | text | Customer's feedback |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Indexes:**
- [customer_id, created_at]
- [specialist_id, created_at]
- [salon_id, created_at]
- [booking_id]
- [customer_reviewed, customer_accepted]

### specialist_notes

Free-form notes from specialists about customers.

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| customer_id | uuid | Foreign key to customers |
| specialist_id | uuid | Foreign key to staff |
| salon_id | uuid | Foreign key to salons |
| booking_id | uuid | Nullable foreign key to bookings |
| note | text | Note content |
| note_type | string | general, preference, observation, recommendation |
| tags | jsonb | Array of tags |
| is_private | boolean | Only visible to this specialist |
| is_important | boolean | Highlight important notes |
| customer_visible | boolean | Whether customer can see this note |
| customer_viewed_at | timestamp | When customer viewed |
| metadata | jsonb | Additional data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Indexes:**
- [customer_id, created_at]
- [specialist_id, created_at]
- [salon_id, created_at]
- [booking_id]
- [note_type]
- [is_important]
- [customer_visible]

### profile_recommendations

Generated recommendations from assessments.

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| customer_id | uuid | Foreign key to customers |
| assessment_id | uuid | Foreign key to professional_assessments |
| specialist_id | uuid | Foreign key to staff |
| field | string | Which field to update (hair_type, skin_type, etc.) |
| current_value | string | Current customer value |
| recommended_value | string | Recommended value |
| reason | string | Why this recommendation |
| confidence_level | string | Low, Medium, High |
| status | string | pending, accepted, rejected, expired |
| expires_at | timestamp | When recommendation expires |
| responded_at | timestamp | When customer responded |
| customer_feedback | text | Customer's feedback |
| metadata | jsonb | Additional data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**Indexes:**
- [customer_id, status]
- [status, expires_at]
- [assessment_id]

## API Endpoints

### Specialist Endpoints (Salon Context Required)

**GET /customers/{customerId}/profile**
Get customer's grooming profile for specialist view.

**Response:**
```json
{
  "customer": {...},
  "grooming_profile": {...},
  "assessments": [...],
  "notes": [...]
}
```

**POST /assessments**
Create a professional assessment.

**Request Body:**
```json
{
  "customer_id": "uuid",
  "specialist_id": "uuid",
  "salon_id": "uuid",
  "booking_id": "uuid (optional)",
  "observed_hair_type": "string (optional)",
  "hair_density": "Low|Medium|High (optional)",
  "scalp_condition": "string (optional)",
  "hairline": "string (optional)",
  "hair_observations": "string (optional)",
  "observed_beard_style": "string (optional)",
  "beard_growth_pattern": "Patchy|Full|Sparse (optional)",
  "beard_observations": "string (optional)",
  "observed_skin_type": "string (optional)",
  "skin_observations": "string (optional)",
  "skin_conditions": ["array", "of", "conditions"],
  "confidence_level": "Low|Medium|High (optional)",
  "notes": "string (optional)",
  "metadata": {}
}
```

**Response:**
```json
{
  "message": "Assessment created successfully",
  "assessment": {...},
  "recommendations": [...] // Auto-generated recommendations
}
```

**POST /notes**
Create a specialist note.

**Request Body:**
```json
{
  "customer_id": "uuid",
  "specialist_id": "uuid",
  "salon_id": "uuid",
  "booking_id": "uuid (optional)",
  "note": "string (required)",
  "note_type": "general|preference|observation|recommendation (optional)",
  "tags": ["array", "of", "tags"],
  "is_private": "boolean (optional)",
  "is_important": "boolean (optional)",
  "customer_visible": "boolean (optional)",
  "metadata": {}
}
```

### Customer Portal Endpoints (Portal Auth Required)

**GET /portal/assessments/pending**
Get pending assessments for the authenticated customer.

**Response:**
```json
{
  "assessments": [...]
}
```

**POST /portal/assessments/{assessmentId}/review**
Customer reviews and responds to an assessment.

**Request Body:**
```json
{
  "accepted": "boolean (required)",
  "feedback": "string (optional)"
}
```

**POST /portal/notes/{noteId}/view**
Mark a note as viewed by customer.

**GET /portal/timeline**
Get customer timeline (assessments and notes combined).

**Response:**
```json
{
  "customer": {...},
  "timeline": [
    {
      "id": "uuid",
      "type": "assessment|note",
      "created_at": "timestamp",
      "specialist": {...},
      "data": {...},
      "customer_reviewed": "boolean (assessment only)",
      "customer_accepted": "boolean (assessment only)",
      "customer_feedback": "string (assessment only)",
      "customer_viewed_at": "timestamp (note only)"
    }
  ],
  "summary": {
    "total_assessments": 0,
    "total_notes": 0,
    "pending_assessments": 0,
    "unread_notes": 0
  }
}
```

**GET /portal/recommendations**
Get pending recommendations for the authenticated customer.

**Response:**
```json
{
  "recommendations": [...],
  "count": 0
}
```

**POST /portal/recommendations/{recommendationId}/respond**
Customer responds to a recommendation.

**Request Body:**
```json
{
  "accepted": "boolean (required)",
  "feedback": "string (optional)"
}
```

## Frontend Components

### RecommendationReview

**Location:** `frontend/src/components/portal/RecommendationReview.tsx`

**Features:**
- Displays pending profile recommendations
- Shows current vs recommended values
- Displays specialist name and salon
- Confidence level indicators
- Accept/Reject buttons
- Expiration date display
- Optional feedback on rejection
- Auto-refreshes after response

**Usage:**
```tsx
<RecommendationReview />
```

## Workflow

### Specialist Creates Assessment

1. Specialist works on customer during booking
2. Specialist observes customer's actual hair/skin type
3. Specialist creates assessment via API
4. System auto-generates recommendations if observations differ from profile
5. Customer receives notification about new recommendations

### Customer Reviews Recommendations

1. Customer logs into portal
2. Sees notification about new recommendations
3. Opens RecommendationReview component
4. Views current vs recommended values
5. Accepts or rejects each recommendation
6. If accepted: Profile updates automatically
7. If rejected: Feedback recorded for specialist

### Example Scenario

**Customer Profile:**
- Hair Type: Straight

**Specialist Observation:**
- Hair Type: Wavy
- Confidence: High

**System Action:**
1. Creates assessment with observed_hair_type = "Wavy"
2. Generates recommendation:
   - Field: hair_type
   - Current: Straight
   - Recommended: Wavy
   - Reason: "Based on professional assessment by Sarah"
   - Confidence: High
   - Expires: 30 days
3. Creates notification for customer

**Customer Action:**
- Reviews recommendation
- Accepts → Profile updates to "Wavy"
- OR Rejects → Feedback recorded, profile unchanged

## Models

### ProfessionalAssessment

**Scopes:**
- `byCustomer($customerId)` - Filter by customer
- `bySpecialist($specialistId)` - Filter by specialist
- `bySalon($salonId)` - Filter by salon
- `pendingReview()` - Not yet reviewed by customer
- `accepted()` - Customer accepted
- `rejected()` - Customer rejected
- `recent()` - Order by created_at desc

**Methods:**
- `markAsReviewed($accepted, $feedback)` - Mark as reviewed by customer

### SpecialistNote

**Scopes:**
- `byCustomer($customerId)` - Filter by customer
- `bySpecialist($specialistId)` - Filter by specialist
- `bySalon($salonId)` - Filter by salon
- `byType($type)` - Filter by note type
- `customerVisible()` - Only customer-visible notes
- `important()` - Only important notes
- `private()` - Only private notes
- `recent()` - Order by created_at desc

**Methods:**
- `markAsViewedByCustomer()` - Mark as viewed by customer

### ProfileRecommendation

**Scopes:**
- `byCustomer($customerId)` - Filter by customer
- `pending()` - Status = pending
- `accepted()` - Status = accepted
- `rejected()` - Status = rejected
- `active()` - Pending and not expired
- `recent()` - Order by created_at desc

**Methods:**
- `markAsAccepted($feedback)` - Mark as accepted
- `markAsRejected($feedback)` - Mark as rejected
- `markAsExpired()` - Mark as expired

## What's Done

### Phase 1 - Data Foundation ✅
- Professional assessments table and model
- Specialist notes table and model
- Backend endpoints for creating assessments and notes
- Reference data validation for observed values

### Phase 2 - Customer Visibility ✅
- Customer timeline endpoint
- Combined view of assessments and notes
- Summary statistics
- Chronological ordering

### Phase 3 - Collaboration ✅
- Profile recommendations table and model
- Auto-generation of recommendations from assessments
- Customer review UI (RecommendationReview component)
- Accept/reject functionality
- Automatic profile updates on acceptance
- Notification integration
- Expiration system (30 days)

### Phase 4 - Service Recommendations ✅
- ServiceRecommendationController with concern-based matching
- Hair type, skin type, and concern mappings
- Priority-based sorting (high/medium/low)
- ServiceRecommendations UI component
- API endpoint: `GET /portal/service-recommendations`

### Phase 5 - Specialist Portal ✅
- Specialist accounts table and authentication
- SpecialistPortalController (login/register/context/logout)
- SpecialistAuthContext for frontend
- Specialist portal login page
- Specialist portal dashboard with tabs (Dashboard, Customers, Assessments, Notes)
- AssessmentForm component for creating assessments
- NoteForm component for creating notes
- Customer management with booking/spending stats
- Customer detail view with assessment/note creation
- Real-time schedule from bookings API
- API endpoints:
  - `POST /specialist-portal/login`
  - `POST /specialist-portal/register`
  - `GET /specialist-portal/context`
  - `POST /specialist-portal/logout`
  - `GET /specialist-portal/customers`
  - `GET /specialist-portal/bookings`
  - `GET /specialist-portal/customers/{customerId}/assessment`

### Phase 6 - Specialist Matching ✅
- Backend specialist recommendation engine
- Match scoring based on specialties, rating, experience, skills
- SpecialistRecommendations UI component with match scores
- API endpoint: `GET /portal/specialist-recommendations`

## What's Left (Future Features)

### Product Recommendations (Low Priority)

**Product Recommendation UI**
- Suggest products based on customer profile
- Avoid allergens
- Match hair/skin type
- Display in booking flow or profile

*Note: Products have not yet been implemented in the system, so this feature is deferred.*

## Advantages of This Design

1. **Customer Control** - Customers remain in control of their own data
2. **Professional Integrity** - Specialists provide guidance without overwriting
3. **Audit Trail** - Every change has evidence and history
4. **AI Accuracy** - Can use both customer-provided and professionally observed data
5. **Trust Building** - Recommendations backed by professional evidence
6. **Flexibility** - Customers can accept or reject based on their preferences

## Migration History

### 2026_08_02_083142 - Create professional_assessments table
- Created specialist observation tracking table
- Added customer response tracking
- Added indexes for filtering

### 2026_08_02_083543 - Create specialist_notes table
- Created free-form notes table
- Added visibility controls
- Added importance flags

### 2026_08_02_084346 - Create profile_recommendations table
- Created recommendations table
- Added status tracking
- Added expiration system

## Security Considerations

- All specialist endpoints require salon context authentication
- All customer endpoints require portal authentication
- Customers can only view their own recommendations
- Specialists can only create assessments for their salon's customers
- Private notes are only visible to the creating specialist
- Customer authorization checks on recommendation responses

## Troubleshooting

### Recommendations not generating
- Check if customer has a grooming profile
- Verify observed values differ from current profile values
- Check ReferenceData validation is passing

### Notifications not appearing
- Verify Notification model exists and is properly configured
- Check customer_id is set correctly
- Ensure notification type is registered

### Timeline not showing data
- Verify customer has assessments or notes
- Check customer_visible flag on notes
- Ensure proper authentication context

## Conclusion

The Specialist Assessment System provides a robust foundation for professional guidance while maintaining customer data ownership. The three-phase approach ensures data integrity, customer visibility, and collaborative decision-making. Future AI features can be built on this foundation to provide even more personalized experiences.
