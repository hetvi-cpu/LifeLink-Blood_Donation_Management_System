<div align="center">

<img src="src/assets/logo.png" alt="LifeLink Logo" width="100" style="border-radius:16px"/>

<h1> LifeLink</h1>
<h3>Blood Donation Management System</h3>

<p>A real-time platform that connects blood donors with recipients — built for the Ahmedabad region.</p>

<br/>

[![React](https://img.shields.io/badge/React-18-61DAFB?style=for-the-badge&logo=react&logoColor=black)](https://reactjs.org/)
[![Vite](https://img.shields.io/badge/Vite-5-646CFF?style=for-the-badge&logo=vite&logoColor=white)](https://vitejs.dev/)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Firebase](https://img.shields.io/badge/Firebase-FCM-FFCA28?style=for-the-badge&logo=firebase&logoColor=black)](https://firebase.google.com/)
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Node.js](https://img.shields.io/badge/Node.js-18-339933?style=for-the-badge&logo=node.js&logoColor=white)](https://nodejs.org/)
[![Socket.io](https://img.shields.io/badge/Socket.io-4-010101?style=for-the-badge&logo=socket.io&logoColor=white)](https://socket.io/)

<br/>

[Features](#-features) · [Tech Stack](#-tech-stack) · [Getting Started](#-getting-started) · [Project Structure](#-project-structure) · [Screenshots](#-screenshots)

</div>

---

## 📖 About

**LifeLink** is a full-stack blood donation web application designed to save lives by making the donor-recipient connection fast, smart, and reliable. When someone urgently needs blood, LifeLink finds nearby compatible donors, sends real-time push notifications, and manages the entire donation workflow — from request to certificate.

Key highlights:
- 🔴 **Smart donor matching** based on blood group compatibility and pincode proximity
- 🔔 **Real-time notifications** via Firebase FCM + Socket.io
- 🏥 **Hospital directory** for all major Ahmedabad hospitals with blood stock levels
- 🚨 **Emergency alert system** for critical blood shortages
- 📊 **Full admin panel** with reports, audit logs, and campaign management

---

## ✨ Features

<details>
<summary><b>👤 For Users & Donors</b></summary>
<br/>

- Register as a blood donor with health eligibility screening (weight, medical history, cooldown period)
- Raise blood requests with blood group, urgency level, hospital, and contact details
- Get automatically matched with nearby compatible donors
- Accept or decline incoming donation requests
- Mark donations as complete and await admin certificate approval
- View full activity history and donation timeline
- Receive real-time push notifications for requests, alerts, and updates
- Register for upcoming blood donation campaigns
- Browse Ahmedabad hospital directory with blood stock availability
- View blood group compatibility chart

</details>

<details>
<summary><b>🛡️ For Admins</b></summary>
<br/>

- Live dashboard with real-time stats — active donors, pending requests, total donations
- Manage users (view, activate, suspend, delete)
- Manage donor registrations and eligibility
- Add, edit, and remove hospital records
- Create and manage blood donation campaigns (NGO / Hospital / Corporate)
- Create emergency alerts and notify matching donors instantly
- Approve completed donations and issue certificates
- Send custom push notifications to individual users or groups
- View and filter audit logs for all system activity
- Generate and download donation, user, campaign, and emergency reports

</details>

---

## 🛠️ Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| Frontend | React 18 + Vite | UI and routing |
| Styling | Tailwind CSS + Framer Motion | Design and animations |
| Auth | Firebase Authentication | User login and session |
| Push Notifications | Firebase Cloud Messaging | Real-time alerts to devices |
| Backend API | PHP 8.2 on Apache (XAMPP) | REST API endpoints |
| Realtime Bridge | Node.js + Express + Socket.io | Live notification delivery |
| Database | MySQL / MariaDB | Data persistence |
| HTTP Client | Axios | Frontend ↔ API communication |

---

## 📁 Project Structure

```
lifelink/
│
├── src/                              # React frontend (Vite)
│   ├── assets/                       # Images and static files
│   ├── components/                   # Reusable UI components
│   │   ├── Hero.jsx
│   │   ├── BloodCompatibility.jsx
│   │   ├── NotificationBell.jsx
│   │   └── ...
│   ├── pages/
│   │   ├── Home.jsx
│   │   ├── Login.jsx
│   │   ├── Registration.jsx
│   │   ├── DonorRegistration.jsx
│   │   ├── CreateRequest.jsx
│   │   ├── RequestMatching.jsx
│   │   ├── Campaigns.jsx
│   │   ├── HospitalInfo.jsx
│   │   ├── Admin/                    # Admin panel pages
│   │   │   ├── AdminDashboard.jsx
│   │   │   ├── ManageUsers.jsx
│   │   │   ├── ManageDonors.jsx
│   │   │   ├── ManageHospitals.jsx
│   │   │   ├── ManageCampaigns.jsx
│   │   │   ├── ManageEmergencyAlerts.jsx
│   │   │   ├── ManageNotifications.jsx
│   │   │   ├── ManageDonations.jsx
│   │   │   ├── ManageAuditLogs.jsx
│   │   │   └── Reports.jsx
│   │   └── User/
│   │       └── UserDashboard.jsx
│   ├── layout/
│   │   ├── DefaultLayout.jsx
│   │   ├── DashboardLayout.jsx
│   │   └── ProtectedRoute.jsx
│   ├── hooks/
│   │   └── useNotifications.js
│   ├── firebase.js                   # Firebase client config
│   └── App.jsx
│
├── lifelink-backend/                 # PHP REST API
│   ├── api/
│   │   ├── admin/                    # Admin-only endpoints
│   │   ├── users/                    # User endpoints
│   │   └── requests/                 # Donation request handling
│   ├── config/
│   │   └── db_config.php             # DB connection — reads from .env
│   ├── database/
│   │   └── schema.sql                # Full DB schema (no data)
│   └── notifications/
│       └── notification_bridge.php
│
└── server/                           # Node.js realtime server
    ├── index.js
    └── package.json
```

---

## 🗄️ Database Schema

17 tables covering the full donation lifecycle:

| Table | Description |
|---|---|
| `users` | All registered users with profile and donor status |
| `admin` | Admin accounts |
| `roles` | Role definitions (Admin / User) |
| `donors` | Donor health eligibility details |
| `blood_requests` | Blood requests raised by users |
| `donor_requests` | Individual donor responses to blood requests |
| `donations` | Completed donation records |
| `campaigns` | Blood donation campaigns |
| `campaign_registrations` | User registrations for campaigns |
| `emergency_alerts` | Admin-created emergency blood alerts |
| `notifications` | Push notification records |
| `hospital_details` | Hospital directory with blood stock |
| `health_records` | User health records |
| `receiving_requests` | Internal blood receiving requests |
| `activity_history` | Full user activity audit trail |
| `reports` | Generated admin reports |
| `feedback` | User feedback and ratings |

---

## 🚀 Getting Started

### Prerequisites

Before you begin, make sure you have:

- ✅ [XAMPP](https://www.apachefriends.org/) — Apache + MySQL + PHP 8.2+
- ✅ [Node.js](https://nodejs.org/) — v18 or higher
- ✅ A [Firebase](https://firebase.google.com/) project with **Authentication** and **Cloud Messaging (FCM)** enabled
- ✅ Firebase **service account key** (`serviceAccountKey.json`) downloaded from Firebase Console → Project Settings → Service Accounts

---

### Step 1 — Clone the Repository

```bash
git clone https://github.com/your-username/lifelink.git
cd lifelink
```

---

### Step 2 — Database Setup

1. Start **XAMPP** and go to `http://localhost/phpmyadmin`
2. Click **Import** and select:
   ```
   lifelink-backend/database/schema.sql
   ```
3. Click **Go** — the `lifelink_db` database and all 17 tables will be created automatically

---

### Step 3 — Backend Configuration

1. Copy `lifelink-backend/` into your XAMPP `htdocs` folder:
   ```
   C:/xampp/htdocs/lifelink-backend/
   ```

2. Create a `.env` file in the **project root**:
   ```env
   # Database
   DB_HOST=127.0.0.1
   DB_USER=root
   DB_PASS=
   DB_NAME=lifelink_db
   DB_PORT=3306

   # Shared secret between PHP and Node.js
   # Generate with: node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
   INTERNAL_SECRET=your_generated_secret_here
   ```

3. Place your `serviceAccountKey.json` inside `lifelink-backend/` and update its path in:
   ```
   lifelink-backend/notifications/notification_bridge.php
   ```

> API base URL: `http://localhost/lifelink-backend/api/`

---

### Step 4 — Notification Server

```bash
cd server
npm install
```

Create `server/.env`:
```env
PORT=3001
ALLOWED_ORIGINS=http://localhost:5173,http://localhost:5174
INTERNAL_SECRET=your_generated_secret_here
```

Place your `serviceAccountKey.json` inside the `server/` folder, then start the server:

```bash
node index.js
```

---

### Step 5 — Frontend

```bash
# From project root
npm install
```

Create a `.env` file in the **project root**:
```env
VITE_FIREBASE_API_KEY=your_api_key
VITE_FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your_project_id
VITE_FIREBASE_STORAGE_BUCKET=your_project.appspot.com
VITE_FIREBASE_MESSAGING_SENDER_ID=your_sender_id
VITE_FIREBASE_APP_ID=your_app_id
```

Start the dev server:
```bash
npm run dev
```

> App runs at `http://localhost:5173`

---

### ✅ All Services Running

| Service | URL |
|---|---|
| React Frontend | `http://localhost:5173` |
| PHP Backend API | `http://localhost/lifelink-backend/api/` |
| Node.js Notification Server | `http://localhost:3001` |
| phpMyAdmin | `http://localhost/phpmyadmin` |

---

## 🔐 Security Notes

> ⚠️ **Never commit sensitive files to version control.**

Make sure your `.gitignore` includes:

```gitignore
# Environment variables
.env
.env.*

# Firebase service account (contains private keys)
serviceAccountKey.json

# Dependencies
node_modules/
```

---

## 👥 User Roles

| Role | Access Level |
|---|---|
| **Admin** | Full access — manage users, donors, hospitals, campaigns, alerts, reports |
| **User / Donor** | Create requests, register as donor, track history, receive notifications |

---

## 📸 Screenshots

| Home | User Dashboard | Admin Panel |
|---|---|---|
| *Coming soon* | *Coming soon* | *Coming soon* |

---

## 🤝 Contributing

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a new branch: `git checkout -b feature/your-feature`
3. Commit your changes: `git commit -m 'Add your feature'`
4. Push to the branch: `git push origin feature/your-feature`
5. Open a Pull Request

---

## 📄 License

This project is developed for academic and educational purposes.

---

<div align="center">
Made with ❤️ for saving lives
</div>
