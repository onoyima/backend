# Fast-Track Background Processing Setup

## What Changed

Fast-Track now uses **Laravel Queues** for instant response:

✅ **Button stops loading immediately** (< 100ms)  
✅ **Success message shows right away**  
✅ **Processing happens in background**  
✅ **All notifications still sent** (emails, SMS, etc.)  
✅ **All workflow logic preserved**  

---

## Setup Required

### Step 1: Configure Queue Driver

**Option A: Database Queue (Recommended for most)**

Edit `.env`:
```env
QUEUE_CONNECTION=database
```

Then create the jobs table:
```bash
php artisan queue:table
php artisan migrate
```

**Option B: Redis Queue (Faster, requires Redis)**

Edit `.env`:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Option C: Sync (For testing only - not async)**

Edit `.env`:
```env
QUEUE_CONNECTION=sync
```

---

### Step 2: Start Queue Worker

**For Development:**
```bash
php artisan queue:work --tries=3
```

**For Production (using Supervisor):**

Create `/etc/supervisor/conf.d/laravel-worker.conf`:
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## How It Works

### Before (Synchronous):
```
User clicks Execute
  ↓
Wait 5-10 seconds... ⏳
  ↓
Create approval
Send emails
Send SMS
Update status
  ↓
Show success ✅
```

### After (Asynchronous):
```
User clicks Execute
  ↓
Queue jobs (instant!) ⚡
  ↓
Show success immediately ✅

Meanwhile in background:
  ↓
Create approval
Send emails
Send SMS
Update status
```

---

## Testing

### 1. Start Queue Worker
```bash
php artisan queue:work
```

### 2. Test Fast-Track
1. Go to Fast-Track page
2. Add students to queue
3. Click Execute
4. **Button should stop loading immediately!** ⚡
5. Success message shows right away

### 3. Check Queue Worker Terminal
You should see:
```
[2025-12-09 14:15:00] Processing: App\Jobs\ProcessFastTrackAction
[2025-12-09 14:15:01] Processed:  App\Jobs\ProcessFastTrackAction
```

### 4. Check Logs
```bash
tail -f storage/logs/laravel.log
```

You should see:
```
[2025-12-09 14:15:01] Fast-Track Job: Successfully processed
```

---

## Production Deployment

### Option 1: Using Supervisor (Recommended)

1. Install Supervisor:
```bash
sudo apt-get install supervisor
```

2. Create config file (see Step 2 above)

3. Start workers:
```bash
sudo supervisorctl start laravel-worker:*
```

4. Check status:
```bash
sudo supervisorctl status
```

### Option 2: Using systemd

Create `/etc/systemd/system/laravel-worker.service`:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/project/artisan queue:work --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

Then:
```bash
sudo systemctl enable laravel-worker
sudo systemctl start laravel-worker
```

### Option 3: Using Cron (Simple but less reliable)

Add to crontab:
```bash
* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty
```

---

## Monitoring

### Check Queue Status
```bash
php artisan queue:monitor
```

### Check Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
php artisan queue:retry all
```

### Clear Failed Jobs
```bash
php artisan queue:flush
```

---

## Troubleshooting

### Jobs not processing?

**Check if queue worker is running:**
```bash
ps aux | grep "queue:work"
```

**Check queue connection:**
```bash
php artisan queue:work --once
```

**Check database:**
```sql
SELECT * FROM jobs;
```

### Jobs failing?

**Check failed jobs:**
```bash
php artisan queue:failed
```

**View error:**
```bash
php artisan queue:failed --id=1
```

**Retry:**
```bash
php artisan queue:retry 1
```

---

## Rollback (If Needed)

If you want to go back to synchronous processing:

1. Edit `.env`:
```env
QUEUE_CONNECTION=sync
```

2. Restart application

This will make it work like before (slower but no queue setup needed).

---

## Summary

**To enable instant Fast-Track:**

1. ✅ Set `QUEUE_CONNECTION=database` in `.env`
2. ✅ Run `php artisan queue:table && php artisan migrate`
3. ✅ Start queue worker: `php artisan queue:work`
4. ✅ Test Fast-Track - should be instant!
5. ✅ Setup Supervisor for production

**Benefits:**
- ⚡ Instant button response
- ✅ All notifications still sent
- ✅ All workflow preserved
- ✅ Better user experience

**No downsides!** Just need to run the queue worker.
