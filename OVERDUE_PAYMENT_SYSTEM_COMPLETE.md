# Complete Overdue Payment System Documentation

## Overview

The overdue payment system automatically creates debts when students return late from their exeat and provides multiple payment and clearance options. The system integrates with Paystack for online payments and includes comprehensive notification and audit features.

## How the System Works

### 1. **Debt Creation Trigger**

**When**: Debt is created when a student returns late from exeat  
**Trigger Point**: Security staff signs in the student at the gate  
**Location**: `ExeatWorkflowService::checkAndCreateOverdueDebt()`

```php
// Triggered when security approves 'security_signin' status
if ($oldStatus === 'security_signin' && $approval->role === 'security') {
    // Check if student is returning late and create debt
    $this->checkAndCreateOverdueDebt($exeatRequest);
}
```

### 2. **Debt Calculation Logic**

**Formula**: ₦10,000 per day (partial days count as full days)

```php
// Calculate hours overdue
$returnDate = Carbon::parse($exeatRequest->return_date);
$actualReturnTime = now();
$hoursOverdue = $returnDate->diffInHours($actualReturnTime);

// Calculate debt amount
$daysOverdue = ceil($hoursOverdue / 24); // Partial days = full days
$debtAmount = $daysOverdue * 10000; // ₦10,000 per day
```

**Examples**:
- 2 hours late = 1 day = ₦10,000
- 25 hours late = 2 days = ₦20,000
- 48 hours late = 2 days = ₦20,000
- 49 hours late = 3 days = ₦30,000

### 3. **Debt Prevention**

- **No Duplicate Debts**: System checks for existing unpaid/uncleared debts for the same exeat
- **Only Late Returns**: Debt is only created if actual return time > expected return time
- **One Debt Per Exeat**: Each exeat request can only have one debt record

```php
// Check if debt already exists for this exeat
$existingDebt = StudentExeatDebt::where('exeat_request_id', $exeatRequest->id)
    ->where('payment_status', '!=', 'cleared')
    ->first();

if (!$existingDebt) {
    // Create new debt record
}
```

---

## Database Structure

### student_exeat_debts Table

```sql
CREATE TABLE student_exeat_debts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    exeat_request_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    overdue_hours INT DEFAULT 0,
    payment_status VARCHAR(255) DEFAULT 'unpaid', -- unpaid, paid, cleared
    payment_reference VARCHAR(255) NULL,
    payment_proof TEXT NULL,
    payment_date TIMESTAMP NULL,
    cleared_by INT UNSIGNED NULL,
    cleared_at TIMESTAMP NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (exeat_request_id) REFERENCES exeat_requests(id),
    FOREIGN KEY (cleared_by) REFERENCES staff(id) ON DELETE SET NULL
);
```

### Payment Status Flow

```
unpaid → paid → cleared
   ↓       ↓       ↓
Student  Admin   Final
Creates  Verifies State
Debt     Payment
```

---

## Payment Methods

### 1. **Paystack Integration (Primary Method)**

**Process Flow**:
1. Student initiates payment via API
2. System creates Paystack transaction
3. Student completes payment on Paystack
4. System verifies payment automatically
5. Debt status changes to 'cleared' immediately

**API Endpoint**: `POST /api/student/debts/{id}/payment`

**Request**:
```json
{
    "payment_method": "paystack"
}
```

**Response**:
```json
{
    "status": "success",
    "message": "Payment initialized successfully",
    "data": {
        "authorization_url": "https://checkout.paystack.com/...",
        "access_code": "access_code_here",
        "reference": "EXEAT-DEBT-1-1695123456"
    }
}
```

### 2. **Manual Clearance (Admin/Dean Only)**

**Process Flow**:
1. Student pays through other means (bank transfer, cash, etc.)
2. Admin/Dean manually clears the debt
3. System records who cleared it and when
4. Student receives clearance notification

**API Endpoint**: `POST /api/admin/student-debts/{id}/clear`

**Request**:
```json
{
    "notes": "Paid via bank transfer - Receipt #12345"
}
```

---

## Complete API Endpoints

### 1. List Student Debts

**GET** `/api/admin/student-debts`

**Query Parameters**:
- `payment_status`: Filter by status (unpaid|paid|cleared)
- `student_id`: Filter by specific student
- `page`: Page number
- `per_page`: Items per page

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
                "payment_date": null,
                "cleared_by": null,
                "cleared_at": null,
                "notes": null,
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
                },
                "clearedByStaff": null
            }
        ],
        "total": 1
    }
}
```

### 2. View Specific Debt

**GET** `/api/admin/student-debts/{id}`

**Response**:
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
        "created_at": "2024-09-24T10:00:00Z",
        "student": {...},
        "exeatRequest": {...},
        "clearedByStaff": null
    }
}
```

### 3. Initialize Payment (Student)

**POST** `/api/student/debts/{id}/payment`

**Request**:
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
        "authorization_url": "https://checkout.paystack.com/...",
        "access_code": "access_code_here",
        "reference": "EXEAT-DEBT-1-1695123456"
    }
}
```

**Error Responses**:
```json
// Debt already paid/cleared
{
    "status": "error",
    "message": "Cannot update payment proof for a debt that is already paid or cleared"
}

// Unauthorized (not student's debt)
{
    "status": "error",
    "message": "Unauthorized. You can only pay your own debts."
}
```

### 4. Verify Payment (Student)

**POST** `/api/student/debts/{id}/verify-payment`

**Request**:
```json
{
    "reference": "EXEAT-DEBT-1-1695123456"
}
```

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

### 5. Clear Debt Manually (Admin/Dean)

**POST** `/api/admin/student-debts/{id}/clear`

**Request**:
```json
{
    "notes": "Paid via bank transfer - Receipt #12345"
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
        "cleared_at": "2024-09-24T11:00:00Z",
        "notes": "Paid via bank transfer - Receipt #12345"
    }
}
```

**Error Responses**:
```json
// Not authorized
{
    "status": "error",
    "message": "Unauthorized. Only deans or admins can clear student debts."
}

// Debt not paid
{
    "status": "error",
    "message": "Cannot clear a debt that is not marked as paid"
}
```

---

## Notification System

### 1. **Debt Creation Notification**

**Triggered**: When debt is created (student returns late)  
**Recipients**: Student only  
**Channels**: Email + In-app notification (NO SMS)

**Email Content**:
```
Dear John,

This is to inform you that you have incurred a debt of ₦20,000 due to late return from your exeat.

Exeat ID: 456
Departure Date: 2024-09-20
Expected Return Date: 2024-09-22

Please make payment and submit proof through the exeat system as soon as possible.

Regards,
Exeat Management System
```

### 2. **Debt Clearance Notification**

**Triggered**: When debt is cleared (payment verified or manually cleared)  
**Recipients**: Student only  
**Channels**: Email + SMS + In-app notification

**Email Content**:
```
Dear John,

Your exeat debt of ₦20,000 has been cleared successfully.

Exeat ID: 456
Cleared Date: 2024-09-24 11:00:00

Thank you for your prompt payment.

Regards,
Exeat Management System
```

**SMS Content**:
```
EXEAT DEBT CLEARED: Your debt of ₦20,000 for exeat #456 has been cleared successfully. Thank you!
```

---

## Paystack Integration Details

### Transaction Initialization

```php
public function initializeTransaction(StudentExeatDebt $debt, Student $student)
{
    $amount = $debt->amount * 100; // Convert to kobo
    $reference = 'EXEAT-DEBT-' . $debt->id . '-' . time();
    
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . config('paystack.secret_key'),
        'Content-Type' => 'application/json',
    ])->post('https://api.paystack.co/transaction/initialize', [
        'amount' => $amount,
        'email' => $student->email,
        'reference' => $reference,
        'callback_url' => route('student.debts.verify-payment', ['debt' => $debt->id]),
        'metadata' => [
            'debt_id' => $debt->id,
            'student_id' => $student->id,
            'exeat_request_id' => $debt->exeat_request_id,
            'custom_fields' => [
                [
                    'display_name' => 'Debt Type',
                    'variable_name' => 'debt_type',
                    'value' => 'Exeat Overdue Fee'
                ]
            ]
        ]
    ]);
}
```

### Transaction Verification

```php
public function verifyTransaction($reference)
{
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . config('paystack.secret_key'),
    ])->get("https://api.paystack.co/transaction/verify/{$reference}");
    
    if ($response->successful()) {
        $data = $response->json();
        
        if ($data['data']['status'] === 'success') {
            return [
                'success' => true,
                'data' => $data['data']
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Payment verification failed'];
}
```

---

## Audit and Logging

### 1. **Debt Creation Logging**

```php
Log::info('Created overdue debt for late return', [
    'exeat_id' => $exeatRequest->id,
    'student_id' => $exeatRequest->student_id,
    'hours_overdue' => $hoursOverdue,
    'debt_amount' => $debtAmount
]);
```

### 2. **Payment Verification Logging**

```php
AuditLog::create([
    'staff_id' => null,
    'student_id' => $debt->student_id,
    'action' => 'payment_verified_and_cleared',
    'target_type' => 'student_exeat_debt',
    'target_id' => $debt->id,
    'details' => json_encode([
        'payment_reference' => $reference,
        'payment_date' => $debt->payment_date,
        'amount' => $debt->amount,
        'payment_method' => 'paystack',
        'transaction_data' => $result['data']
    ]),
    'timestamp' => now(),
]);
```

### 3. **Manual Clearance Logging**

```php
AuditLog::create([
    'staff_id' => $staff->id,
    'student_id' => $debt->student_id,
    'action' => 'debt_cleared',
    'target_type' => 'student_exeat_debt',
    'target_id' => $debt->id,
    'details' => json_encode([
        'cleared_by' => $staff->id,
        'cleared_at' => $debt->cleared_at,
        'amount' => $debt->amount,
        'notes' => $debt->notes
    ]),
    'timestamp' => now(),
]);
```

---

## Security Features

### 1. **Authorization Checks**

- **Students**: Can only pay their own debts
- **Admin/Dean**: Can clear any debt manually
- **Other Staff**: No access to debt management

### 2. **Payment Security**

- **Paystack Integration**: Secure payment processing
- **Reference Validation**: Unique payment references
- **Transaction Verification**: Double verification with Paystack
- **Duplicate Prevention**: No duplicate debts for same exeat

### 3. **Data Integrity**

- **Foreign Key Constraints**: Ensure data consistency
- **Status Validation**: Controlled status transitions
- **Audit Trail**: Complete logging of all actions

---

## Error Handling

### Common Error Scenarios

1. **Student tries to pay someone else's debt**
   - Status: 403 Forbidden
   - Message: "Unauthorized. You can only pay your own debts."

2. **Trying to pay already cleared debt**
   - Status: 422 Unprocessable Entity
   - Message: "Cannot update payment proof for a debt that is already paid or cleared"

3. **Paystack payment fails**
   - Status: 422 Unprocessable Entity
   - Message: "Payment initialization failed"

4. **Non-admin tries to clear debt**
   - Status: 403 Forbidden
   - Message: "Unauthorized. Only deans or admins can clear student debts."

5. **Trying to clear unpaid debt**
   - Status: 422 Unprocessable Entity
   - Message: "Cannot clear a debt that is not marked as paid"

---

## Testing Scenarios

### 1. **Create Overdue Debt**

```bash
# Simulate late return by security
POST /api/staff/exeat-requests/456/approve
{
    "action": "approve",
    "role": "security",
    "status": "security_signin",
    "comments": "Student returned late"
}
```

### 2. **Student Payment Flow**

```bash
# 1. Initialize payment
POST /api/student/debts/1/payment
{
    "payment_method": "paystack"
}

# 2. Complete payment on Paystack (external)

# 3. Verify payment
POST /api/student/debts/1/verify-payment
{
    "reference": "EXEAT-DEBT-1-1695123456"
}
```

### 3. **Admin Manual Clearance**

```bash
# Clear debt manually
POST /api/admin/student-debts/1/clear
{
    "notes": "Paid via bank transfer - Receipt #12345"
}
```

---

## Configuration

### Environment Variables

```env
# Paystack Configuration
PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
PAYSTACK_SECRET_KEY=sk_test_xxxxx
PAYSTACK_PAYMENT_URL=https://api.paystack.co

# Debt Configuration
OVERDUE_DEBT_AMOUNT_PER_DAY=10000
OVERDUE_DEBT_CURRENCY=NGN
```

### Key Features Summary

1. **Automatic Debt Creation**: When students return late
2. **Flexible Payment Options**: Paystack + Manual clearance
3. **Comprehensive Notifications**: Email, SMS, In-app
4. **Complete Audit Trail**: All actions logged
5. **Role-Based Access**: Students, Admins, Deans
6. **Duplicate Prevention**: One debt per exeat
7. **Secure Payment Processing**: Paystack integration
8. **Real-time Status Updates**: Immediate clearance after payment

The system provides a complete solution for managing overdue payments with multiple payment methods, comprehensive notifications, and robust security features.