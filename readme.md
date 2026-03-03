# Nestoida

A rental listing platform for PGs, Flats, Hostels, and co-living spaces in Noida.

## Frontend Features
- Airbnb-inspired UI using Tailwind CSS with custom typography (Manrope + Space Grotesk).
- Responsive layouts with mobile-first sliders for listing cards and galleries.
- Dark/light mode with smooth theme transitions and persistent localStorage state.
- Sticky navbar with a persistent search bar on scroll.
- Live client-side filtering for price range, type, furnishing, and minimum rating.
- Full-text keyword search across sector, address, amenities, rent, and more.
- Listing cards with ratings, pricing, availability, and quick access actions.
- Owner profile chips with photo and verified badge on listing cards.
- Property details page with image gallery slider, labeled images, modal lightbox, related listings panel, ratings/comments slider, inquiry/favorites/report forms, and Google Maps embed with captions.
- Modern animated login/register UI with themed scene and day/night toggle.
- Nestoida SVG logo used in navbar and favicon.
- YouTube-style top loader for navigation and page transitions.
- Skeleton loaders for listing grids and dashboards.
- Back button helper and mobile bottom navigation.

## Backend Features
- Role-based authentication (admin, owner, viewer).
- Email verification with resend flow and rate limiting.
- Password reset for both admin and user accounts.
- Property CRUD with admin approval workflow and status filtering.
- Owner listing edits with full change log for admin/owner audit trail.
- Multi-image uploads with cover image, ordering, and per-image labels.
- Owner and admin profile management with profile photo uploads.
- Owner verification system with verified badge and notifications.
- Ratings and comments system with averaged ratings and counts.
- Favorites system per user with counts on listings.
- Inquiry system (guest or user) tied to owners and listings.
- Report system with admin review and resolution.
- Notifications for owners and admins (ratings, inquiries, verification, edits).
- Listing analytics: views, call clicks, favorites, ratings, and inquiries.
- Event tracking for listing views and phone clicks.
- CSRF protection across all write actions.
- Session invalidation when roles change.

## Screenshots
- Homepage listings: `docs/screenshots/home.png`
- Property details: `docs/screenshots/property.png`
- Owner dashboard: `docs/screenshots/owner-dashboard.png`
- Admin dashboard: `docs/screenshots/admin-dashboard.png`
- Login and register: `docs/screenshots/auth.png`

## Architecture
**Frontend**
- Server-rendered PHP pages styled with Tailwind CSS (CDN) and custom CSS in `assets/css/airbnb.css`.
- Vanilla JS enhancements: theme toggle, live filters, sliders, loaders, and navbar behaviors in `assets/js/`.
- Static assets stored in `assets/` and user uploads in `uploads/`.

**Backend**
- PHP + MySQL (mysqli) running on Apache (LAMP).
- Role-based sessions for admin/owner/viewer, with CSRF protection on all write actions.
- Email flows for verification and password resets via Gmail SMTP or PHP `mail()` fallback.
- Core modules:
  - Listings: `add-property.php`, `edit-property.php`, `owner-edit-property.php`, `property.php`
  - Admin: `dashboard.php`, `manage-users.php`, `manage-reports.php`
  - Owner: `owner-dashboard.php`, `owner-listings.php`, `owner-analytics.php`, `owner-profile.php`
  - Auth: `user-login.php`, `user-register.php`, `login.php`, `logout.php`

**Data Model (high level)**
- `users`, `admin`, `properties`, `property_images`, `listing_feedback`, `property_inquiries`, `listing_reports`
- `notifications`, `admin_notifications`, `property_change_log`, `property_events`, `user_favorites`

## Setup Instructions
1. Create MySQL database named `nestoida`.
2. Import tables or let `db.php` create them on first load.
3. Create `db.php` using `db.example.php` and set credentials.
4. Ensure `uploads/` and `uploads/profiles/` are writable.
5. Run on Apache (LAMP).

## Gmail SMTP Setup (Verification/Reset Emails)
1. Open your Gmail account settings for the Nestoida email.
2. Enable 2-Step Verification.
3. Create an App Password (Mail).
4. Update `.htaccess` values: `SMTP_USER` (your Gmail), `SMTP_PASS` (App Password), `SMTP_FROM_EMAIL` (same Gmail), `APP_URL` (live/local app URL).
5. Restart Apache after changing `.htaccess`.

If SMTP is misconfigured, the app falls back to PHP `mail()` and then to on-screen fallback links (where applicable).

## Google Login/Signup Setup
1. Create OAuth credentials in Google Cloud Console (OAuth Client ID for Web).
2. Add authorized redirect URI: `http://localhost/nestoida/google-callback.php` (or your live domain).
3. Update `.htaccess` values:
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI` (optional, defaults to `APP_URL + /google-callback.php`)
4. Restart Apache after changing `.htaccess`.

## Local Access
Open the app at:
- `http://localhost/nestoida/`
