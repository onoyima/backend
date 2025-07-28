<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Server Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: #fff;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-container {
            background: rgba(0,0,0,0.6);
            border-radius: 16px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 2.5rem 3rem;
            max-width: 900px;
            width: 100%;
            text-align: center;
        }
        .status-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }
        .status-message {
            font-size: 1.25rem;
            margin-bottom: 1.2rem;
            color: #ffd700;
        }
        .status-detail {
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 1.05rem;
        }
        .status-label {
            color: #90caf9;
            font-weight: 700;
        }
        .result-success {
            color: #4caf50;
            font-weight: bold;
        }
        .result-error {
            color: #ff5252;
            font-weight: bold;
        }
        .timestamp {
            font-size: 0.95rem;
            color: #bdbdbd;
            margin-top: 1.5rem;
        }
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            overflow: hidden;
        }
        .status-table th, .status-table td {
            padding: 0.75rem 1rem;
            text-align: left;
        }
        .status-table th {
            background: rgba(30,60,114,0.8);
            color: #ffd700;
            font-weight: 700;
        }
        .status-table tr:nth-child(even) {
            background: rgba(255,255,255,0.04);
        }
        .status-table tr:nth-child(odd) {
            background: rgba(255,255,255,0.10);
        }
        @media (max-width: 700px) {
            .status-container {
                padding: 1rem 0.5rem;
                max-width: 100vw;
            }
            .status-title {
                font-size: 1.2rem;
            }
            .status-table th, .status-table td {
                padding: 0.5rem 0.3rem;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="status-container">
        <div class="status-title">Exeat Application Server API Status</div>
        @if(empty($status_list))
            <div class="status-message">Server Started..........</div>
            <div class="status-detail">
                <span class="status-label">No API requests have been made yet.</span>
            </div>
        @else
            <div class="status-message">Recent API Requests (cleared every 5 minutes)</div>
            <div style="overflow-x:auto;">
                <table class="status-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Endpoint</th>
                            <th>Result</th>
                            <th>File</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($status_list as $i => $row)
                            @php
                                $isSuccess = false;
                                if (is_string($row['result']) && preg_match('/^2\\d{2}/', $row['result'])) {
                                    $isSuccess = true;
                                }
                            @endphp
                            <tr>
                                <td>{{ $i+1 }}</td>
                                <td>{{ $row['endpoint'] }}</td>
                                <td class="{{ $isSuccess ? 'result-success' : 'result-error' }}">{{ $row['result'] }}</td>
                                <td>{{ $row['file'] ?? '-' }}</td>
                                <td>{{ $row['timestamp'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>
