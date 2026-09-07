# Yo.Salon Monetization & Tier Strategy

This document outlines the revenue generation strategy for the Yo.Salon platform, specifically targeting scaling beyond Uganda. It breaks down how features already supported by the backend architecture should be segmented between "Basic" and "Pro" subscriptions, as well as avenues for transactional (non-subscription) revenue.

## 1. Subscription Tiers: Basic vs. Pro

To maximize adoption, the **Basic** tier must remain highly accessible (or even freemium for solo stylists), while the **Pro** tier ($30 - $50/month) unlocks advanced retention and marketing tools.

### Basic Tier (The Essentials)
- Core appointment booking (Calendar).
- Basic staff management.
- 1 or 2 standard website themes (e.g., "Minimal Studio").
- Simple revenue reporting.
- Standard payment processing (Flutterwave / MoMo integration).

### Pro Tier (The Ecosystem)
Based on existing backend architecture, the following should be restricted to Pro:
- **Yo.Salon Copilot (`CopilotController`)**: AI business insights, automated scheduling optimization, and AI-written service descriptions.
- **Premium Brand Experiences (`BrandExperienceController`)**: Immersive 3D, AI-generated website themes (e.g., "Luxury Noir", "Urban Pulse").
- **Advanced CRM & Timelines (`TimelineController`, `Customer_Grooming_Profiles`)**: Detailed customer history timelines, private specialist notes (e.g., "allergic to XYZ product"), and grooming profiles.
- **Loyalty & Memberships (`LoyaltyController`, `MembershipController`)**: Ability for the salon to run VIP points systems and sell monthly memberships to their customers.
- **Gift Cards & Packages (`GiftCardController`, `ServicePackageController`)**: Selling bundled services (e.g., "5 Haircuts for the price of 4") or digital gift cards.
- **Advanced Analytics (`AnalyticsController`)**: Trending charts, staff performance breakdowns, and revenue forecasting.

---

## 2. Transactional Revenue ("Extra Cash")

These are scalable revenue streams outside of standard monthly SaaS subscriptions. Subscriptions pay the overhead; these streams generate the profit.

### A. Platform Transaction Fees (The "Take Rate")
- **Concept:** Take a small margin (e.g., 2% + flat fee) on every customer payment processed through the platform (Card or Mobile Money) before settling the balance to the salon's wallet.
- **Backend Support:** Supported by `TransactionController`, `WalletController`, and `settlements` tables which manage the financial ledger.

### B. SMS & Notification Credits
- **Concept:** Automated SMS reminders are critical for salons to prevent no-shows. Do not include unlimited SMS in the subscription. Instead, sell them as "credits" in bundles (e.g., $10 for 500 SMS credits).
- **Backend Support:** Supported by the `notification_jobs` table. Can integrate with Twilio or Africa's Talking, deducting credits from the salon's internal `WalletController` balance on each send.

### C. The "Discover" Marketplace (Featured Listings)
- **Concept:** A consumer-facing directory (`yosalon.app/discover`) where users search for salons nearby. Salons can "bid" or pay a flat add-on fee to be a **"Featured Salon"** at the top of local search results.
- **Backend Support:** Supported by the `DiscoverController` and `SearchController`, which can prioritize boosted profiles.

### D. Specialist "Solo" Portals
- **Concept:** Treat specialists as independent micro-businesses. A barber working at a larger salon can pay a micro-subscription (e.g., $5/month) to have their own mini-booking page and brand presence on the platform, distinct from the main salon.
- **Backend Support:** Supported by `SpecialistDiscoveryController`, `professional_assessments`, and `CustomerSpecialistController`, which treat staff as independent professionals that customers can "follow".

## 3. Payment Architecture (Platform vs. Gateway)

A common misconception is that charging a transaction fee makes Yo.Salon a payment gateway or bank. **This is not true.**

Flutterwave acts as the payment gateway. They hold the legal licenses to process credit cards and mobile money and interact directly with banks/telecoms. Yo.Salon acts as a **Marketplace/Platform** sitting on top of Flutterwave.

There are two ways to handle the 2% take rate without taking on legal/banking compliance:

### A. The "Subaccount Split" Method
- Flutterwave allows you to create "Subaccounts" for every salon.
- When a customer pays 50,000 UGX, Flutterwave automatically splits the money at the moment of the transaction.
- 98% routes directly to the salon's Flutterwave subaccount; 2% routes to Yo.Salon's main account.
- **Benefit:** Yo.Salon never actually touches or holds the salon's money, minimizing legal compliance risk.

### B. The "Digital Wallet / Ledger" Method (Current Database Architecture)
- Looking at the `transactions`, `wallets`, and `settlements` tables, the system is currently built as a ledger.
- All customer funds go into Yo.Salon's main Flutterwave account.
- The backend database updates a virtual ledger: *"Salon X's digital wallet now has 49,000 UGX."*
- The salon owner clicks "Withdraw" in their dashboard.
- Yo.Salon's backend tells Flutterwave to perform a payout/settlement transfer to the salon's real bank/MoMo account.
- **Benefit:** This is how Uber, Airbnb, and Shopify operate. It gives Yo.Salon complete control over the payout schedule (e.g., weekly payouts) and refunds.

## Conclusion
By segmenting the platform this way, Yo.Salon can rapidly acquire users with a low-friction Basic tier while creating massive upside potential through transactional fees and high-value Pro features. Utilizing the Digital Wallet/Ledger architecture provides complete control over the flow of funds without needing banking licenses.
