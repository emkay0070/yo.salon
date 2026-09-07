# Specialist Architecture

## Vision

Yo.Salon becomes the operating system for the beauty industry, where **specialists own their careers while salons own their businesses**.

This is fundamentally different from traditional salon software where specialists are merely employees. In our vision:

- Specialists are **global professionals** with portable careers
- Customers follow **people**, not just businesses
- Specialists build **professional identities** that travel with them across salons
- Salons are **businesses** that hire and manage talent
- The platform enables **network effects** through customer-specialist relationships

## Core Architecture

### Four Independent Relationships

```
Customer
     │
     ├───────────────┐
     │               │
customer_salon   customer_specialist
     │               │
     │               │
    Salon────salon_specialist────Specialist
                 │
             employment
```

Each relationship serves a distinct purpose:

| Relationship          | Purpose                                                             |
| --------------------- | ------------------------------------------------------------------- |
| Customer ↔ Salon      | Loyalty to the business (visits, membership, wallet)                |
| Customer ↔ Specialist | Loyalty to the professional (follow, favorite, rating)              |
| Salon ↔ Specialist    | Employment/contract (roles, compensation, schedule)                 |
| Booking               | A specific service event connecting all three entities               |

## Data Model

### customer_specialist Table

Represents ongoing customer-specialist relationships that exist independently of any single salon.

**Columns:**
- `customer_id` - The customer
- `specialist_id` - The specialist
- `first_booking_id` - First booking that established this relationship
- `last_booking_id` - Most recent booking
- `total_bookings` - Lifetime booking count
- `total_spent` - Total amount spent with this specialist
- `is_favorite` - Customer has favorited this specialist
- `is_following` - Customer follows this specialist
- `rating_given` - Rating customer gave to specialist
- `notes` - Customer's private notes about this specialist
- `relationship_score` - Algorithmic score based on interactions
- `last_seen_at` - Last time customer viewed specialist profile
- `last_interaction_at` - Last booking or interaction
- `notifications_enabled` - Whether customer wants notifications
- `notification_preferences` - Specific notification types (JSON)

**Key Features:**
- Persists across salon changes (Sarah moves from Salon A to Salon B, her 150 clients follow)
- Enables relationship strength scoring for recommendations
- Supports notification preferences for specialist movements
- Tracks lifetime value of customer-specialist relationships

### salon_specialist Table (Enhanced)

Represents employment relationships between specialists and salons.

**Enhanced Columns:**
- `roles` (JSON) - Multiple roles: `["stylist", "receptionist"]`, `["barber", "manager"]`
- `hired_at` - Employment start date
- `left_at` - Employment end date
- `employment_type` - `full_time`, `part_time`, `contract`, `freelance`
- `commission_rate` - Commission percentage (0-100)
- `hourly_rate` - Hourly rate
- `working_hours` (JSON) - Salon-specific schedule
- `is_primary_salon` - Is this their main location?
- `hiring_status` - `pending`, `active`, `suspended`, `terminated`
- `hiring_notes` - Internal documentation

**Key Features:**
- Specialists can have multiple roles at the same salon
- Freelance/contract specialists can work at multiple salons simultaneously
- Full employment lifecycle tracking (hiring to termination)
- Compensation management per salon

### Specialist Model (Global Identity)

**Professional Identity (Global - Owned by Specialist):**
- Name, photo, handle
- Biography, specialties, skills
- Certifications, portfolio
- Years of experience
- Languages spoken
- Rating, review count
- **Followers** (via customer_specialist)
- **Favorites** (via customer_specialist)
- Career history (via salon_specialist)

**Never resets when changing salons.**

### Salon-Specific Data (Employment Identity)

**Employment Identity (Salon - Owned by Salon):**
- Roles at this salon
- Working hours
- Commission rate
- Hourly rate
- Services offered
- Pricing
- Branch assignment
- Employment dates
- Employment status

**Changes whenever they move to a new salon.**

## API Endpoints

### Customer-Specialist Relationships (Portal)

**Follow/Favorite Management:**
- `GET /portal/specialists/followed` - Get customer's followed specialists
- `GET /portal/specialists/favorites` - Get customer's favorite specialists
- `POST /portal/specialists/{id}/follow` - Toggle follow status
- `POST /portal/specialists/{id}/favorite` - Toggle favorite status
- `POST /portal/specialists/{id}/rating` - Set rating
- `GET /portal/specialists/{id}/relationship` - Get relationship details

**Response Example:**
```json
{
  "specialists": [
    {
      "specialist": {
        "id": "uuid",
        "name": "Sarah Johnson",
        "photo": "url",
        "specialties": ["colorist", "stylist"],
        "rating": 4.9,
        "follower_count": 150
      },
      "is_favorite": true,
      "total_bookings": 12,
      "relationship_score": 85,
      "last_interaction": "2026-07-15T10:00:00Z"
    }
  ]
}
```

### Specialist Discovery (Admin/Salon)

**Talent Marketplace:**
- `GET /specialists/discovery/search` - Search specialists by criteria
- `GET /specialists/discovery/available` - Find specialists open to opportunities
- `GET /specialists/discovery/{id}` - View specialist profile for hiring
- `POST /specialists/discovery/{id}/invite` - Send hiring invitation
- `GET /specialists/discovery/{id}/invitations` - View pending invitations
- `POST /specialists/discovery/{salonId}/respond` - Accept/decline invitation

**Search Parameters:**
- `query` - Text search (name, handle, bio, skills)
- `specialties` - Array of specialties to filter
- `location` - Geographic location
- `min_rating` - Minimum rating (0-5)
- `min_experience` - Minimum years of experience
- `availability` - `immediate`, `within_week`, `within_month`, `flexible`
- `employment_type` - `full_time`, `part_time`, `contract`, `freelance`
- `roles` - Array of roles: `stylist`, `barber`, `colorist`, etc.

**Profile Response Example:**
```json
{
  "specialist": {
    "id": "uuid",
    "name": "Sarah Johnson",
    "handle": "@sarahcolorist",
    "photo": "url",
    "bio": "10 years experience in color...",
    "specialties": ["colorist", "stylist"],
    "skills": ["balayage", "highlights", "color correction"],
    "years_experience": 10,
    "rating": 4.9,
    "review_count": 150,
    "certifications": ["L'Oréal Professional", "Wella"],
    "portfolio": ["url1", "url2"],
    "languages": ["English", "French"]
  },
  "metrics": {
    "follower_count": 150,
    "favorite_count": 85,
    "total_bookings": 1200
  },
  "employment": {
    "is_available_for_hire": true,
    "current_salons": [
      {
        "id": "uuid",
        "name": "Elite Barbers",
        "role": ["stylist"],
        "employment_type": "freelance",
        "is_primary": false,
        "hired_at": "2026-01-15"
      }
    ]
  },
  "recent_followers": [...],
  "recent_reviews": [...]
}
```

## Use Cases

### 1. Specialist Moves Between Salons

**Scenario:** Sarah works at Salon A for 1 year, serves 150 customers, then moves to Salon B.

**Before this architecture:**
- Sarah starts from zero at Salon B
- No customer relationships transfer
- Portfolio and reviews reset

**With this architecture:**
- Sarah's 150 customers receive notification: "Sarah is now available at Salon B"
- One tap to book at new location
- Portfolio, reviews, certifications travel with her
- Customer relationships persist
- Salon B can see her 150 followers as hiring value

### 2. Customer Follows Favorite Specialist

**Scenario:** John only books with David, his favorite barber.

**Flow:**
1. John books with David at Salon A
2. System creates customer_specialist relationship
3. John clicks "Follow" on David's profile
4. David moves to Salon B
5. John receives notification
6. John books David at Salon B in 2 taps

### 3. Salon Discovers and Hires Talent

**Scenario:** Salon X needs a senior colorist.

**Flow:**
1. Salon X searches specialists with: `specialties: ["colorist"]`, `min_rating: 4.5`, `min_experience: 5`
2. Results show Sarah with 150 followers, 4.9 rating, 10 years experience
3. Salon X views Sarah's profile, portfolio, reviews
4. Salon X sends hiring invitation with: `roles: ["colorist", "stylist"]`, `employment_type: "full_time"`, `commission_rate: 40`
5. Sarah receives notification, views offer
6. Sarah accepts invitation
7. System creates salon_specialist record with `hiring_status: "active"`

### 4. Multi-Role Specialist

**Scenario:** Maria works as both a stylist and receptionist at the same salon.

**Flow:**
1. salon_specialist record: `roles: ["stylist", "receptionist"]`
2. Maria can perform both functions
3. Compensation can differ per role
4. Schedule can vary per role

### 5. Freelance Specialist at Multiple Salons

**Scenario:** Tom is a freelance barber working at 3 different salons.

**Flow:**
1. Three salon_specialist records with `employment_type: "freelance"`
2. Each salon has different commission rates
3. Each salon has different working hours
4. Customers can book Tom at any of the 3 locations
5. Tom's follower count aggregates across all salons

## Relationship Strength Scoring

The `relationship_score` in customer_specialist is calculated algorithmically:

```php
score = min(bookings * 10, 50) + min(spending / 100 * 5, 50)
```

**Strength Levels:**
- `new` (0-19): First few interactions
- `emerging` (20-49): Building relationship
- `regular` (50-99): Established customer
- `loyal` (100+): Long-term relationship

**Used for:**
- Recommendations ("Customers like you also book with...")
- Priority notifications
- Special offers
- Relationship-based pricing

## Notification System

When a specialist changes salons:

```php
$service->notifySpecialistMoved(
    $specialistId, 
    $newSalonId, 
    $newSalonName
);
```

Followers with `notifications_enabled: true` receive:
- Push notification
- In-app notification
- Email (if configured)

## Future Enhancements

**Planned Features:**
1. Specialist profile pages with full portfolio
2. Career timeline (salon history)
3. Achievement badges (5 years, 1000 bookings, etc.)
4. Specialist-to-specialist networking
5. Training and certification tracking
6. Salary history and earnings analytics
7. Specialist marketplace with bidding
8. Customer reviews with photos
9. Specialist recommendations engine
10. Geographic availability matching

## Migration Path

**For existing systems:**
1. Run migration to create `customer_specialist` table
2. Backfill from existing bookings
3. Enhance `salon_specialist` with employment details
4. Update booking creation to track relationships
5. Deploy frontend follow/favorite features
6. Launch specialist discovery for salons

## Performance Considerations

**Indexes:**
- `customer_specialist`: customer_id, specialist_id, is_following, is_favorite, relationship_score
- `salon_specialist`: salon_id, specialist_id, hiring_status, employment_type

**Caching:**
- Specialist follower counts
- Relationship scores
- Search results

**Query Optimization:**
- Eager loading relationships
- Pagination for large result sets
- Database-level filtering where possible

## Security Considerations

**Privacy:**
- Customer notes are private to the customer
- Specialist contact info only shared after hiring
- Employment details are salon-confidential

**Access Control:**
- Customers can only view their own relationships
- Salons can only view their own employment records
- Discovery API requires authentication

**Data Ownership:**
- Specialist profile data belongs to the specialist
- Employment data belongs to the salon
- Customer relationship data belongs to the customer

## Conclusion

This architecture transforms Yo.Salon from salon management software into a professional ecosystem where:

- **Specialists** build lifelong careers with portable identities
- **Salons** are businesses that hire and manage talent
- **Customers** maintain relationships with both businesses and professionals
- **Network effects** emerge from customer-specialist connections
- **Platform value** increases as the specialist network grows

This is the foundation for Yo.Salon to become the operating system for the beauty industry.
