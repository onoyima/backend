<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use App\Models\Student;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function getConversations(Request $request)
    {
        $user = $this->getAuthenticatedUser();

        $conversations = $this->chatService->getUserConversations($user);

        return response()->json([
            'conversations' => $conversations->map(function ($conversation) use ($user) {
                return [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'name' => $conversation->name,
                    'description' => $conversation->description,
                    'avatar' => $conversation->avatar,
                    'is_suspended' => $conversation->is_suspended,
                    'participant_count' => $conversation->getParticipantCount(),
                    'unread_count' => $conversation->getUnreadCount($user),
                    'latest_message' => $conversation->getLatestMessage() ? [
                        'id' => $conversation->getLatestMessage()->id,
                        'content' => $conversation->getLatestMessage()->content,
                        'type' => $conversation->getLatestMessage()->type,
                        'sender' => $this->formatUser($conversation->getLatestMessage()->sender),
                        'created_at' => $conversation->getLatestMessage()->created_at,
                    ] : null,
                    'created_at' => $conversation->created_at,
                    'participants' => $conversation->participants->map(function ($participant) {
                        return [
                            'id' => $participant->participant->id,
                            'type' => $participant->participant_type,
                            'name' => $participant->participant->name,
                            'email' => $participant->participant->email,
                            'role' => $participant->role,
                            'can_add_members' => $participant->can_add_members,
                            'can_remove_members' => $participant->can_remove_members,
                            'can_edit_group' => $participant->can_edit_group,
                            'can_delete_messages' => $participant->can_delete_messages,
                            'joined_at' => $participant->joined_at,
                            'left_at' => $participant->left_at,
                            'is_active' => $participant->is_active,
                        ];
                    }),
                ];
            })
        ]);
    }
    public function createDirectConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'participant_id' => 'required|integer',
            'participant_type' => 'required|in:student,staff',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->getAuthenticatedUser();

        if (!$user || !is_object($user)) {
            \Log::error('Authenticated user is missing or invalid', [
                'user' => $user
            ]);
            return response()->json(['error' => 'Authenticated user not found'], 401);
        }

        $participant = $this->getParticipant($request->participant_type, $request->participant_id);

        if (!$participant || !is_object($participant)) {
            \Log::warning('Participant not found or invalid', [
                'type' => $request->participant_type,
                'id' => $request->participant_id
            ]);
            return response()->json(['error' => 'Participant not found'], 404);
        }

        try {
            if (get_class($user) === get_class($participant) && $user->id === $participant->id) {
                return response()->json(['error' => 'You cannot start a conversation with yourself'], 400);
            }

            $conversation = $this->chatService->createDirectConversation($user, $participant);

            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'participants' => $conversation->participants->map(function ($participant) {
                        return $this->formatUser($participant->participant);
                    }),
                ]
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Exception while creating direct conversation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => get_class($user) . ':' . $user->id,
                'participant' => get_class($participant) . ':' . $participant->id
            ]);
            return response()->json(['error' => 'Failed to create conversation'], 500);
        }
    }






    public function searchParticipants(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:student,staff',
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->input('type');
        $query = $request->input('query');

        $results = $type === 'student'
            ? Student::where('fname', 'like', "%{$query}%")
                ->orWhere('lname', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(10)
                ->get()
            : Staff::where('fname', 'like', "%{$query}%")
                ->orWhere('lname', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->limit(10)
                ->get();

        return response()->json([
            'participants' => $results->map(fn ($user) => $this->formatUser($user))
        ]);
    }


   public function createGroupConversation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'participants' => 'required|array',
            'participants.*.id' => 'required|integer',
            'participants.*.type' => 'required|in:student,staff',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $this->getAuthenticatedUser();

        // 🛑 Students cannot add staff to group
        if ($user instanceof Student && collect($request->participants)->contains(fn ($p) => $p['type'] === 'staff')) {
            return response()->json(['error' => 'Students cannot add staff to group chats'], 403);
        }

        $participants = [];
        foreach ($request->participants as $participantData) {
            $participant = $this->getParticipant($participantData['type'], $participantData['id']);
            if (!$participant) {
                return response()->json(['error' => 'Participant not found'], 404);
            }
            $participants[] = $participant;
        }

        $conversation = $this->chatService->createGroupConversation(
            $user,
            $request->name,
            $request->description,
            $participants
        );

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'description' => $conversation->description,
                'participants' => $conversation->participants->map(function ($participant) {
                    return [
                        'user' => $this->formatUser($participant->participant),
                        'role' => $participant->role,
                    ];
                }),
            ]
        ], 201);
    }

    public function getMessages(Request $request, Conversation $conversation)
    {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
            'before' => 'integer|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $limit = $request->get('limit', 20);
        $query = $conversation->messages()
            ->visible()
            ->with(['sender', 'media', 'replyTo.sender'])
            ->orderBy('created_at', 'desc');

        if ($request->has('before')) {
            $query->where('id', '<', $request->before);
        }

        $messages = $query->limit($limit)->get();

        return response()->json([
            'messages' => $messages->reverse()->values()->map(function ($message) use ($user) {
                return $this->formatMessage($message, $user);
            })
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required_without:media|string|max:5000',
            'media' => 'required_without:content|file|max:51200',
            'type' => 'required_with:media|in:image,video,file',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            if ($request->hasFile('media')) {
                $message = $this->chatService->sendMediaMessage(
                    $conversation,
                    $user,
                    $request->file('media'),
                    $request->type
                );
            } else {
                $message = $this->chatService->sendMessage(
                    $conversation,
                    $user,
                    $request->input('content'),
                    'text',
                    ['reply_to_id' => $request->reply_to_id]
                );
            }

            return response()->json([
                'message' => $this->formatMessage($message, $user)
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function markMessagesAsRead(Request $request, Conversation $conversation)
    {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'message_ids' => 'required|array',
            'message_ids.*' => 'integer|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $messages = $conversation->messages()
            ->whereIn('id', $request->message_ids)
            ->get();

        foreach ($messages as $message) {
            $message->markAsRead($user);
        }

        return response()->json(['success' => true]);
    }

    public function searchMessages(Request $request, Conversation $conversation)
    {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $messages = $this->chatService->searchMessages(
            $conversation,
            $request->query,
            $user
        );

        return response()->json([
            'messages' => $messages->map(function ($message) use ($user) {
                return $this->formatMessage($message, $user);
            }),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'last_page' => $messages->lastPage(),
            ]
        ]);
    }

    public function deleteMessage(Request $request, Conversation $conversation, Message $message)
    {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['error' => 'Message not found in conversation'], 404);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->chatService->deleteMessage($message, $user, $request->reason);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

        public function addGroupParticipants(Request $request, Conversation $conversation)
        {
        $user = $this->getAuthenticatedUser();

        if (!$conversation->isUserParticipant($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($conversation->type !== 'group') {
            return response()->json(['error' => 'Not a group conversation'], 400);
        }

        $role = $conversation->getParticipantRole($user);
        if (!in_array($role, ['creator', 'admin'])) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validator = Validator::make($request->all(), [
            'participants' => 'required|array',
            'participants.*.id' => 'required|integer',
            'participants.*.type' => 'required|in:student,staff',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 🛑 Students can't add staff
        if ($user instanceof Student && collect($request->participants)->contains(fn ($p) => $p['type'] === 'staff')) {
            return response()->json(['error' => 'Students cannot add staff to group chats'], 403);
        }

        $participants = [];
        foreach ($request->participants as $participantData) {
            $participant = $this->getParticipant($participantData['type'], $participantData['id']);
            if ($participant) {
                $this->chatService->addParticipant($conversation, $participant);
                $participants[] = $participant;
            }
        }

        return response()->json([
            'success' => true,
            'participants' => collect($participants)->map(fn ($p) => $this->formatUser($p))
        ]);
    }


    private function getAuthenticatedUser()
    {
        return request()->user(); // ✅ works with tokens
    }
    private function getParticipant($type, $id)
    {
        return $type === 'student'
            ? Student::find($id)
            : Staff::find($id);
    }

    private function formatUser($user)
    {
        return [
            'id' => $user->id,
            'type' => $user instanceof Student ? 'student' : 'staff',
            'name' => trim($user->fname . ' ' . $user->lname),
            'email' => $user->email,
            'avatar' => $user->passport ?? null,
        ];
    }

    private function formatMessage(Message $message, $user)
    {
        return [
            'id' => $message->id,
            'content' => $message->content,
            'type' => $message->type,
            'sender' => $this->formatUser($message->sender),
            'metadata' => $message->metadata,
            'status' => $message->status,
            'is_read' => $message->isReadBy($user),
            'reply_to' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'content' => $message->replyTo->content,
                'sender' => $this->formatUser($message->replyTo->sender),
            ] : null,
            'media' => $message->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'original_filename' => $media->original_filename,
                    'mime_type' => $media->mime_type,
                    'file_size' => $media->file_size,
                    'url' => $media->getFileUrl(),
                    'thumbnail_url' => $media->getThumbnailUrl(),
                ];
            }),
            'is_edited' => $message->is_edited,
            'edited_at' => $message->edited_at,
            'is_deleted' => $message->is_deleted,
            'created_at' => $message->created_at,
        ];
    }
}
