## API Endpoints Summary

### GET Endpoints
- `GET /api/parent/exeat-consent/{token}/{action}` - Public parent consent verification
- `GET /api/me` - Get current authenticated user profile
- `GET /api/user` - Get current user information
- `GET /api/student/exeat-requests` - List student's exeat requests
- `GET /api/student/exeat-requests/{id}` - Get specific student exeat request details
- `GET /api/student/exeat-requests/{id}/history` - Get exeat request approval history
- `GET /api/student/profile` - Get student profile information
- `GET /api/student/exeat-categories` - List available exeat categories
- `GET /api/staff/dashboard` - Get staff dashboard data
- `GET /api/staff/exeat-requests` - List exeat requests for staff approval that are yet to be acted upon by the logged in users role, but admin, dean can see all
- `GET /api/staff/exeat-requests/{id}` - Get specific exeat request for staff review
- `GET /api/staff/exeat-requests/role-history` - Get historical view of all exeat requests that have passed through the staff member's assigned roles
- `GET /api/dean/dashboard` - Get dean-specific dashboard data
- `GET /api/dean/exeat-requests` - List exeat requests requiring dean approval
- `GET /api/cmd/dashboard` - Get CMD (medical officer) dashboard data
- `GET /api/cmd/exeat-requests` - List exeat requests requiring CMD approval
- `GET /api/hostel/dashboard` - Get hostel admin dashboard data
- `GET /api/hostel/exeat-requests` - List exeat requests for hostel sign-out
- `GET /api/security/dashboard` - Get security dashboard data
- `GET /api/security/exeat-requests` - List exeat requests for security sign-out
- `GET /api/admin/staff` - List all staff members (admin only)
- `GET /api/admin/staff/{id}` - Get specific staff member details
- `GET /api/admin/staff/assignments` - Get staff role assignment history
- `GET /api/admin/roles` - List all available exeat roles
- `GET /api/chats` - List user's chat conversations
- `GET /api/chats/{id}` - Get specific chat conversation details
- `GET /api/notifications` - List user notifications
- `GET /api/lookup/departments` - List all departments
- `GET /api/lookup/hostels` - List all hostels
- `GET /api/lookup/roles` - List all system roles
- `GET /api/analytics/exeat-usage` - Get exeat usage analytics
- `GET /api/analytics/student-trends` - Get student behavior trends
- `GET /api/analytics/staff-performance` - Get staff performance metrics

### POST Endpoints
- `POST /api/login` - Authenticate user and get access token
- `POST /api/register` - Register new user account
- `POST /api/logout` - Logout and invalidate access token
- `PUT /api/profile` - Update user profile information
- `PUT /api/password` - Change user password
- `POST /api/student/exeat-requests` - Create new exeat request
- `POST /api/staff/exeat-requests/{id}/approve` - Approve exeat request
- `POST /api/staff/exeat-requests/{id}/reject` - Reject exeat request
- `POST /api/staff/exeat-requests/{id}/send-parent-consent` - Send parent consent request
- `POST /api/admin/staff` - Create new staff member
- `PUT /api/admin/staff/{id}` - Update staff member information
- `DELETE /api/admin/staff/{id}` - Delete staff member
- `POST /api/admin/roles` - Assign role to staff member
- `POST /api/admin/staff/{id}/assign-exeat-role` - Assign exeat role to staff
- `DELETE /api/admin/staff/{id}/unassign-exeat-role` - Remove exeat role from staff
- `POST /api/chats` - Create new chat conversation
- `POST /api/chats/{id}/messages` - Send message in chat
- `POST /api/notifications/mark-read` - Mark notifications as read
- `DELETE /api/notifications/{id}` - Delete specific notification



## API Status Analysis

### ✅ Working APIs (No Errors Detected)

#### Authentication & Profile
- `POST /api/login` - ✅ Complete implementation
- `POST /api/register` - ✅ Complete implementation
- `GET /api/me` - ✅ Complete implementation
- `GET /api/user` - ✅ Complete implementation
- `POST /api/logout` - ✅ Complete implementation
- `PUT /api/profile` - ✅ Complete implementation
- `PUT /api/password` - ✅ Complete implementation

#### Student APIs
- `GET /api/student/exeat-requests` - ✅ Complete implementation
- `POST /api/student/exeat-requests` - ✅ Complete implementation with validation
- `GET /api/student/exeat-requests/{id}` - ✅ Complete implementation
- `GET /api/student/exeat-requests/{id}/history` - ✅ Complete implementation
- `GET /api/student/exeat-categories` - ✅ Complete implementation
- `GET /api/student/profile` - ✅ Complete implementation

#### Staff APIs
- `GET /api/staff/dashboard` - ✅ Complete implementation
- `GET /api/staff/exeat-requests` - ✅ Complete implementation with role filtering
- `GET /api/staff/exeat-requests/{id}` - ✅ Complete implementation
- `POST /api/staff/exeat-requests/{id}/approve` - ✅ Complete implementation
- `POST /api/staff/exeat-requests/{id}/reject` - ✅ Complete implementation
- `POST /api/staff/exeat-requests/{id}/send-parent-consent` - ✅ Complete implementation
- `GET /api/staff/exeat-requests/{id}/history` - ✅ Complete implementation
- `GET /api/staff/exeat-requests/role-history` - ✅ Complete implementation with role-based historical filtering

#### Admin APIs
- `GET /api/admin/staff` - ✅ Complete implementation
- `POST /api/admin/staff` - ✅ Complete implementation with validation
- `GET /api/admin/staff/{id}` - ✅ Complete implementation
- `PUT /api/admin/staff/{id}` - ✅ Complete implementation
- `DELETE /api/admin/staff/{id}` - ✅ Complete implementation
- `GET /api/admin/staff/assignments` - ✅ Complete implementation
- `POST /api/admin/staff/{id}/assign-exeat-role` - ✅ Complete implementation
- `DELETE /api/admin/staff/{id}/unassign-exeat-role` - ✅ Complete implementation
- `GET /api/admin/roles` - ✅ Complete implementation
- `POST /api/admin/roles` - ✅ Complete implementation
- `PUT /api/admin/roles/{id}` - ✅ Complete implementation
- `DELETE /api/admin/roles/{id}` - ✅ Complete implementation

#### Chat System
- `GET /api/chats` - ✅ Complete implementation
- `POST /api/chats` - ✅ Complete implementation with validation
- `GET /api/chats/{id}` - ✅ Complete implementation
- `POST /api/chats/{id}/messages` - ✅ Complete implementation

#### Notifications
- `GET /api/notifications` - ✅ Complete implementation
- `POST /api/notifications/mark-read` - ✅ Complete implementation
- `DELETE /api/notifications/{id}` - ✅ Complete implementation

#### Parent Consent
- `GET /api/parent/exeat-consent/{token}/{action}` - ✅ Complete implementation
- `POST /api/parent/consent/{token}/approve` - ✅ Complete implementation
- `POST /api/parent/consent/{token}/decline` - ✅ Complete implementation

### ⚠️ APIs with Potential Issues

#### Lookup APIs
- `GET /api/lookup/departments` - ⚠️ **Missing Controller Implementation**
- `GET /api/lookup/hostels` - ⚠️ **Missing Controller Implementation**
- `GET /api/lookup/roles` - ⚠️ **Missing Controller Implementation**

**Issue**: Routes defined but AdminConfigController methods not implemented.

#### Analytics APIs
- `GET /api/analytics/exeat-usage` - ⚠️ **Missing Controller Implementation**
- `GET /api/analytics/student-trends` - ⚠️ **Missing Controller Implementation**
- `GET /api/analytics/staff-performance` - ⚠️ **Missing Controller Implementation**

**Issue**: Routes defined but ReportController methods not implemented.

#### Communication APIs
- `POST /api/send-email` - ⚠️ **Missing Controller Implementation**
- `POST /api/send-sms` - ⚠️ **Missing Controller Implementation**

**Issue**: Routes defined but CommunicationController methods not implemented.

### 📋 Special Endpoint Documentation

#### Role History Endpoint
**`GET /api/staff/exeat-requests/role-history`**

This endpoint provides a comprehensive historical view of all exeat requests that have passed through the staff member's assigned roles during the approval workflow.

**Key Features:**
- **Role-Based History**: Shows ALL exeat requests that have been processed through the staff member's role stages, regardless of who actually performed the action
- **Workflow Tracking**: Tracks requests that have moved through specific workflow stages (e.g., cmd_review, deputy-dean_review, dean_review, etc.)
- **Enhanced Permissions**: Admin, Dean, and Dean2 roles can view ALL exeat requests in the system
- **Complete Approval Chain**: Returns full approval history for each request with staff information
- **Pagination**: Results are paginated (20 requests per page) for performance

**Use Cases:**
- **Audit Trail**: Monitor all requests processed through your department/role
- **Workflow Analysis**: Review approval patterns and decision history
- **Role Management**: Understand which requests fall under your jurisdiction
- **Historical Oversight**: Track past decisions across different workflow stages

**Response Data:**
- List of exeat requests with complete approval history
- Student information (name, passport)
- Staff member's assigned roles
- Statuses that their roles can handle
- Acting roles for each specific request
- Pagination metadata

**Example**: If you have the 'cmd' role, you'll see all requests that went through the 'cmd_review' stage, even if a dean or admin processed them on behalf of the CMD role.

#### Role-Specific Dashboard APIs
- `GET /api/dean/dashboard` - ⚠️ **Missing Controller Implementation**
- `GET /api/dean/exeat-requests` - ⚠️ **Missing Controller Implementation**
- `GET /api/cmd/dashboard` - ⚠️ **Missing Controller Implementation**
- `GET /api/cmd/exeat-requests` - ⚠️ **Missing Controller Implementation**
- `GET /api/hostel/dashboard` - ⚠️ **Missing Controller Implementation**
- `GET /api/hostel/exeat-requests` - ⚠️ **Missing Controller Implementation**
- `GET /api/security/dashboard` - ⚠️ **Missing Controller Implementation**
- `GET /api/security/exeat-requests` - ⚠️ **Missing Controller Implementation**
