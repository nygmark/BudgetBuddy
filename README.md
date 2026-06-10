# BudgetBuddy 

A personal finance web application where users can track expenses, set budgets, manage savings goals, and monitor their financial health — with a full admin analytics panel.

**Tech Stack**
- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP
- **Database:** MySQL (via XAMPP)
- **Version Control:** Git / GitHub

---

## Requirements

- XAMPP (PHP 8.0+ & MySQL)
- A modern web browser (Chrome, Firefox, Edge)

---

## Installation & Setup

### 1. Clone the repository
```bash
git clone <your-repo-url>
```

### 2. Move to XAMPP's htdocs
Place the project folder inside XAMPP's `htdocs` directory.

### 3. Import the database
- Open phpMyAdmin at `http://localhost/phpmyadmin`
- Create a new database named `budget_buddy`
- Click **Import** and upload `budget_buddy.sql` from the `/sql` folder

### 4. Configure the database
Open `config/database.php` and ensure the credentials match:
```php
$conn = new mysqli('localhost', 'root', '', 'budget_buddy');
```

### 5. Start XAMPP
Start **Apache** and **MySQL** in the XAMPP Control Panel.

### 6. Open the app
```
http://localhost/BudgetBuddy/frontend/menu
```

---

## Default Accounts

| Name         | Email             | Role  |
|--------------|-------------------|-------|
| admin        | *(admin login)*   | Admin |


---

## Features

- **Expense Tracking** — Log daily expenses across multiple categories (Food, Transport, Shopping, Health, Entertainment, Utilities, Education, and more)
- **Spending Breakdown** — Interactive donut chart showing spending by category (Today / This Week / This Month views)
- **Budget Management** — Set monthly spending limits per category
- **Savings Goals** — Create and track progress toward personal savings targets with visual progress bars
- **Dashboard Summaries** — At-a-glance cards for Today's Spending, This Week, This Month, and Total Savings
- **Alerts & Notifications** — Smart reminders when savings goals are in progress
- **Dark Mode** — Toggle between light and dark themes
- **Admin Analytics Panel** — View platform-wide stats: total users, total expenses, total savings, goals reached, top spenders, expense trends, and monthly registrations
- **Admin User Management** — Browse all registered users with expense and savings details, searchable by name or email

---

## Security

- Passwords hashed using `password_hash()` / `password_verify()`
- SQL injection prevention via prepared statements
- Session-based authentication
- Admin routes protected from regular users

---

## Admin Panel

Access at:
```
http://localhost/BudgetBuddy/admin/login
```

Login as **admin** to:
- View platform-wide analytics (expenses, savings, user stats)
- Browse the full users detail report
- Monitor top spenders and monthly expense trends
- Track savings totals and goal completion rates

---

## Project Structure

```
BudgetBuddy/
├── admin/                  # Admin panel pages
├── api/                    # PHP API endpoints
├── auth/                   # Login / registration handlers
├── config/                 # Database connection config
├── frontend/               # User-facing pages (dashboard, expenses, budget, goals, profile)
├── img/                    # App images and assets
├── includes/               # Shared PHP includes (header, footer, etc.)
├── sql/                    # Database SQL files
├── budget_limit.php        # Budget limit handler
├── dashboard.php           # Main dashboard
├── expense_tracker.php     # Expense tracking page
├── index.php               # App entry point / login redirect
├── logout.php              # Logout handler
├── saving_goal.php         # Savings goal page
└── README.md               # This file
```

---

## Developers

- Built as a school project
- **[LIMA]**

---

## License

For educational purposes only.
