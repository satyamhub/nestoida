# Nestoida

Nestoida is a modern full-stack property listing platform focused on Noida, India.

## Stack
- Frontend: React + Vite + Tailwind CSS
- Backend: Node.js + Express
- Database: MongoDB + Mongoose
- Auth: JWT-based authentication

## Project Structure

```bash
nestoida/
├── backend/
│   ├── src/
│   │   ├── config/
│   │   ├── controllers/
│   │   ├── middleware/
│   │   ├── models/
│   │   ├── routes/
│   │   ├── utils/
│   │   ├── validators/
│   │   ├── app.js
│   │   └── server.js
│   ├── .env.example
│   └── package.json
├── frontend/
│   ├── public/
│   ├── src/
│   │   ├── api/
│   │   ├── components/
│   │   ├── context/
│   │   ├── data/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── styles/
│   │   ├── App.jsx
│   │   └── main.jsx
│   ├── index.html
│   ├── tailwind.config.js
│   └── package.json
└── package.json
```

## Setup

1. Install dependencies:
   ```bash
   npm install
   npm run install:all
   ```
2. Configure environment:
   - Copy `backend/.env.example` to `backend/.env`
3. Run development servers:
   ```bash
   npm run dev
   ```

- Frontend: `http://localhost:5173`
- Backend API: `http://localhost:5000/api`

## Key Features Included
- Home page with hero search + featured listings
- Explore page with full filter sidebar and responsive grid
- Property detail page with gallery and owner contact section
- Post property page with image upload field + validation
- Signup/login with Owner/Tenant roles and JWT auth
- Dark mode support and modern Tailwind UI

## API Endpoints (Starter)
- `POST /api/auth/signup`
- `POST /api/auth/login`
- `GET /api/properties`
- `GET /api/properties/featured`
- `GET /api/properties/:id`
- `POST /api/properties` (auth required)

