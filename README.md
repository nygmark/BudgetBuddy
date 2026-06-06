# BudgetBuddy

All **frontend UI pages** (the ones the front-end person will work on) stored together in the **`frontend/`** folder.

These are **.php files** (HTML + PHP mixed, as the frontend guy is comfortable with PHP + HTML being in the same files for rendering, forms, etc.).

They have access to the backend via includes (`../includes/`, `../config/db.php`).

## Frontend Folder (what the front guy works in)
- `frontend/index.php` → entry (goes to login)
- `frontend/login.php`
- `frontend/signup.php`
- `frontend/dashboard.php` (Overview + summaries + budget status + goal progress + reminders)
- `frontend/expense-tracker.php` (Add expense: Amount, Category Food/Transpo, Date, Notes + Daily/Weekly/Monthly totals + filters for Day/Week/Month + Category + history table + delete)
- `frontend/budget-limits.php` (Set daily Food & Transpo limits + check any date + exceed ALERTs)
- `frontend/saving-goal.php` (Set goal name + target + optional deadline + progress bar + remaining + add savings + list)
- `frontend/nav.php` (shared plain nav for the folder)

## Backend (shared, outside the frontend folder)
- `config/db.php`
- `includes/` (auth_check, helpers for calculations, etc.)
- `sql/schema.sql`
- `api/` (optional pure JSON endpoints if needed later)
- Root `logout.php`


## How to run
- paste the code into cmd while xampp is running and put this folder in htdocs folder

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
    food_daily_limit DECIMAL(10,2) DEFAULT 150.00,
    transpo_daily_limit DECIMAL(10,2) DEFAULT 100.00,
    goal_name VARCHAR(100) DEFAULT 'My Savings Goal',
    goal_target DECIMAL(10,2) DEFAULT 10000.00,
    goal_deadline DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    category ENUM('food', 'transpo') NOT NULL,
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

SHOW TABLES;
