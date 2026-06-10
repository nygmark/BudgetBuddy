-- ================================================================
  --  BUDGETBUDDY — ONE-TIME MIGRATION (run this in phpMyAdmin)
  -- ================================================================
  --  WHY: MySQL's category column only allows 'food' and 'transpo'
  --  by default. Run this ALTER TABLE once to unlock all categories.
  --  Without it, Shopping / Health / Entertainment / etc. are silently
  --  rejected and appear blank in the expense history table.
  -- ================================================================
  --  HOW: Open phpMyAdmin → select budget_buddy database → SQL tab
  --  → paste this → click Go.
  -- ================================================================

  USE budget_buddy;

  ALTER TABLE expenses
      MODIFY COLUMN category
      ENUM(
          'food',
          'transpo',
          'shopping',
          'health',
          'entertainment',
          'utilities',
          'education',
          'others'
      ) NOT NULL DEFAULT 'food';
  