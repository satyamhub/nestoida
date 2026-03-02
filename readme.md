# Nestoida

A rental listing platform for PGs, Flats & Hostels in Noida.

## Features
- Admin login system
- Property CRUD
- Image upload
- Approval workflow
- Search by sector

## Setup Instructions
1. Create MySQL database named `nestoida`
2. Import tables
3. Create `db.php` using db.example.php
4. Run on Apache (LAMP)

## Gmail SMTP Setup (Verification/Reset Emails)
1. Open your Gmail account settings for the Nestoida email.
2. Enable 2-Step Verification.
3. Create an App Password (Mail).
4. Update `.htaccess` values:
   - `SMTP_USER` = your Gmail address
   - `SMTP_PASS` = generated App Password
   - `SMTP_FROM_EMAIL` = same Gmail
   - `APP_URL` = your live/local app URL
5. Restart Apache after changing `.htaccess`.

If SMTP is misconfigured, the app falls back to PHP `mail()` and then to on-screen fallback links (where applicable).

## Local Access
Open the app at:
- `http://localhost/nestoida/`
