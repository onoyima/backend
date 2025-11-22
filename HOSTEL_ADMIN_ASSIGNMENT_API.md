# Hostel Admin Assignment API Documentation

## Overview

This system allows Dean and Admin users to assign hostels to staff members and automatically assign hostel admin roles. Hostel admins can only see exeat requests from students in their assigned hostels.

## Authentication

All endpoints require authentication:

```
Authorization: Bearer {token}
Content-Type: application/json
```

## Admin/Dean Endpoints

### 1. Get Assignment Options

**GET** `/api/admin/hostel-assignments/options`
**GET** `/api/dean/hostel-assignments/options`

Returns available hostels and staff for assignment with pagination.

**Query Parameters:**

-   `per_page` (optional): Number of items per page (default: 15)
-   `page` (optional): Page number (default: 1)

**Response:**

```json
{
    "status": "success",
    "data": {
        "hostels": {
            "current_page": 1,
            "data": [
                {
                    "id": 1,
                    "name": "CICL",
                    "gender": "mixed"
                },
                {
                    "id": 2,
                    "name": "BLOCK A",
                    "gender": "male"
                }
            ],
            "first_page_url": "http://localhost:8000/api/admin/hostel-assignments/options?page=1",
            "from": 1,
            "last_page": 2,
            "last_page_url": "http://localhost:8000/api/admin/hostel-assignments/options?page=2",
            "links": [...],
            "next_page_url": "http://localhost:8000/api/admin/hostel-assignments/options?page=2",
            "path": "http://localhost:8000/api/admin/hostel-assignments/options",
            "per_page": 15,
            "prev_page_url": null,
            "to": 15,
            "total": 25
        },
        "staff": {
            "current_page": 1,
            "data": [
                {
                    "id": 101,
                    "fname": "John",
                    "lname": "Doe",
                    "email": "john.doe@veritas.edu.ng"
                }
            ],
            "first_page_url": "http://localhost:8000/api/admin/hostel-assignments/options?page=1",
            "from": 1,
            "last_page": 1,
            "last_page_url": "http://localhost:8000/api/admin/hostel-assignments/options?page=1",
            "links": [...],
            "next_page_url": null,
            "path": "http://localhost:8000/api/admin/hostel-assignments/options",
            "per_page": 15,
            "prev_page_url": null,
            "to": 5,
            "total": 5
        }
    }
}
```

### 2. Create Hostel Assignment

**POST** `/api/admin/hostel-assignments`
**POST** `/api/dean/hostel-assignments`

**Payload:**

```json
{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Optional assignment notes"
}
```

**Validation Rules:**

-   `vuna_accomodation_id`: required|exists:vuna_accomodations,id
-   `staff_id`: required|exists:staff,id
-   `auto_assign_role`: boolean (default: true)
-   `notes`: nullable|string|max:1000

**Success Response (201):**

```json
{
    "status": "success",
    "message": "Hostel admin assignment created successfully.",
    "data": {
        "id": 1,
        "vuna_accomodation_id": 1,
        "staff_id": 101,
        "assigned_at": "2024-09-24T10:00:00Z",
        "status": "active",
        "assigned_by": 5,
        "notes": "Optional assignment notes",
        "hostel": {
            "id": 1,
            "name": "CICL",
            "gender": "mixed"
        },
        "staff": {
            "id": 101,
            "fname": "John",
            "lname": "Doe",
            "email": "john.doe@veritas.edu.ng"
        }
    }
}
```

**Error Response (422) - Duplicate Assignment:**

```json
{
    "status": "error",
    "message": "This staff member is already assigned to this hostel."
}
```

**Error Response (500) - Server Error:**

```json
{
    "status": "error",
    "message": "Failed to create hostel admin assignment."
}
```

### 3. List All Assignments

**GET** `/api/admin/hostel-assignments`
**GET** `/api/dean/hostel-assignments`

**Query Parameters:**

-   `status` (optional): Filter by status (active/inactive)
-   `hostel_id` (optional): Filter by hostel ID
-   `staff_id` (optional): Filter by staff ID
-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 15)

**Response:**

```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "vuna_accomodation_id": 1,
                "staff_id": 101,
                "assigned_at": "2024-09-24T10:00:00Z",
                "assigned_by": 5,
                "status": "active",
                "notes": "Assignment notes",
                "created_at": "2024-09-24T10:00:00Z",
                "updated_at": "2024-09-24T10:00:00Z",
                "hostel": {
                    "id": 1,
                    "name": "CICL",
                    "gender": "mixed"
                },
                "staff": {
                    "id": 101,
                    "fname": "John",
                    "lname": "Doe",
                    "email": "john.doe@veritas.edu.ng"
                }
            }
        ],
        "first_page_url": "http://localhost:8000/api/admin/hostel-assignments?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/admin/hostel-assignments?page=1",
        "links": [...],
        "next_page_url": null,
        "path": "http://localhost:8000/api/admin/hostel-assignments",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

### 4. Update Assignment Status

**PUT** `/api/admin/hostel-assignments/{id}`
**PUT** `/api/dean/hostel-assignments/{id}`

**Payload:**

```json
{
    "status": "inactive"
}
```

**Validation Rules:**

-   `status`: required|in:active,inactive

**Success Response:**

```json
{
    "status": "success",
    "message": "Hostel admin assignment updated successfully.",
    "data": {
        "id": 1,
        "vuna_accomodation_id": 1,
        "staff_id": 101,
        "assigned_at": "2024-09-24T10:00:00Z",
        "assigned_by": 5,
        "status": "inactive",
        "notes": "Assignment notes",
        "created_at": "2024-09-24T10:00:00Z",
        "updated_at": "2024-09-24T11:00:00Z",
        "hostel": {
            "id": 1,
            "name": "CICL",
            "gender": "mixed"
        },
        "staff": {
            "id": 101,
            "fname": "John",
            "lname": "Doe",
            "email": "john.doe@veritas.edu.ng"
        }
    }
}
```

**Error Response (404):**

```json
{
    "message": "No query results for model [App\\Models\\HostelAdminAssignment] {id}"
}
```

### 5. Remove Assignment

**DELETE** `/api/admin/hostel-assignments/{id}`
**DELETE** `/api/dean/hostel-assignments/{id}`

Sets assignment status to inactive (soft delete).

**Success Response:**

```json
{
    "status": "success",
    "message": "Hostel admin assignment removed successfully."
}
```

**Error Response (404):**

```json
{
    "message": "No query results for model [App\\Models\\HostelAdminAssignment] {id}"
}
```

### 6. Get Staff's Hostel Assignments

**GET** `/api/admin/hostel-assignments/staff/{staffId}`
**GET** `/api/dean/hostel-assignments/staff/{staffId}`

Returns all active hostel assignments for a specific staff member.

**Response:**

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "vuna_accomodation_id": 1,
            "staff_id": 101,
            "assigned_at": "2024-09-24T10:00:00Z",
            "assigned_by": 5,
            "status": "active",
            "notes": "Assignment notes",
            "created_at": "2024-09-24T10:00:00Z",
            "updated_at": "2024-09-24T10:00:00Z",
            "hostel": {
                "id": 1,
                "name": "CICL",
                "gender": "mixed"
            }
        },
        {
            "id": 2,
            "vuna_accomodation_id": 3,
            "staff_id": 101,
            "assigned_at": "2024-09-24T11:00:00Z",
            "assigned_by": 5,
            "status": "active",
            "notes": null,
            "created_at": "2024-09-24T11:00:00Z",
            "updated_at": "2024-09-24T11:00:00Z",
            "hostel": {
                "id": 3,
                "name": "BLOCK B",
                "gender": "female"
            }
        }
    ]
}
```

### 7. Get Hostel's Assigned Staff

**GET** `/api/admin/hostel-assignments/hostel/{hostelId}`
**GET** `/api/dean/hostel-assignments/hostel/{hostelId}`

Returns all active staff assignments for a specific hostel.

**Response:**

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "vuna_accomodation_id": 1,
            "staff_id": 101,
            "assigned_at": "2024-09-24T10:00:00Z",
            "assigned_by": 5,
            "status": "active",
            "notes": "Assignment notes",
            "created_at": "2024-09-24T10:00:00Z",
            "updated_at": "2024-09-24T10:00:00Z",
            "staff": {
                "id": 101,
                "fname": "John",
                "lname": "Doe",
                "email": "john.doe@veritas.edu.ng"
            }
        },
        {
            "id": 3,
            "vuna_accomodation_id": 1,
            "staff_id": 102,
            "assigned_at": "2024-09-24T12:00:00Z",
            "assigned_by": 5,
            "status": "active",
            "notes": null,
            "created_at": "2024-09-24T12:00:00Z",
            "updated_at": "2024-09-24T12:00:00Z",
            "staff": {
                "id": 102,
                "fname": "Jane",
                "lname": "Smith",
                "email": "jane.smith@veritas.edu.ng"
            }
        }
    ]
}
```

## Hostel Admin Behavior

### Filtered Exeat Requests

When a hostel admin calls:
**GET** `/api/staff/exeat-requests`

They will only see exeat requests where:

-   `student_accommodation` matches their assigned hostel name(s)
-   Status is `hostel_signout` or `hostel_signin`

### Example Scenarios

**Scenario 1: Staff A assigned to CICL hostel**

-   Can see exeat requests with `student_accommodation = "CICL"`
-   Cannot see requests from other hostels

**Scenario 2: Dean/Admin user**

-   Can see ALL exeat requests regardless of hostel
-   Can act on any request with any role

**Scenario 3: Hostel admin with no assignments**

-   Cannot see any exeat requests
-   Gets empty result set

## Testing Workflow

### 1. Setup Test Data

```bash
# Create hostel assignment
POST /api/admin/hostel-assignments
{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true
}
```

### 2. Test Hostel Admin Access

```bash
# Login as assigned hostel admin
POST /api/auth/login
{
    "email": "hostel.admin@veritas.edu.ng",
    "password": "password"
}

# Check filtered exeat requests
GET /api/staff/exeat-requests
# Should only show requests from assigned hostel
```

### 3. Test Dean/Admin Access

```bash
# Login as dean
POST /api/auth/login
{
    "email": "dean@veritas.edu.ng",
    "password": "password"
}

# Check all exeat requests
GET /api/staff/exeat-requests
# Should show ALL exeat requests
```

## Error Responses

### 422 - Duplicate Assignment

```json
{
    "status": "error",
    "message": "This staff member is already assigned to this hostel."
}
```

### 403 - No Hostel Access

```json
{
    "message": "You do not have permission to view this request from this hostel."
}
```

### 403 - No Assignments

```json
{
    "message": "No access to exeat requests."
}
```

## Database Changes

The system uses existing tables:

-   `hostel_admin_assignments` - Links staff to hostels
-   `vuna_accomodations` - Hostel information
-   `staff_exeat_roles` - Staff role assignments
-   `exeat_requests` - Contains `student_accommodation` field

## Key Features

1. **Automatic Role Assignment**: When assigning a hostel, optionally auto-assigns `hostel_admin` role
2. **Hostel-Based Filtering**: Hostel admins only see their assigned hostel's requests
3. **Dean/Admin Override**: Dean/Admin can see and act on all requests
4. **Targeted Notifications**: Only relevant hostel admins get notified
5. **Audit Trail**: All assignments are logged with timestamps

## Implementation Notes

-   Hostel matching is done by comparing `exeat_request.student_accommodation` with `vuna_accomodation.name`
-   Multiple staff can be assigned to the same hostel
-   One staff can be assigned to multiple hostels
-   Inactive assignments are ignored in filtering
-   System falls back to all hostel admins if no specific assignments exist
