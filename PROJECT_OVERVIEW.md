# TravGir — Project Overview

## What is this?

A **travel booking web platform** built with **Symfony 6.4** (PHP 8.1+). Users can browse and book trips (voyages), manage reservations, submit complaints, and get AI-powered travel assistance.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Symfony 6.4, PHP 8.1+ |
| ORM | Doctrine (MySQL) |
| Frontend | Twig templates |
| Auth | Symfony Security + Google OAuth |
| Payments | Stripe, Flouci |
| AI | NVIDIA AI, Claude API |
| SMS | Twilio |
| Email | Resend |
| Media | Cloudinary |
| Async | Symfony Messenger + Scheduler |

---

## Entities (Database Models)

| Entity | Purpose |
|---|---|
| `User` | Platform users (travelers, admins) |
| `Voyage` | A travel package (destination, price, dates) |
| `VoyageImage` | Photos attached to a voyage |
| `Reservation` | A user booking a voyage |
| `Offer` / `UserOffer` | Promotional offers for users |
| `OfferView` | Tracks which user saw which offer |
| `Reclamation` | User complaints/claims |
| `RefundRequest` | Refund requests linked to reservations |
| `Activity` | Activities available during a voyage |
| `Association` | Travel associations/organizations |
| `UserAssociation` | Users linked to associations |
| `Review` | User reviews on voyages |
| `SearchHistory` | Tracks user search queries |
| `VoyageVisit` | Tracks page visits on voyages |
| `UserDocument` | Documents uploaded by users |
| `UserLogin` | Login history |
| `LoyaltyPoints` | Points earned by users |
| `WaitlistEntry` | Users waiting for a full voyage |
| `Tag` / `Favorite` | Tagging and favoriting voyages |
| `Admin` | Admin accounts |

---

## Controllers (Routes / Pages)

| Controller | What it does |
|---|---|
| `AuthController` | Login, register, logout |
| `GoogleController` | Google OAuth login |
| `UserController` | User profile, settings |
| `VoyageController` | Browse, view, create, edit voyages |
| `ReservationController` | Book a voyage, view bookings, pay |
| `ReclamationController` | Submit and manage complaints |
| `OfferController` | Browse and apply offers |
| `AdminController` | Admin dashboard |
| `AdminRefundController` | Admins process refund requests |
| `StatisticsController` | Charts and stats for voyages |
| `AIAnalyticsController` | AI-powered analytics dashboard |
| `UserStatisticsController` | Per-user stats |
| `VoyageAnalyticsController` | Voyage-specific analytics |
| `VoyageComparisonController` | Compare two voyages side by side |
| `TripPlannerController` | AI-assisted trip planning |
| `BotController` | Chatbot interface |
| `FavoriteController` | Save/remove favorite voyages |
| `WaitlistController` | Join waitlist for a voyage |
| `EventController` | Events/activities listing |
| `ImageController` | Image upload handling |
| `ContactController` | Contact form |

---

## Services (Business Logic)

### Core
- `AuthService` — login/register logic
- `ReservationService` — booking rules, availability
- `VoyageService` / `VoyageImageService` — voyage CRUD + image upload to Cloudinary
- `OfferService` / `UserOfferService` — offer assignment and validation
- `ReclamationService` — complaint handling
- `ReviewService` — rating and review logic
- `RefundRequestService` / `StripeRefundService` — refund processing via Stripe

### Payments
- `StripePaymentService` — Stripe checkout sessions
- `FlouciPaymentService` — Flouci (local payment gateway)

### AI Features
- `AiVoyageService` — AI descriptions/recommendations for voyages
- `AiBudgetPlannerService` — AI-generated budget plans for trips
- `AiCancellationService` — AI help with cancellation decisions
- `AiResponseSuggestionService` — AI replies to user complaints
- `Analytics/NvidiaAIClient` — NVIDIA AI integration for analytics

### Pricing & Engagement
- `DynamicPricingService` — adjusts voyage prices dynamically
- `LoyaltyPointsService` — earn/redeem points
- `WaitlistService` — manage waitlists
- `CarbonFootprintService` — calculate trip carbon footprint
- `TagService` / `FavoriteService` — tags and favorites

### Communication
- `MailerService` — send emails via Resend
- `TwilioSmsService` — send SMS notifications
- `SearchHistoryService` — persist and retrieve search history
- `WeatherService` — fetch weather for a destination

### Analytics
- `Analytics/MetricsService` — aggregate platform metrics
- `VoyageVisitService` — track voyage page views
- `OfferViewService` — track offer impressions

---

## Async / Background Jobs

| Class | Trigger | What it does |
|---|---|---|
| `SendSmsMessage` | On reservation | Sends an SMS confirmation |
| `EscalateReclamationsMessage` | Scheduled | Auto-escalates old unresolved complaints |
| `ReclamationEscalationSchedule` | Cron | Schedules the escalation job |

---

## Key Features Summary

1. **Browse & Book Voyages** — search, filter, compare, book, and pay for trips
2. **Offers & Loyalty** — promotional offers, loyalty points, waitlists
3. **Complaints & Refunds** — submit complaints, request refunds, Stripe refund processing
4. **AI Tools** — budget planner, trip planner chatbot, smart recommendations, AI complaint replies
5. **Analytics** — visit tracking, offer views, user stats, AI-powered dashboards
6. **Admin Panel** — manage users, voyages, refunds, complaints
7. **Auth** — email/password + Google OAuth, login history, document upload
8. **Notifications** — email (Resend) + SMS (Twilio), async via Symfony Messenger
