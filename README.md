# 🌾 AgroConnect — Complete Setup Guide

**Farm-to-Market Digital Platform | Version 1.0**

---

## 📁 Project Structure

```
agroconnect/
├── frontend/
│   └── index.html          ← Complete SPA (Single Page Application)
├── backend/
│   └── api.php             ← REST API (PHP 8.1+)
├── database/
│   └── schema.sql          ← MySQL schema + sample data
└── README.md               ← This file
```

---

## ⚙️ Tech Stack

| Layer     | Technology                      |
|-----------|--------------------------------|
| Frontend  | HTML5, CSS3 (Flexbox/Grid), Vanilla JS |
| Backend   | PHP 8.1+, PDO                  |
| Database  | MySQL 8.0+                     |
| Fonts     | Google Fonts (Playfair Display, DM Sans) |
| Weather   | OpenWeatherMap API (ready to plug in) |
| Market    | Agmarknet / mock data          |

---

## 🚀 Quick Start

### Option A — Frontend Only (Demo Mode)
Open `frontend/index.html` directly in your browser.
All features work with mock data — no server needed!

**Demo Accounts:**
| Email              | Password | Role   |
|--------------------|----------|--------|
| farmer@demo.com    | demo123  | Farmer |
| buyer@demo.com     | demo123  | Buyer  |
| admin@demo.com     | demo123  | Admin  |

---

### Option B — Full Stack Setup

#### 1. Database Setup
```bash
# Login to MySQL
mysql -u root -p

# Run the schema
source /path/to/agroconnect/database/schema.sql

# Verify
USE agroconnect;
SHOW TABLES;
```

#### 2. Backend Configuration
Edit `backend/api.php` — update DB credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'agroconnect');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');
```

Generate real password hashes for demo users:
```php
<?php
echo password_hash('demo123', PASSWORD_BCRYPT, ['cost' => 12]);
```
Update the `INSERT INTO users` values in `schema.sql` with the real hashes.

#### 3. Web Server Setup

**Apache (XAMPP / LAMP):**
```
# Place project in: /var/www/html/agroconnect/
# Access at: http://localhost/agroconnect/frontend/index.html
# API at:    http://localhost/agroconnect/backend/api.php
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name agroconnect.local;
    root /var/www/agroconnect;

    location /api/ {
        try_files $uri $uri/ /backend/api.php?$query_string;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/backend/api.php;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /frontend/index.html;
    }
}
```

**PHP Built-in Server (Development):**
```bash
cd agroconnect
php -S localhost:8000 -t .
# Open: http://localhost:8000/frontend/index.html
```

#### 4. Connect Frontend to Backend
In `frontend/index.html`, update the API base URL at the top of the `<script>` section:
```javascript
const API_BASE = 'http://localhost:8000/backend/api.php';
```

Then replace mock functions with real API calls, e.g.:
```javascript
// Replace: PRODUCTS (mock array)
// With:
async function fetchProducts(filters = {}) {
  const params = new URLSearchParams(filters);
  const res = await fetch(`${API_BASE}/products?${params}`);
  const data = await res.json();
  return data.data;
}
```

---

## 🌦️ Weather API Integration

1. Get a free API key from [openweathermap.org](https://openweathermap.org/api)
2. In `frontend/index.html`, replace `WEATHER` mock with:

```javascript
const OWM_KEY = 'f6c9365d9371ada245453bb5d52fb27e';

async function fetchWeather(city = 'Nashik') {
  const url = `https://api.openweathermap.org/data/2.5/weather?q=${city},IN&appid=${OWM_KEY}&units=metric`;
  const res  = await fetch(url);
  const data = await res.json();
  return {
    temp: Math.round(data.main.temp),
    desc: data.weather[0].description,
    icon: owmIconToEmoji(data.weather[0].icon),
    humidity: data.main.humidity,
    wind: Math.round(data.wind.speed * 3.6),  // m/s → km/h
    pressure: data.main.pressure,
  };
}

function owmIconToEmoji(icon) {
  const map = { '01d':'☀️','01n':'🌙','02d':'🌤','02n':'🌤',
                '03d':'🌥','04d':'☁️','09d':'🌧','10d':'🌦',
                '11d':'⛈','13d':'❄️','50d':'🌫' };
  return map[icon] || '🌤';
}
```

---

## 📊 Market Rates API Integration

Connect to **Agmarknet** (data.gov.in) or use the mock data:

```javascript
// Government Open Data API
const AGMARK_URL = 'https://api.data.gov.in/resource/9ef84268-d588-465a-a308-a864a43d0070';
const AGMARK_KEY = 'YOUR_GOVDATA_API_KEY';

async function fetchMandiRates(commodity = 'Tomato', state = 'Maharashtra') {
  const url = `${AGMARK_URL}?api-key=${AGMARK_KEY}&format=json&filters[Commodity]=${commodity}&filters[State]=${state}`;
  const res  = await fetch(url);
  const data = await res.json();
  return data.records;
}
```

---

## 🔐 Security Checklist

- [x] Passwords hashed with `bcrypt` (cost 12)
- [x] All DB queries use prepared statements (no SQL injection)
- [x] Input sanitization via `sanitize()` helper
- [x] Role-based access control (farmer / buyer / admin)
- [x] Session-based authentication
- [x] CORS headers configured
- [ ] **TODO**: Add CSRF token for forms
- [ ] **TODO**: Rate limiting (use NGINX or PHP)
- [ ] **TODO**: HTTPS (use Let's Encrypt)
- [ ] **TODO**: File upload validation (MIME type check, size limit)
- [ ] **TODO**: Input validation with PHP `filter_var()`

---

## 🗃️ Database Tables Overview

| Table           | Purpose                              |
|-----------------|--------------------------------------|
| `users`         | All user accounts (farmers, buyers, admins) |
| `categories`    | Product & equipment categories       |
| `products`      | Produce listings (vegetables, fruits, etc.) |
| `equipment_ads` | Farm equipment classifieds (OLX-style) |
| `orders`        | Purchase orders                      |
| `messages`      | Buyer-seller chat messages           |
| `market_rates`  | Daily mandi / APMC prices           |
| `reviews`       | Seller ratings & comments            |
| `cart_items`    | Persistent shopping cart             |
| `notifications` | In-app notifications                 |
| `price_alerts`  | User-configured price alerts         |

---

## 🎨 Features Implemented

### ✅ Frontend (Fully Working)
- [x] Auth overlay — Login / Register with role selection
- [x] Sidebar navigation with role-based visibility
- [x] Dashboard with stats, weather widget, market rates, product grid
- [x] Marketplace with search, filter by category / location / price
- [x] Product detail modal with qty selector + Add to Cart
- [x] Equipment Exchange (OLX-style cards) with filter
- [x] Post Equipment Ad modal
- [x] Market Rates table with SVG trend chart
- [x] Weather forecast widget (mock, ready for API)
- [x] Buyer-Farmer chat with auto-reply simulation
- [x] Add Product form (farmer only)
- [x] My Listings page
- [x] Cart with quantity management and checkout
- [x] Admin panel (users / listings / orders tabs)
- [x] Profile page with tabs (listings / orders / settings)
- [x] Toast notifications
- [x] Responsive design (mobile sidebar toggle)
- [x] Global search

### ✅ Backend (PHP API)
- [x] POST /auth/register — Create account
- [x] POST /auth/login — Authenticate
- [x] GET /products — List with filters
- [x] POST /products — Create listing
- [x] GET/PUT/DELETE /products/:id
- [x] GET/POST /equipment — Equipment ads
- [x] GET/POST /orders — Place & track orders
- [x] PUT /orders/:id — Update order status
- [x] GET/POST /messages — Chat messages
- [x] GET /market-rates — Commodity prices
- [x] GET /admin/users — Admin user list
- [x] GET /admin/stats — Platform analytics

---

## 🚀 Deployment (Production)

### Shared Hosting (cPanel)
1. Upload `/frontend` → `public_html/`
2. Upload `/backend`  → `public_html/api/`
3. Upload `/database/schema.sql` → run via phpMyAdmin
4. Update `DB_PASS` in `api.php`

### VPS / Cloud (Ubuntu + Nginx)
```bash
# Install stack
sudo apt update && sudo apt install -y nginx php8.1-fpm php8.1-pdo php8.1-mysql mysql-server

# Clone / upload project
sudo cp -r agroconnect /var/www/

# Configure Nginx (see above)
sudo systemctl reload nginx

# SSL (Let's Encrypt)
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com
```

---

## 🔮 Future Improvements

### Phase 2 — AI & Analytics
- 🤖 **AI Price Prediction** — ML model trained on historical mandi rates to forecast crop prices 7-14 days ahead
- 📊 **Demand Forecasting** — Predict which crops will be in demand by season/region
- 🌱 **Crop Advisory AI** — GPT-powered chatbot for farming queries in local languages

### Phase 3 — Logistics
- 🚚 **Delivery System** — Integrate with Shiprocket / Delhivery APIs for logistics
- 📦 **Cold Chain Tracking** — IoT sensor data for perishable goods transport
- 🗺️ **Route Optimization** — Google Maps API for last-mile delivery

### Phase 4 — Finance
- 💳 **Payment Gateway** — Razorpay / PayU integration for online payments
- 🏦 **Agri Loans** — Partner with NBFCs to offer farmers quick credit
- 📑 **Digital Invoicing** — GST-compliant invoice generation (PDF)

### Phase 5 — Community
- 📱 **Mobile App** — React Native or Flutter app
- 🌍 **Multi-language** — Hindi, Marathi, Punjabi, Tamil support
- 👥 **Farmer Cooperatives** — Group selling & bulk order management
- 📡 **Offline Mode** — PWA with service workers for poor connectivity

---

## 📞 API Quick Reference

```
Base URL: http://yourdomain.com/backend/api.php

POST   /auth/register      Create account
POST   /auth/login         Login
POST   /auth/logout        Logout
GET    /auth/me            Current user

GET    /products           List products (?category=vegetable&state=Maharashtra)
POST   /products           Create product listing
GET    /products/:id       Get single product
PUT    /products/:id       Update product
DELETE /products/:id       Delete product

GET    /equipment          List equipment ads
POST   /equipment          Post equipment ad
GET    /equipment/:id      Get single ad

GET    /orders             My orders
POST   /orders             Place order
PUT    /orders/:id         Update order status

GET    /messages           My messages
POST   /messages           Send message

GET    /market-rates       Today's mandi rates (?state=Maharashtra&crop=Tomato)

GET    /admin/users        All users (admin only)
PUT    /admin/users/:id    Update user (admin only)
GET    /admin/stats        Platform stats (admin only)
```

---

*Built with ❤️ for Indian farmers | AgroConnect v1.0*
