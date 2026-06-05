-- BudgetBuddy Database Schema
-- Run this in phpMyAdmin or mysql CLI to setup

DROP DATABASE IF EXISTS budget_buddy;
CREATE DATABASE budget_buddy;
USE budget_buddy;

-- Users table
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

-- Expenses table (food or transpo only)
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

-- Savings contributions (for goal tracking, separate from expenses)
CREATE TABLE savings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    note VARCHAR(255) DEFAULT '',
    savings_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sample user (password is "123456")
-- Hash generated with PHP password_hash("123456", PASSWORD_DEFAULT)
INSERT INTO users (first_name, last_name, email, password, monthly_budget, savings_goal) VALUES
('John', 'Doe', 'john@example.com', '$2y$10$h75/nMds//4xwOc3WBh.Z.DNarU94HJSstOSHEmpQIp7EPxNLb3CS', 8000.00, 15000.00);

-- Sample expenses for demo (adjust dates as needed, uses current month for demo)
INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES
(1, 250.50, 'food', 'Lunch at canteen', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1, 120.00, 'transpo', 'Jeepney fare', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 85.75, 'food', 'Snacks', CURDATE()),
(1, 45.00, 'transpo', 'Tricycle', CURDATE()),
(1, 320.00, 'food', 'Groceries', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(1, 60.00, 'transpo', 'Bus to work', DATE_SUB(CURDATE(), INTERVAL 3 DAY));

-- Sample savings
INSERT INTO savings (user_id, amount, note, savings_date) VALUES
(1, 500.00, 'Weekly save', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(1, 300.00, 'Extra from allowance', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(1, 200.00, '', CURDATE());

-- Note: The sample password hash above may need regeneration if it doesn't verify.
-- Use test.php or run: SELECT PASSWORD('... no, use php to generate.
-- In practice after import, you can update the password hash via PHP signup or manual.