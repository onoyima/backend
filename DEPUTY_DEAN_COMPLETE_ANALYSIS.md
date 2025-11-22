# Complete Deputy Dean Analysis - All References and Functions

## Overview

The Deputy Dean role is deeply integrated into the exeat workflow system. This document provides a comprehensive analysis of all Deputy Dean references, functions, and responsibilities to facilitate the planned change to "Secretaries".

---

## 1. WORKFLOW INTEGRATION

### Initial Status Assignment
**Location**: `app/Http/Controllers/StudentExeatRequestController.php`

```php
// Line 99: Determines initial status based on exeat type
$isMedical = strtolower($category->name) === 'medical';
$initialStatus = $isMedical ? 'cmd_review' : 'secretary_review';

// Line 121: Creates first approval stage
\App\Models\ExeatApproval::create([
    'exeat_request_id' => $exeat->id,
    'role' => $isMedical ? 'cmd' : 'deputy_dean',
    'status' => 'pending',
]);

// Line 136: Sends approval notification
$role = $isMedical ? 'cmd' : 'deputy_dean';
$this->notificationService->sendApprovalRequiredNotification($exeat, $role);
```

**Function**: 
- **Non-medical exeats** start with `secretary_review` status
- **Medical exeats** go to CMD first, then to deputy dean
- Deputy dean is the **first approver** for regular exeats

---

## 2. WORKFLOW STATUS PROGRESSION

### Status Flow Logic
**Location**: `app/Services/ExeatWorkflowService.php`

```php
// Line 103: Status progression logic
switch ($exeatRequest->status) {
    case 'pending':
        $exeatRequest->status = $exeatRequest->is_medical ? 'cmd_review' : 'secretary_review';
        break;
    case 'cmd_review':
        $exeatRequest->status = 'secretary_review';  // CMD → Deputy Dean
        break;
    case 'secretary_review':
        $exeatRequest->status = 'parent_consent';      // Deputy Dean → Parent
        break;
}

// Line 781: Role mapping for workflow
$roleMap = [
    'cmd_review' => 'cmd',
    'secretary_review' => 'deputy_dean',
    'dean_review' => 'dean',
    'hostel_signout' => 'hostel_admin',
];
```

**Function**:
- Deputy dean review comes **after CMD** (for medical) or **first** (for non-medical)
- Deputy dean approval **triggers parent consent** stage
- Deputy dean is a **mandatory step** in the workflow

---

## 3. PARENT CONSENT OVERRIDE POWERS

### Deputy Dean Can Act on Behalf of Parents
**Location**: `app/Services/ExeatWorkflowService.php`

#### Approval Override
```php
// Line 466: Deputy dean approves on behalf of parent
public function deputyDeanParentConsentApprove(ParentConsent $parentConsent, int $deputyDeanId, string $reason)
{
    $parentConsent->consent_status = 'approved';
    $parentConsent->consent_timestamp = now();
    $parentConsent->acted_by_staff_id = $deputyDeanId;
    $parentConsent->action_type = 'deputy_dean_approval';
    $parentConsent->deputy_dean_reason = $reason;
    $parentConsent->save();
    
    // Changes exeat status from parent_consent → dean_review
    $exeatRequest->status = 'dean_review';
}
```

#### Rejection Override
```php
// Line 516: Deputy dean rejects on behalf of parent
public function deputyDeanParentConsentReject(ParentConsent $parentConsent, int $deputyDeanId, string $reason)
{
    $parentConsent->consent_status = 'declined';
    $parentConsent->consent_timestamp = now();
    $parentConsent->acted_by_staff_id = $deputyDeanId;
    $parentConsent->action_type = 'deputy_dean_rejection';
    $parentConsent->deputy_dean_reason = $reason;
    $parentConsent->save();
    
    // Changes exeat status to rejected
    $exeatRequest->status = 'rejected';
}
```

**Function**:
- Deputy dean has **override authority** over parent consent
- Can **approve or reject** on behalf of parents
- Actions are **logged with reasons** for audit trail
- **Bypasses parent** in the workflow when exercised

---

## 4. ROLE-BASED ACCESS CONTROL

### Staff Role Filtering
**Location**: `app/Http/Controllers/StaffExeatRequestController.php`

```php
// Line 40: Role-based status access
$roleStatusMap = [
    'cmd' => ['cmd_review'],
    'deputy_dean' => ['secretary_review', 'parent_consent'],
    'dean' => $activeStatuses, // All statuses
    'dean2' => $activeStatuses, // All statuses
];

// Line 65: Status to role mapping
$roleMap = [
    'cmd_review' => 'cmd',
    'secretary_review' => 'deputy_dean',
    'dean_review' => 'dean',
];
```

**Function**:
- Deputy dean can see exeats in **`secretary_review`** and **`parent_consent`** statuses
- Deputy dean has **limited scope** compared to dean (who sees all)
- Deputy dean **cannot see** CMD, dean, hostel, or security stages

---

## 5. NOTIFICATION SYSTEM

### Notification Recipients
**Location**: `app/Services/ExeatNotificationService.php`

```php
// Line 618: Stage change notifications
case 'secretary_review':
    $recipients = array_merge($recipients, $this->getDeputyDeanStaff());
    break;

// Line 652: Approval required notifications
case 'deputy_dean':
    return $this->getDeputyDeanStaff();

// Line 671: Reminder notifications
case 'parent_consent_pending':
    return $this->getDeputyDeanStaff();

// Line 822: Get deputy dean staff members
protected function getDeputyDeanStaff(): array
{
    return Staff::whereHas('exeat_roles', function ($query) {
        $query->where('name', 'deputy_dean');
    })->get()->map(function ($staff) {
        return [
            'type' => ExeatNotification::RECIPIENT_STAFF,
            'id' => $staff->id
        ];
    })->toArray();
}
```

**Function**:
- Deputy dean receives notifications for **new exeats** requiring review
- Deputy dean receives **parent consent pending** reminders
- Deputy dean receives **approval required** notifications
- System queries staff with **`deputy_dean` role** for notifications

---

## 6. DASHBOARD AND STATISTICS

### Deputy Dean Dashboard Metrics
**Location**: `app/Http/Controllers/StaffExeatRequestController.php`

```php
// Line 1162: Parent consent statistics for deputy dean
$totalActedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->whereIn('action_type', ['deputy_dean_approval', 'deputy_dean_rejection'])
    ->count();

$approvedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->where('action_type', 'deputy_dean_approval')
    ->count();

$rejectedByDeputyDean = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->where('action_type', 'deputy_dean_rejection')
    ->count();

// Response includes deputy dean specific metrics
return response()->json([
    'status' => 'success',
    'data' => [
        'pending_consents' => $totalPending,
        'total_acted_by_deputy_dean' => $totalActedByDeputyDean,
        'approved_by_deputy_dean' => $approvedByDeputyDean,
        'rejected_by_deputy_dean' => $rejectedByDeputyDean
    ]
]);
```

**Function**:
- Deputy dean dashboard shows **parent consent override statistics**
- Tracks **total actions**, **approvals**, and **rejections** by deputy dean
- Provides **performance metrics** for deputy dean role

---

## 7. DATABASE SCHEMA

### Exeat Requests Table
**Location**: `database/migrations/2024_07_01_000001_create_exeat_requests_table.php`

```sql
-- Line 26: Status enum includes secretary_review
$table->enum('status', [
    'pending', 
    'cmd_review', 
    'secretary_review',  -- Deputy dean status
    'parent_consent', 
    'dean_review', 
    'hostel_signout', 
    'security_signout', 
    'security_signin', 
    'hostel_signin', 
    'completed', 
    'rejected', 
    'appeal'
])->default('pending');
```

### Parent Consents Table
**Location**: `database/migrations/2025_01_21_000004_add_acted_by_staff_id_to_parent_consents_table.php`

```sql
-- Deputy dean override tracking fields
$table->foreignId('acted_by_staff_id')->nullable()
      ->constrained('staff')->onDelete('set null')
      ->comment('Staff ID if Deputy Dean acted on behalf of parent');

$table->enum('action_type', ['parent', 'deputy_dean'])->default('parent')
      ->comment('Who performed the consent action');

$table->text('deputy_dean_reason')->nullable()
      ->comment('Reason provided by Deputy Dean for override');
```

### Exeat Roles Table
**Location**: `database/migrations/2025_07_25_150000_create_exeat_roles_table.php`

```sql
-- Deputy dean role definition
[
    'name' => 'deputy_dean', 
    'display_name' => 'Deputy Dean', 
    'description' => 'Can approve/reject exeat requests only after CMD (for medical) or parent has recommended/approved.'
]
```

**Function**:
- **`secretary_review`** is a core status in the workflow
- **Parent consent override** is tracked with deputy dean details
- **Deputy dean role** is defined in the roles system

---

## 8. AUTHORIZATION AND PERMISSIONS

### Role-Based Permissions
**Location**: `app/Providers/AuthServiceProvider.php`

```php
// Line 49: Staff role authorization
$allowedRoles = [
    'staff', 'teacher', 'housemaster', 'security', 
    'dean', 'admin', 'super_admin', 'cmd', 
    'deputy_dean',  // Deputy dean included in allowed roles
    'hostel_admin'
];
```

**Location**: `app/Policies/DashboardPolicy.php`

```php
// Line 36: Dashboard access policy
$allowedRoles = [
    'staff', 'teacher', 'housemaster', 'security', 
    'dean', 'admin', 'super_admin', 'cmd', 
    'deputy_dean',  // Deputy dean can access dashboard
    'hostel_admin'
];
```

**Function**:
- Deputy dean has **system access** permissions
- Deputy dean can **access dashboard** and staff features
- Deputy dean is **recognized role** in authorization system

---

## 9. ANALYTICS AND REPORTING

### Statistics Integration
**Location**: `app/Http/Controllers/StaffExeatStatisticsController.php`

```php
// Line 74: Role-specific statistics
$roles = ['cmd', 'deputy_dean', 'dean', 'hostel_admin', 'security'];

// Line 110: Statistics filtering
'role' => 'nullable|string|in:cmd,deputy_dean,dean,hostel_admin,security'
```

**Location**: `app/Services/DashboardAnalyticsService.php`

```php
// Line 59: Parent request pending count
$parentRequestpending = ExeatRequest::where('status', 'secretary_review')->count();
```

**Function**:
- Deputy dean is included in **role-specific statistics**
- **`secretary_review`** status is tracked in analytics
- Deputy dean performance is **measurable and reportable**

---

## 10. USER INTERFACE INTEGRATION

### Status Display Mapping
**Location**: `app/Services/ExeatNotificationService.php`

```php
// Line 708: Status display names
$statusMap = [
    'pending' => 'Pending Review',
    'cmd_review' => 'CMD Review',
    'secretary_review' => 'Deputy Dean Review',  // UI display name
    'parent_consent' => 'Parent Consent',
    'dean_review' => 'Dean Review',
];

// Line 749: Role display names
$roleMap = [
    'cmd' => 'CMD',
    'deputy_dean' => 'Deputy Dean',  // UI display name
    'dean' => 'Dean',
    'hostel_admin' => 'Hostel Administrator',
];

// Line 525: Staff office titles
$statusTitles = [
    'cmd' => 'Chief Medical Director',
    'deputy_dean' => 'Deputy Dean of Students Affairs',  // Full title
    'dean' => 'Dean of Students Affairs',
];
```

**Function**:
- **"Deputy Dean Review"** appears in UI status displays
- **"Deputy Dean"** appears in role displays
- **"Deputy Dean of Students Affairs"** appears in notifications

---

## 11. API ROUTES

### Deputy Dean Specific Routes
**Location**: `routes/api.php`

```php
// Line 223: Deputy Dean parent consent routes
Route::prefix('staff/parent-consents')->group(function () {
    Route::get('/pending', [StaffExeatRequestController::class, 'getPendingParentConsents']);
    // Deputy dean can view and act on pending parent consents
});
```

**Function**:
- Deputy dean has **specific API endpoints** for parent consent management
- Deputy dean can **view pending consents** and take action

---

## SUMMARY OF DEPUTY DEAN RESPONSIBILITIES

### 1. **Primary Workflow Role**
- **First approver** for non-medical exeats
- **Second approver** for medical exeats (after CMD)
- **Mandatory step** in the approval process

### 2. **Parent Consent Override Authority**
- Can **approve on behalf of parents** with reason
- Can **reject on behalf of parents** with reason
- **Bypasses parent consent** requirement when exercised

### 3. **System Access and Permissions**
- **Dashboard access** with role-specific metrics
- **API endpoints** for parent consent management
- **Notification recipient** for relevant exeat stages

### 4. **Database Integration**
- **Core status** in exeat workflow (`secretary_review`)
- **Tracked actions** in parent consent overrides
- **Role definition** in exeat roles system

### 5. **UI and Display Integration**
- **Status displays** show "Deputy Dean Review"
- **Role displays** show "Deputy Dean"
- **Notifications** use "Deputy Dean of Students Affairs"

---

## IMPACT OF CHANGING TO SECRETARIES

### Required Changes:
1. **Database migrations** to update role names and statuses
2. **Code updates** in 15+ files across controllers, services, and models
3. **UI updates** for status and role display names
4. **Permission updates** in authorization policies
5. **API route updates** and documentation
6. **Notification template updates**
7. **Analytics and reporting updates**

### Considerations:
- **Workflow logic** remains the same (approval sequence)
- **Parent override authority** may need review (appropriate for secretaries?)
- **Role permissions** may need adjustment (secretary vs deputy dean authority)
- **Database migration** needed for existing data
- **Testing required** for all affected workflows

The deputy dean role is **deeply integrated** throughout the system and serves as a **critical workflow step** with **significant authority** including parent consent overrides.