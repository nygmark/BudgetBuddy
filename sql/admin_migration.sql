-- BudgetBuddy Migration: Admin Panel + Budget Period feature
  --
  -- HOW TO RUN IN phpMyAdmin:
  --   1. Click your database name in the LEFT panel first (e.g. budget_buddy)
  --   2. Click the SQL tab
  --   3. Paste this file and click Go
  --
  -- Do NOT run this without selecting a database first!

  -- Admin panel table
  CREATE TABLE IF NOT EXISTS admins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );

  -- Budget period column (for the new single-budget + period selector)
  ALTER TABLE users
      ADD COLUMN IF NOT EXISTS budget_period ENUM('daily','weekly','monthly') DEFAULT 'monthly';

  -- Optional columns from earlier versions (safe to run again)
  ALTER TABLE users ADD COLUMN IF NOT EXISTS food_daily_limit    DECIMAL(10,2) DEFAULT 150.00;
  ALTER TABLE users ADD COLUMN IF NOT EXISTS transpo_daily_limit DECIMAL(10,2) DEFAULT 100.00;
  ALTER TABLE users ADD COLUMN IF NOT EXISTS goal_name           VARCHAR(100)  DEFAULT 'My Savings Goal';
  ALTER TABLE users ADD COLUMN IF NOT EXISTS goal_target         DECIMAL(10,2) DEFAULT 10000.00;
  ALTER TABLE users ADD COLUMN IF NOT EXISTS goal_deadline       DATE NULL;

  -- After running this, go to: /admin/setup.php to create your admin account.
  -- Delete setup.php from your server once the account is created!
  
  -- Expanded expense categories + custom label for "Others"
  ALTER TABLE expenses
      MODIFY COLUMN category ENUM('food','transpo','shopping','health','entertainment','utilities','education','others') NOT NULL;

  ALTER TABLE expenses
      ADD COLUMN IF NOT EXISTS custom_category VARCHAR(100) DEFAULT '';
  
  -- Expand expense category choices (run once on existing DB)
  ALTER TABLE expenses
      MODIFY COLUMN category ENUM('food','transpo','shopping','health','entertainment','utilities','education','others') NOT NULL;
  