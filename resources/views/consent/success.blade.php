<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Response - {{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #004f40 0%, #00695c 50%, #00796b 100%);
            color: #fff;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .consent-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(0, 79, 64, 0.3);
            padding: 3rem 2.5rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            color: #004f40;
            position: relative;
            overflow: hidden;
        }
        
        .consent-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #004f40, #00796b, #004f40);
        }
        
        .icon-container {
            margin-bottom: 2rem;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #4caf50;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        .decline-icon {
            width: 80px;
            height: 80px;
            background: #ff5722;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        .already-responded-icon {
            width: 80px;
            height: 80px;
            background: #ff9800;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin-bottom: 1rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .consent-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #004f40;
            letter-spacing: 0.5px;
        }
        
        .consent-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #00695c;
            line-height: 1.6;
        }
        
        .consent-details {
            background: rgba(0, 79, 64, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(0, 79, 64, 0.1);
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 500;
            color: #004f40;
        }
        
        .detail-value {
            color: #00695c;
            font-weight: 400;
        }
        
        .next-steps {
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4caf50;
            padding: 1rem 1.5rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1.5rem;
            text-align: left;
        }
        
        .next-steps.declined {
            background: rgba(255, 87, 34, 0.1);
            border-left-color: #ff5722;
        }
        
        .next-steps h4 {
            margin: 0 0 0.5rem 0;
            color: #004f40;
            font-size: 1rem;
        }
        
        .next-steps p {
            margin: 0;
            font-size: 0.95rem;
            color: #00695c;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #757575;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 79, 64, 0.1);
        }
        
        @media (max-width: 600px) {
            .consent-container {
                padding: 2rem 1.5rem;
                margin: 10px;
            }
            
            .consent-title {
                font-size: 1.5rem;
            }
            
            .consent-message {
                font-size: 1rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="consent-container">
        <div class="icon-container">
            @if($type === 'approved')
                <div class="success-icon">✓</div>
            @elseif($type === 'declined')
                <div class="decline-icon">✗</div>
            @else
                <div class="already-responded-icon">!</div>
            @endif
        </div>
        
        <h1 class="consent-title">{{ $title }}</h1>
        <p class="consent-message">{{ $message }}</p>
        
        @if(isset($student) && $student)
        <div class="consent-details">
            <div class="detail-row">
                <span class="detail-label">Student:</span>
                <span class="detail-value">{{ $student['name'] ?? 'N/A' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Matric No:</span>
                <span class="detail-value">{{ $student['matric_no'] ?? 'N/A' }}</span>
            </div>
            @if(isset($student['destination']))
            <div class="detail-row">
                <span class="detail-label">Destination:</span>
                <span class="detail-value">{{ $student['destination'] }}</span>
            </div>
            @endif
            @if(isset($student['departure_date']))
            <div class="detail-row">
                <span class="detail-label">Departure:</span>
                <span class="detail-value">{{ date('M j, Y g:i A', strtotime($student['departure_date'])) }}</span>
            </div>
            @endif
            @if(isset($student['return_date']))
            <div class="detail-row">
                <span class="detail-label">Expected Return:</span>
                <span class="detail-value">{{ date('M j, Y g:i A', strtotime($student['return_date'])) }}</span>
            </div>
            @endif
        </div>
        @endif
        
        @if($type === 'approved')
        <div class="next-steps">
            <h4>What happens next?</h4>
            <p>The exeat request will now proceed through the approval workflow. Your child will be notified of the progress via their student portal.</p>
        </div>
        @elseif($type === 'declined')
        <div class="next-steps declined">
            <h4>Request Status</h4>
            <p>The exeat request has been declined and will not proceed further. Your child will be notified of this decision.</p>
        </div>
        @endif
        
        <div class="timestamp">
            Response recorded on {{ date('F j, Y \\a\\t g:i A') }}
        </div>
    </div>
</body>
</html>