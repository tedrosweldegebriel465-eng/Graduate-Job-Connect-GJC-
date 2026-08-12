# Graduate Job Connect (GJC)

> **Connecting Graduates with Opportunities across Ethiopia**

Graduate Job Connect (GJC) is a modern full-stack graduate employment and recruitment platform designed to connect university graduates with employers and job opportunities across Ethiopia.

---

## 1. Project Overview

Graduate Job Connect bridges the gap between fresh university graduates entering the workforce and employers looking for top talent across Ethiopia. The platform provides a seamless digital ecosystem for job discovery, application tracking, employer vacancy management, and administrative platform governance.

---

## 2. Screenshots

All application interface screenshots are located in the `screenshots/` directory:

### Home Page
![Home Page](screenshots/Home%20Page.png)

### Authentication & Registration
* **Login**
  ![Login](screenshots/Login.png)
* **Register**
  ![Register](screenshots/Register.png)
* **Register Details**
  ![Register 1](screenshots/Register1.png)
* **Register Confirmation**
  ![Register 2](screenshots/Register2.png)

### Graduate Portal
![Graduate Portal](screenshots/Graduate.png)

### Employer Portal
![Employer Portal](screenshots/Employer.png)

### Administrator Portal
![Administrator Portal](screenshots/Admin.png)

---

## 3. Key Features

### Graduate
* **Job Discovery & Search:** Search vacancies with flexible keyword filters.
* **Multi-Criteria Filtering:** Filter jobs by category, location, job type, and work mode.
* **Diverse Vacancy Types:** Full-time, internship, and contract opportunities.
* **Flexible Work Modes:** Onsite, remote, and hybrid work modes.
* **Direct Job Applications:** Submit applications with cover letters and CV uploads.
* **Application Status Tracking:** Track application status real-time (`pending`, `shortlisted`, `accepted`, `rejected`).
* **Bookmarked Opportunities:** Save and manage target job vacancies.
* **Graduate Profile Management:** Academic information, CGPA, university, department, skills, and portfolio links.

### Employer
* **Vacancy Publishing:** Create and publish detailed job postings with salary ranges and application deadlines.
* **Vacancy Management:** Edit, update, and manage job listing statuses.
* **Applicant Review & Tracking:** View applicants, review candidate profiles, and access uploaded CVs.
* **Status Updates:** Manage applicant hiring pipeline (shortlist, accept, reject candidates).
* **Company Profile Management:** Manage company branding, industry, location, and corporate details.

### Administrator
* **Platform Analytics:** Real-time metrics monitoring users, vacancies, and application volume.
* **Account Moderation:** Review, approve, reject, or suspend user registrations.
* **Job Moderation:** Governance and moderation of posted job vacancies.
* **System Governance:** Platform-wide administrative control and user account lifecycle management.

---

## 4. Technology Stack

* **Frontend:** HTML5, Vanilla CSS3, JavaScript (ES6+), Fetch API
* **Backend:** Node.js, Express.js REST API
* **Database:** MongoDB, Mongoose
* **Authentication:** JWT (JSON Web Tokens), Bcrypt password hashing, 6-digit Email OTP Verification
* **Security:** Helmet, CORS, Express Rate Limiting, Input Validation, Role-Based Access Control (RBAC)

---

## 5. Authentication & Security

* **6-Digit Email OTP Verification:** Secure account verification via 6-digit OTP codes with expiration timers and attempt limits.
* **Password Hashing:** Passwords are encrypted using Bcrypt prior to storage.
* **JSON Web Tokens (JWT):** Stateless session management using signed JWTs.
* **Role-Based Access Control (RBAC):** Strict role isolation enforcing granular route access for Graduates, Employers, and Admins.
* **Security Headers & Rate Limiting:** Protected with Helmet security headers and Express rate limiting to prevent brute-force attacks.
* **Input Validation & Sanitization:** Defensive validation against malicious input payloads.

---

## 6. Project Structure

```text
Graduate-Job-Connect/
├── client/
│   ├── css/
│   ├── js/
│   ├── index.html
│   ├── login.html
│   ├── register.html
│   ├── verify-otp.html
│   ├── jobs.html
│   ├── admin/
│   ├── employer/
│   └── graduate/
├── server/
│   ├── config/
│   ├── controllers/
│   ├── middleware/
│   ├── models/
│   ├── routes/
│   ├── services/
│   ├── scripts/
│   ├── app.js
│   └── server.js
├── uploads/
├── screenshots/
├── .env.example
├── package.json
└── README.md
```

---

## 7. Installation & Setup

Follow these steps to set up and run the project locally:

### Prerequisites
1. **Node.js**: Install Node.js (v18+ recommended)
2. **MongoDB**: Install MongoDB Community Server or configure a MongoDB Atlas cluster URI.

### Steps
1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/Graduate-Job-Connect.git
   cd Graduate-Job-Connect
   ```

2. **Install dependencies:**
   ```bash
   npm install
   ```

3. **Configure environment variables:**
   Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
   Configure `.env` with your local or cloud credentials:
   ```env
   PORT=5000
   NODE_ENV=development
   MONGODB_URI=mongodb://localhost:27017/graduate_job_connect
   JWT_SECRET=your_secure_random_jwt_secret_key
   JWT_EXPIRES_IN=7d
   ```
   > ⚠️ **Security Warning:** Never commit `.env` or expose production secrets, database credentials, or secret keys in public repositories.

4. **Seed initial development data:**
   ```bash
   npm run seed
   ```

5. **Start the application:**
   ```bash
   npm run dev
   # or
   npm start
   ```

6. **Access the application:**
   Open your browser and navigate to `http://localhost:5000`.

---

## 8. Development Credentials

> ⚠️ **Note:** These credentials are for **local development and testing purposes only**. Do not use these credentials in a production environment.

| Role | Email | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@jobportal.com` | `password` |
| **Employer** | `employer@test.com` | `password` |
| **Graduate** | `graduate@test.com` | `password` |

---

## 9. REST API Overview

| Method | Endpoint | Description | Access |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/register` | Register a new user account | Public |
| `POST` | `/api/auth/login` | Authenticate user and receive JWT | Public |
| `POST` | `/api/auth/verify-otp` | Verify 6-digit email OTP code | Public |
| `GET` | `/api/auth/me` | Fetch logged-in user profile | Private |
| `GET` | `/api/jobs` | Browse active job listings | Public |
| `POST` | `/api/jobs` | Create new job vacancy | Employer |
| `POST` | `/api/applications` | Apply for a job | Graduate |
| `GET` | `/api/applications/employer` | View job applicants | Employer |
| `PUT` | `/api/applications/:id/status` | Update applicant status | Employer / Admin |
| `GET` | `/api/admin/stats` | Fetch platform statistics | Admin |
| `PUT` | `/api/admin/users/:id/status` | Approve, reject, or suspend user | Admin |

---

## 10. User Roles

* **Graduate (Job Seeker):** Searches and filters vacancies, manages academic profile/skills, submits applications, uploads CVs, and tracks status.
* **Employer (Recruiter):** Posts vacancies, specifies job criteria and salaries, reviews candidate profiles/CVs, and manages candidate pipelines.
* **Administrator:** Oversees user accounts, moderates job listings, manages user status approvals/suspensions, and monitors platform analytics.

---

## 11. Project Goal

The primary goal of Graduate Job Connect is to provide a modern, production-grade graduate recruitment ecosystem tailored for Ethiopia's growing university graduate workforce. It empowers job seekers with transparent opportunities while offering employers a streamlined candidate acquisition platform.

---

## 12. Future Improvements

* Automated AI resume parsing and skills matching scores.
* Integrated video interview scheduling and real-time candidate messaging.
* Native mobile applications (iOS / Android).
* Multi-language support (Amharic, Afaan Oromoo, Tigrinya, English).
* In-app push and email notifications for status updates.

---

## 13. License

This project is licensed under the [MIT License](LICENSE).
