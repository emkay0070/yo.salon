# Specialist Operating System - Complete Documentation

## Table of Contents
1. [Philosophy & Vision](#philosophy--vision)
2. [Architecture Overview](#architecture-overview)
3. [Navigation Structure](#navigation-structure)
4. [Page Documentation](#page-documentation)
5. [Data Structures](#data-structures)
6. [API Endpoints](#api-endpoints)
7. [Technical Implementation](#technical-implementation)
8. [Future Expansion](#future-expansion)
9. [File Structure](#file-structure)

---

## Philosophy & Vision

### The Core Question
Every page in the Specialist OS answers:

> **"How does this make me a better professional?"**

### The Three Personalities of yo.salon

1. **Customer** → "I want to look good."
2. **Salon** → "I run a business."
3. **Specialist** → "This is my career."

### The Specialist OS Philosophy

The salon runs a business.  
The customer manages their grooming.  
The specialist builds a career.

Most salon software helps specialists manage appointments.  
A world-class platform helps them build a profession.

### The Differentiator

If a specialist leaves one salon tomorrow, they don't start over—they take their verified career with them. This is incredibly valuable and unique to yo.salon.

---

## Architecture Overview

### The Six Worlds

The Specialist OS is organized into **six worlds**, not pages:

1. **Workspace** - My day (70% of time spent here)
2. **Schedule** - Everything about time
3. **Clients** - Specialist's CRM
4. **Craft** - Becoming better professionally
5. **Career** - Professional identity and reputation
6. **Intelligence** - AI-powered professional insights

### Additional Worlds

7. **Finance** - Revenue and motivation (separated from Career)
8. **Profile** - Pure identity
9. **Settings** - Preferences and security
10. **Journey** - Professional timeline and career milestones

### Technology Stack

- **Frontend**: Next.js 14 (App Router), React, TypeScript, TailwindCSS, Framer Motion
- **State Management**: React Query (TanStack Query), Context API
- **Icons**: Lucide React
- **Authentication**: SpecialistAuthContext
- **Backend Integration**: REST API (planned Laravel endpoints)

---

## Navigation Structure

### Sidebar Navigation

```
Workspace          → /specialist-portal/workspace
Calendar           → /specialist-portal/calendar
Appointments       → /specialist-portal/appointments
Clients            → /specialist-portal/clients
Craft              → /specialist-portal/craft
Career             → /specialist-portal/career
Intelligence       → /specialist-portal/intelligence
Finance            → /specialist-portal/finance
Journey            → /specialist-portal/journey
Profile            → /specialist-portal/profile
Settings           → /specialist-portal/settings
```

### Navigation Features

- **Mobile Responsive**: Collapsible sidebar with backdrop
- **Specialist Branding**: Gold accent colors, specialist avatar
- **Active State**: Gold highlight for current page
- **Logout**: Dedicated logout button at bottom of sidebar

---

## Page Documentation

### 1. Workspace (`/specialist-portal/workspace`)

**Purpose**: Daily workspace - answers "What do I need to do today?"

**Features**:
- Personalized greeting based on time of day
- Current date display
- Quick stats cards:
  - Today's Earnings (UGX)
  - Today's Rating
  - Customers Waiting
  - Completed Appointments
- Today's Schedule:
  - Appointment list with customer avatars
  - Service names and times
  - Status indicators (completed, in-progress, upcoming)
- Next Break display
- Availability status (Open/Closed with closing time)

**Data Requirements**:
```typescript
interface TodayStats {
  earnings: number;
  rating: number;
  waitingCustomers: number;
  completedToday: number;
}

interface Appointment {
  time: string;
  customer: string;
  service: string;
  status: 'completed' | 'in-progress' | 'upcoming';
}

interface NextBreak {
  time: string;
  duration: string;
}

interface Availability {
  status: 'Open' | 'Closed';
  closesAt: string;
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/today/bookings` - Today's appointments
- `GET /api/v1/specialist-portal/today/stats` - Today's statistics
- `GET /api/v1/specialist-portal/availability/status` - Current availability

---

### 2. Calendar (`/specialist-portal/calendar`)

**Purpose**: Time management - everything about time

**Features**:
- **Weekly Schedule View**:
  - Week navigation (previous/next/today)
  - 7-day grid with working hours
  - Visual indication of working vs off days
  - Working hours display
- **Break Management**:
  - Daily break time display
  - Edit break functionality
- **Time Off**:
  - Request time off button
  - Upcoming time off display
- **Exceptions View**:
  - Holidays and events
  - Vacation requests
  - Add exception functionality
  - Exception type indicators (vacation, holiday)

**Data Requirements**:
```typescript
interface WeeklySchedule {
  workingHours: { start: string; end: string };
  break: { start: string; end: string };
  days: string[]; // ['Monday', 'Tuesday', ...]
}

interface Exception {
  id: number;
  type: 'vacation' | 'holiday' | 'event';
  title: string;
  start: string; // YYYY-MM-DD
  end: string; // YYYY-MM-DD
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/schedule/weekly` - Weekly schedule
- `PUT /api/v1/specialist-portal/schedule/break` - Update break time
- `GET /api/v1/specialist-portal/schedule/exceptions` - Exceptions list
- `POST /api/v1/specialist-portal/schedule/exceptions` - Create exception
- `PUT /api/v1/specialist-portal/schedule/exceptions/:id` - Update exception
- `DELETE /api/v1/specialist-portal/schedule/exceptions/:id` - Delete exception

**Integration with Availability Engine**:
- Uses the Availability Engine's assignment schedule resolution
- Supports multi-location schedules via assignment system
- Exception management integrates with ScheduleException model

---

### 3. Appointments (`/specialist-portal/appointments`)

**Purpose**: Daily work management

**Features**:
- **Tabbed Interface**:
  - Upcoming
  - Completed
  - Cancelled
  - No Shows
- **Appointment Cards**:
  - Customer avatar and name
  - Service name and duration
  - Time and date
  - Status indicator with color coding
- **Actions** (for upcoming appointments):
  - Confirm
  - Cancel
- **Filter Functionality**
- **Empty States** with icons

**Data Requirements**:
```typescript
type AppointmentStatus = 'upcoming' | 'completed' | 'cancelled' | 'no-show';

interface Appointment {
  id: number;
  customer: {
    name: string;
    phone: string;
  };
  service: {
    name: string;
    duration: number;
  };
  startTime: string; // ISO datetime
  endTime: string; // ISO datetime
  status: AppointmentStatus;
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/appointments?status=upcoming` - List appointments
- `PUT /api/v1/specialist-portal/appointments/:id/confirm` - Confirm appointment
- `PUT /api/v1/specialist-portal/appointments/:id/cancel` - Cancel appointment
- `PUT /api/v1/specialist-portal/appointments/:id/no-show` - Mark as no-show

---

### 4. Clients (`/specialist-portal/clients`)

**Purpose**: Specialist's CRM - customer relationships

**Features**:
- **Client List View**:
  - Search by name, phone, email
  - Client cards with avatar, name, bookings count, total spent
  - Favorite indicator
  - Total clients count
- **Client Detail View**:
  - Client header with avatar, name, contact info
  - Stats cards:
    - Total Bookings
    - Total Spent
    - Last Visit
    - Preferred Service
  - **Grooming Profile**:
    - Hair Type
    - Face Shape
    - Skin Type
    - Allergies
  - **Notes Section**:
    - Specialist notes
    - Add note functionality
  - **Service History** (planned)
  - **Photos** (planned)

**Data Requirements**:
```typescript
interface Client {
  id: number;
  name: string;
  email: string;
  phone: string;
  totalBookings: number;
  totalSpent: number;
  lastVisit: string; // YYYY-MM-DD
  isFavorite: boolean;
  preferredService: string;
  notes: string;
  groomingProfile: {
    hairType: string;
    faceShape: string;
    skinType: string;
    allergies: string[];
  };
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/clients` - List clients
- `GET /api/v1/specialist-portal/clients/:id` - Client details
- `GET /api/v1/specialist-portal/clients/search query` - Search clients
- `POST /api/v1/specialist-portal/clients/:id/notes` - Add note
- `GET /api/v1/specialist-portal/clients/:id/history` - Service history
- `GET /api/v1/specialist-portal/clients/:id/photos` - Client photos

---

### 5. Craft (`/specialist-portal/craft`)

**Purpose**: Becoming better professionally - craft mastery

**Features**:
- **Tabbed Interface** (5 sections):
  1. **Portfolio**:
     - Work gallery (transformations, single work)
     - Before/after images
     - Service labels
     - Like counts
     - Add work functionality
  2. **Skills**:
     - Skill mastery levels (percentage)
     - Certification counts
     - Progress bars
     - Add skill functionality
  3. **Learning**:
     - Courses, workshops, tutorials
     - Progress tracking
     - Platform attribution
     - Completion badges
     - Browse courses functionality
  4. **Products**:
     - Products mastered
     - Usage statistics
     - Brand information
     - Browse products functionality
  5. **Achievements**:
     - Milestones, awards, skill badges
     - Date earned
     - Type indicators
     - Filter functionality

**Data Requirements**:
```typescript
interface PortfolioItem {
  id: number;
  type: 'transformation' | 'work';
  before?: string; // image URL
  after?: string; // image URL
  image?: string; // image URL for single work
  service: string;
  likes: number;
}

interface Skill {
  name: string;
  level: number; // 0-100
  certifications: number;
}

interface LearningItem {
  id: number;
  title: string;
  type: 'course' | 'workshop' | 'tutorial';
  progress: number; // 0-100
  platform: string;
}

interface Product {
  name: string;
  brand: string;
  mastered: boolean;
  usage: number; // 0-100
}

interface Achievement {
  id: number;
  title: string;
  icon: string; // emoji
  date: string; // YYYY-MM-DD
  type: 'milestone' | 'award' | 'achievement' | 'skill';
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/craft/portfolio` - Portfolio items
- `POST /api/v1/specialist-portal/craft/portfolio` - Add portfolio item
- `GET /api/v1/specialist-portal/craft/skills` - Skills list
- `POST /api/v1/specialist-portal/craft/skills` - Add skill
- `GET /api/v1/specialist-portal/craft/learning` - Learning items
- `GET /api/v1/specialist-portal/craft/products` - Products list
- `GET /api/v1/specialist-portal/craft/achievements` - Achievements list

---

### 6. Career (`/specialist-portal/career`)

**Purpose**: Professional identity and reputation - what makes yo.salon unique

**Features**:
- **Reputation Score Card**:
  - Overall rating (e.g., 4.98)
  - Total reviews count
  - Total clients served
- **Key Metrics**:
  - Retention Rate
  - Rebooking Rate
  - Attendance Rate
  - Punctuality Rate
- **Revenue & Experience**:
  - Total career earnings
  - Years of experience
- **Most Requested Service**:
  - Signature service display
- **Certifications**:
  - List of professional certifications
- **Achievements**:
  - Awards, milestones, customer favorites
- **Employment History**:
  - Timeline of salons worked at
  - Roles and periods
  - This is the key differentiator - career follows the specialist

**Data Requirements**:
```typescript
interface CareerStats {
  rating: number;
  totalReviews: number;
  totalClients: number;
  retentionRate: number;
  rebookingRate: number;
  attendanceRate: number;
  punctualityRate: number;
  revenueGenerated: number;
  yearsExperience: number;
  certifications: string[];
  achievements: string[];
  topService: string;
  employmentHistory: {
    salon: string;
    period: string; // "2023 - 2025"
    role: string;
  }[];
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/career` - Career statistics
- `GET /api/v1/specialist-portal/career/reviews` - Reviews list
- `GET /api/v1/specialist-portal/career/certifications` - Certifications
- `GET /api/v1/specialist-portal/career/employment` - Employment history

---

### 7. Intelligence (`/specialist-portal/intelligence`)

**Purpose**: AI-powered professional insights - the AI layer

**Features**:
- **Customers Needing Follow-up**:
  - Customer name and last booking date
  - Days overdue
  - Suggested service recommendation
- **Trends & Opportunities**:
  - Seasonal trends (e.g., "Customers with dry hair increase during July")
  - Recommendations
  - Impact level (high/medium)
- **Product Opportunities**:
  - Customer likely to buy specific products
  - Likelihood percentage
  - Reason for recommendation
- **Strengths**:
  - Top-performing services
  - Rating and booking counts
  - Visual strength indicators
- **At Risk Customers**:
  - Customers who haven't returned
  - Risk level (high/medium)
  - Reason for risk
  - Quick action buttons
- **Refresh Button** - Update insights

**Data Requirements**:
```typescript
interface IntelligenceData {
  followUps: {
    id: number;
    customer: string;
    lastBooking: string; // YYYY-MM-DD
    daysOverdue: number;
    suggestedService: string;
  }[];
  trends: {
    id: number;
    trend: string;
    recommendation: string;
    impact: 'high' | 'medium' | 'low';
  }[];
  opportunities: {
    id: number;
    customer: string;
    likelihood: number; // 0-100
    product: string;
    reason: string;
  }[];
  strengths: {
    id: number;
    service: string;
    rating: number; // 0-100
    bookings: number;
  }[];
  atRisk: {
    id: number;
    customer: string;
    lastBooking: string; // YYYY-MM-DD
    risk: 'high' | 'medium' | 'low';
    reason: string;
  }[];
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/intelligence` - All insights
- `GET /api/v1/specialist-portal/intelligence/follow-ups` - Follow-up recommendations
- `GET /api/v1/specialist-portal/intelligence/trends` - Trend analysis
- `GET /api/v1/specialist-portal/intelligence/opportunities` - Sales opportunities
- `GET /api/v1/specialist-portal/intelligence/strengths` - Performance strengths
- `GET /api/v1/specialist-portal/intelligence/at-risk` - At-risk customers

**AI Features**:
- Predictive analytics for rebooking
- Product recommendation engine
- Trend analysis based on booking patterns
- Customer churn prediction
- Performance optimization suggestions

---

### 8. Finance (`/specialist-portal/finance`)

**Purpose**: Revenue and motivation - separated from Career

**Features**:
- **Period Selector**:
  - Today
  - This Week
  - This Month
- **Main Revenue Card**:
  - Total revenue for selected period
  - Visual emphasis (green gradient)
- **Earnings Breakdown**:
  - Commission
  - Tips
  - Average Ticket
- **Clients Served**:
  - Count for selected period
- **Projected Earnings**:
  - Based on current bookings
  - Percentage increase indicator
- **Target Progress**:
  - Daily target progress bar
  - Monthly target progress bar
  - Visual progress indicators

**Data Requirements**:
```typescript
interface FinanceData {
  revenue: number;
  commission: number;
  tips: number;
  averageTicket: number;
  clients: number;
  projected: number;
}

interface Targets {
  daily: number;
  monthly: number;
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/finance?period=today` - Finance data
- `GET /api/v1/specialist-portal/finance/targets` - Revenue targets
- `GET /api/v1/specialist-portal/finance/projections` - Earnings projections
- `GET /api/v1/specialist-portal/finance/payouts` - Payout history
- `POST /api/v1/specialist-portal/finance/export` - Export for taxes

---

### 9. Journey (`/specialist-portal/journey`)

**Purpose**: Professional timeline and career milestones - the biggest differentiator

**Features**:
- **Career Stats Grid**:
  - Years Experience
  - Total Clients
  - Total Bookings
  - Total Revenue
- **Additional Stats**:
  - Average Rating
  - Certifications
  - Awards
  - Salons Worked
- **Career Timeline**:
  - Vertical timeline with connecting line
  - Milestone dots with icons
  - Year indicators
  - Type labels (milestone, employment, achievement, award)
  - Arrow to next milestone
  - Visual color coding by type
- **Career Summary**:
  - Narrative summary of career
  - Download Career Report button

**Data Requirements**:
```typescript
interface Milestone {
  id: number;
  year: number;
  title: string;
  type: 'milestone' | 'employment' | 'achievement' | 'award';
  icon: string; // emoji
}

interface JourneyStats {
  totalYears: number;
  totalClients: number;
  totalBookings: number;
  totalRevenue: number;
  averageRating: number;
  certifications: number;
  awards: number;
  salonsWorked: number;
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/journey` - Journey data
- `GET /api/v1/specialist-portal/journey/milestones` - Milestones timeline
- `GET /api/v1/specialist-portal/journey/export` - Download career report

**The Journey Vision**:
Every booking, every review, every achievement, every certificate, every salon, every promotion, every milestone - one timeline. This becomes the specialist's professional history. If they leave one salon tomorrow, they don't start over—they take their verified career with them.

---

### 10. Profile (`/specialist-portal/profile`)

**Purpose**: Pure identity - nothing operational

**Features**:
- **Profile Photo**:
  - Large avatar display
  - Edit button (when in edit mode)
- **Bio**:
  - Text area for bio
  - Edit mode toggle
- **Location**:
  - Display current location
- **Languages**:
  - List of languages spoken
  - Pill display
- **Social Links**:
  - Platform and URL
  - Multiple platforms
- **Certificates**:
  - List from specialist data
  - Pill display
- **Edit Mode**:
  - Toggle edit button
  - Save/Cancel actions
  - Loading state during save

**Data Requirements**:
```typescript
interface ProfileData {
  bio: string;
  languages: string[];
  socialLinks: {
    platform: string;
    url: string;
  }[];
  location: string;
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/profile` - Profile data
- `PUT /api/v1/specialist-portal/profile` - Update profile
- `POST /api/v1/specialist-portal/profile/photo` - Upload photo

---

### 11. Settings (`/specialist-portal/settings`)

**Purpose**: Preferences and security

**Features**:
- **Notifications**:
  - Push notifications toggle
  - Email notifications toggle
  - SMS notifications toggle
- **Appearance**:
  - Dark mode toggle
- **Security**:
  - Change password (navigate)
  - Two-factor authentication toggle
  - Active sessions (navigate)
- **Devices**:
  - Manage devices (navigate)
- **Logout**:
  - Logout button with red accent

**Data Requirements**:
```typescript
interface SettingsData {
  notifications: {
    push: boolean;
    email: boolean;
    sms: boolean;
  };
  appearance: {
    darkMode: boolean;
  };
  security: {
    twoFactorEnabled: boolean;
  };
}
```

**API Endpoints**:
- `GET /api/v1/specialist-portal/settings` - Get settings
- `PUT /api/v1/specialist-portal/settings` - Update settings
- `POST /api/v1/specialist-portal/settings/password` - Change password
- `GET /api/v1/specialist-portal/settings/sessions` - Active sessions
- `DELETE /api/v1/specialist-portal/settings/sessions/:id` - Revoke session
- `GET /api/v1/specialist-portal/settings/devices` - Connected devices

---

## Data Structures

### Specialist Context

```typescript
interface Specialist {
  id: string;
  name: string;
  email: string;
  phone: string;
  handle: string;
  bio: string;
  specialties: string[];
  photo_url: string | null;
  rating: number;
  review_count: number;
}

interface Salon {
  id: string;
  name: string;
  slug: string;
  logo: string | null;
}

interface SpecialistAccount {
  id: string;
  email: string;
  phone: string;
  is_active: boolean;
  last_login_at: string;
}
```

### Authentication Context

```typescript
interface SpecialistAuthContextType {
  specialistAccount: SpecialistAccount | null;
  specialist: Specialist | null;
  salons: Salon[];
  activeSalon: Salon | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
}
```

---

## API Endpoints Summary

### Authentication
- `POST /api/v1/specialist-portal/login` - Login
- `POST /api/v1/specialist-portal/logout` - Logout
- `GET /api/v1/specialist-portal/me` - Current specialist info

### Workspace
- `GET /api/v1/specialist-portal/today/bookings` - Today's appointments
- `GET /api/v1/specialist-portal/today/stats` - Today's statistics
- `GET /api/v1/specialist-portal/availability/status` - Current availability

### Calendar
- `GET /api/v1/specialist-portal/schedule/weekly` - Weekly schedule
- `PUT /api/v1/specialist-portal/schedule/break` - Update break time
- `GET /api/v1/specialist-portal/schedule/exceptions` - Exceptions list
- `POST /api/v1/specialist-portal/schedule/exceptions` - Create exception
- `PUT /api/v1/specialist-portal/schedule/exceptions/:id` - Update exception
- `DELETE /api/v1/specialist-portal/schedule/exceptions/:id` - Delete exception

### Appointments
- `GET /api/v1/specialist-portal/appointments` - List appointments
- `PUT /api/v1/specialist-portal/appointments/:id/confirm` - Confirm appointment
- `PUT /api/v1/specialist-portal/appointments/:id/cancel` - Cancel appointment
- `PUT /api/v1/specialist-portal/appointments/:id/no-show` - Mark as no-show

### Clients
- `GET /api/v1/specialist-portal/clients` - List clients
- `GET /api/v1/specialist-portal/clients/:id` - Client details
- `GET /api/v1/specialist-portal/clients/search` - Search clients
- `POST /api/v1/specialist-portal/clients/:id/notes` - Add note
- `GET /api/v1/specialist-portal/clients/:id/history` - Service history
- `GET /api/v1/specialist-portal/clients/:id/photos` - Client photos

### Craft
- `GET /api/v1/specialist-portal/craft/portfolio` - Portfolio items
- `POST /api/v1/specialist-portal/craft/portfolio` - Add portfolio item
- `GET /api/v1/specialist-portal/craft/skills` - Skills list
- `POST /api/v1/specialist-portal/craft/skills` - Add skill
- `GET /api/v1/specialist-portal/craft/learning` - Learning items
- `GET /api/v1/specialist-portal/craft/products` - Products list
- `GET /api/v1/specialist-portal/craft/achievements` - Achievements list

### Career
- `GET /api/v1/specialist-portal/career` - Career statistics
- `GET /api/v1/specialist-portal/career/reviews` - Reviews list
- `GET /api/v1/specialist-portal/career/certifications` - Certifications
- `GET /api/v1/specialist-portal/career/employment` - Employment history

### Intelligence
- `GET /api/v1/specialist-portal/intelligence` - All insights
- `GET /api/v1/specialist-portal/intelligence/follow-ups` - Follow-up recommendations
- `GET /api/v1/specialist-portal/intelligence/trends` - Trend analysis
- `GET /api/v1/specialist-portal/intelligence/opportunities` - Sales opportunities
- `GET /api/v1/specialist-portal/intelligence/strengths` - Performance strengths
- `GET /api/v1/specialist-portal/intelligence/at-risk` - At-risk customers

### Finance
- `GET /api/v1/specialist-portal/finance` - Finance data
- `GET /api/v1/specialist-portal/finance/targets` - Revenue targets
- `GET /api/v1/specialist-portal/finance/projections` - Earnings projections
- `GET /api/v1/specialist-portal/finance/payouts` - Payout history
- `POST /api/v1/specialist-portal/finance/export` - Export for taxes

### Journey
- `GET /api/v1/specialist-portal/journey` - Journey data
- `GET /api/v1/specialist-portal/journey/milestones` - Milestones timeline
- `GET /api/v1/specialist-portal/journey/export` - Download career report

### Profile
- `GET /api/v1/specialist-portal/profile` - Profile data
- `PUT /api/v1/specialist-portal/profile` - Update profile
- `POST /api/v1/specialist-portal/profile/photo` - Upload photo

### Settings
- `GET /api/v1/specialist-portal/settings` - Get settings
- `PUT /api/v1/specialist-portal/settings` - Update settings
- `POST /api/v1/specialist-portal/settings/password` - Change password
- `GET /api/v1/specialist-portal/settings/sessions` - Active sessions
- `DELETE /api/v1/specialist-portal/settings/sessions/:id` - Revoke session
- `GET /api/v1/specialist-portal/settings/devices` - Connected devices

---

## Technical Implementation

### File Structure

```
frontend/
├── src/
│   ├── app/
│   │   └── specialist-portal/
│   │       ├── layout.tsx                    # Portal layout with auth wrapper
│   │       ├── login/
│   │       │   └── page.tsx                 # Login page
│   │       ├── workspace/
│   │       │   └── page.tsx                 # Daily workspace
│   │       ├── calendar/
│   │       │   └── page.tsx                 # Schedule management
│   │       ├── appointments/
│   │       │   └── page.tsx                 # Appointments management
│   │       ├── clients/
│   │       │   └── page.tsx                 # CRM
│   │       ├── craft/
│   │       │   └── page.tsx                 # Craft mastery
│   │       ├── career/
│   │       │   └── page.tsx                 # Professional identity
│   │       ├── intelligence/
│   │       │   └── page.tsx                 # AI insights
│   │       ├── finance/
│   │       │   └── page.tsx                 # Revenue and motivation
│   │       ├── journey/
│   │       │   └── page.tsx                 # Career timeline
│   │       ├── profile/
│   │       │   └── page.tsx                 # Pure identity
│   │       └── settings/
│   │           └── page.tsx                 # Preferences
│   ├── components/
│   │   └── specialist/
│   │       └── SpecialistLayout.tsx          # Sidebar navigation layout
│   └── contexts/
│       └── SpecialistAuthContext.tsx        # Authentication context
```

### Key Technologies

- **Next.js 14 App Router**: File-based routing, server components
- **React Query**: Data fetching, caching, synchronization
- **Framer Motion**: Smooth animations and transitions
- **Lucide React**: Consistent icon set
- **TailwindCSS**: Utility-first styling
- **TypeScript**: Type safety

### Design Patterns

- **Context API**: Authentication and specialist data
- **React Query**: Server state management
- **Compound Components**: Reusable UI patterns
- **Animation Patterns**: Staggered animations for lists
- **Responsive Design**: Mobile-first approach

### Color Scheme

- **Primary**: Gold (#FFD700) - Brand accent
- **Background**: Dark theme base
- **Card**: Slightly lighter than background
- **Surface**: Interactive elements
- **Border**: Subtle borders for structure
- **Text**: Primary and secondary hierarchy

### Animation Strategy

- **Page Load**: Fade in with slight upward motion
- **List Items**: Staggered entrance animations
- **Cards**: Scale and fade on hover
- **Transitions**: Smooth state changes

---

## Future Expansion

### Planned Features

#### Craft Expansion
- **Portfolio**: Video support, before/after sliders, social sharing
- **Skills**: Skill verification system, peer endorsements
- **Learning**: Integrated course platform, certification tracking
- **Products**: Product reviews, recommendation engine
- **Achievements**: Gamification, leaderboards

#### Intelligence Expansion
- **Predictive Analytics**: Advanced ML models for predictions
- **Personalized Recommendations**: AI-powered service suggestions
- **Performance Coaching**: AI-driven improvement suggestions
- **Market Insights**: Industry trends and benchmarks

#### Journey Expansion
- **Interactive Timeline**: Clickable milestones with details
- **Career Comparisons**: Anonymous peer comparisons
- **Goal Setting**: Personal and professional goals
- **Achievement Sharing**: Social sharing of milestones

#### Integration Features
- **Multi-Salon Support**: Work across multiple salons
- **Independent Mode**: Transition to independent specialist
- **Team Features**: Specialist teams and collaboration
- **Marketplace**: Find new salons or opportunities

### Technical Improvements

- **Real-time Updates**: WebSocket integration for live data
- **Offline Support**: PWA capabilities
- **Push Notifications**: Browser and mobile push
- **Performance Optimization**: Code splitting, lazy loading
- **Accessibility**: WCAG AA compliance

---

## File Structure

### Complete File List

```
frontend/src/app/specialist-portal/
├── layout.tsx                              # Portal layout wrapper
├── login/page.tsx                          # Login page (existing)
├── workspace/page.tsx                      # Daily workspace
├── calendar/page.tsx                       # Schedule management
├── appointments/page.tsx                  # Appointments management
├── clients/page.tsx                        # CRM
├── craft/page.tsx                          # Craft mastery
├── career/page.tsx                         # Professional identity
├── intelligence/page.tsx                   # AI insights
├── finance/page.tsx                        # Revenue and motivation
├── journey/page.tsx                        # Career timeline
├── profile/page.tsx                        # Pure identity
└── settings/page.tsx                       # Preferences

frontend/src/components/specialist/
└── SpecialistLayout.tsx                    # Sidebar navigation

frontend/src/contexts/
└── SpecialistAuthContext.tsx              # Authentication context
```

### Component Dependencies

```
SpecialistLayout
├── SpecialistAuthContext
├── Navigation items
└── Responsive sidebar

All Pages
├── SpecialistAuthContext
├── React Query
├── Framer Motion
└── Lucide Icons
```

---

## Summary

The Specialist Operating System is a comprehensive platform designed to help grooming professionals build their careers, not just manage appointments. With 11 distinct pages organized around the philosophy of "How does this make me a better professional?", it provides:

1. **Daily Operations**: Workspace, Calendar, Appointments
2. **Relationship Management**: Clients with full CRM
3. **Professional Growth**: Craft, Career, Intelligence
4. **Financial Management**: Finance with motivation
5. **Identity & Settings**: Profile, Settings
6. **Career Legacy**: Journey - the professional timeline

The key differentiator is the **Journey** feature - a verified career timeline that follows the specialist across salons, making their professional identity portable and valuable.

This is not a portal. It's an operating system for a grooming professional's entire career.
