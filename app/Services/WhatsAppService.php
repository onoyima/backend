<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Utils\PhoneUtility;
use Twilio\Rest\Client as TwilioClient;

class WhatsAppService
{
    private $twilioClient;
    private $fromNumber;

    public function __construct()
    {
        // Initialize Twilio client if credentials are available
        if (config('services.twilio.sid') && config('services.twilio.token')) {
            $this->twilioClient = new TwilioClient(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );
            $this->fromNumber = config('services.twilio.whatsapp_from');
        }
    }

    /**
     * Check if WhatsApp is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->twilioClient) && !empty($this->fromNumber);
    }

    /**
     * Send a text message via Twilio WhatsApp
     * 
     * @param string $to The recipient phone number
     * @param string $message The message to send
     * @param bool $useTemplate Whether to use a template for business-initiated messages
     * @return array Response with success status and details
     */
    public function sendMessage(string $to, string $message, bool $useTemplate = true): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('WhatsApp service is not properly configured');
        }

        $formattedTo = PhoneUtility::formatForWhatsApp($to);
        $fromNumber = 'whatsapp:' . $this->fromNumber; // Direct format for Twilio

        try {
            // For business-initiated messages, we need to use a template to avoid error 63016
            // Error 63016: Failed to send freeform message because you are outside the allowed window
            if ($useTemplate) {
                // Use a simple notification template
                $messageParams = [
                    'from' => $fromNumber,
                    'body' => 'Your Veritas University notification is ready: ' . $message
                ];
            } else {
                // Use free-form message (only works within 24-hour conversation window)
                $messageParams = [
                    'from' => $fromNumber,
                    'body' => $message
                ];
            }
            
            // Send WhatsApp message using Twilio
            $message = $this->twilioClient->messages->create(
                $formattedTo,
                $messageParams
            );

            Log::info('WhatsApp message sent successfully', [
                'to' => $to,
                'formatted_to' => $formattedTo,
                'message_sid' => $message->sid,
                'status' => $message->status,
            ]);

            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
                'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                'date_updated' => $message->dateUpdated->format('Y-m-d H:i:s'),
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
            ];

        } catch (Exception $e) {
            Log::error('WhatsApp service exception', [
                'to' => $to,
                'formatted_to' => $formattedTo,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Send a formatted message via Twilio WhatsApp using templates
     * 
     * Note: Twilio WhatsApp requires approved templates for business-initiated messages
     * This method formats the message according to our template structure
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $languageCode = 'en'): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('WhatsApp service is not properly configured');
        }

        // Convert template and parameters to a formatted message
        $message = $this->formatTemplateMessage($templateName, $parameters);
        
        // Use the standard message sending method with template flag set to true
        return $this->sendMessage($to, $message, true);
    }
    
    /**
     * Format a template message with parameters
     * 
     * @param string $templateName The name of the template
     * @param array $parameters The parameters to replace in the template
     * @return string The formatted message
     */
    private function formatTemplateMessage(string $templateName, array $parameters = []): string
    {
        // This is a simplified implementation
        // In a real application, you might want to load templates from a database or config
        
        // For Twilio WhatsApp, we need to use approved templates
        // These templates should match what's approved in your Twilio console
        $templates = [
            // Standard templates that should work with Twilio's default approved templates
            'exeat_request' => "Your Veritas University exeat request has been received. Reason: {{1}}. Duration: {{2}}. Please check your portal for updates.",
            'notification' => "Your Veritas University notification: {{1}} - {{2}}",
            'alert' => "ALERT from Veritas University: {{1}} - {{2}}"
        ];
        
        $template = $templates[$templateName] ?? "Your Veritas University notification: {{1}} - {{2}}";
        
        // Replace parameters in template
        foreach ($parameters as $index => $param) {
            $value = $param['text'] ?? $param['value'] ?? '';
            $template = str_replace("{{" . ($index + 1) . "}}", $value, $template);
        }
        
        return $template;
    }

    /**
     * Get WhatsApp account information from Twilio
     */
    public function getAccountInfo(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp service is not properly configured'];
        }

        try {
            // Get Twilio account information
            $account = $this->twilioClient->account->fetch();
            
            return [
                'success' => true,
                'account_sid' => $account->sid,
                'account_name' => $account->friendlyName,
                'account_status' => $account->status,
                'account_type' => $account->type,
                'whatsapp_from' => $this->fromNumber,
                'whatsapp_formatted' => PhoneUtility::formatForWhatsApp($this->fromNumber),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
    
    /**
     * Check message status
     * 
     * @param string $messageSid The Twilio message SID
     * @return array The message status information
     */
    public function checkMessageStatus(string $messageSid): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp service is not properly configured'];
        }
        
        try {
            $message = $this->twilioClient->messages($messageSid)->fetch();
            
            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
                'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                'date_updated' => $message->dateUpdated->format('Y-m-d H:i:s'),
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }

    /**
     * Create a formatted exeat consent message for WhatsApp
     */
    public function createExeatConsentMessage(string $studentName, string $reason, string $duration, string $approveUrl, string $rejectUrl): string
    {
        $message = "🏫 *EXEAT REQUEST*\n\n";
        $message .= "Your child *{$studentName}* has requested permission to leave school.\n\n";
        $message .= "📋 *Details:*\n";
        $message .= "• Reason: {$reason}\n";
        $message .= "• Duration: {$duration}\n\n";
        $message .= "Please respond:\n\n";
        $message .= "✅ *APPROVE:* {$approveUrl}\n";
        $message .= "❌ *REJECT:* {$rejectUrl}\n\n";
        $message .= "_This link expires in 24 hours._";

        return $message;
    }
    
    /**
     * Get delivery logs for WhatsApp messages
     * 
     * @param int $limit The maximum number of logs to retrieve
     * @return array The delivery logs
     */
    public function getDeliveryLogs(int $limit = 20): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp service is not properly configured'];
        }
        
        try {
            $messages = $this->twilioClient->messages->read(
                ['limit' => $limit]
            );
            
            $logs = [];
            foreach ($messages as $message) {
                // Only include WhatsApp messages
                if (strpos($message->from, 'whatsapp:') === 0 || strpos($message->to, 'whatsapp:') === 0) {
                    $logs[] = [
                        'message_sid' => $message->sid,
                        'from' => $message->from,
                        'to' => $message->to,
                        'status' => $message->status,
                        'direction' => $message->direction,
                        'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                        'date_updated' => $message->dateUpdated->format('Y-m-d H:i:s'),
                        'error_code' => $message->errorCode,
                        'error_message' => $message->errorMessage,
                    ];
                }
            }
            
            return [
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
}
