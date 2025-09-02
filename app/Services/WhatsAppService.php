<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private $phoneNumberId;
    private $accessToken;
    private $apiVersion;
    private $businessAccountId;
    private $testNumber;

    public function __construct()
    {
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.token');
        $this->apiVersion = config('services.whatsapp.api_version', 'v22.0');
        $this->businessAccountId = config('services.whatsapp.business_account_id');
        $this->testNumber = config('services.whatsapp.test_number');
    }

    /**
     * Check if WhatsApp is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    /**
     * Format phone number for WhatsApp (Nigerian format)
     */
    public function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 234 (Nigeria country code)
        if (substr($phone, 0, 1) === '0') {
            $phone = '234' . substr($phone, 1);
        }
        
        // If doesn't start with 234, add it
        if (substr($phone, 0, 3) !== '234') {
            $phone = '234' . $phone;
        }
        
        return $phone;
    }

    /**
     * Send a text message via WhatsApp Business API
     */
    public function sendMessage(string $to, string $message): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('WhatsApp service is not properly configured');
        }

        $formattedPhone = $this->formatPhoneNumber($to);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedPhone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($url, $data);

            $responseData = $response->json();
            $success = $response->successful();

            if ($success) {
                Log::info('WhatsApp message sent successfully', [
                    'original_to' => $to,
                    'formatted_to' => $formattedPhone,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                    'wa_id' => $responseData['contacts'][0]['wa_id'] ?? null,
                ]);
            } else {
                Log::error('WhatsApp API error', [
                    'original_to' => $to,
                    'formatted_to' => $formattedPhone,
                    'http_code' => $response->status(),
                    'error' => $responseData['error'] ?? 'Unknown error',
                ]);
            }

            return [
                'success' => $success,
                'http_code' => $response->status(),
                'response' => $responseData,
                'message_id' => $success ? ($responseData['messages'][0]['id'] ?? null) : null,
                'wa_id' => $success ? ($responseData['contacts'][0]['wa_id'] ?? null) : null,
            ];

        } catch (Exception $e) {
            Log::error('WhatsApp service exception', [
                'original_to' => $to,
                'formatted_to' => $formattedPhone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => 0,
                'response' => null,
            ];
        }
    }

    /**
     * Send a template message via WhatsApp Business API
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], string $languageCode = 'en'): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('WhatsApp service is not properly configured');
        }

        $formattedPhone = $this->formatPhoneNumber($to);
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $templateData = [
            'name' => $templateName,
            'language' => [
                'code' => $languageCode
            ]
        ];

        if (!empty($parameters)) {
            $templateData['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $parameters
                ]
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedPhone,
            'type' => 'template',
            'template' => $templateData
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($url, $data);

            $responseData = $response->json();
            $success = $response->successful();

            if ($success) {
                Log::info('WhatsApp template sent successfully', [
                    'original_to' => $to,
                    'formatted_to' => $formattedPhone,
                    'template' => $templateName,
                    'message_id' => $responseData['messages'][0]['id'] ?? null,
                ]);
            } else {
                Log::error('WhatsApp template API error', [
                    'original_to' => $to,
                    'formatted_to' => $formattedPhone,
                    'template' => $templateName,
                    'http_code' => $response->status(),
                    'error' => $responseData['error'] ?? 'Unknown error',
                ]);
            }

            return [
                'success' => $success,
                'http_code' => $response->status(),
                'response' => $responseData,
                'message_id' => $success ? ($responseData['messages'][0]['id'] ?? null) : null,
            ];

        } catch (Exception $e) {
            Log::error('WhatsApp template service exception', [
                'original_to' => $to,
                'formatted_to' => $formattedPhone,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => 0,
                'response' => null,
            ];
        }
    }

    /**
     * Get WhatsApp Business Account information
     */
    public function getBusinessAccountInfo(): array
    {
        if (!$this->businessAccountId || !$this->accessToken) {
            return ['success' => false, 'error' => 'Business account ID or access token not configured'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->businessAccountId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->timeout(30)->get($url);

            return [
                'success' => $response->successful(),
                'http_code' => $response->status(),
                'data' => $response->json(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => 0,
            ];
        }
    }

    /**
     * Get phone number information
     */
    public function getPhoneNumberInfo(): array
    {
        if (!$this->phoneNumberId || !$this->accessToken) {
            return ['success' => false, 'error' => 'Phone number ID or access token not configured'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->timeout(30)->get($url);

            return [
                'success' => $response->successful(),
                'http_code' => $response->status(),
                'data' => $response->json(),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'http_code' => 0,
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
}