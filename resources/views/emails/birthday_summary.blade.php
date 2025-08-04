<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Birthday Summary</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px; color: #000000; background-color: #ffffff;">

    <h2 style="margin-bottom: 5px;">🎉 Daily Birthday Email Summary</h2>
    <p style="margin: 5px 0;"><strong>Date:</strong> {{ $date }}</p>
    <p style="margin: 5px 0;"><strong>Total Students Sent:</strong> {{ $studentsSent }}</p>
    <p style="margin: 5px 0;"><strong>Total Staff Sent:</strong> {{ $staffSent }}</p>

    @if(isset($recipients) && count($recipients))
        <h3 style="margin-top: 20px;">Recipients</h3>
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #cccccc; padding: 10px; background-color: #f5f5f5; text-align: left;">Photo</th>
                    <th style="border: 1px solid #cccccc; padding: 10px; background-color: #f5f5f5; text-align: left;">Name</th>
                    <th style="border: 1px solid #cccccc; padding: 10px; background-color: #f5f5f5; text-align: left;">Email</th>
                    <th style="border: 1px solid #cccccc; padding: 10px; background-color: #f5f5f5; text-align: left;">Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recipients as $recipient)
                    <tr>
                        <td style="border: 1px solid #cccccc; padding: 10px;">
                            @if ($recipient['passport'])
                                <img src="data:image/jpeg;base64,{{ $recipient['passport'] }}" alt="Passport" width="60" height="60" style="border-radius: 4px; object-fit: cover; display: block;">
                            @else
                                N/A
                            @endif
                        </td>
                        <td style="border: 1px solid #cccccc; padding: 10px;">{{ $recipient['name'] }}</td>
                        <td style="border: 1px solid #cccccc; padding: 10px;">{{ $recipient['email'] }}</td>
                        <td style="border: 1px solid #cccccc; padding: 10px;">{{ ucfirst($recipient['type']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="margin-top: 20px;">No birthday emails sent today.</p>
    @endif

</body>
</html>
