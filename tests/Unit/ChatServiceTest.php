<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Student;
use App\Models\Staff;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $chatService;
    protected $student1;
    protected $student2;
    protected $staff;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->chatService = new ChatService();
        $this->student1 = Student::factory()->create();
        $this->student2 = Student::factory()->create();
        $this->staff = Staff::factory()->create();
    }

    /** @test */
    public function it_can_create_direct_conversation()
    {
        $conversation = $this->chatService->createDirectConversation(
            $this->student1,
            $this->student2
        );

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals('direct', $conversation->type);
        $this->assertCount(2, $conversation->participants);
    }

    /** @test */
    public function it_can_create_group_conversation()
    {
        $participants = [$this->student1, $this->student2];
        
        $conversation = $this->chatService->createGroupConversation(
            'Test Group',
            $this->staff,
            $participants,
            'This is a test group description'
        );

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertEquals('group', $conversation->type);
        $this->assertEquals('Test Group', $conversation->name);
        $this->assertCount(3, $conversation->participants);
    }

    /** @test */
    public function it_can_send_text_message()
    {
        $conversation = $this->chatService->createDirectConversation(
            $this->student1,
            $this->student2
        );

        $message = $this->chatService->sendTextMessage(
            $conversation,
            $this->student1,
            'Hello, this is a test message!'
        );

        $this->assertEquals('Hello, this is a test message!', $message->content);
        $this->assertEquals('text', $message->type);
        $this->assertEquals($this->student1->id, $message->sender_id);
    }

    /** @test */
    public function it_can_get_user_conversations()
    {
        $conversation1 = $this->chatService->createDirectConversation(
            $this->student1,
            $this->student2
        );
        
        $conversation2 = $this->chatService->createGroupConversation(
            'Study Group',
            $this->student1,
            [$this->student2, $this->staff]
        );

        $conversations = $this->chatService->getUserConversations($this->student1);

        $this->assertCount(2, $conversations);
        $this->assertTrue($conversations->contains($conversation1));
        $this->assertTrue($conversations->contains($conversation2));
    }

    /** @test */
    public function it_can_search_messages()
    {
        $conversation = $this->chatService->createDirectConversation(
            $this->student1,
            $this->student2
        );

        $this->chatService->sendTextMessage(
            $conversation,
            $this->student1,
            'This is a message about Laravel development'
        );

        $this->chatService->sendTextMessage(
            $conversation,
            $this->student2,
            'Another message about PHP programming'
        );

        $results = $this->chatService->searchMessages($conversation, 'Laravel');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Laravel', $results->first()->content);
    }

    /** @test */
    public function it_can_suspend_conversation()
    {
        $conversation = $this->chatService->createGroupConversation(
            'Test Group',
            $this->staff,
            [$this->student1]
        );

        $this->chatService->suspendConversation(
            $conversation,
            $this->staff,
            'Inappropriate content'
        );

        $conversation->refresh();
        $this->assertTrue($conversation->is_suspended);
        $this->assertNotNull($conversation->suspended_at);
        $this->assertEquals('Inappropriate content', $conversation->suspension_reason);
    }
}