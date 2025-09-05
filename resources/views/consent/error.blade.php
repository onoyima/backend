<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Error - {{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 50%, #f44336 100%);
            color: #fff;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(183, 28, 28, 0.3);
            padding: 3rem 2.5rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            color: #b71c1c;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #b71c1c, #f44336, #b71c1c);
        }
        
        .icon-container {
            margin-bottom: 2rem;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: #f44336;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
            margin-bottom: 1rem;
        }
        
        .expired-icon {
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
        
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #b71c1c;
            letter-spacing: 0.5px;
        }
        
        .error-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #d32f2f;
            line-height: 1.6;
        }
        
        .error-details {
            background: rgba(183, 28, 28, 0.05);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .possible-causes {
            background: rgba(255, 152, 0, 0.1);
            border-left: 4px solid #ff9800;
            padding: 1rem 1.5rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1.5rem;
            text-align: left;
        }
        
        .possible-causes h4 {
            margin: 0 0 1rem 0;
            color: #b71c1c;
            font-size: 1rem;
        }
        
        .possible-causes ul {
            margin: 0;
            padding-left: 1.2rem;
            color: #d32f2f;
        }
        
        .possible-causes li {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .contact-info {
            background: rgba(76, 175, 80, 0.1);
            border-left: 4px solid #4caf50;
            padding: 1rem 1.5rem;
            border-radius: 0 8px 8px 0;
            margin-top: 1.5rem;
            text-align: left;
        }
        
        .contact-info h4 {
            margin: 0 0 0.5rem 0;
            color: #2e7d32;
            font-size: 1rem;
        }
        
        .contact-info p {
            margin: 0;
            font-size: 0.95rem;
            color: #388e3c;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #757575;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(183, 28, 28, 0.1);
        }
        
        @media (max-width: 600px) {
            .error-container {
                padding: 2rem 1.5rem;
                margin: 10px;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-message {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon-container">
            @if($type === 'expired')
                <div class="expired-icon">⏰</div>
            @else
                <div class="error-icon">⚠</div>
            @endif
        </div>
        
        <h1 class="error-title">{{ $title }}</h1>
        <p class="error-message">{{ $message }}</p>
        
        @if($type === 'expired')
        <div class="possible-causes">
            <h4>Why did this happen?</h4>
            <ul>
                <li>Consent links expire after 24 hours for security reasons</li>
                <li>This helps protect your family's privacy and security</li>
                <li>The original request may have been sent some time ago</li>
            </ul>
        </div>
        @elseif($type === 'not_found')
        <div class="possible-causes">
            <h4>Possible reasons:</h4>
            <ul>
                <li>The link may be invalid or corrupted</li>
                <li>The consent request may have been removed</li>
                <li>There might be a temporary issue with the URL shortener</li>
                <li>The link may have expired (consent links are valid for 24 hours)</li>
            </ul>
        </div>
        @endif
        
        <div class="contact-info">
            <h4>Need Help?</h4>
            <p>If you believe this is an error, please contact the school administration or ask your child to submit a new exeat request through their student portal.</p>
        </div>
        
        <div class="timestamp">
            Error occurred on {{ date('F j, Y \\a\\t g:i A') }}
        </div>
    </div>
</body>
</html>