# EmKay Studios Platform Architecture

**Version:** 1.1
**Last Updated:** July 14, 2026
**Purpose:** Defines the multi-site architecture, experience families, and scaling strategy for the EmKay Studios platform.

---

## Table of Contents

01. [Platform Overview](#01-platform-overview)
02. [Ecosystem Architecture](#02-ecosystem-architecture)
03. [Experience Families](#03-experience-families)
04. [User Journey](#04-user-journey)
05. [Dashboard: Digital Salon HQ](#05-dashboard-digital-salon-hq)
06. [Technical Architecture](#06-technical-architecture)
07. [Scaling Strategy](#07-scaling-strategy)
08. [Experience Layer Redefinition](#08-experience-layer-redefinition)

---

## 01 Platform Overview

### Core Philosophy
EmKay Studios is not a website builder. It's an Experience-as-a-Service platform that generates immersive digital environments for businesses.

### The Ecosystem Model

```
EmKay Studios (Platform Core)
    ↓
Yo Salon (Vertical Product)
    ↓
Customer Salon Websites (Generated Instances)
    ↓
Salon Management Dashboard (Admin Layer)
```

**Key Insight:** Every customer gets the same **quality** and **engine**, but not the same **design**.

### Platform Goals

1. **Scalability:** Architecture supports unlimited industries beyond salons
2. **Customization:** AI generates unique experiences within experience families
3. **Consistency:** All experiences follow EmKay Studios standards
4. **Separation:** Marketing, customer-facing, and management experiences are distinct

---

## 02 Ecosystem Architecture

The EmKay ecosystem consists of four interconnected layers:

### Layer 1: EmKay Studios Platform Core

**Domain:** `emkaystudios.com`
**Purpose:** Platform marketing and business development
**Audience:** Business owners, decision-makers, investors

**Content:**
- Platform vision and philosophy
- Industry showcase (Yo Salon, Yo Restaurant, Yo Clinic, etc.)
- Technology demonstration
- Partnership opportunities
- Company information
- Careers

**Experience Type:** Marketing Experience (inspires and converts)

**Key Features:**
- Hero experience showcasing platform capabilities
- Interactive demos of Experience Engine
- Case studies from multiple industries
- Developer documentation portal
- Pricing for enterprise partnerships

---

### Layer 2: Yo Salon Vertical Product

**Domain:** `yosalon.app`
**Purpose:** Product-specific marketing for salon industry
**Audience:** Salon owners, barbershop owners, independent stylists

**Content:**
- Hero Experience
- Feature demonstrations
- AI Experience Generator preview
- Pricing tiers
- Customer testimonials
- Interactive demo
- Book a demo CTA
- Start free trial CTA

**Experience Type:** Marketing Experience (inspires and converts)

**Design Direction:**
- Reference implementation of EmKay standards
- Futuristic, premium, innovative
- Demonstrates what's possible with the platform
- Purple lighting, glass doors, camera flies inside

**Key Sections:**
1. **Hero:** Immersive 3D salon entrance
2. **Problem:** Most salons have basic websites
3. **Solution:** Yo Salon creates immersive digital presence
4. **Platform:** Everything you need to run your salon
5. **Experience Preview:** Choose your experience family
6. **Benchmark Room:** Interactive 3D demo
7. **Conversion:** Start free trial

---

### Layer 3: Customer Salon Websites (Generated Instances)

**Domain:** `[salon-name].yosalon.app` or custom domains
**Purpose:** Customer-facing salon websites
**Audience:** Salon customers booking appointments

**Content:**
- Immersive 3D salon entrance
- Service menu with visual previews
- Online booking system
- Staff profiles
- Gallery of work
- Customer reviews
- Contact information

**Experience Type:** Customer Experience (engages and converts)

**Design Direction:**
- Each salon gets unique design within their chosen experience family
- AI-generated based on salon branding and preferences
- Consistent EmKay quality standards
- Interactive 3D elements

**Key Features:**
- AI Experience Generator creates unique designs
- Experience family themes (Luxury Noir, Modern Glass, Urban Pulse)
- Real-time booking integration
- Mobile-responsive design
- SEO optimization

---

### Layer 4: Salon Management Dashboard

**Domain:** `dashboard.yosalon.app`
**Purpose:** Salon administration and management
**Audience:** Salon owners, staff, managers

**Content:**
- Appointment management
- Customer database
- Staff scheduling
- Inventory tracking
- Analytics and reporting
- Marketing tools
- Website customization

**Experience Type:** Management Experience (organizes and optimizes)

**Design Direction:**
- Clean, functional, data-focused
- High contrast for readability
- Efficient workflows
- Real-time updates

**Key Features:**
- Calendar view with drag-and-drop
- Customer CRM with booking history
- Staff availability management
- Revenue and performance analytics
- Website content editor
- Experience customization controls

---

## 03 Experience Families

### Philosophy
Not templates. **Experience Families.**

Templates force identical designs. Experience families provide a design language that AI customizes for each business.

### Family Structure

Each Experience Family defines:

- **Camera Language:** Movement patterns, speed, angles
- **Motion Physics:** Spring configurations, animation styles
- **Typography:** Font pairings, scales, weights
- **Lighting:** Primary/secondary/ambient light setup
- **Spacing:** Grid system, padding ratios
- **Materials:** Glass, metal, wood, concrete textures
- **Particles:** Type, density, behavior
- **Color Palette:** Base palette with AI-generated accents
- **Sound:** Ambient tracks, UI sounds

### Available Experience Families

#### Luxury Noir
- **Materials:** Black marble, gold accents, chrome
- **Lighting:** Warm gold, soft amber
- **Camera:** Slow, elegant movements
- **Typography:** Serif headings, clean body
- **Sound:** Jazz, ambient piano
- **Vibe:** Sophisticated, premium

#### Modern Glass
- **Materials:** Glass, polished metal, cool lighting
- **Lighting:** Cool blue, white, soft shadows
- **Camera:** Smooth, floating movements
- **Typography:** Modern sans-serif, geometric
- **Sound:** Ambient electronic, soft pads
- **Vibe:** Contemporary, clean

#### Urban Pulse
- **Materials:** Concrete, neon accents, graffiti elements
- **Lighting:** Bold colors, high contrast
- **Camera:** Fast, dynamic movements
- **Typography:** Bold, expressive
- **Sound:** Hip hop, urban beats
- **Vibe:** Edgy, energetic

#### Minimal Zen
- **Materials:** White surfaces, light wood, soft glass
- **Lighting:** Natural, soft, diffused
- **Camera:** Gentle, flowing movements
- **Typography:** Clean, minimalist
- **Sound:** Ambient nature, soft tones
- **Vibe:** Calm, peaceful

---

## 04 User Journey

### Salon Owner Journey

1. **Discovery** - Visits yosalon.app, sees hero experience
2. **Exploration** - Scrolls through features, sees benchmark room demo
3. **Selection** - Chooses experience family (Luxury Noir, Modern Glass, etc.)
4. **Onboarding** - Signs up, provides salon info and branding
5. **Customization** - AI generates initial website design
6. **Refinement** - Tweaks design in dashboard
7. **Launch** - Website goes live on custom domain
8. **Management** - Uses dashboard for daily operations

### Customer Journey

1. **Discovery** - Finds salon website via search/social
2. **Experience** - Immersive 3D entrance, explores services
3. **Booking** - Books appointment online
4. **Visit** - Arrives at salon, experience matches website
5. **Loyalty** - Returns, refers others

---

## 05 Dashboard: Digital Salon HQ

### Purpose
The dashboard is the command center where salon owners manage their business and customize their website experience.

### Core Modules

#### 1. Appointment Management
- Calendar view with drag-and-drop scheduling
- Real-time availability updates
- Automated reminders
- Conflict detection

#### 2. Customer Database (CRM)
- Customer profiles with booking history
- Service preferences tracking
- Notes and communication history
- Loyalty program management

#### 3. Staff Management
- Staff profiles and availability
- Schedule optimization
- Performance tracking
- Commission calculations

#### 4. Inventory Tracking
- Product inventory levels
- Usage analytics
- Reorder alerts
- Cost tracking

#### 5. Analytics & Reporting
- Revenue trends
- Customer retention metrics
- Service popularity
- Peak time analysis

#### 6. Website Customization
- Experience family selector
- Color palette adjustment
- Content editing
- 3D scene configuration

#### 7. Marketing Tools
- Email campaigns
- Social media integration
- Review management
- Promotional offers

### Design Principles

- **Function First:** Clean, data-focused interface
- **High Contrast:** Excellent readability
- **Efficient Workflows:** Minimal clicks to complete tasks
- **Real-time Updates:** Live data synchronization
- **Mobile Responsive:** Manage from anywhere

---

## 06 Technical Architecture

### Technology Stack

#### Frontend
- **Framework:** Next.js 16 with App Router
- **3D Engine:** React Three Fiber + Three.js
- **Animations:** Framer Motion
- **Styling:** Tailwind CSS v4
- **UI Components:** shadcn/ui + Radix UI

#### Backend
- **API:** Next.js API Routes
- **Database:** PostgreSQL (multi-tenant)
- **Authentication:** NextAuth.js
- **File Storage:** AWS S3 / Cloudflare R2
- **Real-time:** WebSocket / Server-Sent Events

#### AI/ML
- **Experience Generation:** OpenAI GPT-4
- **Image Generation:** DALL-E 3 / Stable Diffusion
- **3D Model Generation:** Custom pipeline
- **Brand Analysis:** Computer vision

### Architecture Patterns

#### Multi-Tenancy
- Shared database with tenant isolation
- Subdomain routing per salon
- Tenant-scoped data access
- Resource quotas per plan

#### Experience Engine
- Reusable 3D scene components
- Configurable experience families
- Material and lighting presets
- Animation physics library

#### Performance
- Edge caching with Cloudflare
- Image optimization
- 3D asset lazy loading
- Code splitting by route

---

## 07 Scaling Strategy

### Vertical Expansion (Industry Expansion)

After establishing Yo Salon as the reference implementation, expand to:

1. **Yo Restaurant** - Fine dining, casual dining, cafes
2. **Yo Clinic** - Medical clinics, dental practices, wellness centers
3. **Yo Fitness** - Gyms, studios, personal trainers
4. **Yo Retail** - Boutiques, showrooms, galleries

Each vertical reuses the EmKay Experience Core with industry-specific:
- Experience families
- 3D asset libraries
- Business logic
- Dashboard modules

### Horizontal Expansion (Platform Growth)

1. **Enterprise Partnerships** - White-label solutions for large salon chains
2. **Developer API** - Allow third-party developers to build on the platform
3. **Marketplace** - Community-created experience families and assets
4. **International** - Multi-language support, regional experience families

### Technical Scaling

- **Database:** Read replicas, connection pooling
- **CDN:** Global edge caching
- **3D Assets:** CDN-hosted with regional edge nodes
- **AI:** Rate limiting, queue-based processing
- **Infrastructure:** Auto-scaling on cloud platform

---

## 08 Experience Layer Redefinition

### Three Experience Layers

#### Layer 1: Marketing Experience
**Purpose:** Inspire and convert
**Audience:** Potential customers, business owners
**Characteristics:**
- Hero experiences with 3D elements
- Feature demonstrations
- Interactive demos
- Conversion-focused CTAs
**Examples:**
- yosalon.app hero
- EmKay Studios platform showcase
- Industry-specific landing pages

#### Layer 2: Customer Experience
**Purpose:** Engage and serve
**Audience:** End customers
**Characteristics:**
- Immersive 3D environments
- Service exploration
- Booking systems
- Brand consistency
**Examples:**
- Individual salon websites
- Restaurant menus
- Clinic appointment portals

#### Layer 3: Management Experience
**Purpose:** Organize and optimize
**Audience:** Business owners, staff
**Characteristics:**
- Clean, functional interface
- Data visualization
- Efficient workflows
- Real-time updates
**Examples:**
- Salon dashboard
- Restaurant POS
- Clinic management system

### EmKay Experience Core

The reusable engine that powers all three layers:

- **3D Scene System** - React Three Fiber components
- **Material Library** - PBR material presets
- **Animation Physics** - Spring configurations
- **Typography System** - Font pairings and scales
- **Motion Language** - Consistent animation patterns
- **Component Library** - Reusable UI components

### Design Bible Compliance

All experiences follow the Design Bible:

- **Spring Physics:** Consistent animation feel
- **Glass Materials:** Proper blur, opacity, reflections
- **Motion Language:** Predictable, delightful animations
- **Spacing System:** Consistent padding and margins
- **Color System:** Semantic color tokens

---

## Conclusion

The EmKay Studios platform is designed to scale from a single vertical (Yo Salon) to a multi-industry platform while maintaining quality and consistency through:

1. **Reusable Experience Core** - Shared engine across all products
2. **Experience Families** - Design languages that AI customizes
3. **Three Experience Layers** - Distinct experiences for different audiences
4. **Multi-tenant Architecture** - Efficient resource utilization
5. **AI-Powered Generation** - Unique experiences at scale

This architecture enables rapid expansion into new industries while ensuring every customer receives a premium, immersive digital experience.
```

### Onboarding Steps Detail

#### Step 1: Describe Your Salon
**Input:** Natural language description
**Example:** "Modern luxury salon in downtown, targeting young professionals, warm and welcoming atmosphere"
**AI Output:** Brand personality tags, mood keywords, suggested experience families

#### Step 2: Upload Logo
**Input:** Logo file (PNG, SVG, JPG)
**AI Output:** Brand color extraction, style analysis, typography suggestions

#### Step 3: Upload Interior Photos
**Input:** 3-5 photos of salon interior
**AI Output:** Material analysis, lighting conditions, color palette generation

#### Step 4: Select Experience Family
**Input:** User selects from 8 families (with AI recommendations)
**AI Output:** Base configuration loaded, ready for customization

#### Step 5: AI Generation
**Process:** 
- Extract colors from logo photos
- Analyze interior for material matching
- Generate custom color palette
- Select appropriate typography
- Configure lighting to match interior
- Choose soundtrack based on mood
- Generate copy based on description

**Output:** Fully customized salon experience

#### Step 6: Preview
**User Action:** Interactive preview of generated experience
**Options:** 
- Regenerate with different settings
- Manual adjustments
- Request AI refinement

#### Step 7: Publish
**User Action:** Confirm and publish
**System Actions:**
- Provision subdomain (e.g., elitecuts.yosalon.app)
- Configure custom domain (if provided)
- Deploy to CDN
- Enable analytics
- Send confirmation email

---

## 05 Dashboard: Digital Salon HQ

### Philosophy
Most SaaS dashboards are boring tables and charts. The EmKay Dashboard is an immersive management experience—the Digital Salon HQ.

When a salon owner logs in, they should feel like they're walking backstage at their salon.

### Dashboard Experience

#### Spatial Layout
```
┌─────────────────────────────────────────────────┐
│  Today's Bookings                    [Menu]     │
│  ┌─────────────┐  ┌─────────────┐              │
│  │ 10:00 AM    │  │ 11:30 AM    │              │
│  │ John Smith  │  │ Sarah Jones │              │
│  │ Haircut     │  │ Color       │              │
│  └─────────────┘  └─────────────┘              │
│                                                 │
│  Customers Waiting                              │
│  ┌─────────────────────────────────────────┐   │
│  │ Queue visualization (animated)         │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Revenue (Floating Hologram)                    │
│  ┌─────────────────────────────────────────┐   │
│  │ 3D chart with glass panels              │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Inventory (Shelves)                            │
│  ┌─────────────────────────────────────────┐   │
│  │ Product shelves with 3D items            │   │
│  └─────────────────────────────────────────┘   │
│                                                 │
│  Messages (Floating Notifications)               │
│  ┌─────────────────────────────────────────┐   │
│  │ Glass notification cards                │   │
│  └─────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

### Dashboard Modules

#### 1. Today's Bookings
**Visual:** Floating glass panels arranged in timeline
**Interaction:** 
- Click to expand booking details
- Drag to reschedule
- Hover to see customer info
- Spring animations on interactions

#### 2. Customers Waiting
**Visual:** Animated queue visualization
**Features:**
- Real-time status updates
- Estimated wait times
- Customer photos (if available)
- Service in progress indicator

#### 3. Revenue
**Visual:** Floating hologram 3D chart
**Features:**
- Daily/weekly/monthly views
- Glass panel overlays for details
- Interactive data points
- Comparison with previous periods

#### 4. Inventory
**Visual:** 3D shelves with product items
**Features:**
- Low stock indicators (glowing)
- Click to reorder
- Category filtering
- Usage analytics

#### 5. Messages
**Visual:** Floating notification cards
**Features:**
- Customer inquiries
- Review notifications
- System alerts
- Quick reply actions

#### 6. Team Management
**Visual:** Staff cards with availability
**Features:**
- Schedule view
- Performance metrics
- Time tracking
- Task assignments

### Dashboard Navigation

**Primary Navigation (Left Side):**
- Dashboard (Home)
- Calendar
- Customers
- Services
- Team
- Inventory
- Analytics
- Settings

**Quick Actions (Floating):**
- New Booking
- Add Customer
- Send Message
- Run Report

### Dashboard Performance

**Targets:**
- Load time: < 2s
- 60 FPS on desktop
- 30 FPS on mobile
- Real-time updates via WebSocket

**Optimizations:**
- Virtual scrolling for long lists
- Lazy load charts and 3D elements
- Debounce rapid updates
- Cache static data

---

## 06 Technical Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Client Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   EmKay     │  │  Yo Salon    │  │  Customer    │  │
│  │   Marketing │  │  Marketing   │  │  Instance    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    API Layer                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Next.js    │  │  Next.js     │  │  Next.js     │  │
│  │   API Routes │  │  API Routes  │  │  API Routes  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│              Business Logic Layer                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Auth       │  │  Content     │  │  Booking     │  │
│  │   Service    │  │  Service     │  │  Service     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Tenant     │  │  Analytics   │  │   AI         │  │
│  │   Service    │  │  Service     │  │  Service     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                   Experience Engine                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Scene      │  │  Animation   │  │   Asset      │  │
│  │   Manager    │  │   Engine     │  │   Manager    │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Audio      │  │   Input      │  │   Plugin     │  │
│  │   Manager    │  │   Manager    │  │   System     │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                    Data Layer                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ PostgreSQL   │  │   Redis      │  │   S3 / CDN   │  │
│  │   (Primary)  │  │   (Cache)    │  │   (Assets)   │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│                 Infrastructure                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   Vercel     │  │    AWS       │  │   Cloudflare │  │
│  │   (Hosting)  │  │   (Services) │  │   (CDN)      │  │
│  └──────────────┘└──────────────┘  └──────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Multi-Tenancy Strategy

**Tenant Isolation:**
- Database row-level security
- Separate schemas per tenant
- Isolated file storage paths
- Rate limiting per tenant

**Custom Domains:**
- CNAME configuration
- SSL certificate automation
- DNS management
- Domain verification

**Resource Quotas:**
- Bandwidth limits per plan
- Storage limits per plan
- API rate limits
- Concurrent user limits

### Experience Engine Architecture

**Core Modules:**

```typescript
// Experience Engine Interface
interface ExperienceEngine {
  // Scene Management
  sceneManager: SceneManager;
  
  // Animation
  animationEngine: AnimationEngine;
  
  // Audio
  audioManager: AudioManager;
  
  // Input
  inputManager: InputManager;
  
  // Assets
  assetManager: AssetManager;
  
  // Plugins
  pluginSystem: PluginSystem;
}

// Industry-Specific Plugins
interface SalonPlugin extends ExperiencePlugin {
  bookingSystem: BookingSystem;
  serviceMenu: ServiceMenu;
  stylistProfiles: StylistProfiles;
}

interface RestaurantPlugin extends ExperiencePlugin {
  menuSystem: MenuSystem;
  reservationSystem: ReservationSystem;
  tableManagement: TableManagement;
}
```

### AI Service Architecture

**AI Generation Pipeline:**

```
User Input (Logo, Photos, Description)
    ↓
Image Analysis (Computer Vision)
    ↓
Color Extraction (Color Quantization)
    ↓
Material Recognition (Texture Analysis)
    ↓
Brand Personality (NLP)
    ↓
Experience Family Selection (ML Model)
    ↓
Configuration Generation (Rule-Based)
    ↓
Content Generation (LLM)
    ↓
Experience Assembly (Experience Engine)
    ↓
Preview Generation
```

**AI Models:**
- Color extraction: Custom CNN
- Material recognition: ResNet-50 fine-tuned
- Brand personality: BERT-based classifier
- Experience recommendation: Random forest classifier
- Content generation: GPT-4 fine-tuned

---

## 07 Scaling Strategy

### Beyond Yo Salon

The platform architecture is designed to support unlimited industries:

```
EmKay Studios Platform
    ↓
┌──────────────┬──────────────┬──────────────┬──────────────┐
│   Yo Salon   │ Yo Restaurant│  Yo Clinic   │   Yo Gym    │
│              │              │              │              │
│  Salons &    │  Restaurants │  Healthcare  │   Fitness    │
│  Barbershops │  & Hospitality│   Clinics    │   Centers    │
└──────────────┴──────────────┴──────────────┴──────────────┘
    ↓              ↓              ↓              ↓
Experience Families per industry
```

### Industry-Specific Experience Families

Each industry has its own set of experience families:

**Yo Salon:**
- Modern Glass, Luxury Noir, Urban Pulse, Natural Calm, Minimal Studio, Neon Future, Executive, Classic Heritage

**Yo Restaurant:**
- Elegant Dining, Casual Bistro, Fast Casual, Fine Dining, Street Food, Cafe Culture, Rooftop Lounge, Wine Bar

**Yo Clinic:**
- Medical Professional, Wellness Center, Spa & Relaxation, Dental Care, Veterinary, Physical Therapy, Mental Health, Cosmetic Clinic

**Yo Gym:**
- High Energy, Performance Focus, Wellness & Yoga, CrossFit, Personal Training, Group Fitness, Luxury Club, Functional Training

### Shared Core, Unique Plugins

**Shared (Core Engine):**
- SceneManager
- AnimationEngine
- AudioManager
- InputManager
- AssetManager
- PluginSystem

**Unique (Industry Plugins):**
- SalonPlugin (booking, services, stylists)
- RestaurantPlugin (menu, reservations, tables)
- ClinicPlugin (appointments, medical records, patients)
- GymPlugin (classes, memberships, trainers)

### Scaling Roadmap

**Phase 1: Yo Salon (Current)**
- Complete Yo Salon SaaS
- 8 experience families
- AI generation for salons
- Dashboard for salon owners

**Phase 2: Yo Restaurant**
- Restaurant-specific experience families
- Menu management system
- Reservation system
- Table management
- Restaurant dashboard

**Phase 3: Yo Clinic**
- Clinic-specific experience families
- Appointment scheduling
- Patient management
- Medical records (HIPAA compliant)
- Clinic dashboard

**Phase 4: Yo Gym**
- Gym-specific experience families
- Class scheduling
- Membership management
- Trainer profiles
- Gym dashboard

**Phase 5: Platform Expansion**
- White-label solution
- API for third-party integrations
- Marketplace for experience families
- Developer platform
- Enterprise features

---

## 08 Experience Layer Redefinition

### Three Experience Layers

The Design Bible defines one immersive experience. The Platform Architecture defines three distinct experience layers:

#### 1. Marketing Experience
**Purpose:** Inspire and convert
**Audience:** Potential customers (B2B)
**Goals:**
- Capture attention
- Communicate value
- Drive sign-ups
- Build brand

**Characteristics:**
- Bold, impressive visuals
- Clear CTAs
- Feature demonstrations
- Social proof
- Pricing information

**Examples:**
- EmKay Studios marketing site
- Yo Salon marketing site
- Yo Restaurant marketing site

#### 2. Customer Experience
**Purpose:** Attract and serve clients
**Audience:** End customers (B2C)
**Goals:**
- Showcase services
- Enable bookings
- Provide information
- Build trust
- Encourage return visits

**Characteristics:**
- Brand-aligned design
- Service-focused content
- Easy booking flow
- Contact information
- Reviews and testimonials

**Examples:**
- Individual salon websites
- Individual restaurant websites
- Individual clinic websites

#### 3. Management Experience
**Purpose:** Help owners run their business
**Audience:** Business owners (B2B)
**Goals:**
- Manage bookings
- View analytics
- Communicate with customers
- Manage team
- Track inventory

**Characteristics:**
- Data-rich interfaces
- Efficient workflows
- Real-time updates
- Action-oriented
- Comprehensive tools

**Examples:**
- Salon owner dashboard
- Restaurant owner dashboard
- Clinic owner dashboard

### Layer-Specific Guidelines

#### Marketing Experience Guidelines
- **Hero:** Immersive, attention-grabbing
- **Animation:** Bold, energetic
- **CTA:** Prominent, clear
- **Content:** Benefit-focused
- **Performance:** Optimized for conversion

#### Customer Experience Guidelines
- **Hero:** Brand-aligned, welcoming
- **Animation:** Subtle, supportive
- **CTA:** Service-focused
- **Content:** Information-focused
- **Performance:** Optimized for usability

#### Management Experience Guidelines
- **Hero:** Functional, informative
- **Animation:** Minimal, purposeful
- **CTA:** Action-focused
- **Content:** Data-focused
- **Performance:** Optimized for efficiency

### Shared Principles Across Layers

All three layers follow EmKay Studios core principles:

1. **Space Over Pages:** Spatial design, not flat layouts
2. **Physics Over Transitions:** Spring physics, natural motion
3. **Atmosphere Over Information:** Mood and emotion alongside content
4. **Modular Over Monolithic:** Reusable components and patterns
5. **Performance Over Visuals:** 60 FPS non-negotiable

### Layer-Specific Deviations

Each layer adapts the principles to its goals:

**Marketing Experience:**
- More bold animations (to capture attention)
- Higher contrast (for readability on hero)
- More particles (for visual impact)

**Customer Experience:**
- Calmer animations (to not distract from services)
- Brand-specific colors (to match identity)
- Fewer particles (for faster load times)

**Management Experience:**
- Minimal animations (to prioritize data)
- Neutral colors (for data clarity)
- No particles (for maximum performance)

---

## Appendix

### Glossary

- **Experience Family:** A design language that AI customizes for each business (not a template)
- **Marketing Experience:** B2B experience that inspires and converts potential customers
- **Customer Experience:** B2C experience that serves end clients
- **Management Experience:** B2B experience that helps business owners manage operations
- **Digital Salon HQ:** The immersive dashboard for salon owners
- **Experience Engine:** Core technology powering all EmKay experiences
- **Plugin System:** Extensible architecture for industry-specific features

### Domain Strategy

**Primary Domains:**
- `emkaystudios.com` - EmKay Studios marketing
- `yosalon.app` - Yo Salon marketing
- `yorestaurant.app` - Yo Restaurant marketing (future)
- `yclinic.app` - Yo Clinic marketing (future)

**Customer Domains:**
- `subdomain.yosalon.app` - Default customer sites
- `customdomain.com` - Customer's custom domain

### Technical Stack

**Frontend:**
- Next.js 14 (App Router)
- React 18
- TypeScript
- Tailwind CSS
- Framer Motion
- Three.js / React Three Fiber

**Backend:**
- Next.js API Routes
- Prisma ORM
- PostgreSQL (Neon)
- Redis (Upstash)
- AWS S3 (assets)

**AI/ML:**
- OpenAI GPT-4 (content generation)
- Custom CNN (image analysis)
- TensorFlow.js (browser-based ML)

**Infrastructure:**
- Vercel (hosting)
- AWS (services)
- Cloudflare (CDN)
- Sentry (error tracking)

### Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | June 30, 2026 | Initial platform architecture |

---

**End of EmKay Studios Platform Architecture**

This document defines the multi-site architecture, experience families, and scaling strategy for the EmKay Studios platform. All development should align with this architecture.
