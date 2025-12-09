# Fast-Track Gate Control - User Guide

## Overview
The Fast-Track Gate Control is a specialized interface for Security, Admin, and Dean roles to quickly process student sign-outs and sign-ins at the gate.

## Access
**URL:** `/staff/gate-events/fast-track`

**Authorized Roles:**
- Security
- Admin
- Dean
- Deputy Dean

## Features

### 1. **Dual-Tab Interface**
- **SIGN OUT Tab (Red):** Process students leaving campus
- **SIGN IN Tab (Green):** Process students returning to campus

**Safety Feature:** Switching tabs clears your queue to prevent accidental mixed actions.

### 2. **Search/Scan Interface**
Search for students using any of the following:
- **Student First Name** (e.g., "John")
- **Student Last Name** (e.g., "Doe")
- **Student Middle Name**
- **Student Matric Number** (e.g., "VUNA/22/1234")
- **Student Database ID** (numeric)
- **Exeat Request ID** (numeric)

**Features:**
- Auto-focused input field
- Debounced search (300ms delay)
- Press Enter to add first result to queue
- Click any result to add to queue

### 3. **Debug Mode (Automatic)**
If a student doesn't appear in search results with the correct status, the system automatically shows them with their actual status appended:

**Example:**
```
John Doe
VUNA/22/001 [STATUS: dean_review]
```

This tells you the student is stuck at the Dean's office and not ready for security processing.

### 4. **Action Queue**
- Add multiple students to a staging queue
- Review the list before processing
- "Clear All" button to empty queue
- "Execute" button to process all at once

**Queue Limit:** 10 students maximum per batch

### 5. **Eligible Students List**
A paginated list (10 per page) showing all students ready for the current action.

**Features:**
- Date filtering (by departure_date for sign-out, return_date for sign-in)
- "Add to Queue" button for each student
- Auto-refreshes after successful processing
- Pagination controls

## Backend API Endpoints

### Search Endpoint
```
GET /api/staff/exeat-requests/fast-track/search
Parameters:
  - search: string (name, matric, or ID)
  - type: 'sign_out' | 'sign_in'
```

### List Endpoint
```
GET /api/staff/exeat-requests/fast-track/list
Parameters:
  - type: 'sign_out' | 'sign_in'
  - page: number (default: 1)
  - date: YYYY-MM-DD (optional)
```

### Execute Endpoint
```
POST /api/staff/exeat-requests/fast-track/execute
Body:
  {
    "request_ids": [1, 2, 3, ...]
  }
```

## Search Logic

### Strict Search (Primary)
1. Searches for students with correct status:
   - Sign Out: `security_signout`
   - Sign In: `security_signin`
2. Matches against all name fields and matric number
3. Returns up to 50 results

### Fallback Search (Debug Mode)
If strict search returns nothing:
1. Searches without status filter
2. Shows students with their actual status
3. Returns up to 10 results

## Status Flow Reference

**For Sign Out:**
- Student must be at status: `security_signout`
- After processing: status becomes `security_signin`

**For Sign In:**
- Student must be at status: `security_signin`
- After processing: status becomes `hostel_signin` or `completed`

## Troubleshooting

### "No eligible students found"
**Possible causes:**
1. Student name/matric is misspelled
2. Student's exeat request is at a different stage (check debug mode output)
3. Student doesn't have an active exeat request

### Student shows with "[STATUS: xxx]"
This means the student exists but is not at the correct stage:
- `dean_review`: Waiting for Dean approval
- `hostel_signout`: Waiting for Hostel sign-out
- `parent_consent`: Waiting for parent consent
- etc.

### Search not working
1. Ensure you're typing at least 2 characters
2. Check network tab for API errors
3. Verify you have the correct role permissions

## Best Practices

1. **Use the Queue System:** Add multiple students before executing to save time
2. **Double-Check Tab:** Always verify you're on the correct tab (Sign Out vs Sign In)
3. **Review Before Execute:** Check the queue list before clicking Execute
4. **Use Date Filter:** Filter the eligible list by date to find students leaving/returning today
5. **Clear Queue on Tab Switch:** The system does this automatically for safety

## Technical Notes

- Frontend: Next.js (React) with TypeScript
- Backend: Laravel (PHP)
- Search: Case-insensitive, partial matching
- Pagination: 10 items per page for eligible list
- Queue Limit: 10 students per batch
- Debounce: 300ms delay on search input
