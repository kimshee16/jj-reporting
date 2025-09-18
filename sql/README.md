# SQL Database Files

This folder contains all the SQL files for the JJ Reporting Dashboard database schema.

## Files Overview

### Core Tables
- **`create_facebook_campaigns_table.sql`** - Creates the `facebook_ads_accounts_campaigns` table for storing campaign data locally
- **`database_update.sql`** - General database updates and modifications

### Reports & Analytics
- **`create_saved_reports_tables.sql`** - Creates tables for saved reports and scheduling functionality
- **`create_reports_tables.sql`** - Additional report-related tables

### Alerts & Notifications
- **`create_alert_rules_tables.sql`** - Creates alert rules and notification tables (with constraints)
- **`create_alert_rules_tables_simple.sql`** - Creates alert rules tables (simplified, no constraints)
- **`create_alerts_tables.sql`** - Alternative alerts table structure

### Export & Data Management
- **`create_export_tables.sql`** - Creates export jobs, templates, and history tables

## Installation Order

1. **Core Tables First:**
   ```sql
   source create_facebook_campaigns_table.sql
   ```

2. **Reports & Analytics:**
   ```sql
   source create_saved_reports_tables.sql
   source create_reports_tables.sql
   ```

3. **Alerts (choose one):**
   ```sql
   -- For simple setup (recommended):
   source create_alert_rules_tables_simple.sql
   
   -- OR for full constraints:
   source create_alert_rules_tables.sql
   ```

4. **Export System:**
   ```sql
   source create_export_tables.sql
   ```

5. **Any Updates:**
   ```sql
   source database_update.sql
   ```

## Notes

- All files use `CREATE TABLE IF NOT EXISTS` to prevent errors on re-run
- Constraint-free versions are provided for compatibility
- Tables are designed for MySQL/MariaDB
- Use `utf8mb4` character set for full Unicode support

## Quick Setup

To set up the entire database schema:

```bash
mysql -u root -p report-database < create_facebook_campaigns_table.sql
mysql -u root -p report-database < create_saved_reports_tables.sql
mysql -u root -p report-database < create_alert_rules_tables_simple.sql
mysql -u root -p report-database < create_export_tables.sql
```
