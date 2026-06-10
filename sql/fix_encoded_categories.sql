-- BudgetBuddy — One-time migration to fix old category encoding bug.
  -- Run this ONCE in phpMyAdmin if you have existing expense data that was
  -- entered before the category bug was fixed.
  --
  -- Background: The old code stored categories like shopping/health/etc. as
  -- "food" in the category column with a "[label]" prefix in the description.
  -- This script detects and corrects those rows.
  --
  -- HOW TO RUN: phpMyAdmin → select budget_buddy → SQL tab → paste → Go

  USE budget_buddy;

  -- Fix shopping
  UPDATE expenses
  SET category = 'shopping', description = TRIM(REGEXP_REPLACE(description, '^\\[shopping\\]\\s*', ''))
  WHERE category = 'food' AND description REGEXP '^\\[shopping\\]';

  -- Fix health
  UPDATE expenses
  SET category = 'health', description = TRIM(REGEXP_REPLACE(description, '^\\[health\\]\\s*', ''))
  WHERE category = 'food' AND description REGEXP '^\\[health\\]';

  -- Fix entertainment
  UPDATE expenses
  SET category = 'entertainment', description = TRIM(REGEXP_REPLACE(description, '^\\[entertainment\\]\\s*', ''))
  WHERE category = 'food' AND description REGEXP '^\\[entertainment\\]';

  -- Fix utilities
  UPDATE expenses
  SET category = 'utilities', description = TRIM(REGEXP_REPLACE(description, '^\\[utilities\\]\\s*', ''))
  WHERE category = 'food' AND description REGEXP '^\\[utilities\\]';

  -- Fix education
  UPDATE expenses
  SET category = 'education', description = TRIM(REGEXP_REPLACE(description, '^\\[education\\]\\s*', ''))
  WHERE category = 'food' AND description REGEXP '^\\[education\\]';

  -- Fix others (keep the [label] prefix in description so the custom name is preserved)
  UPDATE expenses
  SET category = 'others'
  WHERE category = 'food' AND description REGEXP '^\\[';