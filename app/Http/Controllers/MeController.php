<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Student;

class MeController extends Controller
{
    /**
     * Retrieves the authenticated user's profile based on their role (Staff or Student).
     *
     * @param Request $request The incoming HTTP request.
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $roles = [];
        $profile = [];

        // Check if the authenticated user is a Staff member
        if ($user instanceof Staff) {
            // Load related models for Staff: contacts and work profiles
            $user->load(['contacts', 'workProfiles']);

            // Get staff roles from exeatRoles relationship
            $roles = $user->exeatRoles()->with('role')->get()->pluck('role.name')->toArray();

            // Add detailed exeat roles for profile
            $exeatRolesDetailed = $user->exeatRoles()->with('role')->get()->map(function($assignment) {
                if ($assignment->role) {
                    return [
                        'id' => $assignment->role->id,
                        'name' => $assignment->role->name,
                        'display_name' => $assignment->role->display_name,
                        'description' => $assignment->role->description,
                        'assigned_at' => $assignment->assigned_at,
                    ];
                }
                return null;
            })->filter()->values()->toArray();

            // Structure the staff profile
            $profile = [
                'personal' => [
                    'first_name' => $user->fname,
                    'middle_name' => $user->mname,
                    'last_name' => $user->lname,
                    'gender' => $user->gender,
                    'dob' => $user->dob,
                    'marital_status' => $user->marital_status,
                    'title' => $user->title, // Staff title
                    'nationality' => [
                        'country_id' => $user->country_id,
                        'state_id' => $user->state_id,
                        'lga_name' => $user->lga_name,
                        'city' => $user->city,
                    ],
                    'religion' => $user->religion,
                    'contact' => [
                        'address' => $user->address,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'username' => $user->p_email, // Staff personal email as username
                    ],
                    'extras' => [
                        'passport' => $user->passport ? base64_encode($user->passport) : null,
                        'signature' => $user->signature ? base64_encode($user->signature) : null,
                        'status' => $user->status,
                    ],
                    'exeat_roles' => $exeatRolesDetailed,
                ],
                'contacts' => $user->contacts, // Staff contacts (e.g., emergency contacts)
                'work_profiles' => $user->workProfiles, // Staff work-related profiles
            ];
        }
        // Check if the authenticated user is a Student
        elseif ($user instanceof Student) {
            // Load related models for Student: contacts, medicals, and academics
            $user->load(['contacts', 'medicals', 'academics']);

            // Get student roles
            $roles = $user->roleUsers()->pluck('student_role_id')->toArray();

            // Get the first academic, medical, and contact record for structuring
            $academic = $user->academics->first();
            $medical = $user->medicals->first();
            $contact = $user->contacts->first();

            // Structure the student profile
            $profile = [
                'personal' => [
                    'first_name' => $user->fname,
                    'middle_name' => $user->mname,
                    'last_name' => $user->lname,
                    'gender' => $user->gender,
                    'dob' => $user->dob,
                    'marital_status' => $user->marital_status,
                    'title' => $user->title_id, // Student title ID
                    'nationality' => [
                        'country_id' => $user->country_id,
                        'state_id' => $user->state_id,
                        'lga_name' => $user->lga_name,
                        'city' => $user->city,
                    ],
                    'contact' => [
                        'address' => $user->address,
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'username' => $user->username, // Student username
                    ],
                    'extras' => [
                        'passport' => $user->passport ? base64_encode($user->passport) : null,
                        'signature' => $user->signature ? base64_encode($user->signature) : null,
                        'hobbies' => $user->hobbies,
                        'status' => $user->status,
                    ],
                ],
                'academic' => $academic ? [
                    'matric_no' => $academic->matric_no,
                    'old_matric_no' => $academic->old_matric_no,
                    'course_study_id' => $academic->course_study_id,
                    'level' => $academic->level,
                    'entry_mode_id' => $academic->entry_mode_id,
                    'study_mode_id' => $academic->study_mode_id,
                    'academic_session_id' => $academic->academic_session_id,
                    'admissions_type_id' => $academic->admissions_type_id,
                    'faculty_id' => $academic->faculty_id,
                    'department_id' => $academic->department_id,
                    'acad_status_id' => $academic->acad_status_id,
                    'admitted_date' => $academic->admitted_date,
                    'jamb_no' => $academic->jamb_no,
                    'jamb_score' => $academic->jamb_score,
                    'is_hostel' => $academic->is_hostel,
                    'studentship' => $academic->studentship,
                    'studentship_id' => $academic->studentship_id,
                    'program_type' => $academic->program_type,
                ] : null,
                'medical' => $medical ? [
                    'physical' => $medical->physical,
                    'blood_group' => $medical->blood_group,
                    'genotype' => $medical->genotype,
                    'condition' => $medical->condition,
                    'allergies' => $medical->allergies,
                ] : null,
                'sponsor_contact' => $contact ? [
                    'title' => $contact->title,
                    'full_name' => trim($contact->surname . ' ' . $contact->other_names),
                    'relationship' => $contact->relationship,
                    'address' => $contact->address,
                    'state' => $contact->state,
                    'city' => $contact->city,
                    'phone_no' => $contact->phone_no,
                    'phone_no_two' => $contact->phone_no_two,
                    'email' => $contact->email,
                    'email_two' => $contact->email_two,
                ] : null,
            ];
        }

        // Return the user ID, roles, and structured profile
        return response()->json([
            'user_id' => $user->id,
            'roles' => $roles,
            'profile' => $profile,
        ]);
    }
}
