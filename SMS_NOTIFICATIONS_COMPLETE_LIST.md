# Complete List of SMS Notifications in the Exeat System

## Overview

This document lists all places where SMS notifications are sent in the exeat management system, including the triggers, recipients, and message content.

---

## 1. Parent Consent Notifications (ExeatWorkflowService)

### Location: `app/Services/ExeatWorkflowService.php`

**Trigger**: When parent consent is required for exeat approval

**Recipients**: Parents/Guardians

**SMS Content**:
```
Dear Parent of [Student Name], reason: "[Exeat Reason]".
Approve: [Approval Link]
Reject: [Rejection Link]
Valid until: [Expiry Date]
```

**Delivery Methods**:
- **Preferred Mode: "any"**: WhatsApp first, SMS fallback
- **Preferred Mode: "text"**: Direct SMS
- **Preferred Mode: "whatsapp"**: WhatsApp only

**Implementation**:
```php
// Method: sendSmsOrWhatsapp()
$notificationSMS = "Dear Parent of $studentName, reason: \"$reason\".\nApprove: $linkApprove\nReject: $linkReject\nValid until: $expiryText";

// For "any" mode
$this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'whatsapp');
// Fallback to SMS if WhatsApp fails
$this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'sms');

// For "text" mode
$this->sendSmsOrWhatsapp($parentPhone, $notificationSMS, 'sms');
```

---

## 2. Staff Comment Notifications (ExeatNotificationService)

### Location: `app/Services/ExeatNotificationService.php`

**Trigger**: When staff adds comments to exeat requests

**Recipients**: Students (associated with the exeat request)

**SMS Content**: Raw comment only (no template formatting)
```
[Staff Comment Text Only]
```

**Key Features**:
- **Email**: Full template with staff name and office
- **SMS**: Raw comment only for character efficiency
- **No student name**: SMS doesn't include "Dear [Student]" prefix

**Implementation**:
```php
// Method: sendStaffCommentNotification()
// SMS message is just the raw comment (no template)
$smsMessage = $comment;

// Create separate SMS notification with raw comment
$smsNotification = $this->createStaffCommentSmsNotification($exeatRequest, $student, $smsMessage);

// Deliver SMS with raw comment only
$this->deliveryService->deliverNotification($smsNotification, 'sms');
```

---

## 3. Debt Clearance Notifications (ExeatNotificationService)

### Location: `app/Services/ExeatNotificationService.php`

**Trigger**: When student debt is cleared (payment verified or manually cleared)

**Recipients**: Students (who had the debt)

**SMS Content**:
```
EXEAT NOTIFICATION: Your debt for exeat #[Exeat ID] has been cleared successfully. Thank you.
```

**Implementation**:
```php
// Method: sendDebtClearanceNotification()
if ($student->phone) {
    $this->deliveryService->queueNotificationDelivery(
        $this->createDebtClearanceSmsNotification($exeatRequest, $student),
        'sms'
    );
}

// SMS Content
$smsContent = "EXEAT NOTIFICATION: Your debt for exeat #{$exeatRequest->id} has been cleared successfully. Thank you.";
```

---

## 4. Exeat Modification Notifications (ExeatNotificationService)

### Location: `app/Services/ExeatNotificationService.php`

**Trigger**: When admin/staff modifies exeat request details

**Recipients**: Students (whose exeat was modified)

**SMS Content**:
```
EXEAT ALERT: [Modification Message] Please check your exeat dashboard for details.
```

**Implementation**:
```php
// Method: sendExeatModifiedNotification()
if ($student && $student->phone) {
    $this->deliveryService->queueNotificationDelivery(
        $this->createExeatModifiedSmsNotification($exeatRequest, $student, $message),
        'sms'
    );
}

// SMS Content
$smsContent = "EXEAT ALERT: {$message} Please check your exeat dashboard for details.";
```

---

## 5. Debt Recalculation Notifications (ExeatNotificationService)

### Location: `app/Services/ExeatNotificationService.php`

**Trigger**: When student debt is recalculated due to return date changes

**Recipients**: Students (whose debt was recalculated)

**SMS Content**:
```
EXEAT DEBT ALERT: Your debt has been recalculated. Additional: ₦[Amount]. Total: ₦[Total]. Check dashboard for details.
```

**Implementation**:
```php
// Method: sendDebtRecalculationNotification()
if ($student->phone) {
    $this->deliveryService->queueNotificationDelivery(
        $this->createDebtRecalculationSmsNotification($exeatRequest, $student, $additionalAmount, $totalAmount),
        'sms'
    );
}

// SMS Content
$smsContent = "EXEAT DEBT ALERT: Your debt has been recalculated. Additional: ₦{$additionalAmount}. Total: ₦{$totalAmount}. Check dashboard for details.";
```

---

## 6. General SMS Delivery System (NotificationDeliveryService)

### Location: `app/Services/NotificationDeliveryService.php`

**Purpose**: Core SMS delivery infrastructure using Twilio

**Features**:
- **Phone Formatting**: Uses `PhoneUtility::formatForSMS()` for international format
- **Twilio Integration**: Sends SMS via Twilio API
- **Custom Sender**: Adds "VERITAS EXEAT:" prefix for branded messages
- **Message Truncation**: Handles SMS character limits (160 chars)
- **Error Handling**: Comprehensive error logging and status tracking

**Implementation**:
```php
// Method: deliverSMS()
$formattedPhone = PhoneUtility::formatForSMS($recipient['phone']);

// For custom sender names (if configured)
$message = $client->messages->create(
    $formattedPhone,
    [
        'from' => $from,
        'body' => "VERITAS EXEAT: " . $this->formatSMSMessage($notification)
    ]
);

// For regular SMS
$message = $client->messages->create(
    $formattedPhone,
    [
        'from' => $from,
        'body' => $this->formatSMSMessage($notification)
    ]
);
```

---

## SMS Notifications NOT Sent

### 1. Debt Creation Notifications
**Location**: `app/Services/ExeatNotificationService.php`  
**Method**: `sendDebtNotification()`  
**Status**: **SMS REMOVED** - Only email and in-app notifications sent  
**Reason**: Cost optimization - debt notifications are sent via email only

```php
// SMS removed for overdue debts - only email sent
if ($student->email) {
    $this->deliveryService->queueNotificationDelivery(
        $this->createDebtEmailNotification($exeatRequest, $student, $amount),
        'email'
    );
}
// Note: SMS intentionally not sent for debt creation
```

---

## SMS Configuration Requirements

### Environment Variables
```env
# Twilio Configuration
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_SMS_FROM=your_twilio_phone_number

# Optional: Custom sender name configuration
TWILIO_MESSAGING_SERVICE_SID=your_messaging_service_sid
```

### Config File: `config/services.php`
```php
'twilio' => [
    'sid' => env('TWILIO_SID'),
    'token' => env('TWILIO_TOKEN'),
    'sms_from' => env('TWILIO_SMS_FROM'),
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
],
```

---

## SMS Message Formatting Rules

### 1. Character Limits
- **Standard SMS**: 160 characters
- **Long SMS**: Automatically split by carriers
- **System Handling**: Truncation with preservation of important content

### 2. Message Prefixes
- **Branded Messages**: "VERITAS EXEAT: [Message]"
- **Alert Messages**: "EXEAT ALERT: [Message]"
- **Debt Messages**: "EXEAT DEBT ALERT: [Message]"
- **Notification Messages**: "EXEAT NOTIFICATION: [Message]"

### 3. Content Optimization
- **Staff Comments**: Raw comment only (no template)
- **Debt Clearance**: Concise confirmation message
- **Modifications**: Brief alert with dashboard reference
- **Parent Consent**: Essential info with action links

---

## SMS Delivery Tracking

### Delivery Logs
All SMS notifications are tracked in the `notification_delivery_logs` table:

```sql
- notification_id: Links to exeat_notifications table
- delivery_method: 'sms'
- status: 'pending', 'delivered', 'failed'
- delivered_at: Timestamp of successful delivery
- error_message: Error details if delivery failed
- metadata: Twilio response data (message SID, status, etc.)
```

### Status Monitoring
- **Real-time Status**: Twilio webhook updates
- **Delivery Confirmation**: Provider message ID tracking
- **Error Handling**: Automatic retry mechanisms
- **Analytics**: Delivery success rates and failure analysis

---

## Summary of SMS Notification Types

| **Notification Type** | **Trigger** | **Recipient** | **Status** |
|----------------------|-------------|---------------|------------|
| Parent Consent | Exeat requires parent approval | Parents/Guardians | ✅ Active |
| Staff Comments | Staff adds comment to exeat | Students | ✅ Active (Raw comment only) |
| Debt Clearance | Debt payment verified/cleared | Students | ✅ Active |
| Exeat Modification | Admin modifies exeat details | Students | ✅ Active |
| Debt Recalculation | Debt amount recalculated | Students | ✅ Active |
| Debt Creation | Student returns late | Students | ❌ Disabled (Email only) |

### Total Active SMS Types: **5 notification types**
### Total Disabled SMS Types: **1 notification type** (debt creation)

---

## Cost Optimization Notes

1. **Debt Creation SMS Removed**: Saves costs by using email for initial debt notifications
2. **Staff Comment Optimization**: Raw comment only in SMS, full template in email
3. **Message Length Optimization**: Concise messages to stay within SMS limits
4. **Targeted Delivery**: SMS only sent when phone numbers are available
5. **Fallback Strategy**: WhatsApp preferred over SMS for parent notifications when possible

The SMS notification system is designed to be cost-effective while maintaining essential communication with students and parents throughout the exeat process.