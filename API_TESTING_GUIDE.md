# Hostel Admin Assignment & Student Debt Management API Testing Guide

## Overview

This API system manages hostel assignments for staff members and handles student exeat debt payments. It includes role-based access control, automatic debt creation for late returns, and payment processing via Paystack.

**Base URL**: `http://localhost:8000/api`

---

## Authentication

All endpoints require Bearer token authentication:
```
Authorization: Bearer {your_access_token}
Content-Type: application/json
```

---

# HOSTEL ADMIN ASSIGNMENT APIs

## 1. Get Assignment Options (Paginated)

**GET** `/admin/hostel-assignments/options`

**Purpose**: Get available hostels and staff for creating assignments

**Query Parameters**:
- `per_page` (optional): Items per page (default: 15)
- `page` (optional): Page number (default: 1)

**Response**:
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
                }
            ],
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
            "total": 5
        }
    }
}
```

---

## 2. Create Hostel Assignment

**POST** `/admin/hostel-assignments`

**Purpose**: Assign a hostel to a staff member with optional role assignment

**Payload**:
```json
{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Primary hostel admin"
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
        "notes": "Primary hostel admin",
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

**Error Response (422)**:
```json
{
    "status": "error",
    "message": "This staff member is already assigned to this hostel."
}
```

---

## 3. List All Assignments

**GET** `/admin/hostel-assignments`

**Purpose**: Get paginated list of all hostel assignments with filtering

**Query Parameters**:
- `status` (optional): active|inactive
- `hostel_id` (optional): Filter by hostel
- `staff_id` (optional): Filter by staff
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response**:
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
                "status": "active",
                "notes": "Primary admin",
                "hostel": {
                    "id": 1,
                    "name": "CICL"
                },
                "staff": {
                    "id": 101,
                    "fname": "John",
                    "lname": "Doe"
                }
            }
        ],
        "total": 1
    }
}
```

---

## 4. Update Assignment Status

**PUT** `/admin/hostel-assignments/{id}`

**Purpose**: Change assignment status (active/inactive)

**Payload**:
```json
{
    "status": "inactive"
}
```

**Success Response**:
```json
{
    "status": "success",
    "message": "Hostel admin assignment updated successfully.",
    "data": {
        "id": 1,
        "status": "inactive",
        "updated_at": "2024-09-24T11:00:00Z"
    }
}
```

---

## 5. Remove Assignment

**DELETE** `/admin/hostel-assignments/{id}`

**Purpose**: Soft delete assignment (sets status to inactive)

**Success Response**:
```json
{
    "status": "success",
    "message": "Hostel admin assignment removed successfully."
}
```

---

## 6. Get Staff's Hostel Assignments

**GET** `/admin/hostel-assignments/staff/{staffId}`

**Purpose**: Get all active hostels assigned to a specific staff member

**Response**:
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "vuna_accomodation_id": 1,
            "staff_id": 101,
            "status": "active",
            "hostel": {
                "id": 1,
                "name": "CICL",
                "gender": "mixed"
            }
        }
    ]
}
```

---

## 7. Get Hostel's Assigned Staff

**GET** `/admin/hostel-assignments/hostel/{hostelId}`

**Purpose**: Get all active staff assigned to a specific hostel

**Response**:
```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "vuna_accomodation_id": 1,
            "staff_id": 101,
            "status": "active",
            "staff": {
                "id": 101,
                "fname": "John",
                "lname": "Doe",
                "email": "john.doe@veritas.edu.ng"
            }
        }
    ]
}
```

---

# STUDENT DEBT MANAGEMENT APIs

## 8. List Student's Own Debts

**GET** `/student/debts`

**Purpose**: Student views their own debts

**Response**:
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
                "created_at": "2024-09-24T10:00:00Z",
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
                }
            }
        ],
        "total": 1
    }
}
```

---

## 9. Initialize Payment (Paystack)

**POST** `/student/debts/{id}/payment-proof`

**Purpose**: Initialize Paystack payment for a debt

**Payload**:
```json
{
    "payment_method": "paystack"
}
```

**Success Response**:
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

**Error Response (422)**:
```json
{
    "status": "error",
    "message": "Cannot update payment proof for a debt that is already paid or cleared"
}
```

---

## 10. Verify Payment

**GET** `/student/debts/{id}/verify-payment?reference=EXEAT-DEBT-1-1695123456`

**Purpose**: Verify Paystack payment and clear debt automatically

**Success Response**:
```json
{
    "status": "success",
    "message": "Payment verified and debt cleared successfully.",
    "data": {
        "id": 1,
        "payment_status": "cleared",
        "payment_date": "2024-09-24T11:00:00Z",
        "cleared_at": "2024-09-24T11:00:00Z",
        "payment_reference": "EXEAT-DEBT-1-1695123456"
    }
}
```

---

## 11. List All Student Debts (Admin)

**GET** `/admin/student-debts`

**Purpose**: Admin views all student debts with filtering

**Query Parameters**:
- `payment_status` (optional): unpaid|paid|cleared
- `student_id` (optional): Filter by student
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response**:
```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "student_id": 123,
                "amount": "20000.00",
                "payment_status": "unpaid",
                "student": {
                    "id": 123,
                    "fname": "John",
                    "lname": "Doe",
                    "matric_no": "VU/2020/CSC/001"
                },
                "exeatRequest": {
                    "id": 456,
                    "departure_date": "2024-09-20",
                    "return_date": "2024-09-22",
                    "student_accommodation": "CICL"
                }
            }
        ],
        "total": 25
    }
}
```

---

## 12. Clear Debt Manually (Admin)

**POST** `/admin/student-debts/{id}/clear`

**Purpose**: Admin manually clears a debt (for offline payments)

**Payload**:
```json
{
    "notes": "Student paid via bank transfer on 2024-09-24. Receipt number: BT-12345."
}
```

**Success Response**:
```json
{
    "status": "success",
    "message": "Student debt has been cleared successfully.",
    "data": {
        "id": 1,
        "payment_status": "cleared",
        "cleared_by": 5,
        "cleared_at": "2024-09-24T12:00:00Z",
        "notes": "Student paid via bank transfer on 2024-09-24. Receipt number: BT-12345.",
        "clearedByStaff": {
            "id": 5,
            "fname": "Admin",
            "lname": "User"
        }
    }
}
```

---

## 13. Create Exeat Request (With Debt Check)

**POST** `/student/exeat-requests`

**Purpose**: Student creates new exeat request (blocked if debts exist)

**Payload**:
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
        "reason": "Medical appointment",
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
        "total_debt_amount": 30000.00,
        "number_of_debts": 2,
        "debts": [
            {
                "debt_id": 1,
                "amount": 20000.00,
                "payment_status": "unpaid",
                "exeat_request_id": 456
            }
        ],
        "payment_instructions": "Please pay your outstanding debts through the payment system or contact the admin office for assistance."
    }
}
```

---

# TESTING WORKFLOW

## Complete Test Scenario

### 1. Setup Hostel Assignment
```bash
# Get available options
GET /api/admin/hostel-assignments/options

# Create assignment
POST /api/admin/hostel-assignments
{
    "vuna_accomodation_id": 1,
    "staff_id": 101,
    "auto_assign_role": true,
    "notes": "Test assignment"
}

# Verify assignment
GET /api/admin/hostel-assignments
```

### 2. Test Debt Payment Flow
```bash
# Student views debts
GET /api/student/debts

# Initialize payment
POST /api/student/debts/1/payment-proof
{
    "payment_method": "paystack"
}

# Complete payment on Paystack (external)

# Verify payment
GET /api/student/debts/1/verify-payment?reference=EXEAT-DEBT-1-1695123456

# Try creating exeat (should work after payment)
POST /api/student/exeat-requests
{
    "category_id": 1,
    "reason": "Medical appointment",
    "destination": "Lagos",
    "departure_date": "2024-09-25",
    "return_date": "2024-09-27",
    "preferred_mode_of_contact": "whatsapp"
}
```

### 3. Test Admin Debt Management
```bash
# View all debts
GET /api/admin/student-debts?payment_status=unpaid

# Clear debt manually
POST /api/admin/student-debts/1/clear
{
    "notes": "Paid via bank transfer - Receipt #12345"
}
```

---

# ERROR CODES

- **200**: Success
- **201**: Created successfully
- **401**: Unauthorized (invalid/missing token)
- **403**: Forbidden (debt blocking, insufficient permissions)
- **404**: Resource not found
- **422**: Validation error, payment failed
- **500**: Internal server error

---

# KEY FEATURES

1. **Many-to-Many Relationships**: One staff can manage multiple hostels, one hostel can have multiple staff
2. **Automatic Role Assignment**: Assigns hostel_admin role when creating assignments
3. **Debt Prevention**: Students with unpaid debts cannot create new exeat requests
4. **Paystack Integration**: Secure online payment processing with automatic verification
5. **Manual Clearance**: Admin can clear debts for offline payments
6. **Complete Audit Trail**: All operations are logged with timestamps and user context
7. **Role-Based Access**: Students, Admins, and Deans have different permission levels

This API system provides comprehensive hostel management and debt collection capabilities with robust security and payment processing features.