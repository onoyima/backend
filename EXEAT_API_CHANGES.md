# Exeat Request API Changes

## Overview

This document outlines the changes made to the exeat request APIs to ensure consistency across different user roles (admin, dean, and staff).

## Changes Made

### 1. Route Standardization

All exeat request edit endpoints now follow a consistent pattern:

- Admin: `PUT /api/admin/exeat-requests/{id}`
- Dean: `PUT /api/dean/exeat-requests/{id}`
- Staff: `PUT /api/staff/exeat-requests/{id}`

Previously, some routes had inconsistent patterns like `/exeats/{id}/edit` or `/exeat-requests/{id}/edit`.

### 2. Controller Consistency

All controllers now implement the same edit method structure with consistent:
- Input validation
- Permission checking
- Debt recalculation for late returns
- Audit logging
- Response format

### 3. Response Format

All edit endpoints now return a consistent JSON response format:

```json
{
  "message": "Exeat request updated successfully.",
  "exeat_request": { /* exeat request object */ },
  "changes": { /* changes made to the request */ }
}
```

### 4. Added Staff Edit Functionality

The `StaffExeatRequestController` now includes an edit method that matches the functionality of the admin and dean controllers, allowing staff to edit exeat requests with proper permission checks.

## Testing

A test script (`test_exeat_endpoints.php`) has been created to verify the consistency of these endpoints. Run the script with a valid authentication token to test all three endpoints and compare their response structures.

## Usage

To edit an exeat request, send a PUT request to the appropriate endpoint with the fields you want to update:

```
PUT /api/admin/exeat-requests/{id}
PUT /api/dean/exeat-requests/{id}
PUT /api/staff/exeat-requests/{id}
```

With a request body like:

```json
{
  "category_id": 1,
  "reason": "Updated reason",
  "destination": "Updated destination",
  "departure_date": "2023-10-15",
  "return_date": "2023-10-20",
  "actual_return_date": "2023-10-21",
  "status": "completed",
  "is_medical": false,
  "comment": "Optional comment about the edit"
}
```

All fields are optional - only include the fields you want to update.