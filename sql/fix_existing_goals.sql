-- Run this ONCE in phpMyAdmin to remove the pre-made "My Savings Goal"
  -- that was auto-inserted for accounts created before this update.
  -- This only clears the default goal; all savings history is kept.

  UPDATE users
  SET goal_name = NULL,
      goal_target = 0,
      goal_deadline = NULL
  WHERE goal_name = 'My Savings Goal'
    AND goal_target = 10000;
  