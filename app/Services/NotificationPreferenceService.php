<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\Student;
use App\Models\Staff;
use App\Models\StudentContact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationPreferenceService
{
    /**
     * Get user notification preferences.
     */
    public function getUserPreferences(string $userType, int $userId): array
    {
        $cacheKey = "notification_preferences_{$userType}_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userType, $userId) {
            $preferences = NotificationPreference::where('user_type', $userType)
                ->where('user_id', $userId)
                ->get();
                
            // If no preferences exist, create defaults
            if ($preferences->isEmpty()) {
                $preferences = $this->createDefaultPreferences($userType, $userId);
            }
            
            // Convert channel-based preferences to boolean format
            $result = [
                'in_app_enabled' => false,
                'email_enabled' => false,
                'sms_enabled' => false,
                'push_enabled' => false,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'frequency' => 'immediate'
            ];
            
            foreach ($preferences as $preference) {
                $channelKey = $preference->channel . '_enabled';
                if (isset($result[$channelKey])) {
                    $result[$channelKey] = $preference->enabled;
                }
                
                // Set common fields from the first preference
                if ($preference->quiet_hours_start) {
                    $result['quiet_hours_start'] = $preference->quiet_hours_start;
                }
                if ($preference->quiet_hours_end) {
                    $result['quiet_hours_end'] = $preference->quiet_hours_end;
                }
                if ($preference->frequency) {
                    $result['frequency'] = $preference->frequency;
                }
            }
            
            return $result;
        });
    }

    /**
     * Create or update user notification preferences.
     */
    public function updateUserPreferences(
        string $userType,
        int $userId,
        array $preferences
    ): Collection {
        $updatedPreferences = collect();
        
        // Handle channel-based preferences
        foreach ($preferences as $key => $value) {
            if (in_array($key, ['in_app_enabled', 'email_enabled', 'sms_enabled', 'push_enabled'])) {
                $channel = str_replace('_enabled', '', $key);
                
                $preference = NotificationPreference::updateOrCreate(
                    [
                        'user_type' => $userType,
                        'user_id' => $userId,
                        'notification_type' => 'all',
                        'channel' => $channel
                    ],
                    [
                        'enabled' => $value,
                        'quiet_hours_start' => $preferences['quiet_hours_start'] ?? '22:00',
                        'quiet_hours_end' => $preferences['quiet_hours_end'] ?? '06:00',
                        'frequency' => 'immediate'
                    ]
                );
                
                $updatedPreferences->push($preference);
            }
        }
        
        // If no channel preferences provided, create defaults
        if ($updatedPreferences->isEmpty()) {
            $updatedPreferences = $this->createDefaultPreferences($userType, $userId);
        }
        
        // Clear cache
        $this->clearUserPreferencesCache($userType, $userId);
        
        return $updatedPreferences;
    }

    /**
     * Get default preferences for a user type.
     */
    public function getDefaultPreferences(string $userType): array
    {
        $channels = [
            'in_app' => true,  // Always enabled for all users
            'email' => false,
            'sms' => false,
            'push' => false
        ];
        
        // Customize defaults based on user type
        switch ($userType) {
            case NotificationPreference::USER_TYPE_STUDENT:
                $channels['email'] = true;  // Enable email for students
                break;
                
            case NotificationPreference::USER_TYPE_STAFF:
                // Staff only get in-app notifications by default
                break;
        }
        
        return $channels;
    }

    /**
     * Create default preferences for a user.
     */
    public function createDefaultPreferences(string $userType, int $userId): Collection
    {
        $channels = $this->getDefaultPreferences($userType);
        $preferences = collect();
        
        foreach ($channels as $channel => $enabled) {
            $preference = NotificationPreference::create([
                'user_type' => $userType,
                'user_id' => $userId,
                'notification_type' => 'all',
                'channel' => $channel,
                'enabled' => $enabled,
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '06:00',
                'frequency' => 'immediate'
            ]);
            $preferences->push($preference);
        }
        
        return $preferences;
    }

    /**
     * Get preferences for multiple users.
     */
    public function getBulkPreferences(array $users): Collection
    {
        $preferences = collect();
        
        foreach ($users as $user) {
            $preference = $this->getUserPreferences($user['type'], $user['id']);
            
            if (!$preference) {
                $preference = $this->createDefaultPreferences($user['type'], $user['id']);
            }
            
            $preferences->push([
                'user' => $user,
                'preferences' => $preference
            ]);
        }
        
        return $preferences;
    }

    /**
     * Update notification type preference.
     */
    public function updateNotificationType(
        string $userType,
        int $userId,
        string $notificationType
    ): NotificationPreference {
        return $this->updateUserPreferences($userType, $userId, [
            'notification_type' => $notificationType
        ]);
    }

    /**
     * Update delivery method preferences.
     */
    public function updateDeliveryMethods(
        string $userType,
        int $userId,
        array $methods
    ): NotificationPreference {
        $updates = [];
        
        foreach (['in_app', 'email', 'sms', 'whatsapp'] as $method) {
            $updates["{$method}_enabled"] = in_array($method, $methods);
        }
        
        return $this->updateUserPreferences($userType, $userId, $updates);
    }

    /**
     * Update quiet hours.
     */
    public function updateQuietHours(
        string $userType,
        int $userId,
        string $startTime,
        string $endTime
    ): NotificationPreference {
        return $this->updateUserPreferences($userType, $userId, [
            'quiet_hours_start' => $startTime,
            'quiet_hours_end' => $endTime
        ]);
    }

    /**
     * Disable all notifications for a user.
     */
    public function disableAllNotifications(string $userType, int $userId): NotificationPreference
    {
        return $this->updateUserPreferences($userType, $userId, [
            'in_app_enabled' => false,
            'email_enabled' => false,
            'sms_enabled' => false,
            'whatsapp_enabled' => false
        ]);
    }

    /**
     * Enable all notifications for a user.
     */
    public function enableAllNotifications(string $userType, int $userId): NotificationPreference
    {
        return $this->updateUserPreferences($userType, $userId, [
            'in_app_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true
        ]);
    }

    /**
     * Get users who have specific notification preferences.
     */
    public function getUsersWithPreference(string $preferenceKey, $preferenceValue): Collection
    {
        return NotificationPreference::where($preferenceKey, $preferenceValue)
            ->get()
            ->map(function ($preference) {
                return [
                    'type' => $preference->user_type,
                    'id' => $preference->user_id,
                    'preferences' => $preference
                ];
            });
    }

    /**
     * Get users who have enabled a specific delivery method.
     */
    public function getUsersWithDeliveryMethod(string $method): Collection
    {
        $column = "{$method}_enabled";
        
        return NotificationPreference::where($column, true)
            ->get()
            ->map(function ($preference) {
                return [
                    'type' => $preference->user_type,
                    'id' => $preference->user_id,
                    'preferences' => $preference
                ];
            });
    }

    /**
     * Check if user has enabled a specific delivery method.
     */
    public function hasDeliveryMethodEnabled(
        string $userType,
        int $userId,
        string $method
    ): bool {
        $preference = $this->getUserPreferences($userType, $userId);
        
        if (!$preference) {
            $defaults = $this->getDefaultPreferences($userType);
            return $defaults["{$method}_enabled"] ?? false;
        }
        
        return $preference->{"{$method}_enabled"} ?? false;
    }

    /**
     * Get active delivery methods for a user.
     */
    public function getActiveDeliveryMethods(
        string $userType,
        int $userId,
        bool $includeUrgentOverride = false
    ): array {
        $preference = $this->getUserPreferences($userType, $userId);
        
        if (!$preference) {
            $preference = $this->getUserPreferences($userType, $userId); // This will create defaults if none exist
        }
        
        $methods = [];
        
        if ($preference['in_app_enabled']) {
            $methods[] = 'in_app';
        }
        
        if ($preference['email_enabled']) {
            $methods[] = 'email';
        }
        
        if ($preference['sms_enabled']) {
            $methods[] = 'sms';
        }
        
        if ($preference['push_enabled']) {
            $methods[] = 'whatsapp';
        }
        
        // For urgent notifications, ensure at least one method is enabled
        if ($includeUrgentOverride && empty($methods)) {
            $methods = ['in_app', 'email'];
        }
        
        return $methods;
    }

    /**
     * Check if user wants specific notification types.
     */
    public function wantsNotificationType(
        string $userType,
        int $userId,
        string $notificationType
    ): bool {
        $preference = $this->getUserPreferences($userType, $userId);
        
        if (!$preference) {
            return true; // Default to receiving all notifications
        }
        
        // If user wants all notifications
        if ($preference->notification_type === NotificationPreference::NOTIFICATION_TYPE_ALL) {
            return true;
        }
        
        // If user wants only urgent notifications
        if ($preference->notification_type === NotificationPreference::NOTIFICATION_TYPE_URGENT) {
            return in_array($notificationType, [
                'emergency',
                'approval_required'
            ]);
        }
        
        // If user wants no notifications
        if ($preference->notification_type === NotificationPreference::NOTIFICATION_TYPE_NONE) {
            return false;
        }
        
        return true;
    }

    /**
     * Get notification summary for a user.
     */
    public function getNotificationSummary(string $userType, int $userId): array
    {
        $preference = $this->getUserPreferences($userType, $userId);
        
        if (!$preference) {
            $preference = $this->getUserPreferences($userType, $userId); // This will create defaults if none exist
        }
        
        $activeMethods = $this->getActiveDeliveryMethods($userType, $userId);
        
        return [
            'notification_type' => 'all', // Default since we don't store this per channel
            'active_methods' => $activeMethods,
            'quiet_hours' => [
                'start' => $preference['quiet_hours_start'],
                'end' => $preference['quiet_hours_end']
            ],
            'methods' => [
                'in_app' => $preference['in_app_enabled'],
                'email' => $preference['email_enabled'],
                'sms' => $preference['sms_enabled'],
                'whatsapp' => $preference['push_enabled'] // Using push_enabled for whatsapp
            ]
        ];
    }

    /**
     * Import preferences from another user.
     */
    public function importPreferences(
        string $fromUserType,
        int $fromUserId,
        string $toUserType,
        int $toUserId
    ): ?NotificationPreference {
        $sourcePreference = $this->getUserPreferences($fromUserType, $fromUserId);
        
        if (!$sourcePreference) {
            return null;
        }
        
        $preferences = $sourcePreference->toArray();
        unset($preferences['id'], $preferences['user_type'], $preferences['user_id'], 
              $preferences['created_at'], $preferences['updated_at']);
        
        return $this->updateUserPreferences($toUserType, $toUserId, $preferences);
    }

    /**
     * Reset preferences to default.
     */
    public function resetToDefaults(string $userType, int $userId): NotificationPreference
    {
        $defaults = $this->getDefaultPreferences($userType);
        
        return $this->updateUserPreferences($userType, $userId, $defaults);
    }

    /**
     * Clear user preferences cache.
     */
    public function clearUserPreferencesCache(string $userType, int $userId): void
    {
        $cacheKey = "notification_preferences_{$userType}_{$userId}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all preferences cache.
     */
    public function clearAllPreferencesCache(): void
    {
        // This would require a more sophisticated cache tagging system
        // For now, we'll just clear the entire cache
        Cache::flush();
    }

    /**
     * Get preferences statistics.
     */
    public function getPreferencesStats(): array
    {
        $stats = NotificationPreference::selectRaw('
            user_type,
            notification_type,
            COUNT(*) as count,
            AVG(CASE WHEN in_app_enabled = 1 THEN 1 ELSE 0 END) * 100 as in_app_percentage,
            AVG(CASE WHEN email_enabled = 1 THEN 1 ELSE 0 END) * 100 as email_percentage,
            AVG(CASE WHEN sms_enabled = 1 THEN 1 ELSE 0 END) * 100 as sms_percentage,
            AVG(CASE WHEN whatsapp_enabled = 1 THEN 1 ELSE 0 END) * 100 as whatsapp_percentage
        ')
        ->groupBy('user_type', 'notification_type')
        ->get()
        ->groupBy('user_type');
        
        return $stats->map(function ($userTypeStats) {
            return $userTypeStats->map(function ($stat) {
                return [
                    'notification_type' => $stat->notification_type,
                    'count' => $stat->count,
                    'delivery_methods' => [
                        'in_app' => round($stat->in_app_percentage, 2),
                        'email' => round($stat->email_percentage, 2),
                        'sms' => round($stat->sms_percentage, 2),
                        'whatsapp' => round($stat->whatsapp_percentage, 2)
                    ]
                ];
            })->keyBy('notification_type');
        })->toArray();
    }
}