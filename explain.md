# Nestoida Project Explanation (Interview Ready)

This doc explains the Nestoida project end‑to‑end so you can answer interview questions confidently: what it is, how it’s built, how data flows, and the key technical decisions.

---

## 1. What is Nestoida?
Nestoida is a rental discovery platform for **PGs, flats, hostels, and co‑living spaces in Noida**. It helps renters search and compare listings, and lets owners manage their properties. It includes a verification system, ratings, feedback, and admin moderation.

**Core value:** verified listings + fast discovery + direct owner contact.

---

## 2. Tech Stack (Why these choices)

**Backend:** PHP + MySQL (mysqli) on Apache
- Simple, fast to build, easy to deploy on shared hosting/EC2.
- MySQL suits relational listing data and reporting.

**Frontend:** Tailwind CSS (CDN) + custom CSS + vanilla JS
- Tailwind speeds UI iteration.
- Vanilla JS keeps it lightweight and reduces dependencies.

**Deployment:** AWS EC2 (Apache + MySQL on same server)
- Full control of stack and cost‑effective.

---

## 3. User Roles

1. **Viewer** – can search, view listings, save favorites, submit inquiries, leave ratings/comments.
2. **Owner** – can create/edit listings, upload multiple images, see analytics and feedback.
3. **Admin** – can approve/reject listings, manage users, verify owners, review reports.

---

## 4. Key Features (Frontend + Backend)

### Discovery & Search
- Full‑text search across **sector, price, amenities, address, description**.
- Live filters: price range, type, furnishing, min rating.
- Smooth UI with a sticky search bar on scroll.

### Listings
- Add/edit listings with multiple images, cover image selection, and image tags (bedroom/bathroom).
- Google Maps preview using coordinates or Google Maps URL.
- Related listings on the property detail page.

### Ratings & Comments
- Ratings + comments on listings with average rating calculation.
- Comments slider when many feedback entries exist.

### Owner Verification
- Admin can mark owners as verified.
- Verified badge appears on listings.
- Owners get notifications when verified.

### Notifications
- Owner notifications for ratings, inquiries, verification.
- Admin notifications for edits and important actions.

### Favorites & Reports
- Users can save favorites.
- Report system for issues, reviewed by admin.

### Security
- **CSRF protection** for all write actions.
- Session invalidation if role changes.
- Email verification before login.

---

## 5. Architecture (High Level)

```
Browser
  ↓
PHP pages (index.php, property.php, dashboards)
  ↓
MySQL database
```

- PHP pages render server‑side, with JS for interactivity.
- `db.php` centralizes DB connection + helper utilities.
- `reset-utils.php` handles reset/verification tokens + SMTP sending.

---

## 6. Main Pages and Responsibilities

- `index.php` → homepage listings + search + filters
- `property.php` → listing detail, gallery, reviews, inquiry, report
- `add-property.php` / `edit-property.php` → admin listing creation/edit
- `owner-edit-property.php` → owner listing edit
- `owner-dashboard.php` → owner’s listings, analytics, notifications
- `dashboard.php` → admin moderation + stats
- `manage-users.php` → admin role + verification management
- `manage-reports.php` → admin report review
- `user-login.php` / `user-register.php` → user auth
- `login.php` → admin auth
- `google-auth.php` / `google-callback.php` → Google OAuth

---

## 7. Database (Important Tables)

**Core tables:**
- `users` – user accounts, roles, verification
- `admin` – admin accounts
- `properties` – listings

**Feature tables:**
- `property_images` – multi‑image gallery
- `listing_feedback` – ratings + comments
- `property_inquiries` – inquiries sent to owners
- `user_favorites` – saved listings
- `listing_reports` – reports for moderation
- `notifications` / `admin_notifications` – alerts
- `property_change_log` – audit history
- `property_events` – analytics events

---

## 8. Security Practices

- CSRF tokens for all POST requests
- Session regeneration on login
- Email verification required before login
- Password reset tokens with expiration
- Admin actions logged

---

## 9. Deployment Summary (AWS EC2)

- Ubuntu + Apache + MySQL
- Repo cloned into `/var/www/html/nestoida`
- `.htaccess` contains env vars (SMTP, DB, Google OAuth)
- Use a domain (`nip.io`/real domain) for Google OAuth and HTTPS

---

## 10. Why this project is interview‑ready

- Full stack system with real‑world features
- Role‑based access control
- Moderation workflows
- Notifications + analytics
- Strong UI/UX polish with responsive design

---

## 11. Common Interview Questions & Answers

**Q: Why PHP + MySQL instead of a modern framework?**
A: The goal was fast delivery and easy deployment. PHP + MySQL is stable, lightweight, and works well for CRUD‑heavy applications with minimal infrastructure complexity.

**Q: How do you prevent fake submissions?**
A: Admin approval workflow + verification checks. I also added reporting and owner verification badges.

**Q: How do you secure forms?**
A: CSRF tokens on all POST actions, server‑side validation, session regeneration on login.

**Q: How is search implemented?**
A: MySQL queries with LIKE matching across multiple fields + client‑side filtering for price and rating.

**Q: How do you handle ratings and averages?**
A: Ratings stored in `listing_feedback`, and the homepage queries aggregate average and count per property.

**Q: What was the hardest part?**
A: Coordinating multiple workflows (admin approval, owner edits, verification, notifications) while keeping UI responsive and clean.

---

If you want, I can also prepare a **short verbal pitch** (30–45 seconds) or a **deeper system design explanation**.

---

## Backend & SQL Interview Q&A (Nestoida)

**Q: Why use MySQL for this project?**
A: MySQL fits relational data like users, listings, images, and reviews. It supports indexing, joins, and transactional integrity, which are essential for search, moderation, and analytics.

**Q: How did you model listings and images?**
A: `properties` stores core listing data; `property_images` stores multiple images per listing, with `is_cover`, `sort_order`, and `label` for UI control.

**Q: How are ratings calculated?**
A: Ratings are stored in `listing_feedback`. I compute average and count using a grouped subquery joined back to listings, which keeps query costs reasonable.

**Q: How did you prevent duplicate ratings by the same user?**
A: `property_ratings` uses a `(property_id, voter_hash)` unique key; I use `INSERT ... ON DUPLICATE KEY UPDATE`.

**Q: How is search implemented?**
A: Server‑side `LIKE` queries for multi‑field search, and client‑side filters for quick changes without reloading.

**Q: Why store verification tokens hashed?**
A: It’s safer — if the DB is leaked, tokens can’t be used directly.

**Q: How did you handle security for forms?**
A: CSRF tokens on all POST requests and session regeneration on login.

**Q: How do you handle email verification?**
A: `email_verifications` table stores hashed tokens with expiry. On verify, I mark the email and expire all other tokens.

**Q: What kind of indexes did you add?**
A: Indexes on `properties.status`, `listing_feedback.property_id`, `property_images.property_id`, and notification tables for fast dashboard queries.

**Q: How do you handle admin approvals?**
A: Listing status is stored in `properties.status`. Admin dashboard can approve/reject/update status.

**Q: How do you prevent unauthorized edits?**
A: Owner edits check ownership in SQL and role in session before applying updates.

---
