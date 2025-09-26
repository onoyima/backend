# Complete Migration Plan: Deputy Dean → Secretary

## Overview

This document provides a step-by-step plan to migrate from "Deputy Dean" to "Secretary" role throughout the exeat system. The migration involves database changes, code updates, and careful consideration of authority levels.

---

# PHASE 1: PLANNING & DECISIONS

## 1.1 Authority Level Decisions

**Current Deputy Dean Powers:**
- Approve/reject exeat requests
- **Override parent consent** (approve/reject on behalf of parents)
- Access to parent consent management
- Dashboard with role-specific metrics

**Questions to Decide:**
1. Should **Secretary** have parent consent override authority?
2. Should **Secretary** maintain same approval authority?
3. What should the full title be? ("Secretary of Students Affairs"?)
4. Should workflow position remain the same?

**Recommended Approach:**
- **Keep same authority** (Secretary inherits all Deputy Dean powers)
- **Keep workflow position** (maintains system stability)
- **Update titles** to reflect Secretary role

---

# PHASE 2: DATABASE MIGRATION

## 2.1 Create Migration for Role Updates

**File**: `database/migrations/2024_XX_XX_XXXXXX_migrate_deputy_dean_to_secretary.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update exeat_roles table
        DB::table('exeat_roles')
            ->where('name', 'deputy_dean')
            ->update([
                'name' => 'secretary',
                'display_name' => 'Secretary',
                'description' => 'Can approve/reject exeat requests and act on behalf of parents when needed.',
                'updated_at' => now()
            ]);

        // Update existing staff role assignments
        // This preserves all current deputy dean staff assignments
        // No changes needed to staff_exeat_roles table as it references exeat_roles.id
        
        // Update parent consent action types
        DB::table('parent_consents')
            ->where('action_type', 'deputy_dean_approval')
            ->update(['action_type' => 'secretary_approval']);
            
        DB::table('parent_consents')
            ->where('action_type', 'deputy_dean_rejection')
            ->update(['action_type' => 'secretary_rejection']);
    }

    public function down(): void
    {
        // Reverse the changes
        DB::table('exeat_roles')
            ->where('name', 'secretary')
            ->update([
                'name' => 'deputy_dean',
                'display_name' => 'Deputy Dean',
                'description' => 'Can approve/reject exeat requests only after CMD (for medical) or parent has recommended/approved.',
                'updated_at' => now()
            ]);

        DB::table('parent_consents')
            ->where('action_type', 'secretary_approval')
            ->update(['action_type' => 'deputy_dean_approval']);
            
        DB::table('parent_consents')
            ->where('action_type', 'secretary_rejection')
            ->update(['action_type' => 'deputy_dean_rejection']);
    }
};
```

## 2.2 Update Status Enum Migration

**File**: `database/migrations/2024_XX_XX_XXXXXX_update_exeat_status_enum.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing records first
        DB::table('exeat_requests')
            ->where('status', 'secretary_review')
            ->update(['status' => 'secretary_review']);

        // Alter the enum to replace secretary_review with secretary_review
        DB::statement("ALTER TABLE exeat_requests MODIFY COLUMN status ENUM(
            'pending', 
            'cmd_review', 
            'secretary_review',
            'parent_consent', 
            'dean_review', 
            'hostel_signout', 
            'security_signout', 
            'security_signin', 
            'hostel_signin', 
            'completed', 
            'rejected', 
            'appeal'
        ) DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Reverse the changes
        DB::table('exeat_requests')
            ->where('status', 'secretary_review')
            ->update(['status' => 'secretary_review']);

        DB::statement("ALTER TABLE exeat_requests MODIFY COLUMN status ENUM(
            'pending', 
            'cmd_review', 
            'secretary_review',
            'parent_consent', 
            'dean_review', 
            'hostel_signout', 
            'security_signout', 
            'security_signin', 
            'hostel_signin', 
            'completed', 
            'rejected', 
            'appeal'
        ) DEFAULT 'pending'");
    }
};
```

## 2.3 Update Parent Consents Table

**File**: `database/migrations/2024_XX_XX_XXXXXX_update_parent_consents_for_secretary.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_consents', function (Blueprint $table) {
            // Rename deputy_dean_reason to secretary_reason
            $table->renameColumn('deputy_dean_reason', 'secretary_reason');
        });

        // Update action_type enum
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM(
            'parent', 
            'secretary_approval', 
            'secretary_rejection'
        ) DEFAULT 'parent'");

        // Update comment
        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN secretary_reason TEXT NULL COMMENT 'Reason provided by Secretary for override'");
    }

    public function down(): void
    {
        Schema::table('parent_consents', function (Blueprint $table) {
            $table->renameColumn('secretary_reason', 'deputy_dean_reason');
        });

        DB::statement("ALTER TABLE parent_consents MODIFY COLUMN action_type ENUM(
            'parent', 
            'deputy_dean_approval', 
            'deputy_dean_rejection'
        ) DEFAULT 'parent'");
    }
};
```

---

# PHASE 3: CODE UPDATES

## 3.1 Student Exeat Request Controller

**File**: `app/Http/Controllers/StudentExeatRequestController.php`

```php
// Line 99: Update initial status
$initialStatus = $isMedical ? 'cmd_review' : 'secretary_review';

// Line 121: Update role assignment
'role' => $isMedical ? 'cmd' : 'secretary',

// Line 136: Update notification role
$role = $isMedical ? 'cmd' : 'secretary';
```

## 3.2 Exeat Workflow Service

**File**: `app/Services/ExeatWorkflowService.php`

```php
// Line 103: Update status progression
case 'pending':
    $exeatRequest->status = $exeatRequest->is_medical ? 'cmd_review' : 'secretary_review';
    break;
case 'cmd_review':
    $exeatRequest->status = 'secretary_review';
    break;
case 'secretary_review':
    $exeatRequest->status = 'parent_consent';
    break;

// Line 466: Rename method
public function secretaryParentConsentApprove(ParentConsent $parentConsent, int $secretaryId, string $reason)
{
    $parentConsent->consent_status = 'approved';
    $parentConsent->consent_timestamp = now();
    $parentConsent->acted_by_staff_id = $secretaryId;
    $parentConsent->action_type = 'secretary_approval';
    $parentConsent->secretary_reason = $reason;
    // ... rest of method
}

// Line 516: Rename method
public function secretaryParentConsentReject(ParentConsent $parentConsent, int $secretaryId, string $reason)
{
    $parentConsent->consent_status = 'declined';
    $parentConsent->consent_timestamp = now();
    $parentConsent->acted_by_staff_id = $secretaryId;
    $parentConsent->action_type = 'secretary_rejection';
    $parentConsent->secretary_reason = $reason;
    // ... rest of method
}

// Line 781: Update role mapping
$roleMap = [
    'cmd_review' => 'cmd',
    'secretary_review' => 'secretary',
    'dean_review' => 'dean',
    'hostel_signout' => 'hostel_admin',
];
```

## 3.3 Exeat Notification Service

**File**: `app/Services/ExeatNotificationService.php`

```php
// Line 525: Update status titles
$statusTitles = [
    'cmd' => 'Chief Medical Director',
    'secretary' => 'Secretary of Students Affairs',
    'dean' => 'Dean of Students Affairs',
];

// Line 617: Update stage change notifications
case 'secretary_review':
    $recipients = array_merge($recipients, $this->getSecretaryStaff());
    break;

// Line 652: Update approval recipients
case 'secretary':
    return $this->getSecretaryStaff();

// Line 671: Update reminder recipients
case 'parent_consent_pending':
    return $this->getSecretaryStaff();

// Line 696: Update emergency recipients
$this->getSecretaryStaff()

// Line 709: Update status map
'secretary_review' => 'Secretary Review',

// Line 749: Update role map
'secretary' => 'Secretary',

// Line 822: Rename method
protected function getSecretaryStaff(): array
{
    return Staff::whereHas('exeat_roles', function ($query) {
        $query->where('name', 'secretary');
    })->get()->map(function ($staff) {
        return [
            'type' => ExeatNotification::RECIPIENT_STAFF,
            'id' => $staff->id
        ];
    })->toArray();
}
```

## 3.4 Staff Exeat Request Controller

**File**: `app/Http/Controllers/StaffExeatRequestController.php`

```php
// Line 33: Update active statuses
$activeStatuses = [
    'pending', 'cmd_review', 'secretary_review', 'parent_consent',
    'dean_review', 'hostel_signout', 'security_signout', 'security_signin',
    'hostel_signin', 'cancelled'
];

// Line 40: Update role status map
$roleStatusMap = [
    'cmd' => ['cmd_review'],
    'secretary' => ['secretary_review', 'parent_consent'],
    'dean' => $activeStatuses,
];

// Line 65: Update role map
$roleMap = [
    'cmd_review' => 'cmd',
    'secretary_review' => 'secretary',
    'dean_review' => 'dean',
];

// Line 1162: Update statistics queries
$totalActedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->whereIn('action_type', ['secretary_approval', 'secretary_rejection'])
    ->count();

$approvedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->where('action_type', 'secretary_approval')
    ->count();

$rejectedBySecretary = \App\Models\ParentConsent::where('acted_by_staff_id', $user->id)
    ->where('action_type', 'secretary_rejection')
    ->count();

// Update response
'data' => [
    'pending_consents' => $totalPending,
    'total_acted_by_secretary' => $totalActedBySecretary,
    'approved_by_secretary' => $approvedBySecretary,
    'rejected_by_secretary' => $rejectedBySecretary
]
```

## 3.5 Staff Notification Controller

**File**: `app/Http/Controllers/StaffNotificationController.php`

```php
// Line 209: Update status mapping
$status = match ($role) {
    'cmd' => 'cmd_review',
    'secretary' => 'secretary_review',
    'dean' => 'dean_review',
    'hostel_admin' => 'hostel_signout',
};

// Line 337: Update status mapping (duplicate)
$status = match ($role) {
    'cmd' => 'cmd_review',
    'secretary' => 'secretary_review',
    'dean' => 'dean_review',
    'hostel_admin' => 'hostel_signout',
};
```

## 3.6 Authorization Updates

**File**: `app/Providers/AuthServiceProvider.php`

```php
// Line 49: Update allowed roles
$allowedRoles = [
    'staff', 'teacher', 'housemaster', 'security', 
    'dean', 'admin', 'super_admin', 'cmd', 
    'secretary',  // Changed from deputy_dean
    'hostel_admin'
];
```

**File**: `app/Policies/DashboardPolicy.php`

```php
// Line 36: Update allowed roles
$allowedRoles = [
    'staff', 'teacher', 'housemaster', 'security', 
    'dean', 'admin', 'super_admin', 'cmd', 
    'secretary',  // Changed from deputy_dean
    'hostel_admin'
];
```

## 3.7 Statistics and Analytics

**File**: `app/Http/Controllers/StaffExeatStatisticsController.php`

```php
// Line 74: Update roles array
$roles = ['cmd', 'secretary', 'dean', 'hostel_admin', 'security'];

// Line 110: Update validation
'role' => 'nullable|string|in:cmd,secretary,dean,hostel_admin,security'
```

**File**: `app/Services/DashboardAnalyticsService.php`

```php
// Line 59: Update status reference
$parentRequestpending = ExeatRequest::where('status', 'secretary_review')->count();
```

## 3.8 Model Updates

**File**: `app/Models/StaffExeatRole.php`

```php
// Line 9: Update comment
/**
 * staff_exeat_roles links staff to exeat_roles (dean, secretary, cmd, etc.)
 * Used to enforce workflow and permissions.
 */
```

**File**: `app/Models/ParentConsent.php`

```php
// Line 22: Update fillable field
'secretary_reason',  // Changed from deputy_dean_reason
```

**File**: `app/Models/ExeatRequest.php`

```php
// Line 236: Update status comment
// pending, cmd_review, secretary_review, parent_consent, dean_review,
// hostel_signout, security_signout, security_signin, hostel_signin,
// completed, rejected, appeal
```

**File**: `app/Models/ExeatApproval.php`

```php
// Line 17: Update comment
/**
 * The role of the staff member for this approval step (dean, secretary, cmd, etc.)
 * Used to enforce workflow logic in controllers.
 */
```

---

# PHASE 4: TESTING PLAN

## 4.1 Database Migration Testing

```bash
# Run migrations
php artisan migrate

# Verify role updates
SELECT * FROM exeat_roles WHERE name = 'secretary';

# Verify status updates
SELECT status, COUNT(*) FROM exeat_requests GROUP BY status;

# Verify parent consent updates
SELECT action_type, COUNT(*) FROM parent_consents GROUP BY action_type;
```

## 4.2 Workflow Testing

1. **Create new exeat request** (should go to secretary_review)
2. **Secretary approval** (should trigger parent_consent)
3. **Secretary parent override** (should work with new action types)
4. **Notification system** (secretary should receive notifications)
5. **Dashboard metrics** (secretary statistics should display)

## 4.3 API Testing

```bash
# Test exeat creation
POST /api/student/exeat-requests
# Verify status is 'secretary_review'

# Test secretary approval
POST /api/staff/exeat-requests/{id}/approve
# Verify workflow progression

# Test parent consent override
POST /api/staff/parent-consents/{id}/approve-on-behalf
# Verify secretary override functionality
```

---

# PHASE 5: DEPLOYMENT CHECKLIST

## 5.1 Pre-Deployment

- [ ] **Backup database** (critical - contains existing deputy dean data)
- [ ] **Test migrations** on staging environment
- [ ] **Verify all code changes** are complete
- [ ] **Update API documentation**
- [ ] **Prepare rollback plan**

## 5.2 Deployment Steps

1. **Put system in maintenance mode**
   ```bash
   php artisan down
   ```

2. **Run database migrations**
   ```bash
   php artisan migrate
   ```

3. **Clear caches**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Bring system back online**
   ```bash
   php artisan up
   ```

## 5.3 Post-Deployment Verification

- [ ] **Login as secretary** (formerly deputy dean)
- [ ] **Create test exeat request** (verify secretary_review status)
- [ ] **Test secretary approval** workflow
- [ ] **Test parent consent override** functionality
- [ ] **Verify dashboard** shows secretary metrics
- [ ] **Check notifications** are sent to secretary
- [ ] **Test API endpoints** return correct data

---

# PHASE 6: ROLLBACK PLAN

## 6.1 If Issues Occur

1. **Put system in maintenance mode**
2. **Run rollback migrations**
   ```bash
   php artisan migrate:rollback --step=3
   ```
3. **Restore code changes** (git revert)
4. **Clear caches**
5. **Bring system back online**

## 6.2 Data Integrity Check

```sql
-- Verify rollback worked
SELECT * FROM exeat_roles WHERE name = 'deputy_dean';
SELECT DISTINCT status FROM exeat_requests;
SELECT DISTINCT action_type FROM parent_consents;
```

---

# ESTIMATED EFFORT

## Time Requirements

- **Planning & Decisions**: 2-4 hours
- **Database Migrations**: 4-6 hours
- **Code Updates**: 8-12 hours
- **Testing**: 6-8 hours
- **Documentation Updates**: 2-3 hours
- **Deployment**: 2-3 hours

**Total Estimated Time**: 24-36 hours

## Risk Level: **MEDIUM-HIGH**

**Risks**:
- Database migration affects existing data
- Multiple interconnected systems
- Workflow disruption if errors occur
- User confusion during transition

**Mitigation**:
- Comprehensive testing on staging
- Complete database backup
- Rollback plan ready
- User communication about changes

---

# SUCCESS CRITERIA

## Migration is Successful When:

1. **All existing deputy dean staff** can login and function as secretaries
2. **New exeat requests** go to `secretary_review` status
3. **Secretary approval** progresses workflow correctly
4. **Parent consent override** works with secretary role
5. **Dashboard and statistics** show secretary data
6. **Notifications** are sent to secretary staff
7. **No data loss** or corruption occurs
8. **All API endpoints** return correct data
9. **UI displays** show "Secretary" instead of "Deputy Dean"

The migration is **comprehensive but manageable** with proper planning and testing. The key is maintaining data integrity while updating all interconnected systems consistently.