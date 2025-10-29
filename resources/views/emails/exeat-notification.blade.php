<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>{{ $notification->title }}</title>
<style>
    /* Reset and base */
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        color: #004f40;
        background-color: #ffffff;
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }

    .email-container {
        border: 1px solid #004f40;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
    }

    .header {
        background-color: #004f40;
        color: #ffffff;
        padding: 25px 20px;
        text-align: center;
        font-size: 24px;
        font-weight: bold;
        letter-spacing: 1px;
        border-radius: 8px 8px 0 0;
        user-select: none;
    }

    .content {
        background-color: #f9f9f9;
        padding: 30px 25px;
        border-left: 5px solid #004f40;
        color: #004f40;
        font-size: 16px;
    }

    /* Priority border colors */
    .priority-urgent {
        border-left-color: #b71c1c; /* dark red */
    }
    .priority-high {
        border-left-color: #ef6c00; /* dark orange */
    }
    .priority-medium {
        border-left-color: #f9a825; /* goldenrod */
    }
    .priority-low {
        border-left-color: #2e7d32; /* dark green */
    }

    .exeat-details {
        background-color: #ffffff;
        padding: 20px;
        margin: 25px 0;
        border-radius: 8px;
        border: 1px solid #004f40;
        color: #004f40;
    }

    .exeat-details h3 {
        margin-top: 0;
        border-bottom: 2px solid #004f40;
        padding-bottom: 5px;
        font-weight: 700;
        font-size: 20px;
    }

    .detail-row {
        margin: 10px 0;
        font-size: 15px;
    }

    .detail-label {
        font-weight: 700;
        color: #002b22;
        display: inline-block;
        width: 140px;
    }

    /* Action required box */
    .action-required {
        margin: 25px 0;
        padding: 18px 20px;
        background-color: #e0f2f1;
        border: 1px solid #004f40;
        border-radius: 6px;
        color: #004f40;
        font-weight: 600;
        font-size: 15px;
    }

    p {
        margin-bottom: 1em;
    }

    /* Footer */
    .footer {
        background-color: #004f40;
        color: #ffffff;
        padding: 15px 20px;
        text-align: center;
        font-size: 13px;
        border-radius: 0 0 8px 8px;
        user-select: none;
    }

    /* Button styles for parent consent */
    .consent-buttons {
        text-align: center;
        margin: 30px 0;
        padding: 25px;
        background-color: #ffffff;
        border-radius: 8px;
        border: 1px solid #004f40;
    }

    .consent-button {
        display: inline-block;
        padding: 15px 30px;
        margin: 0 10px;
        text-decoration: none;
        border-radius: 6px;
        font-weight: bold;
        font-size: 16px;
        text-align: center;
        min-width: 120px;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .approve-button {
        background-color: #2e7d32;
        color: #ffffff;
        border-color: #2e7d32;
    }

    .approve-button:hover {
        background-color: #1b5e20;
        border-color: #1b5e20;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
    }

    .reject-button {
        background-color: #d32f2f;
        color: #ffffff;
        border-color: #d32f2f;
    }

    .reject-button:hover {
        background-color: #b71c1c;
        border-color: #b71c1c;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(211, 47, 47, 0.3);
    }

    .consent-note {
        margin-top: 20px;
        font-size: 14px;
        color: #666;
        font-style: italic;
    }

    /* Responsive */
    @media only screen and (max-width: 620px) {
        body {
            padding: 15px;
        }
        .content, .exeat-details {
            padding: 20px 15px;
        }
        .detail-label {
            width: 100%;
            display: block;
            margin-bottom: 3px;
        }
        .consent-button {
            display: block;
            margin: 10px auto;
            width: 80%;
        }
    }
</style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            {{ $notification->title }}
        </div>

        <div class="content priority-{{ $notification->priority }}">
            <p>Dear {{ $recipient['name'] }},</p>

            <div style="margin: 20px 0;">
                {!! nl2br(e($notification->message)) !!}
            </div>

            @if(isset($approveUrl) && isset($rejectUrl))
            <div class="consent-buttons">
                <h3 style="margin-top: 0; color: #004f40; text-align: center;">Parent Consent Required</h3>
                <p style="text-align: center; margin-bottom: 25px; color: #004f40;">Please click one of the buttons below to give your consent:</p>
                
                <a href="{{ $approveUrl }}" class="consent-button approve-button">✓ APPROVE</a>
                <a href="{{ $rejectUrl }}" class="consent-button reject-button">✗ REJECT</a>
                
                <div class="consent-note">
                    <p>By clicking approve, you give consent for your child to proceed with this exeat request.</p>
                    <p>By clicking reject, you deny consent for this exeat request.</p>
                </div>
            </div>
            @endif

            @if($notification->exeatRequest)
            <div class="exeat-details">
                <h3>Exeat Request Details:</h3>

                <div class="detail-row">
                    <span class="detail-label">Student:</span>
                    {{ $notification->exeatRequest->student->fname ?? 'N/A' }} {{ $notification->exeatRequest->student->lname ?? 'N/A' }}
                </div>

                <div class="detail-row">
                    <span class="detail-label">Matric Number:</span>
                    {{ $notification->exeatRequest->matric_no }}
                </div>

                {{-- <div class="detail-row">
                    <span class="detail-label">Current Status:</span>
                    <span style="text-transform: capitalize;">{{ str_replace('_', ' ', $notification->exeatRequest->status) }}</span>
                </div> --}}

                <div class="detail-row">
                    <span class="detail-label">Reason:</span>
                    {{ $notification->exeatRequest->reason }}
                </div>

                <div class="detail-row">
                    <span class="detail-label">Destination:</span>
                    {{ $notification->exeatRequest->destination }}
                </div>

                <div class="detail-row">
                    <span class="detail-label">Departure Date:</span>
                    {{ \Carbon\Carbon::parse($notification->exeatRequest->departure_date)->format('M d, Y') }}
                </div>

                <div class="detail-row">
                    <span class="detail-label">Return Date:</span>
                    {{ \Carbon\Carbon::parse($notification->exeatRequest->return_date)->format('M d, Y') }}
                </div>

@if($notification->exeatRequest->is_medical)
                <div class="detail-row">
                    <span class="detail-label">Request Type:</span>
                    Medical
                </div>
@else
                <div></div>
@endif
            </div>
            @endif

            @if($notification->notification_type === 'approval_required')
            <div class="action-required">
                <p><strong>Action Required:</strong> Please log in to the system to review and take action on this exeat request.</p>
            </div>
            @endif

            <p>
                Best regards,<br />
                <strong>Veritas University Exeat Management System</strong>
            </p>
        </div>

        <div class="footer">
            <p>This is an automated notification from VERITAS University Exeat Management System.</p>
            <p>Please do not reply to this email. For support, contact the ICT Unit at <a href="mailto:ictsupport@veritas.edu.ng" style="color: #a7d7cc;">ictsupport@veritas.edu.ng</a>.</p>
            <p>&copy; {{ date('Y') }} VERITAS University. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
