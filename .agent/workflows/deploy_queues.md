---
description: Deploying Fast-Track Queue System to Production
---

Follow these steps to safely deploy the new Queue-based notification system to your production server.

### 1. Deploy Code Changes
Ensure the following new and modified files are uploaded to your production server:
- `database/migrations/2025_12_10_132440_create_jobs_table.php` (New)
- `app/Services/NotificationDeliveryService.php` (Modified)
- `app/Services/ExeatNotificationService.php` (Modified)
- `app/Http/Controllers/StaffExeatRequestController.php` (If you have local changes)

### 2. Update Environment Configuration
On your production server, open the `.env` file and update the queue connection:

```ini
QUEUE_CONNECTION=database
```

After updating the `.env` file, clear the configuration cache to apply changes:
```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Run Database Migration
Run the specific migration to create the `jobs` table. This is safe and will not affect other tables.

```bash
php artisan migrate --force
```
*Note: If you only want to run this specific migration, use:*
```bash
php artisan migrate --path=database/migrations/2025_12_10_132440_create_jobs_table.php --force
```

### 4. Start the Queue Worker
You need a process to process the queued emails.

**Option A: Using Supervisor (Recommended for Linux/Production)**
If you are using Supervisor (standard for Laravel), create a configuration file at `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work database --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/worker.log
```
Then run:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

**Option B: Temporary Testing (Terminal)**
To test immediately without Supervisor, keep a terminal window open running:
```bash
php artisan queue:work
```
*Note: If you close this window, emails will stop sending.*

### 5. Verify Notifcations
1. Go to the Fast-Track page.
2. Click "Execute" on a student.
3. The loading spinner should finish almost immediately.
4. Check that the email/SMS arrives within 1-5 minutes (depending on your queue worker).
