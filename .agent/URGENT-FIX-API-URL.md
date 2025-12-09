# URGENT FIX: Fast-Track API URL Issue

## Problem

The frontend is trying to access:
```
/staff/gate-events/undefined/staff/exeat-requests/fast-track/list
```

The word "undefined" means `process.env.NEXT_PUBLIC_API_BASE_URL` is not set!

## Solution

### Step 1: Edit the Frontend .env File

**File:** `backend/front/exeat_front/.env`

**Add or update this line:**

```env
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
```

**If your backend is on a different URL, use that instead:**

```env
# For production
NEXT_PUBLIC_API_BASE_URL=https://your-domain.com/api

# For local development with different port
NEXT_PUBLIC_API_BASE_URL=http://localhost:YOUR_PORT/api
```

### Step 2: Restart the Frontend Development Server

After editing `.env`, you MUST restart the Next.js server:

```bash
# Stop the current server (Ctrl+C)
# Then restart:
cd backend/front/exeat_front
npm run dev
```

**IMPORTANT:** Next.js only reads `.env` files on startup. Changes won't take effect until you restart!

### Step 3: Verify the Fix

1. Open browser console (F12)
2. Look for the Fast-Track search logs
3. Check the URL - it should now be:
   ```
   http://localhost:8000/api/staff/exeat-requests/fast-track/search?...
   ```
   (No more "undefined"!)

## How to Edit .env File

### Option 1: Using Command Line

```bash
cd backend/front/exeat_front
echo NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api >> .env
```

### Option 2: Using Text Editor

1. Open `backend/front/exeat_front/.env` in any text editor
2. Add the line:
   ```
   NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
   ```
3. Save the file
4. Restart the dev server

### Option 3: Create .env.local (Recommended for Development)

```bash
cd backend/front/exeat_front
echo NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api > .env.local
```

`.env.local` takes precedence over `.env` and is better for local development.

## Verification

After restarting, the console should show:

```
[Fast-Track] Search URL: http://localhost:8000/api/staff/exeat-requests/fast-track/search?search=1336&type=sign_out
```

NOT:

```
[Fast-Track] Search URL: undefined/staff/exeat-requests/fast-track/search?search=1336&type=sign_out
```

## Common Mistakes

1. ❌ Forgetting to restart the server after editing .env
2. ❌ Using wrong variable name (must be `NEXT_PUBLIC_API_BASE_URL`)
3. ❌ Missing `/api` at the end of the URL
4. ❌ Using `http://` when backend requires `https://`

## Current Status

- ❌ Frontend cannot reach backend (404 errors)
- ❌ `NEXT_PUBLIC_API_BASE_URL` is undefined
- ⏳ Waiting for .env file to be updated
- ⏳ Waiting for server restart

## Next Steps

1. ✅ Update `.env` file with correct API URL
2. ✅ Restart Next.js dev server
3. ✅ Test Fast-Track search again
4. ✅ Verify no more 404 errors
