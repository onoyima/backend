<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->student = Student::factory()->create();
        $this->staff = Staff::factory()->create();
    }

    /** @test */
    public function it_can_create_direct_conversation_between_student_and_staff()
    {
        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson('/api/chat/conversations/direct', [
                'participant_type' => 'Staff',
                'participant_id' => $this->staff->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'type',
                'participants',
                'created_at',
            ]);

        $this->assertDatabaseHas('conversations', [
            'type' => 'direct',
        ]);

        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $response->json('id'),
            'participant_type' => Student::class,
            'participant_id' => $this->student->id,
        ]);
    }

    /** @test */
    public function it_can_send_text_message()
    {
        $conversation = Conversation::createDirect([$this->student, $this->staff]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'type' => 'text',
                'content' => 'Hello, this is a test message!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'content',
                'type',
                'sender',
                'created_at',
            ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'content' => 'Hello, this is a test message!',
            'type' => 'text',
        ]);
    }

    /** @test */
    public function it_can_upload_image_message()
    {
        Storage::fake('chat_media');
        
        $conversation = Conversation::createDirect([$this->student, $this->staff]);
        $image = UploadedFile::fake()->image('test-image.jpg');

        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/chat/conversations/{$conversation->id}/messages", [
                'type' => 'image',
                'content' => '',
                'media' => [$image],
            ]);

        $response->assertStatus(201);
        
        $message = Message::find($response->json('id'));
        $this->assertNotNull($message->media->first());
        
        Storage::disk('chat_media')->assertExists($message->media->first()->file_path);
    }

    /** @test */
    public function it_can_get_conversations_for_user()
    {
        $conversation1 = Conversation::createDirect([$this->student, $this->staff]);
        $conversation2 = Conversation::createGroup('Test Group', $this->student, [$this->staff]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson('/api/chat/conversations');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'type',
                    'name',
                    'latest_message',
                    'unread_count',
                ]
            ]);
    }

    /** @test */
    public function it_can_search_messages()
    {
        $conversation = Conversation::createDirect([$this->student, $this->staff]);
        
        Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => Student::class,
            'sender_id' => $this->student->id,
            'content' => 'This is a test message about Laravel',
            'type' => 'text',
        ]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->postJson("/api/chat/conversations/{$conversation->id}/messages/search", [
                'query' => 'Laravel',
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    /** @test */
    public function it_prevents_unauthorized_access_to_conversations()
    {
        $otherStudent = Student::factory()->create();
        $conversation = Conversation::createDirect([$otherStudent, $this->staff]);

        $response = $this->actingAs($this->student, 'sanctum')
            ->getJson("/api/chat/conversations/{$conversation->id}/messages");

        $response->assertStatus(403);
    }
}