# Fast-Track Debugging Guide

## Issue: "No eligible students found" even though student has correct status

### Step 1: Check Backend Logs

1. **Open Laravel logs:**
   ```
   backend/storage/logs/laravel.log
   ```

2. **Search for a student** in the Fast-Track interface

3. **Look for these log entries:**
   ```
   [YYYY-MM-DD HH:MM:SS] local.INFO: Fast-Track Search Request
   [YYYY-MM-DD HH:MM:SS] local.INFO: Fast-Track Search: Target status determined
   [YYYY-MM-DD HH:MM:SS] local.INFO: Fast-Track Search: Strict search completed
   ```

4. **Check the logged data:**
   - `search`: What you typed
   - `type`: 'sign_out' or 'sign_in'
   - `targetStatus`: Should be 'security_signout' or 'security_signin'
   - `results_count`: How many results found

### Step 2: Check Frontend Console

1. **Open browser Developer Tools** (F12)
2. **Go to Console tab**
3. **Search for a student**
4. **Look for these console logs:**
   ```
   [Fast-Track] Starting search: {query: "...", activeTab: "..."}
   [Fast-Track] Search URL: ...
   [Fast-Track] Response status: 200
   [Fast-Track] Response data: {...}
   [Fast-Track] Exeat requests count: 0
   ```

5. **Check the data:**
   - Is the URL correct?
   - Is the response status 200?
   - How many exeat_requests were returned?

### Step 3: Run Diagnostic Script

1. **Navigate to backend directory:**
   ```bash
   cd backend
   ```

2. **Run the diagnostic:**
   ```bash
   php fast-track-diagnostic.php
   ```

3. **Follow the prompts:**
   - It will show you how many students have `security_signout` status
   - It will show you sample records
   - You can test a search to see what's returned

### Step 4: Manual Database Check

1. **Open database client** (phpMyAdmin, TablePlus, etc.)

2. **Run this query to find students ready to sign out:**
   ```sql
   SELECT 
       er.id,
       er.status,
       er.matric_no as request_matric,
       s.fname,
       s.lname,
       s.matric_no as student_matric,
       er.updated_at
   FROM exeat_requests er
   LEFT JOIN students s ON er.student_id = s.id
   WHERE er.status = 'security_signout'
   ORDER BY er.updated_at DESC
   LIMIT 10;
   ```

3. **Check the results:**
   - Are there any records?
   - Do the student names match what you're searching for?
   - Is the `student_id` linking correctly?

### Step 5: Test Specific Student

If you know a specific student's matric number:

1. **Run this query:**
   ```sql
   SELECT 
       er.id,
       er.status,
       s.fname,
       s.lname,
       s.matric_no
   FROM exeat_requests er
   LEFT JOIN students s ON er.student_id = s.id
   WHERE s.matric_no = 'VUNA/22/1234'  -- Replace with actual matric
   ORDER BY er.updated_at DESC
   LIMIT 1;
   ```

2. **Check:**
   - What is the current status?
   - Is it `security_signout` or something else?

### Common Issues and Solutions

#### Issue 1: Status is not exactly 'security_signout'

**Symptoms:**
- Fallback search shows: `[STATUS: Security_Signout]` or `[STATUS: security_signout ]` (with space)

**Solution:**
The backend uses `LIKE` matching which should handle this, but if it's still failing:
```sql
UPDATE exeat_requests 
SET status = 'security_signout' 
WHERE status LIKE '%security_signout%' 
AND status != 'security_signout';
```

#### Issue 2: Student record is missing

**Symptoms:**
- Exeat request exists but `student_id` doesn't match any student
- Logs show "student_name: N/A"

**Solution:**
```sql
-- Find orphaned exeat requests
SELECT id, student_id, matric_no, status
FROM exeat_requests
WHERE student_id NOT IN (SELECT id FROM students);

-- If matric_no is populated, try to fix:
UPDATE exeat_requests er
SET student_id = (
    SELECT id FROM students s 
    WHERE s.matric_no = er.matric_no 
    LIMIT 1
)
WHERE er.student_id NOT IN (SELECT id FROM students)
AND er.matric_no IS NOT NULL;
```

#### Issue 3: Search term doesn't match

**Symptoms:**
- Student exists with correct status
- But search doesn't find them

**Test:**
Try searching with different variations:
- Full name: "John Doe"
- First name only: "John"
- Last name only: "Doe"
- Matric number: "VUNA/22/1234"
- Partial matric: "1234"

#### Issue 4: Frontend not sending request

**Symptoms:**
- No console logs appear
- No network request in Network tab

**Solution:**
1. Check if `NEXT_PUBLIC_API_BASE_URL` is set in `.env.local`
2. Verify you're typing at least 2 characters
3. Check if JavaScript errors are blocking execution

#### Issue 5: CORS or Authentication Error

**Symptoms:**
- Console shows: `[Fast-Track] Response status: 401` or `403`
- Or CORS error

**Solution:**
1. Check if token exists: `localStorage.getItem('token')`
2. Verify token is valid (not expired)
3. Check Laravel CORS configuration

### Step 6: Enable SQL Query Logging

To see the exact SQL being executed:

1. **Edit `StaffExeatRequestController.php`** (temporarily):
   ```php
   // Add before the query
   \DB::enableQueryLog();
   
   $results = ExeatRequest::with([...])->where(...)->get();
   
   // Add after the query
   $queries = \DB::getQueryLog();
   Log::info('Fast-Track SQL Queries', ['queries' => $queries]);
   ```

2. **Search again** and check logs for the actual SQL

### Step 7: Test with Postman/cURL

Test the API directly:

```bash
curl -X GET \
  'http://localhost:8000/api/staff/exeat-requests/fast-track/search?search=John&type=sign_out' \
  -H 'Authorization: Bearer YOUR_TOKEN_HERE' \
  -H 'Accept: application/json'
```

Replace:
- `localhost:8000` with your API URL
- `YOUR_TOKEN_HERE` with your actual token
- `John` with the search term
- `sign_out` with `sign_in` if testing sign-in

### Expected Response

**Success:**
```json
{
  "exeat_requests": [
    {
      "id": 123,
      "student": {
        "id": 55,
        "fname": "John",
        "lname": "Doe",
        "matric_no": "VUNA/22/1234"
      },
      "status": "security_signout",
      "action_type": "sign_out"
    }
  ]
}
```

**No results:**
```json
{
  "exeat_requests": []
}
```

**Debug mode (student found but wrong status):**
```json
{
  "exeat_requests": [
    {
      "id": 123,
      "student": {
        "id": 55,
        "fname": "John",
        "lname": "Doe",
        "matric_no": "VUNA/22/1234 [STATUS: dean_review]"
      },
      "status": "dean_review",
      "action_type": "sign_out"
    }
  ]
}
```

### Contact Points

If all else fails, provide these details:

1. **Backend logs** (last 50 lines from `storage/logs/laravel.log`)
2. **Frontend console logs** (screenshot or copy/paste)
3. **Database query results** (from Step 4)
4. **Specific student matric number** you're testing with
5. **Which tab** you're on (Sign Out or Sign In)

### Quick Checklist

- [ ] Backend logs show the search request
- [ ] Frontend console shows the search being triggered
- [ ] Database has students with `security_signout` status
- [ ] Student record exists and links correctly
- [ ] Search term matches student name/matric
- [ ] API returns 200 status code
- [ ] Response contains exeat_requests array
- [ ] Token is valid and not expired
- [ ] CORS is configured correctly
- [ ] No JavaScript errors in console
