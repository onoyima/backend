<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the chat system
    |
    */

    'media' => [
        'disk' => 'chat_media',
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'allowed_mime_types' => [
            'image' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml',
            ],
            'video' => [
                'video/mp4',
                'video/avi',
                'video/mpeg',
                'video/quicktime',
                'video/x-msvideo',
                'video/x-matroska',
            ],
            'file' => [
                'application/pdf',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/x-rar-compressed',
            ],
        ],
        'thumbnails' => [
            'width' => 300,
            'height' => 300,
            'quality' => 85,
        ],
    ],

    'messages' => [
        'max_length' => 5000,
        'pagination' => [
            'per_page' => 20,
            'max_per_page' => 100,
        ],
        'search' => [
            'min_length' => 3,
            'max_length' => 100,
        ],
    ],

    'conversations' => [
        'group' => [
            'max_name_length' => 100,
            'max_description_length' => 500,
            'max_participants' => 100,
        ],
    ],

    'realtime' => [
        'enabled' => true,
        'broadcast_driver' => env('BROADCAST_DRIVER', 'pusher'),
        'queue_connection' => env('QUEUE_CONNECTION', 'sync'),
    ],

    'notifications' => [
        'enabled' => true,
        'channels' => ['database', 'broadcast'],
        'new_message_delay' => 5, // seconds
    ],

    'audit' => [
        'enabled' => true,
        'retention_days' => 365,
        'log_admin_actions' => true,
        'log_message_events' => true,
        'log_participant_changes' => true,
    ],

    'security' => [
        'encrypt_media_urls' => true,
        'media_url_expiry' => 3600, // seconds
        'rate_limit' => [
            'messages_per_minute' => 60,
            'conversations_per_hour' => 10,
        ],
    ],
];