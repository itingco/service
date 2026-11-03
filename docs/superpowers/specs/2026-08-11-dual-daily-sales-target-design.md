# Dual Daily Sales Target Design

## Goal
Clarify the sales dashboard by showing two different daily numbers side by side instead of using one ambiguous "Daily Sales Target" value.

## Definitions
- **Daily Sales Target / HK**: static daily target = Monthly Sales Target / Total HK.
- **Required Daily Sales**: dynamic catch-up requirement = max(0, Monthly Sales Target - MTD) / Remaining HK.

## UI
Place the two columns directly next to Monthly Sales Target in this order:
1. Monthly Sales Target
2. Daily Sales Target / HK
3. Required Daily Sales

The same terminology must be used in the web dashboard, PDF, Excel summary, and Sales Insights pace logic.

## Behavioral constraints
- Existing monthly target, MTD, growth, forecast, achievement, gap, and contribution formulas do not change.
- Sales Insights must continue to use the dynamic catch-up number for `required_daily_sales` and action warnings.
- A zero monthly target produces zero for both daily values.
- Total Daily Sales Target / HK is based on total target / Total HK (equivalent to summing row static daily targets).
- Total Required Daily Sales is the sum of each line's positive catch-up requirement, preserving the existing behavior where an over-achieving line does not offset another line's required catch-up.
