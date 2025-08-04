<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentAcademic;
use App\Models\BirthdayEmailLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class BirthdayController extends Controller
{
    public function sendBirthdayEmails()
    {
        $today = Carbon::today()->format('m-d');
        $studentsSent = 0;
        $staffSent = 0;
        $recipients = [];

        // --- Fetch Students ---
        $students = Student::where('status', 1)
            ->whereRaw("DATE_FORMAT(dob, '%m-%d') = ?", [$today])
            ->whereHas('academics', function ($query) {
                $query->where('level', '!=', 1000);
            })
            ->get();

        foreach ($students as $student) {
            $email = $student->username;
            $recipientName = $student->first_name . ' ' . $student->last_name;

            if (!BirthdayEmailLog::where('email', $email)->whereDate('updated_at', today())->exists()) {
                $photoBase64 = $student->passport ? 'data:image/jpeg;base64,' . base64_encode($student->passport) : null;

                $this->sendBirthdayEmail(
                    $recipientName,
                    $email,
                    $student->dob,
                    'Student',
                    'Wishing you success in your studies!',
                    'Your University',
                    $photoBase64
                );

                BirthdayEmailLog::create([
                    'email' => $email,
                    'recipient_name' => $recipientName,
                    'type' => 'student',
                    'updated_at' => now()
                ]);

                $recipients[] = [
                    'name' => $recipientName,
                    'email' => $email,
                    'type' => 'student',
                    'passport' => $photoBase64
                ];

                $studentsSent++;
            }
        }

        // --- Fetch Staff ---
        $staff = Staff::where('status', 1)
            ->whereRaw("DATE_FORMAT(dob, '%m-%d') = ?", [$today])
            ->get();

        foreach ($staff as $person) {
            $email = $person->p_email;
            $recipientName = $person->fname . ' ' . $person->lname;

            if (!BirthdayEmailLog::where('email', $email)->whereDate('updated_at', today())->exists()) {
                $photoBase64 = $person->passport ? 'data:image/jpeg;base64,' . base64_encode($person->passport) : null;

                $this->sendBirthdayEmail(
                    $recipientName,
                    $email,
                    $person->dob,
                    'Staff',
                    'Thank you for your dedication!',
                    'Your University',
                    $photoBase64
                );

                BirthdayEmailLog::create([
                    'email' => $email,
                    'recipient_name' => $recipientName,
                    'type' => 'staff',
                    'updated_at' => now()
                ]);

                $recipients[] = [
                    'name' => $recipientName,
                    'email' => $email,
                    'type' => 'staff',
                    'passport' => $photoBase64
                ];

                $staffSent++;
            }
        }

        // --- Send summary to admin ---
        Mail::send('emails.birthday_summary', [
            'studentsSent' => $studentsSent,
            'staffSent' => $staffSent,
            'date' => Carbon::now()->toDayDateTimeString(),
            'recipients' => $recipients
        ], function ($message) {
            $message->to('onoyimab@veritas.edu.ng', 'Birthday Admin')
                    ->subject('Daily Birthday Email Summary');
        });
    }

    private function sendBirthdayEmail($recipientName, $email, $dob, $type, $roleMsg, $organization, $passportImage)
    {
        Mail::send('emails.birthday', [
            'RECIPIENT_NAME' => $recipientName,
            'BIRTH_DATE' => Carbon::parse($dob)->format('F j'),
            'RECIPIENT_TYPE' => $type,
            'ROLE_SPECIFIC_MESSAGE' => $roleMsg,
            'ORGANIZATION_NAME' => $organization,
            'PASSPORT_IMAGE' => $passportImage
        ], function ($message) use ($email, $recipientName, $organization) {
            $message->to($email, $recipientName)
                ->subject('Happy Birthday from ' . $organization);
        });
    }

    public function sendBirthdayEmailToSpecificUsers()
{
    $studentsSent = 0;
    $staffSent = 0;
    $recipients = [];

    // --- Specific Student ---
    $student = Student::with('academics')->find(1336);
    if (
        $student &&
        $student->status == 1
        ) 
        {
        $email = $student->username;
        $recipientName = $student->first_name . ' ' . $student->last_name;

        if (!BirthdayEmailLog::where('email', $email)->whereDate('updated_at', today())->exists()) {
            $photoBase64 = $student->passport ? 'data:image/jpeg;base64,' . base64_encode($student->passport) : null;

            $this->sendBirthdayEmail(
                $recipientName,
                $email,
                $student->dob,
                'Student',
                'Wishing you success in your studies!',
                'Your University',
                $photoBase64
            );

            BirthdayEmailLog::create([
                'email' => $email,
                'recipient_name' => $recipientName,
                'type' => 'student',
                'updated_at' => now()
            ]);

            $recipients[] = [
                'name' => $recipientName,
                'email' => $email,
                'type' => 'student',
                'passport' => $photoBase64
            ];

            $studentsSent++;
        }
    }

    // --- Specific Staff ---
    $staff = Staff::find(596);
    if ($staff && $staff->status == 1) {
        $email = $staff->p_email;
        $recipientName = $staff->fname . ' ' . $staff->lname;

        if (!BirthdayEmailLog::where('email', $email)->whereDate('updated_at', today())->exists()) {
            $photoBase64 = $staff->passport ? 'data:image/jpeg;base64,' . base64_encode($staff->passport) : null;

            $this->sendBirthdayEmail(
                $recipientName,
                $email,
                $staff->dob,
                'Staff',
                'Thank you for your dedication!',
                'Your University',
                $photoBase64
            );

            BirthdayEmailLog::create([
                'email' => $email,
                'recipient_name' => $recipientName,
                'type' => 'staff',
                'updated_at' => now()
            ]);

            $recipients[] = [
                'name' => $recipientName,
                'email' => $email,
                'type' => 'staff',
                'passport' => $photoBase64
            ];

            $staffSent++;
        }
    }

    // --- Send summary to admin ---
    Mail::send('emails.birthday_summary', [
        'studentsSent' => $studentsSent,
        'staffSent' => $staffSent,
        'date' => Carbon::now()->toDayDateTimeString(),
        'recipients' => $recipients
    ], function ($message) {
        $message->to('onoyimab@veritas.edu.ng', 'Birthday Admin')
                ->subject('Direct Birthday Email Summary (Manual Trigger)');
    });
}

}
