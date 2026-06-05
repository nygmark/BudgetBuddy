# BudgetBuddy

Plain functional frontend (no design) + PHP backend for expense tracking, savings goals, budget overview, history filters, and reminders.

## Features Implemented
- **Dashboard Overview**
  - Total expenses (current month)
  - Total savings (all-time)
  - Remaining budget
  - Pie chart (Food vs Transpo) using canvas
  - Progress bar for savings goal

- **Expense History**
  - Add expense (amount, food/transpo category, date, desc)
  - View past by Day / Week / Month (client-side filter)
  - Filter by category (food or transpo)
  - Table with delete

- **Notif/Reminders**
  - Log daily expense reminder (if none today)
  - Save money for goal reminder
  - Budget almost used alert (80%+)

- **Savings & Settings**
  - Add savings contributions
  - Update monthly budget & savings goal

- Auth: Login + Signup (fixed + secured)

## Setup (XAMPP / similar)

1. Put this folder in htdocs (e.g. `C:\xampp\htdocs\BudgetBuddy`)

2. Start Apache + MySQL in XAMPP control panel.

3. Import the database:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import `sql/schema.sql`  OR run it directly.
   - This creates `budget_buddy` DB + tables + sample user.

4. (Optional) Test hash: visit http://localhost/BudgetBuddy/test.php

5. Open: http://localhost/BudgetBuddy/auth/login.php
   - Demo login: `john@example.com` / `123456`

6. After login you land on `dashboard.php` which contains the full plain frontend.

## Files
- `auth/login.php` + `auth/signup.php` : fixed, use prepared statements, sessions, auto-login on signup
- `dashboard.php` : the main no-design UI + all logic (POST handlers + queries + embedded data)
- `config/db.php`
- `sql/schema.sql` : full DB + sample data

## Notes
- "Without any design" = plain HTML elements, tables, forms, minimal inline styles only for progress/pie to function.
- All data is per logged-in user (via session).
- To reset data, re-run the schema DROP/CREATE.
- For production: add CSRF, better validation, HTTPS, etc. (this is student/assignment level)

## CMD DATABASE
DROP DATABASE IF EXISTS budget_buddy;

CREATE DATABASE budget_buddy;

USE budget_buddy;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    monthly_budget DECIMAL(10,2) DEFAULT 5000.00,
    savings_goal DECIMAL(10,2) DEFAULT 10000.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    category ENUM('food','transpo') NOT NULL,
    description VARCHAR(255) DEFAULT '',
    expense_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    savings_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (
    first_name,
    last_name,
    email,
    password,
    monthly_budget,
    savings_goal
) VALUES (
    'John',
    'Doe',
    'john@example.com',
    '$2y$10$h75/nMds//4xwOc3WBh.Z.DNarU94HJSstOSHEmpQIp7EPxNLb3CS',
    8000.00,
    15000.00
);

INSERT INTO expenses (
    user_id,
    amount,
    category,
    description,
    expense_date
) VALUES
(1,250.50,'food','Lunch at canteen',DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1,120.00,'transpo','Jeepney fare',DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1,85.75,'food','Snacks',CURDATE()),
(1,45.00,'transpo','Tricycle',CURDATE()),
(1,320.00,'food','Groceries',DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(1,60.00,'transpo','Bus to work',DATE_SUB(CURDATE(), INTERVAL 3 DAY));

INSERT INTO savings (
    user_id,
    amount,
    note,
    savings_date
) VALUES
(1,500.00,'Weekly save',DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(1,300.00,'Extra from allowance',DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1,200.00,'',CURDATE());