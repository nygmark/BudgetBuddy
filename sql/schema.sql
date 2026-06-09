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
      monthly_budget DECIMAL(10,2) DEFAULT 5000.00,   -- used as general budget amount
      savings_goal DECIMAL(10,2) DEFAULT 10000.00,
      budget_period ENUM('daily','weekly','monthly') DEFAULT 'monthly',
      -- kept for backward compatibility (used by dashboard alerts)
      food_daily_limit DECIMAL(10,2) DEFAULT 150.00,
      transpo_daily_limit DECIMAL(10,2) DEFAULT 100.00,
      goal_name VARCHAR(100) DEFAULT 'My Savings Goal',
      goal_target DECIMAL(10,2) DEFAULT 10000.00,
      goal_deadline DATE NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );

  -- Expenses table
  CREATE TABLE expenses (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      category ENUM('food','transpo','shopping','health','entertainment','utilities','education','others') NOT NULL,
      description VARCHAR(255) DEFAULT '',
      expense_date DATE NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );

  -- Savings contributions
  CREATE TABLE savings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      note VARCHAR(255) DEFAULT '',
      savings_date DATE NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  );

  -- Admin panel (separate from users)
  CREATE TABLE admins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
  -- To create your admin account: go to /admin/setup.php then delete it after.

  -- Sample user (password: "123456")
  INSERT INTO users (first_name, last_name, email, password, monthly_budget, savings_goal,
                     budget_period, food_daily_limit, transpo_daily_limit,
                     goal_name, goal_target, goal_deadline)
  VALUES ('John', 'Doe', 'john@example.com', '$2y$10$h75/nMds//4xwOc3WBh.Z.DNarU94HJSstOSHEmpQIp7EPxNLb3CS',
          8000.00, 15000.00, 'monthly',
          200.00, 150.00,
          'New Phone', 10000.00, DATE_ADD(CURDATE(), INTERVAL 60 DAY));

  -- Sample expenses
  INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES
  (1, 85.75,  'food',   'Snacks',              CURDATE()),
  (1, 45.00,  'transpo','Tricycle to school',  CURDATE()),
  (1, 120.00, 'transpo','Jeepney fare',        DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
  (1, 250.50, 'food',   'Lunch at canteen',    DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
  (1, 60.00,  'transpo','Bus to work',         DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
  (1, 320.00, 'food',   'Groceries',           DATE_SUB(CURDATE(), INTERVAL 5 DAY));

  -- Sample savings
  INSERT INTO savings (user_id, amount, note, savings_date) VALUES
  (1, 500.00, 'Weekly save',          DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
  (1, 300.00, 'Extra from allowance', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
  (1, 200.00, 'Sold old item',        CURDATE());

  -- ============================================
  -- For EXISTING databases (run these if you don't want to DROP):
  -- ALTER TABLE users ADD COLUMN food_daily_limit DECIMAL(10,2) DEFAULT 150.00;
  -- ALTER TABLE users ADD COLUMN transpo_daily_limit DECIMAL(10,2) DEFAULT 100.00;
  -- ALTER TABLE users ADD COLUMN goal_name VARCHAR(100) DEFAULT 'My Savings Goal';
  -- ALTER TABLE users ADD COLUMN goal_target DECIMAL(10,2) DEFAULT 10000.00;
  -- ALTER TABLE users ADD COLUMN goal_deadline DATE NULL;
  -- For budget period feature: run sql/admin_migration.sql
  -- ============================================
  