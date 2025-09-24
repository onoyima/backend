# Complete Hostel Admin Assignment API Documentation

## Overview

This comprehensive API system allows Dean and Admin users to assign hostels to staff members with automatic role assignment capabilities. The system implements role-based access control where hostel admins can only view exeat requests from students in their assigned hostels.

## Base URLs

-   **Admin Routes**: `/api/admin/hostel-assignments`
-   **Dean Routes**: `/api/dean/hostel-assignments`

## Authentication

All endpoints require Bearer token authentication:

```http
Authorization: Bearer {your_access_token}
Content-Type: application/json
```

## Complete API Endpoints

### 1. Get Assignment Options (Paginated)

**Endpoint**: `GET /api/admin/hostel-assignments/options`  
**Endpoint**: `GET /api/dean/hostel-assignments/options`

**Description**: Returns paginated lists of available hostels and staff for assignment creation.

**Query Parameters**:

-   `per_page` (optional, integer): Items per page (default: 15)
-   `page` (optional, integer): Page number (default: 1)

**Example Request**:

```http
GET /api/admin/hostel-assignments/options?per_page=20&page=1
Authorization: Bearer your_token_here
```

**Success Response (200)**:

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

---

### 2. Create Hostel Assignment

**Endpoint**: `POST /api/admin/hostel-assignments`  
**Endpoint**: `POST /api/dean/hostel-assignments`

**Description**: Creates a new hostel assignment for a staff member with optional automatic role assignment.

**Request Payload**:

```json
{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Optional assignment notes"
}
```

**Validation Rules**:

-   `vuna_accomodation_id`: required|exists:vuna_accomodations,id
-   `staff_id`: required|exists:staff,id
-   `auto_assign_role`: boolean (default: true)
-   `notes`: nullable|string|max:1000

**Example Request**:

```http
POST /api/admin/hostel-assignments
Authorization: Bearer your_token_here
Content-Type: application/json

{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Assigned as primary hostel admin"
}
```

**Success Response (201)**:

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
        "notes": "Assigned as primary hostel admin",
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
}
```

**Error Response (422) - Duplicate Assignment**:

```json
{
    "status": "error",
    "message": "This staff member is already assigned to this hostel."
}
```

**Error Response (500) - Server Error**:

```json
{
    "status": "error",
    "message": "Failed to create hostel admin assignment."
}
```

---

### 3. List All Assignments (Paginated)

**Endpoint**: `GET /api/admin/hostel-assignments`  
**Endpoint**: `GET /api/dean/hostel-assignments`

**Description**: Returns a paginated list of all hostel assignments with filtering options.

**Query Parameters**:

-   `status` (optional): Filter by status (active|inactive)
-   `hostel_id` (optional): Filter by hostel ID
-   `staff_id` (optional): Filter by staff ID
-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 15)

**Example Request**:

```http
GET /api/admin/hostel-assignments?status=active&hostel_id=1&page=1&per_page=10
Authorization: Bearer your_token_here
```

**Success Response (200)**:

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
                "notes": "Primary hostel admin",
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
        "next_page_url": null,
        "path": "http://localhost:8000/api/admin/hostel-assignments",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

---

### 4. Update Assignment Status

**Endpoint**: `PUT /api/admin/hostel-assignments/{id}`  
**Endpoint**: `PUT /api/dean/hostel-assignments/{id}`

**Description**: Updates the status of an existing hostel assignment.

**Request Payload**:

```json
{
    "status": "inactive"
}
```

**Validation Rules**:

-   `status`: required|in:active,inactive

**Example Request**:

```http
PUT /api/admin/hostel-assignments/1
Authorization: Bearer your_token_here
Content-Type: application/json

{
    "status": "inactive"
}
```

**Success Response (200)**:

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
        "notes": "Primary hostel admin",
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

**Error Response (404)**:

```json
{
    "message": "No query results for model [App\\Models\\HostelAdminAssignment] 1"
}
```

---

### 5. Remove Assignment (Soft Delete)

**Endpoint**: `DELETE /api/admin/hostel-assignments/{id}`  
**Endpoint**: `DELETE /api/dean/hostel-assignments/{id}`

**Description**: Soft deletes a hostel assignment by setting its status to inactive.

**Example Request**:

```http
DELETE /api/admin/hostel-assignments/1
Authorization: Bearer your_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "message": "Hostel admin assignment removed successfully."
}
```

**Error Response (404)**:

```json
{
    "message": "No query results for model [App\\Models\\HostelAdminAssignment] 1"
}
```

---

### 6. Get Staff's Hostel Assignments

**Endpoint**: `GET /api/admin/hostel-assignments/staff/{staffId}`  
**Endpoint**: `GET /api/dean/hostel-assignments/staff/{staffId}`

**Description**: Returns all active hostel assignments for a specific staff member.

**Example Request**:

```http
GET /api/admin/hostel-assignments/staff/101
Authorization: Bearer your_token_here
```

**Success Response (200)**:

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
            "notes": "Primary admin for CICL",
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

---

### 7. Get Hostel's Assigned Staff

**Endpoint**: `GET /api/admin/hostel-assignments/hostel/{hostelId}`  
**Endpoint**: `GET /api/dean/hostel-assignments/hostel/{hostelId}`

**Description**: Returns all active staff assignments for a specific hostel.

**Example Request**:

```http
GET /api/admin/hostel-assignments/hostel/1
Authorization: Bearer your_token_here
```

**Success Response (200)**:

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
            "notes": "Primary admin",
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
            "notes": "Secondary admin",
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

---

## System Integration

### Hostel Admin Filtering

When hostel admins call the exeat requests endpoint:

**Endpoint**: `GET /api/staff/exeat-requests`

The system automatically filters results based on their hostel assignments:

-   **Hostel Admins**: Only see requests where `student_accommodation` matches their assigned hostel name(s)
-   **Dean/Admin Users**: See ALL exeat requests regardless of hostel
-   **Unassigned Staff**: Get empty result set

### Automatic Role Assignment

When creating assignments with `auto_assign_role: true`:

1. System checks if staff member has `hostel_admin` role
2. If not present, automatically assigns the role
3. Role assignment is logged for audit purposes
4. Existing roles are not duplicated

### Notification Targeting

The system uses hostel assignments for targeted notifications:

-   Only relevant hostel admins receive notifications for their assigned hostels
-   Falls back to all hostel admins if no specific assignments exist
-   Dean and admin users receive all notifications

---

## Error Handling

### Common HTTP Status Codes

-   **200**: Success
-   **201**: Created successfully
-   **401**: Unauthorized (invalid/missing token)
-   **403**: Forbidden (insufficient permissions)
-   **404**: Resource not found
-   **422**: Validation error
-   **500**: Internal server error

### Validation Errors

Validation errors return detailed field-specific messages:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "vuna_accomodation_id": [
            "The selected vuna accomodation id is invalid."
        ],
        "staff_id": ["The staff id field is required."]
    }
}
```

---

## Testing Examples

### Complete Workflow Test

```bash
# 1. Get assignment options
curl -X GET "http://localhost:8000/api/admin/hostel-assignments/options" \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json"

# 2. Create assignment
curl -X POST "http://localhost:8000/api/admin/hostel-assignments" \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Primary hostel admin"
  }'

# 3. List assignments with filters
curl -X GET "http://localhost:8000/api/admin/hostel-assignments?status=active&hostel_id=1" \
  -H "Authorization: Bearer your_token"

# 4. Update assignment status
curl -X PUT "http://localhost:8000/api/admin/hostel-assignments/1" \
  -H "Authorization: Bearer your_token" \
  -H "Content-Type: application/json" \
  -d '{"status": "inactive"}'

# 5. Get staff assignments
curl -X GET "http://localhost:8000/api/admin/hostel-assignments/staff/101" \
  -H "Authorization: Bearer your_token"

# 6. Get hostel staff
curl -X GET "http://localhost:8000/api/admin/hostel-assignments/hostel/1" \
  -H "Authorization: Bearer your_token"

# 7. Remove assignment
curl -X DELETE "http://localhost:8000/api/admin/hostel-assignments/1" \
  -H "Authorization: Bearer your_token"
```

---

## Database Schema

### hostel_admin_assignments Table

```sql
CREATE TABLE hostel_admin_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vuna_accomodation_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (vuna_accomodation_id) REFERENCES vuna_accomodations(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (assigned_by) REFERENCES staff(id),

    INDEX idx_hostel_admin_assignments_hostel (vuna_accomodation_id),
    INDEX idx_hostel_admin_assignments_staff (staff_id),
    INDEX idx_hostel_admin_assignments_status (status),
    UNIQUE KEY unique_active_assignment (vuna_accomodation_id, staff_id, status)
);
```

---

## Security Considerations

1. **Authentication**: All endpoints require valid Bearer tokens
2. **Authorization**: Role-based access control (admin/dean only)
3. **Input Validation**: Comprehensive validation on all inputs
4. **SQL Injection Prevention**: Using Eloquent ORM with parameter binding
5. **Audit Logging**: All operations are logged with user context
6. **Rate Limiting**: Standard Laravel rate limiting applies
7. **CORS**: Configured for appropriate origins only

---

## Performance Notes

1. **Pagination**: All list endpoints use pagination to prevent large data loads
2. **Eager Loading**: Related models (hostel, staff) are eager loaded to prevent N+1 queries
3. **Database Indexes**: Proper indexing on frequently queried columns
4. **Caching**: Consider implementing Redis caching for frequently accessed data
5. **Query Optimization**: Filtered queries use database indexes effectively

---

## Changelog

-   **v1.0.0**: Initial implementation with basic CRUD operations
-   **v1.1.0**: Added pagination support for options endpoint
-   **v1.2.0**: Enhanced error handling and validation
-   **v1.3.0**: Added comprehensive logging and audit trail
-   **v1.4.0**: Implemented automatic role assignment feature

---

# Student Exeat Debt Management API

## Overview

The Student Exeat Debt Management system handles overdue payments when students return late from exeat. It includes debt creation, payment processing via Paystack, and administrative clearance capabilities.

## Base URLs

-   **Student Routes**: `/api/student/debts`
-   **Admin Routes**: `/api/admin/student-debts`
-   **Dean Routes**: `/api/dean/student-debts`

---

## Student Debt APIs

### 1. List Student's Own Debts

**Endpoint**: `GET /api/student/debts`

**Description**: Returns all debts belonging to the authenticated student.

**Example Request**:

```http
GET /api/student/debts
Authorization: Bearer student_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "student_id": 123,
                "exeat_request_id": 456,
                "amount": "20000.00",
                "overdue_hours": 25,
                "payment_status": "unpaid",
                "payment_reference": null,
                "payment_date": null,
                "cleared_by": null,
                "cleared_at": null,
                "notes": null,
                "created_at": "2024-09-24T10:00:00Z",
                "updated_at": "2024-09-24T10:00:00Z",
                "student": {
                    "id": 123,
                    "fname": "John",
                    "lname": "Doe",
                    "email": "john.doe@student.veritas.edu.ng"
                },
                "exeatRequest": {
                    "id": 456,
                    "departure_date": "2024-09-20",
                    "return_date": "2024-09-22",
                    "reason": "Medical appointment",
                    "destination": "Lagos"
                },
                "clearedByStaff": null
            }
        ],
        "first_page_url": "http://localhost:8000/api/student/debts?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://localhost:8000/api/student/debts?page=1",
        "next_page_url": null,
        "path": "http://localhost:8000/api/student/debts",
        "per_page": 15,
        "prev_page_url": null,
        "to": 1,
        "total": 1
    }
}
```

---

### 2. View Specific Debt Details

**Endpoint**: `GET /api/student/debts/{id}`

**Description**: Returns detailed information about a specific debt.

**Example Request**:

```http
GET /api/student/debts/1
Authorization: Bearer student_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "data": {
        "id": 1,
        "student_id": 123,
        "exeat_request_id": 456,
        "amount": "20000.00",
        "overdue_hours": 25,
        "payment_status": "unpaid",
        "payment_reference": null,
        "payment_proof": null,
        "payment_date": null,
        "cleared_by": null,
        "cleared_at": null,
        "notes": null,
        "created_at": "2024-09-24T10:00:00Z",
        "updated_at": "2024-09-24T10:00:00Z",
        "student": {
            "id": 123,
            "fname": "John",
            "lname": "Doe",
            "email": "john.doe@student.veritas.edu.ng",
            "phone": "+2348012345678"
        },
        "exeatRequest": {
            "id": 456,
            "departure_date": "2024-09-20",
            "return_date": "2024-09-22",
            "reason": "Medical appointment",
            "destination": "Lagos",
            "status": "completed"
        },
        "clearedByStaff": null
    }
}
```

**Error Response (404)**:

```json
{
    "message": "No query results for model [App\\Models\\StudentExeatDebt] 1"
}
```

**Error Response (403) - Not Student's Debt**:

```json
{
    "status": "error",
    "message": "Unauthorized. You can only view your own debts."
}
```

---

### 3. Initialize Payment (Paystack)

**Endpoint**: `POST /api/student/debts/{id}/payment-proof`

**Description**: Initializes a Paystack payment transaction for the debt.

**Request Payload**:

```json
{
    "payment_method": "paystack"
}
```

**Validation Rules**:

-   `payment_method`: required|string|in:paystack

**Example Request**:

```http
POST /api/student/debts/1/payment-proof
Authorization: Bearer student_token_here
Content-Type: application/json

{
    "payment_method": "paystack"
}
```

**Success Response (200)**:

```json
{
    "status": "success",
    "message": "Payment initialized successfully",
    "data": {
        "authorization_url": "https://checkout.paystack.com/0peioxfhpn",
        "access_code": "0peioxfhpn",
        "reference": "EXEAT-DEBT-1-1695123456"
    }
}
```

**Error Response (422) - Already Paid**:

```json
{
    "status": "error",
    "message": "Cannot update payment proof for a debt that is already paid or cleared"
}
```

**Error Response (403) - Not Student's Debt**:

```json
{
    "status": "error",
    "message": "Unauthorized. You can only pay your own debts."
}
```

**Error Response (404) - Student Not Found**:

```json
{
    "status": "error",
    "message": "Student not found"
}
```

---

### 4. Verify Payment

**Endpoint**: `GET /api/student/debts/{id}/verify-payment`

**Description**: Verifies a Paystack payment and automatically clears the debt if successful.

**Query Parameters**:

-   `reference` (optional): Payment reference to verify (uses stored reference if not provided)

**Example Request**:

```http
GET /api/student/debts/1/verify-payment?reference=EXEAT-DEBT-1-1695123456
Authorization: Bearer student_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "message": "Payment verified and debt cleared successfully.",
    "data": {
        "id": 1,
        "student_id": 123,
        "exeat_request_id": 456,
        "amount": "20000.00",
        "overdue_hours": 25,
        "payment_status": "cleared",
        "payment_reference": "EXEAT-DEBT-1-1695123456",
        "payment_date": "2024-09-24T11:00:00Z",
        "cleared_at": "2024-09-24T11:00:00Z",
        "notes": null,
        "created_at": "2024-09-24T10:00:00Z",
        "updated_at": "2024-09-24T11:00:00Z"
    }
}
```

**Error Response (422) - Payment Failed**:

```json
{
    "status": "error",
    "message": "Payment verification failed"
}
```

**Error Response (422) - No Reference**:

```json
{
    "status": "error",
    "message": "Payment reference not found"
}
```

**Error Response (403) - Not Student's Debt**:

```json
{
    "status": "error",
    "message": "Unauthorized. You can only verify your own debt payments."
}
```

**Error Response (500) - Processing Error**:

```json
{
    "status": "error",
    "message": "An error occurred while processing your payment verification. Please contact support."
}
```

---

## Admin/Dean Debt Management APIs

### 5. List All Student Debts (Admin)

**Endpoint**: `GET /api/admin/student-debts`

**Description**: Returns paginated list of all student debts with filtering options.

**Query Parameters**:

-   `payment_status` (optional): Filter by status (unpaid|paid|cleared)
-   `student_id` (optional): Filter by specific student
-   `page` (optional): Page number (default: 1)
-   `per_page` (optional): Items per page (default: 15)

**Example Request**:

```http
GET /api/admin/student-debts?payment_status=unpaid&page=1&per_page=10
Authorization: Bearer admin_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "student_id": 123,
                "exeat_request_id": 456,
                "amount": "20000.00",
                "overdue_hours": 25,
                "payment_status": "unpaid",
                "payment_reference": null,
                "payment_date": null,
                "cleared_by": null,
                "cleared_at": null,
                "notes": null,
                "created_at": "2024-09-24T10:00:00Z",
                "updated_at": "2024-09-24T10:00:00Z",
                "student": {
                    "id": 123,
                    "fname": "John",
                    "lname": "Doe",
                    "email": "john.doe@student.veritas.edu.ng",
                    "phone": "+2348012345678"
                },
                "exeatRequest": {
                    "id": 456,
                    "departure_date": "2024-09-20",
                    "return_date": "2024-09-22",
                    "reason": "Medical appointment",
                    "destination": "Lagos",
                    "status": "completed"
                },
                "clearedByStaff": null
            }
        ],
        "first_page_url": "http://localhost:8000/api/admin/student-debts?page=1",
        "from": 1,
        "last_page": 2,
        "last_page_url": "http://localhost:8000/api/admin/student-debts?page=2",
        "next_page_url": "http://localhost:8000/api/admin/student-debts?page=2",
        "path": "http://localhost:8000/api/admin/student-debts",
        "per_page": 15,
        "prev_page_url": null,
        "to": 15,
        "total": 25
    }
}
```

---

### 6. View Specific Debt (Admin)

**Endpoint**: `GET /api/admin/student-debts/{id}`

**Description**: Returns detailed information about any student debt.

**Example Request**:

```http
GET /api/admin/student-debts/1
Authorization: Bearer admin_token_here
```

**Success Response (200)**:

```json
{
    "status": "success",
    "data": {
        "id": 1,
        "student_id": 123,
        "exeat_request_id": 456,
        "amount": "20000.00",
        "overdue_hours": 25,
        "payment_status": "unpaid",
        "payment_reference": null,
        "payment_proof": null,
        "payment_date": null,
        "cleared_by": null,
        "cleared_at": null,
        "notes": null,
        "created_at": "2024-09-24T10:00:00Z",
        "updated_at": "2024-09-24T10:00:00Z",
        "student": {
            "id": 123,
            "fname": "John",
            "lname": "Doe",
            "email": "john.doe@student.veritas.edu.ng",
            "phone": "+2348012345678",
            "matric_no": "VU/2020/CSC/001"
        },
        "exeatRequest": {
            "id": 456,
            "departure_date": "2024-09-20",
            "return_date": "2024-09-22",
            "reason": "Medical appointment",
            "destination": "Lagos",
            "status": "completed",
            "student_accommodation": "CICL"
        },
        "clearedByStaff": null
    }
}
```

---

### 7. Clear Debt Manually (Admin/Dean)

**Endpoint**: `POST /api/admin/student-debts/{id}/clear`  
**Endpoint**: `POST /api/dean/student-debts/{id}/clear`

**Description**: Manually clears a student debt (for payments made outside the system).

**Request Payload**:

```json
{
    "notes": "Paid via bank transfer - Receipt #12345"
}
```

**Validation Rules**:

-   `notes`: nullable|string|max:1000

**Example Request**:

```http
POST /api/admin/student-debts/1/clear
Authorization: Bearer admin_token_here
Content-Type: application/json

{
    "notes": "Student paid via bank transfer on 2024-09-24. Receipt number: BT-12345. Verified by John Admin."
}
```

**Success Response (200)**:

```json
{
    "status": "success",
    "message": "Student debt has been cleared successfully.",
    "data": {
        "id": 1,
        "student_id": 123,
        "exeat_request_id": 456,
        "amount": "20000.00",
        "overdue_hours": 25,
        "payment_status": "cleared",
        "payment_reference": null,
        "payment_date": null,
        "cleared_by": 5,
        "cleared_at": "2024-09-24T12:00:00Z",
        "notes": "Student paid via bank transfer on 2024-09-24. Receipt number: BT-12345. Verified by John Admin.",
        "created_at": "2024-09-24T10:00:00Z",
        "updated_at": "2024-09-24T12:00:00Z",
        "student": {
            "id": 123,
            "fname": "John",
            "lname": "Doe",
            "email": "john.doe@student.veritas.edu.ng"
        },
        "exeatRequest": {
            "id": 456,
            "departure_date": "2024-09-20",
            "return_date": "2024-09-22",
            "reason": "Medical appointment"
        },
        "clearedByStaff": {
            "id": 5,
            "fname": "Admin",
            "lname": "User",
            "email": "admin@veritas.edu.ng"
        }
    }
}
```

**Error Response (403) - Unauthorized**:

```json
{
    "status": "error",
    "message": "Unauthorized. Only deans or admins can clear student debts."
}
```

**Error Response (422) - Debt Not Paid**:

```json
{
    "status": "error",
    "message": "Cannot clear a debt that is not marked as paid"
}
```

**Error Response (404)**:

```json
{
    "message": "No query results for model [App\\Models\\StudentExeatDebt] 1"
}
```

---

## Debt Prevention System

### 8. Exeat Creation with Debt Check

**Endpoint**: `POST /api/student/exeat-requests`

**Description**: Creates a new exeat request but blocks creation if student has outstanding debts.

**Request Payload**:

```json
{
    "category_id": 1,
    "reason": "Medical appointment",
    "destination": "Lagos",
    "departure_date": "2024-09-25",
    "return_date": "2024-09-27",
    "preferred_mode_of_contact": "whatsapp"
}
```

**Success Response (201) - No Debts**:

```json
{
    "message": "Exeat request created successfully.",
    "exeat_request": {
        "id": 789,
        "student_id": 123,
        "category_id": 1,
        "reason": "Medical appointment",
        "destination": "Lagos",
        "departure_date": "2024-09-25",
        "return_date": "2024-09-27",
        "status": "deputy-dean_review",
        "created_at": "2024-09-24T13:00:00Z"
    }
}
```

**Error Response (403) - Outstanding Debts**:

```json
{
    "status": "error",
    "message": "You have outstanding exeat debts that must be cleared before creating a new exeat request.",
    "details": {
        "total_debt_amount": 30000.0,
        "number_of_debts": 2,
        "debts": [
            {
                "debt_id": 1,
                "amount": 20000.0,
                "overdue_hours": 25,
                "payment_status": "unpaid",
                "exeat_request_id": 456,
                "departure_date": "2024-09-20",
                "return_date": "2024-09-22"
            },
            {
                "debt_id": 2,
                "amount": 10000.0,
                "overdue_hours": 5,
                "payment_status": "paid",
                "exeat_request_id": 789,
                "departure_date": "2024-09-15",
                "return_date": "2024-09-16"
            }
        ],
        "payment_instructions": "Please pay your outstanding debts through the payment system or contact the admin office for assistance."
    }
}
```

---

## Paystack Integration Details

### Payment Flow

1. **Initialize Payment**: Student calls `/api/student/debts/{id}/payment-proof`
2. **Paystack Redirect**: Student completes payment on Paystack checkout page
3. **Automatic Verification**: Paystack redirects to `/api/student/debts/{id}/verify-payment`
4. **Debt Clearance**: System automatically clears debt if payment successful
5. **Notification**: Student receives clearance notification

### Payment Metadata

When initializing payments, the system includes comprehensive metadata:

```json
{
    "metadata": {
        "debt_id": 1,
        "student_id": 123,
        "exeat_request_id": 456,
        "custom_fields": [
            {
                "display_name": "Debt Type",
                "variable_name": "debt_type",
                "value": "Exeat Overdue Fee"
            },
            {
                "display_name": "Student Name",
                "variable_name": "student_name",
                "value": "John Doe"
            },
            {
                "display_name": "Exeat Request",
                "variable_name": "exeat_request",
                "value": "456"
            },
            {
                "display_name": "Overdue Hours",
                "variable_name": "overdue_hours",
                "value": "25"
            }
        ]
    }
}
```

### Payment Reference Format

```
EXEAT-DEBT-{debt_id}-{timestamp}
Example: EXEAT-DEBT-1-1695123456
```

---

## Notification System

### Debt Creation Notification

**Triggered**: When student returns late and debt is created  
**Recipients**: Student only  
**Channels**: Email + In-app notification

**Email Template**:

```
Subject: Exeat Debt Notification - ₦20,000

Dear John,

This is to inform you that you have incurred a debt of ₦20,000 due to late return from your exeat.

Exeat Details:
- Exeat ID: 456
- Departure Date: 2024-09-20
- Expected Return Date: 2024-09-22
- Actual Return: 2024-09-23 (25 hours late)

Payment Instructions:
Please log into the exeat system and pay your debt using the online payment option, or contact the admin office for alternative payment methods.

Regards,
Exeat Management System
```

### Debt Clearance Notification

**Triggered**: When debt is cleared (payment verified or manually cleared)  
**Recipients**: Student only  
**Channels**: Email + SMS + In-app notification

**Email Template**:

```
Subject: Exeat Debt Cleared - ₦20,000

Dear John,

Your exeat debt of ₦20,000 has been successfully cleared.

Payment Details:
- Debt ID: 1
- Amount: ₦20,000
- Payment Method: Paystack
- Payment Date: 2024-09-24 11:00:00
- Reference: EXEAT-DEBT-1-1695123456

You can now create new exeat requests without restrictions.

Thank you for your prompt payment.

Regards,
Exeat Management System
```

**SMS Template**:

```
EXEAT DEBT CLEARED: Your debt of ₦20,000 for exeat #456 has been cleared successfully. You can now create new exeat requests. Thank you!
```

---

## Testing Scenarios

### Complete Payment Flow Test

```bash
# 1. Student tries to create exeat with debt
POST /api/student/exeat-requests
# Expected: 403 with debt details

# 2. Student views their debts
GET /api/student/debts
# Expected: List of unpaid debts

# 3. Student initializes payment
POST /api/student/debts/1/payment-proof
{
    "payment_method": "paystack"
}
# Expected: Paystack authorization URL

# 4. Student completes payment on Paystack (external)

# 5. Paystack redirects to verify endpoint
GET /api/student/debts/1/verify-payment?reference=EXEAT-DEBT-1-1695123456
# Expected: Debt cleared successfully

# 6. Student creates new exeat request
POST /api/student/exeat-requests
# Expected: Success - no debt blocking
```

### Admin Manual Clearance Test

```bash
# 1. Admin views all debts
GET /api/admin/student-debts?payment_status=unpaid

# 2. Admin views specific debt
GET /api/admin/student-debts/1

# 3. Admin clears debt manually
POST /api/admin/student-debts/1/clear
{
    "notes": "Paid via bank transfer - Receipt #12345"
}
# Expected: Debt cleared with admin details
```

---

## Error Handling Summary

### Common HTTP Status Codes

-   **200**: Success
-   **201**: Created successfully
-   **401**: Unauthorized (invalid/missing token)
-   **403**: Forbidden (debt blocking, insufficient permissions)
-   **404**: Resource not found
-   **422**: Validation error, payment failed
-   **500**: Internal server error

### Debt-Specific Error Messages

1. **Outstanding Debts Block Exeat Creation**:

    - Status: 403
    - Message: "You have outstanding exeat debts that must be cleared..."

2. **Student Accessing Other's Debt**:

    - Status: 403
    - Message: "Unauthorized. You can only [action] your own debts."

3. **Payment Already Processed**:

    - Status: 422
    - Message: "Cannot update payment proof for a debt that is already paid or cleared"

4. **Admin Clearing Unpaid Debt**:

    - Status: 422
    - Message: "Cannot clear a debt that is not marked as paid"

5. **Paystack Payment Failed**:
    - Status: 422
    - Message: "Payment verification failed"

---

## Security Features

1. **Student Isolation**: Students can only access their own debts
2. **Role-Based Clearance**: Only Admin/Dean can manually clear debts
3. **Payment Verification**: Double verification with Paystack
4. **Audit Trail**: Complete logging of all debt operations
5. **Reference Validation**: Unique payment references prevent duplicates
6. **Debt Prevention**: Outstanding debts block new exeat creation

The debt management system provides comprehensive financial control while maintaining user-friendly payment options and robust security measures.
