
# ⚡ Production Cron Job Setup

For your queue system to work automatically (sending emails in the background), you must set up a **Cron Job** on your production server.

## Step 1: Login to your Hosting Panel
Log in to your cPanel, Plesk, or SSH terminal.

## Step 2: Find "Cron Jobs"
Look for "Cron Jobs" or "Scheduler" in your dashboard.

## Step 3: Add New Cron Job
Add a new cron job that runs **Every Minute** (`* * * * *`).

**Command to run:**
```bash
/usr/local/bin/php /home/YOUR_USERNAME/public_html/backend/artisan schedule:run >> /dev/null 2>&1
```

### ⚠️ IMPORTANT: Replace Path ⚠️
You must replace `/home/YOUR_USERNAME/public_html/backend` with the **actual path** to your backend folder on the server.
You must also ensure `/usr/local/bin/php` is the correct path to PHP (sometimes it is just `php` or `/usr/bin/php`).

## How it works
1. This single Cron Job triggers Laravel's built-in scheduler every minute.
2. Laravel's scheduler (which I just updated in your code) sees the `queue:work` command.
3. It starts the worker, sends pending emails, and stops.
4. This ensures no duplicate emails and minimal server load.
