<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use App\Models\Staff;
use App\Models\ExeatRole;
use App\Models\StaffExeatRole;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test students
        $student1 = Student::factory()->create([
            'fname' => 'John',
            'lname' => 'Doe',
            'email' => 'john.doe@example.com',
        ]);

        $student2 = Student::factory()->create([
            'fname' => 'Jane',
            'lname' => 'Smith',
            'email' => 'jane.smith@example.com',
        ]);

        $student3 = Student::factory()->create([
            'fname' => 'Alice',
            'lname' => 'Johnson',
            'email' => 'alice.johnson@example.com',
        ]);

        // Create test staff
        $staff1 = Staff::factory()->create([
            'fname' => 'Dr. Michael',
            'lname' => 'Brown',
            'email' => 'michael.brown@example.com',
        ]);

        $staff2 = Staff::factory()->create([
            'fname' => 'Prof. Sarah',
            'lname' => 'Williams',
            'email' => 'sarah.williams@example.com',
        ]);

        // Create admin role and assign to staff1
        $adminRole = ExeatRole::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Full system administrator',
        ]);

        StaffExeatRole::create([
            'staff_id' => $staff1->id,
            'exeat_role_id' => $adminRole->id,
        ]);

        // Create sample conversations
        
        // 1. Direct conversation between students
        $conv1 = Conversation::createDirect([$student1, $student2]);
        
        // 2. Direct conversation between student and staff
        $conv2 = Conversation::createDirect([$student1, $staff1]);
        
        // 3. Group conversation - Study group
        $studyGroup = Conversation::createGroup(
            'CS101 Study Group',
            $student1,
            [$student2, $student3, $staff1]
        );
        
        // 4. Group conversation - Staff only
        $staffGroup = Conversation::createGroup(
            'Faculty Discussion',
            $staff1,
            [$staff2]
        );

        // Create sample messages
        
        // Messages in student-student conversation
        Message::create([
            'conversation_id' => $conv1->id,
            'sender_type' => Student::class,
            'sender_id' => $student1->id,
            'content' => 'Hey Jane, did you finish the assignment?',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $conv1->id,
            'sender_type' => Student::class,
            'sender_id' => $student2->id,
            'content' => 'Hi John! Yes, I just submitted it. How about you?',
            'type' => 'text',
        ]);

        // Messages in student-staff conversation
        Message::create([
            'conversation_id' => $conv2->id,
            'sender_type' => Student::class,
            'sender_id' => $student1->id,
            'content' => 'Dr. Brown, I have a question about today\'s lecture.',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $conv2->id,
            'sender_type' => Staff::class,
            'sender_id' => $staff1->id,
            'content' => 'Of course, John. What would you like to know?',
            'type' => 'text',
        ]);

        // Messages in study group
        Message::create([
            'conversation_id' => $studyGroup->id,
            'sender_type' => Student::class,
            'sender_id' => $student1->id,
            'content' => 'Welcome to our CS101 study group!',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $studyGroup->id,
            'sender_type' => Student::class,
            'sender_id' => $student2->id,
            'content' => 'Thanks for setting this up!',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $studyGroup->id,
            'sender_type' => Staff::class,
            'sender_id' => $staff1->id,
            'content' => 'I\'ll be monitoring this group to help with any questions.',
            'type' => 'text',
        ]);

        // Messages in staff group
        Message::create([
            'conversation_id' => $staffGroup->id,
            'sender_type' => Staff::class,
            'sender_id' => $staff1->id,
            'content' => 'Sarah, can you review the new curriculum changes?',
            'type' => 'text',
        ]);

        Message::create([
            'conversation_id' => $staffGroup->id,
            'sender_type' => Staff::class,
            'sender_id' => $staff2->id,
            'content' => 'Sure, I\'ll take a look this afternoon.',
            'type' => 'text',
        ]);

        $this->command->info('Chat system seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 3 students');
        $this->command->info('- 2 staff members (1 admin)');
        $this->command->info('- 4 conversations (2 direct, 2 group)');
        $this->command->info('- 8 sample messages');
    }
}