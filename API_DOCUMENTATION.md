# Comprehensive API Documentation

## Base URL
```
Production: https://your-domain.com/api
Development: http://localhost:8000/api
```

## Authentication
All protected endpoints require a Bearer token in the Authorization header:
```
Authorization: Bearer {your_token_here}
```

---

## Authentication APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /me | GET | Auth | Get current authenticated user profile | Yes |
| /user | GET | Auth | Get current user information | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /login | POST | Auth | Authenticate user and get access token | No |
| /register | POST | Auth | Register new user account | No |
| /logout | POST | Auth | Logout and invalidate access token | Yes |

### PUT Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /profile | PUT | Auth | Update user profile information | Yes |
| /password | PUT | Auth | Change user password | Yes |

---

## Student APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /student/profile | GET | Student | Get student profile information | Yes |
| /student/exeat-categories | GET | Student | Get available exeat categories | Yes |
| /student/exeat-requests | GET | Student | List student's exeat requests | Yes |
| /student/exeat-requests/{id} | GET | Student | Get specific student exeat request details | Yes |
| /student/exeat-requests/{id}/history | GET | Student | Get exeat request approval history | Yes |
| /student/notifications | GET | Student | Get student notifications | Yes |
| /student/notifications/unread-count | GET | Student | Get unread notification count | Yes |
| /student/notifications/preferences | GET | Student | Get notification preferences | Yes |
| /student/notifications/{id} | GET | Student | Get specific notification | Yes |
| /student/notifications/exeat/{exeatId} | GET | Student | Get exeat-specific notifications | Yes |
| /student/notifications/statistics/overview | GET | Student | Get notification statistics | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /student/exeat-requests | POST | Student | Create new exeat request | Yes |
| /student/notifications/{id}/mark-read | POST | Student | Mark notification as read | Yes |
| /student/notifications/test | POST | Student | Test notification system | Yes |

### PUT Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /student/notifications/preferences | PUT | Student | Update notification preferences | Yes |

### POST (Special) Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /student/notifications/preferences/reset | POST | Student | Reset notification preferences | Yes |

---

## Staff APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /staff/dashboard | GET | Staff | Get staff dashboard data | Yes |
| /staff/exeat-requests | GET | Staff | List exeat requests for staff approval | Yes |
| /staff/exeat-requests/{id} | GET | Staff | Get specific exeat request for staff review | Yes |
| /staff/exeat-requests/{id}/history | GET | Staff | Get exeat request approval history | Yes |
| /staff/exeat-requests/role-history | GET | Staff | Get role-based approval history | Yes |
| /staff/notifications | GET | Staff | Get staff notifications | Yes |
| /staff/notifications/unread-count | GET | Staff | Get unread notification count | Yes |
| /staff/notifications/preferences | GET | Staff | Get notification preferences | Yes |
| /staff/notifications/pending-approvals | GET | Staff | Get pending approval notifications | Yes |
| /staff/notifications/statistics/overview | GET | Staff | Get notification statistics | Yes |
| /staff/notifications/{id} | GET | Staff | Get specific notification | Yes |
| /staff/notifications/exeat/{exeatId} | GET | Staff | Get exeat-specific notifications | Yes |
| /staff/parent-consents/pending | GET | Staff | Get pending parent consents | Yes |
| /staff/parent-consents/statistics | GET | Staff | Get parent consent statistics | Yes |
| /staff/exeat-history | GET | Staff | Get staff exeat history | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /staff/exeat-requests/{id}/approve | POST | Staff | Approve exeat request | Yes |
| /staff/exeat-requests/{id}/reject | POST | Staff | Reject exeat request | Yes |
| /staff/exeat-requests/{id}/send-parent-consent | POST | Staff | Send parent consent request | Yes |
| /staff/notifications/{id}/mark-read | POST | Staff | Mark notification as read | Yes |
| /staff/notifications/send-to-student | POST | Staff | Send notification to student | Yes |
| /staff/notifications/send-reminder | POST | Staff | Send reminder notification | Yes |
| /staff/notifications/send-emergency | POST | Staff | Send emergency notification | Yes |
| /staff/parent-consents/{consentId}/approve | POST | Staff | Approve parent consent | Yes |
| /staff/parent-consents/{consentId}/reject | POST | Staff | Reject parent consent | Yes |

### PUT Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /staff/notifications/preferences | PUT | Staff | Update notification preferences | Yes |

---

## Admin APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /admin/roles | GET | Admin | List all available exeat roles | Yes |
| /admin/staff | GET | Admin | List all staff members | Yes |
| /admin/staff/{id} | GET | Admin | Get specific staff member details | Yes |
| /admin/staff/assignments | GET | Admin | Get staff role assignment history | Yes |
| /admin/notifications | GET | Admin | Get admin notifications | Yes |
| /admin/notifications/statistics | GET | Admin | Get notification statistics | Yes |
| /admin/notifications/delivery-logs | GET | Admin | Get notification delivery logs | Yes |
| /admin/notifications/user-preferences/{userId} | GET | Admin | Get user notification preferences | Yes |
| /admin/notifications/preferences-statistics | GET | Admin | Get preferences statistics | Yes |
| /admin/notifications/templates | GET | Admin | Get notification templates | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /admin/roles | POST | Admin | Create new role | Yes |
| /admin/staff | POST | Admin | Create new staff member | Yes |
| /admin/staff/{id}/assign-exeat-role | POST | Admin | Assign exeat role to staff | Yes |
| /admin/notifications/bulk-send | POST | Admin | Send bulk notifications | Yes |
| /admin/notifications/retry-failed | POST | Admin | Retry failed notification deliveries | Yes |
| /admin/notifications/clear-preferences-cache | POST | Admin | Clear preferences cache | Yes |

### PUT Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /admin/roles/{id} | PUT | Admin | Update role information | Yes |
| /admin/staff/{id} | PUT | Admin | Update staff member information | Yes |
| /admin/notifications/user-preferences/{userId} | PUT | Admin | Update user notification preferences | Yes |

### DELETE Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /admin/roles/{id} | DELETE | Admin | Delete role | Yes |
| /admin/staff/{id} | DELETE | Admin | Delete staff member | Yes |
| /admin/staff/{id}/unassign-exeat-role | DELETE | Admin | Remove exeat role from staff | Yes |

---

## Role-Specific APIs

### Dean APIs
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /dean/dashboard | GET | Dean | Get dean-specific dashboard data | Yes |
| /dean/exeat-requests | GET | Dean | List exeat requests requiring dean approval | Yes |
| /dean/notifications | GET | Dean | Get dean notifications | Yes |
| /dean/notifications/unread-count | GET | Dean | Get unread notification count | Yes |
| /dean/notifications/preferences | GET | Dean | Get notification preferences | Yes |
| /dean/notifications/pending-approvals | GET | Dean | Get pending approval notifications | Yes |
| /dean/notifications/statistics/overview | GET | Dean | Get notification statistics | Yes |
| /dean/notifications/{id} | GET | Dean | Get specific notification | Yes |
| /dean/notifications/exeat/{exeatId} | GET | Dean | Get exeat-specific notifications | Yes |
| /dean/notifications/{id}/mark-read | POST | Dean | Mark notification as read | Yes |
| /dean/notifications/mark-all-read | POST | Dean | Mark all notifications as read | Yes |
| /dean/notifications/send-to-students | POST | Dean | Send notification to students | Yes |
| /dean/notifications/send-reminder | POST | Dean | Send reminder notification | Yes |
| /dean/notifications/send-emergency | POST | Dean | Send emergency notification | Yes |
| /dean/notifications/preferences | PUT | Dean | Update notification preferences | Yes |

### CMD APIs
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /cmd/dashboard | GET | CMD | Get CMD (medical officer) dashboard data | Yes |
| /cmd/exeat-requests | GET | CMD | List exeat requests requiring CMD approval | Yes |

### Hostel APIs
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /hostel/dashboard | GET | Hostel | Get hostel admin dashboard data | Yes |
| /hostel/exeat-requests | GET | Hostel | List exeat requests for hostel sign-out | Yes |

### Security APIs
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /security/dashboard | GET | Security | Get security dashboard data | Yes |
| /security/exeat-requests | GET | Security | List exeat requests for security sign-out | Yes |

---

## Parent Consent APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /parent/exeat-consent/{token}/{action} | GET | Parent | Public parent consent verification | No |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /parent/consent/{token}/approve | POST | Parent | Approve parent consent | No |
| /parent/consent/{token}/decline | POST | Parent | Decline parent consent | No |

---

## Chat & Communication APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /chats | GET | Chat | List user's chat conversations | Yes |
| /chats/{id} | GET | Chat | Get specific chat conversation details | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /chats | POST | Chat | Create new chat conversation | Yes |
| /chats/{id}/messages | POST | Chat | Send message in chat | Yes |
| /send-email | POST | Communication | Send email | Yes |
| /send-sms | POST | Communication | Send SMS | Yes |

---

## Notification APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /notifications | GET | Notification | List user notifications | Yes |

### POST Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /notifications/mark-read | POST | Notification | Mark notifications as read | Yes |

### DELETE Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /notifications/{id} | DELETE | Notification | Delete specific notification | Yes |

---

## Analytics & Reporting APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /analytics/exeat-usage | GET | Analytics | Get exeat usage analytics | Yes |
| /analytics/student-trends | GET | Analytics | Get student behavior trends | Yes |
| /analytics/staff-performance | GET | Analytics | Get staff performance metrics | Yes |
| /exeats/by-status/{status} | GET | Analytics | Get exeats by status | Yes |
| /exeats/statistics | GET | Analytics | Get exeat statistics | Yes |

---

## Lookup Data APIs

### GET Endpoints
| Endpoint | Method | Category | Description | Auth Required |
|----------|--------|----------|-------------|---------------|
| /lookup/departments | GET | Lookup | List all departments | Yes |
| /lookup/hostels | GET | Lookup | List all hostels | Yes |
| /lookup/roles | GET | Lookup | List all system roles | Yes |

---

## Summary by HTTP Methods

### GET Endpoints (67 total)
- Authentication: 2 endpoints
- Student: 10 endpoints
- Staff: 13 endpoints
- Admin: 10 endpoints
- Dean: 9 endpoints
- CMD: 2 endpoints
- Hostel: 2 endpoints
- Security: 2 endpoints
- Parent: 1 endpoint
- Chat: 2 endpoints
- Notification: 1 endpoint
- Analytics: 5 endpoints
- Lookup: 3 endpoints
- Other: 5 endpoints

### POST Endpoints (35 total)
- Authentication: 3 endpoints
- Student: 3 endpoints
- Staff: 8 endpoints
- Admin: 5 endpoints
- Dean: 5 endpoints
- Parent: 2 endpoints
- Chat: 2 endpoints
- Communication: 2 endpoints
- Notification: 1 endpoint
- Other: 4 endpoints

### PUT Endpoints (5 total)
- Authentication: 2 endpoints
- Student: 1 endpoint
- Staff: 1 endpoint
- Admin: 2 endpoints
- Dean: 1 endpoint

### DELETE Endpoints (4 total)
- Admin: 3 endpoints
- Notification: 1 endpoint

---

**Total APIs: 111 endpoints**
- Public endpoints: 5
- Protected endpoints: 106
- Authentication required: 106 endpoints
- Role-specific endpoints: 45 endpoints

## Middleware & Security
- **Authentication**: Laravel Sanctum (Bearer tokens)
- **Role-based Access**: Custom role middleware
- **Admin Override**: Hardcoded admin access for staff IDs 596, 2, and 3
- **Rate Limiting**: Applied to sensitive endpoints

## Key Features
- **Exeat Management**: Complete workflow from request to approval
- **Multi-role Support**: Dean, CMD, Hostel Admin, Security, Admin roles
- **Notification System**: Comprehensive notification management
- **Parent Consent**: Token-based parent approval system
- **Chat System**: Real-time messaging capabilities
- **Analytics**: Usage statistics and reporting
- **Audit Logging**: Complete activity tracking
