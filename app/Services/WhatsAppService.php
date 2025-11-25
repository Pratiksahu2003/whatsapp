<?php

namespace App\Services;

use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $phoneNumberId;
    protected $accessToken;
    protected $verifyToken;

    public function __construct($user = null)
    {
        if ($user && $user->hasWhatsAppCredentials()) {
            // Use user-specific credentials - Default to Cloud API v24.0 (latest)
            $this->apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v24.0';
            $this->phoneNumberId = $user->whatsapp_phone_number_id;
            $this->accessToken = $user->whatsapp_access_token;
            $this->verifyToken = $user->whatsapp_verify_token;
        } else {
            // Fallback to config (for backward compatibility) - Default to Cloud API v24.0 (latest)
            $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v24.0');
            $this->phoneNumberId = config('services.whatsapp.phone_number_id');
            $this->accessToken = config('services.whatsapp.access_token');
            $this->verifyToken = config('services.whatsapp.verify_token');
        }
    }

    /**
     * Send a text message via WhatsApp
     *
     * @param string $to Phone number in international format (e.g., 1234567890)
     * @param string $message Message text
     * @param int|null $userId User ID to associate with the message
     * @return array
     */
    public function sendTextMessage(string $to, string $message, ?int $userId = null): array
    {
        try {
            // Validate credentials before sending
            if (empty($this->phoneNumberId) || empty($this->accessToken)) {
                return [
                    'success' => false,
                    'error' => 'WhatsApp credentials are not configured. Please go to Settings and configure your Phone Number ID and Access Token.',
                    'error_code' => 'MISSING_CREDENTIALS'
                ];
            }

            // Normalize phone number - remove any non-digit characters
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            
            // Validate phone number format (should be 7-15 digits for international format)
            if (strlen($normalizedTo) < 7 || strlen($normalizedTo) > 15) {
                return [
                    'success' => false,
                    'error' => 'Invalid phone number format. Phone number must be 7-15 digits in international format (country code + number, e.g., 919873105808 for India).',
                    'error_code' => 'INVALID_PHONE_FORMAT'
                ];
            }

            Log::info('Sending WhatsApp message', [
                'to' => $normalizedTo,
                'original_to' => $to,
                'message_length' => strlen($message),
                'phone_number_id' => $this->phoneNumberId
            ]);

            // According to WhatsApp Cloud API v24.0 documentation:
            // Endpoint: POST https://graph.facebook.com/v24.0/{phone-number-id}/messages
            // Headers: Authorization: Bearer {access-token}, Content-Type: application/json
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $normalizedTo,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message
                ]
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $messageId = $responseData['messages'][0]['id'] ?? null;
                
                // Check for contacts information in response
                $contacts = $responseData['contacts'] ?? [];
                $contactStatus = $contacts[0]['wa_id'] ?? null;
                $contactInput = $contacts[0]['input'] ?? null;
                
                // Log full response for debugging delivery issues
                Log::info('WhatsApp message API response', [
                    'to' => $normalizedTo,
                    'message_id' => $messageId,
                    'contact_status' => $contactStatus,
                    'contact_input' => $contactInput,
                    'has_contacts' => !empty($contacts),
                    'response_status' => $response->status(),
                    'full_response' => $responseData
                ]);

                // Check if message was actually accepted by WhatsApp
                if (!$messageId) {
                    Log::error('WhatsApp API returned success but no message_id - message may not be delivered', [
                        'to' => $normalizedTo,
                        'response' => $responseData,
                        'has_contacts' => !empty($contacts)
                    ]);
                    
                    // Save as failed since we can't track it
                    $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
                    Message::create([
                        'user_id' => $userId,
                        'direction' => 'sent',
                        'phone_number' => $normalizedTo,
                        'conversation_id' => $conversationId,
                        'message_type' => 'text',
                        'content' => $message,
                        'status' => 'failed',
                        'error_message' => 'Message API call succeeded but no message_id returned. This usually means: 1) Recipient has not messaged you in the last 24 hours (use template message instead), 2) Phone number is invalid, or 3) Recipient does not have WhatsApp.',
                        'metadata' => $responseData,
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Message API call succeeded but no message_id was returned. This usually means the message cannot be delivered. Common reasons: 1) Recipient has not messaged you in the last 24 hours - you must use a template message for new contacts, 2) Phone number is invalid or not on WhatsApp, 3) Recipient has blocked your number. Please use a template message for contacts who haven\'t messaged you recently.',
                        'error_code' => 'NO_MESSAGE_ID',
                        'data' => $responseData,
                        'help_text' => 'For new contacts or contacts who haven\'t messaged you in 24 hours, you must use Template Messages. Free-form messages only work within 24 hours of the recipient\'s last message to you.'
                    ];
                }

                // Check if contact information is missing (indicates potential delivery issue)
                $deliveryWarning = null;
                if (empty($contacts)) {
                    $deliveryWarning = 'Contact verification status unknown. If the recipient has not messaged you in the last 24 hours, the message may not be delivered. Use a template message for new contacts or contacts outside the 24-hour window.';
                    Log::warning('Message sent but no contact information in response', [
                        'to' => $normalizedTo,
                        'message_id' => $messageId,
                        'warning' => 'This may indicate the recipient is not in your contact list or outside 24-hour window'
                    ]);
                } else {
                    // Check if contact input matches (phone number validation)
                    if ($contactInput && $contactInput !== $normalizedTo) {
                        $deliveryWarning = "Phone number format may be incorrect. Contact input: {$contactInput}, Sent to: {$normalizedTo}. Message may not be delivered.";
                        Log::warning('Phone number mismatch in contact information', [
                            'sent_to' => $normalizedTo,
                            'contact_input' => $contactInput,
                            'message_id' => $messageId
                        ]);
                    }
                }

                // Generate conversation ID
                $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
                
                // Save to database
                // Note: Status starts as 'sent' - webhook will update to 'delivered' when message is actually delivered
                // If webhook doesn't update within reasonable time, we can assume delivered (see syncPendingMessages)
                Message::create([
                    'user_id' => $userId,
                    'direction' => 'sent',
                    'message_id' => $messageId,
                    'phone_number' => $normalizedTo,
                    'conversation_id' => $conversationId,
                    'message_type' => 'text',
                    'content' => $message,
                    'status' => 'sent', // Will be updated to 'delivered' via webhook
                    'sent_at' => now(),
                    'metadata' => $responseData,
                ]);

                Log::info('WhatsApp message saved to database', [
                    'message_id' => $messageId,
                    'to' => $normalizedTo,
                    'status' => 'sent',
                    'has_delivery_warning' => !empty($deliveryWarning)
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'contact_status' => $contactStatus,
                    'contact_input' => $contactInput,
                    'data' => $responseData,
                    'warning' => $deliveryWarning,
                    'note' => empty($contacts) ? 'IMPORTANT: If the recipient has not messaged you in the last 24 hours, this message will NOT be delivered. You must use a Template Message for new contacts or contacts outside the 24-hour messaging window.' : null
                ];
            }

            $errorData = $response->json('error', []);
            $errorMessage = $errorData['message'] ?? 'Unknown error';
            $errorCode = $errorData['code'] ?? null;
            $errorSubcode = $errorData['error_subcode'] ?? null;
            
            // Provide helpful error messages for common errors
            $helpfulMessage = $this->getHelpfulErrorMessage($errorCode, $errorSubcode, $errorMessage);
            
            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'error_code' => $errorCode,
                'error_subcode' => $errorSubcode,
                'response' => $response->json()
            ]);

            // Save failed message to database
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'message_type' => 'text',
                'content' => $message,
                'status' => 'failed',
                'error_message' => $helpfulMessage,
                'metadata' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $helpfulMessage,
                'error_code' => $errorCode,
                'error_subcode' => $errorSubcode,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp service exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Save exception to database
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'message_type' => 'text',
                'content' => $message,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get helpful error message based on error code
     *
     * @param int|null $errorCode
     * @param int|null $errorSubcode
     * @param string $defaultMessage
     * @return string
     */
    protected function getHelpfulErrorMessage(?int $errorCode, ?int $errorSubcode, string $defaultMessage): string
    {
        // Error code 133010: Account not registered
        if ($errorCode === 133010 || ($errorCode === 100 && $errorSubcode === 133010)) {
            return 'Your WhatsApp Business Account phone number is not registered. ' .
                   'Please register your phone number in Meta Business Manager: ' .
                   '1. Go to Meta Business Manager > WhatsApp > API Setup, ' .
                   '2. Complete the phone number registration process, ' .
                   '3. Verify your phone number, ' .
                   '4. Make sure your Phone Number ID in Settings matches the registered number. ' .
                   'If you\'re using a test number, you may need to register it for production use.';
        }

        // Error code 131047: Message undeliverable
        if ($errorCode === 131047) {
            return 'Message cannot be delivered. Possible reasons: ' .
                   '1) Recipient phone number is invalid or not registered on WhatsApp, ' .
                   '2) Recipient has blocked your number, ' .
                   '3) You are trying to send a session message but recipient hasn\'t messaged you in the last 24 hours (use a template message instead).';
        }
        
        // Error code 131026: Recipient phone number not registered
        if ($errorCode === 131026) {
            return 'The recipient phone number is not registered on WhatsApp. ' .
                   'Please verify: 1) The phone number is correct and in international format (country code + number), ' .
                   '2) The recipient has WhatsApp installed, ' .
                   '3) The recipient has not blocked your number.';
        }
        
        // Error code 131031: Too many messages
        if ($errorCode === 131031) {
            return 'Too many messages sent. You have exceeded the rate limit. ' .
                   'Please wait before sending more messages. For new contacts, use template messages.';
        }
        
        // Error code 131051: Message expired
        if ($errorCode === 131051) {
            return 'Message expired. The 24-hour messaging window has closed. ' .
                   'You must use a template message to contact this recipient.';
        }

        // Error code 131026: Recipient phone number not registered
        if ($errorCode === 131026) {
            return 'The recipient phone number is not registered on WhatsApp. ' .
                   'Please verify: 1) The phone number is correct and in international format (country code + number), ' .
                   '2) The recipient has WhatsApp installed, 3) The recipient has not blocked your number.';
        }

        // Error code 190: Invalid access token
        if ($errorCode === 190) {
            return 'Invalid or expired access token. Please update your Access Token in Settings. ' .
                   'You can generate a new token from Meta Business Manager > WhatsApp > API Setup.';
        }

        // Error code 100: Invalid parameter
        if ($errorCode === 100) {
            if ($errorSubcode === 131047) {
                return 'Invalid recipient phone number. Please check the phone number format ' .
                       '(should be digits only, without + or spaces).';
            }
            return 'Invalid request parameters. ' . $defaultMessage;
        }

        // Error code 80007: Rate limit exceeded
        if ($errorCode === 80007) {
            return 'Rate limit exceeded. You are sending messages too quickly. ' .
                   'Please wait a few moments before trying again.';
        }

        // Return default message if no specific handler
        return $defaultMessage;
    }

    /**
     * Send a media message (image, document, etc.)
     *
     * @param string $to Phone number in international format
     * @param string $mediaUrl URL of the media file
     * @param string $type Media type (image, document, audio, video)
     * @param string|null $caption Optional caption for the media
     * @param int|null $userId User ID to associate with the message
     * @return array
     */
    public function sendMediaMessage(string $to, string $mediaUrl, string $type = 'image', ?string $caption = null, ?int $userId = null): array
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => $type,
                $type => [
                    'link' => $mediaUrl
                ]
            ];

            if ($caption && in_array($type, ['image', 'video', 'document'])) {
                $payload[$type]['caption'] = $caption;
            }

            // According to WhatsApp Cloud API v24.0 documentation
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id');
                
                // Normalize phone number
                $normalizedTo = preg_replace('/[^0-9]/', '', $to);
                
                // Generate conversation ID
                $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
                
                // Save to database
                Message::create([
                    'user_id' => $userId,
                    'direction' => 'sent',
                    'message_id' => $messageId,
                    'phone_number' => $normalizedTo,
                    'conversation_id' => $conversationId,
                    'message_type' => $type,
                    'content' => $caption,
                    'media_url' => $mediaUrl,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => $response->json(),
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'data' => $response->json()
                ];
            }

            $errorMessage = $response->json('error.message', 'Unknown error');
            
            // Normalize phone number
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            
            // Generate conversation ID
            $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
            
            // Save failed message to database
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'conversation_id' => $conversationId,
                'message_type' => $type,
                'content' => $caption,
                'media_url' => $mediaUrl,
                'status' => 'failed',
                'error_message' => $errorMessage,
                'metadata' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp media message exception', [
                'message' => $e->getMessage()
            ]);

            // Normalize phone number
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            
            // Generate conversation ID
            $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
            
            // Save exception to database
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'conversation_id' => $conversationId,
                'message_type' => $type,
                'content' => $caption,
                'media_url' => $mediaUrl,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a template message
     *
     * @param string $to Phone number in international format
     * @param string $templateName Template name
     * @param string $languageCode Language code (e.g., 'en_US')
     * @param array $parameters Template parameters
     * @param int|null $userId User ID to associate with the message
     * @return array
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US', array $parameters = [], ?int $userId = null): array
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode
                    ]
                ]
            ];

            if (!empty($parameters)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function ($param) {
                            return ['type' => 'text', 'text' => $param];
                        }, $parameters)
                    ]
                ];
            }

            // According to WhatsApp Cloud API v24.0 documentation
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $messageId = $response->json('messages.0.id');
                
                // Normalize phone number
                $normalizedTo = preg_replace('/[^0-9]/', '', $to);
                
                // Generate conversation ID
                $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
                
                // Save to database
                Message::create([
                    'user_id' => $userId,
                    'direction' => 'sent',
                    'message_id' => $messageId,
                    'phone_number' => $normalizedTo,
                    'conversation_id' => $conversationId,
                    'message_type' => 'template',
                    'template_name' => $templateName,
                    'template_parameters' => $parameters,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => $response->json(),
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'data' => $response->json()
                ];
            }

            $errorMessage = $response->json('error.message', 'Unknown error');
            
            // Normalize phone number
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            
            // Generate conversation ID
            $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
            
            // Save failed message to database
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'conversation_id' => $conversationId,
                'message_type' => 'template',
                'template_name' => $templateName,
                'template_parameters' => $parameters,
                'status' => 'failed',
                'error_message' => $errorMessage,
                'metadata' => $response->json(),
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp template message exception', [
                'message' => $e->getMessage()
            ]);

            // Normalize phone number
            $normalizedTo = preg_replace('/[^0-9]/', '', $to);
            
            // Generate conversation ID
            $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
            
            // Save exception to database
            Message::create([
                'user_id' => $userId,
                'direction' => 'sent',
                'phone_number' => $normalizedTo,
                'conversation_id' => $conversationId,
                'message_type' => 'template',
                'template_name' => $templateName,
                'template_parameters' => $parameters,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify webhook token
     *
     * @param string $mode
     * @param string $token
     * @param string $challenge
     * @return string|false
     */
    public function verifyWebhook(string $mode, string $token, string $challenge)
    {
        Log::info('Verifying webhook', [
            'mode' => $mode,
            'token_provided' => !empty($token),
            'token_length' => strlen($token ?? ''),
            'has_verify_token' => !empty($this->verifyToken),
            'verify_token_length' => strlen($this->verifyToken ?? ''),
            'tokens_match' => $token === $this->verifyToken
        ]);

        if ($mode !== 'subscribe') {
            Log::warning('Webhook verification failed: Invalid mode', ['mode' => $mode]);
            return false;
        }

        if (empty($this->verifyToken)) {
            Log::error('Webhook verification failed: Verify token not configured');
            return false;
        }

        if ($token === $this->verifyToken) {
            Log::info('Webhook verification successful: Tokens match');
            return $challenge;
        }

        Log::warning('Webhook verification failed: Token mismatch', [
            'expected_length' => strlen($this->verifyToken),
            'provided_length' => strlen($token ?? '')
        ]);

        return false;
    }

    /**
     * Process status updates from webhook
     *
     * @param array $data
     * @return array
     */
    public function processStatusUpdate(array $data): array
    {
        try {
            // Validate webhook structure
            if (!isset($data['entry']) || !is_array($data['entry']) || empty($data['entry'])) {
                Log::warning('Invalid webhook structure: missing entry', ['data_keys' => array_keys($data)]);
                return ['success' => false, 'message' => 'Invalid webhook structure: missing entry'];
            }

            if (!isset($data['entry'][0]['changes']) || !is_array($data['entry'][0]['changes']) || empty($data['entry'][0]['changes'])) {
                Log::warning('Invalid webhook structure: missing changes', ['entry' => $data['entry'][0] ?? null]);
                return ['success' => false, 'message' => 'Invalid webhook structure: missing changes'];
            }

            if (!isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                Log::debug('No status updates in webhook data', [
                    'has_value' => isset($data['entry'][0]['changes'][0]['value']),
                    'value_keys' => isset($data['entry'][0]['changes'][0]['value']) ? array_keys($data['entry'][0]['changes'][0]['value']) : []
                ]);
                return ['success' => false, 'message' => 'No status updates in webhook data'];
            }

            $statuses = $data['entry'][0]['changes'][0]['value']['statuses'];
            if (!is_array($statuses) || empty($statuses)) {
                Log::warning('Statuses is not an array or is empty', ['statuses' => $statuses]);
                return ['success' => false, 'message' => 'Statuses array is empty'];
            }

            $processed = [];
            $errors = [];

            foreach ($statuses as $index => $status) {
                try {
                    $messageId = $status['id'] ?? null;
                    if (!$messageId) {
                        Log::warning('Status update received without message ID', [
                            'status_index' => $index,
                            'status' => $status
                        ]);
                        $errors[] = "Status update #{$index} missing message ID";
                        continue;
                    }

                    $statusValue = $status['status'] ?? null; // sent, delivered, read, failed
                    if (!$statusValue) {
                        Log::warning('Status update received without status value', [
                            'message_id' => $messageId,
                            'status' => $status
                        ]);
                        $errors[] = "Status update for {$messageId} missing status value";
                        continue;
                    }

                    $timestamp = $status['timestamp'] ?? time();
                    $recipientId = $status['recipient_id'] ?? null;

                    Log::info('Processing status update', [
                        'message_id' => $messageId,
                        'status' => $statusValue,
                        'recipient_id' => $recipientId,
                        'timestamp' => $timestamp
                    ]);

                    // Find message by message_id (try exact match first)
                    $message = Message::where('message_id', $messageId)->first();

                    // If not found, try multiple fallback methods
                    if (!$message && $recipientId) {
                        $normalizedRecipient = preg_replace('/[^0-9]/', '', $recipientId);
                        
                        // Method 1: Find by recipient phone number and recent timestamp (within last 24 hours)
                        $message = Message::where('phone_number', $normalizedRecipient)
                            ->where('direction', 'sent')
                            ->where('created_at', '>=', now()->subHours(24))
                            ->where(function($query) {
                                $query->whereNull('delivered_at')
                                      ->orWhereNull('message_id');
                            })
                            ->orderBy('created_at', 'desc')
                            ->first();
                        
                        // Method 2: If still not found, try without delivered_at filter
                        if (!$message) {
                            $message = Message::where('phone_number', $normalizedRecipient)
                                ->where('direction', 'sent')
                                ->where('created_at', '>=', now()->subHours(48))
                                ->orderBy('created_at', 'desc')
                                ->first();
                        }
                        
                        if ($message) {
                            // Update message_id if it was missing
                            if (!$message->message_id) {
                                $message->update(['message_id' => $messageId]);
                                Log::info('Message ID updated from status webhook', [
                                    'message_id' => $messageId,
                                    'phone_number' => $normalizedRecipient,
                                    'message_db_id' => $message->id
                                ]);
                            }
                        }
                    }

                if ($message) {
                    $updateData = [];
                    
                    // Update timestamps based on status
                    switch ($statusValue) {
                        case 'sent':
                            if (!$message->sent_at) {
                                $updateData['sent_at'] = date('Y-m-d H:i:s', $timestamp);
                            }
                            // Only update status if it's not already sent or higher
                            if (!in_array($message->status, ['sent', 'delivered', 'read'])) {
                                $updateData['status'] = 'sent';
                            }
                            break;
                        case 'delivered':
                            $updateData['delivered_at'] = date('Y-m-d H:i:s', $timestamp);
                            $updateData['status'] = 'delivered';
                            // Ensure sent_at is set if not already
                            if (!$message->sent_at) {
                                $updateData['sent_at'] = date('Y-m-d H:i:s', $timestamp);
                            }
                            break;
                        case 'read':
                            $updateData['read_at'] = date('Y-m-d H:i:s', $timestamp);
                            $updateData['status'] = 'read';
                            // Also ensure delivered_at and sent_at are set if not already
                            if (!$message->delivered_at) {
                                $updateData['delivered_at'] = date('Y-m-d H:i:s', $timestamp);
                            }
                            if (!$message->sent_at) {
                                $updateData['sent_at'] = date('Y-m-d H:i:s', $timestamp);
                            }
                            break;
                        case 'failed':
                            $updateData['failed_at'] = date('Y-m-d H:i:s', $timestamp);
                            $updateData['status'] = 'failed';
                            $updateData['error_message'] = $status['errors'][0]['message'] ?? 
                                ($status['errors'][0]['title'] ?? 'Message failed');
                            break;
                    }

                    // Only update if there's data to update
                    if (!empty($updateData)) {
                        $message->update($updateData);
                        $processed[] = ['message_id' => $messageId, 'status' => $statusValue, 'phone_number' => $message->phone_number];
                        
                        Log::info('Message status updated successfully', [
                            'message_id' => $messageId,
                            'old_status' => $message->getOriginal('status'),
                            'new_status' => $statusValue,
                            'user_id' => $message->user_id,
                            'phone_number' => $message->phone_number,
                            'timestamp' => $timestamp,
                            'update_data' => $updateData
                        ]);
                    } else {
                        Log::debug('Status update received but no changes needed', [
                            'message_id' => $messageId,
                            'status' => $statusValue,
                            'current_status' => $message->status
                        ]);
                    }
                } else {
                    // Log detailed information for debugging
                    $recentMessages = Message::where('direction', 'sent')
                        ->where('created_at', '>=', now()->subHours(24))
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get(['id', 'message_id', 'phone_number', 'status', 'created_at', 'user_id'])
                        ->toArray();
                    
                    Log::warning('Status update received for unknown message', [
                        'message_id' => $messageId,
                        'status' => $statusValue,
                        'recipient_id' => $recipientId,
                        'normalized_recipient' => $recipientId ? preg_replace('/[^0-9]/', '', $recipientId) : null,
                        'full_status' => $status,
                        'recent_sent_messages' => $recentMessages,
                        'total_recent_messages' => count($recentMessages)
                    ]);
                    
                    $errors[] = "Status update for message {$messageId} (status: {$statusValue}) - message not found in database";
                }
                } catch (\Exception $e) {
                    Log::error('Error processing individual status update', [
                        'status_index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'status' => $status
                    ]);
                    $errors[] = "Error processing status update #{$index}: " . $e->getMessage();
                }
            }

            $result = [
                'success' => count($processed) > 0 || empty($errors),
                'processed' => $processed,
                'processed_count' => count($processed)
            ];
            
            if (!empty($errors)) {
                $result['errors'] = $errors;
                $result['error_count'] = count($errors);
            }
            
            Log::info('Status update processing completed', [
                'processed_count' => count($processed),
                'error_count' => count($errors),
                'processed' => $processed
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp status update processing exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process incoming webhook
     *
     * @param array $data
     * @param int|null $userId User ID to associate with received messages
     * @return array
     */
    public function processWebhook(array $data, ?int $userId = null): array
    {
        try {
            // Validate webhook structure
            if (!isset($data['entry']) || !is_array($data['entry']) || empty($data['entry'])) {
                Log::warning('Invalid webhook structure: missing entry', ['data_keys' => array_keys($data)]);
                return ['success' => false, 'message' => 'Invalid webhook structure: missing entry'];
            }

            if (!isset($data['entry'][0]['changes']) || !is_array($data['entry'][0]['changes']) || empty($data['entry'][0]['changes'])) {
                Log::warning('Invalid webhook structure: missing changes', ['entry' => $data['entry'][0] ?? null]);
                return ['success' => false, 'message' => 'Invalid webhook structure: missing changes'];
            }

            $value = $data['entry'][0]['changes'][0]['value'] ?? null;
            if (!$value) {
                Log::warning('Invalid webhook structure: missing value', ['changes' => $data['entry'][0]['changes'][0] ?? null]);
                return ['success' => false, 'message' => 'Invalid webhook structure: missing value'];
            }

            // Check if this is a status update
            if (isset($value['statuses']) && is_array($value['statuses']) && !empty($value['statuses'])) {
                Log::info('Processing status updates from webhook', ['status_count' => count($value['statuses'])]);
                return $this->processStatusUpdate($data);
            }

            // Check if this is a message
            if (!isset($value['messages']) || !is_array($value['messages']) || empty($value['messages'])) {
                Log::debug('No messages or status updates in webhook data', [
                    'has_statuses' => isset($value['statuses']),
                    'has_messages' => isset($value['messages']),
                    'value_keys' => array_keys($value)
                ]);
                return ['success' => false, 'message' => 'No messages or status updates in webhook data'];
            }

            $messages = $value['messages'];
            $processed = [];
            $errors = [];

            // Get phone number ID from webhook metadata for user identification
            $phoneNumberId = $data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;
            
            // Try to find user by phone number ID if userId not provided
            if (!$userId && $phoneNumberId) {
                $user = \App\Models\User::where('whatsapp_phone_number_id', $phoneNumberId)->first();
                if ($user) {
                    $userId = $user->id;
                    Log::info('User identified from phone number ID in webhook', [
                        'user_id' => $userId,
                        'phone_number_id' => $phoneNumberId
                    ]);
                }
            }

            foreach ($messages as $index => $message) {
                try {
                    // Validate required fields
                    if (!isset($message['from']) || !isset($message['id']) || !isset($message['type'])) {
                        Log::warning('Invalid message structure in webhook', [
                            'message_index' => $index,
                            'has_from' => isset($message['from']),
                            'has_id' => isset($message['id']),
                            'has_type' => isset($message['type']),
                            'message_keys' => array_keys($message)
                        ]);
                        $errors[] = "Message #{$index} missing required fields (from, id, or type)";
                        continue;
                    }

                $from = $message['from'];
                $messageId = $message['id'];
                $type = $message['type'];
                $timestamp = $message['timestamp'] ?? time();

                $messageData = [
                    'from' => $from,
                    'message_id' => $messageId,
                    'type' => $type,
                    'timestamp' => $timestamp
                ];

                $dbData = [
                    'direction' => 'received',
                    'message_id' => $messageId,
                    'phone_number' => $from,
                    'message_type' => $type,
                    'whatsapp_timestamp' => date('Y-m-d H:i:s', $timestamp),
                    'status' => 'delivered',
                    'metadata' => $message,
                ];

                // Handle different message types
                switch ($type) {
                    case 'text':
                        $messageData['text'] = $message['text']['body'];
                        $dbData['content'] = $message['text']['body'];
                        break;
                    case 'image':
                    case 'video':
                    case 'audio':
                    case 'document':
                        $messageData['media_id'] = $message[$type]['id'];
                        $messageData['mime_type'] = $message[$type]['mime_type'] ?? null;
                        $dbData['media_id'] = $message[$type]['id'];
                        $dbData['mime_type'] = $message[$type]['mime_type'] ?? null;
                        if (isset($message[$type]['caption'])) {
                            $messageData['caption'] = $message[$type]['caption'];
                            $dbData['content'] = $message[$type]['caption'];
                        }
                        break;
                    case 'location':
                        $messageData['latitude'] = $message['location']['latitude'];
                        $messageData['longitude'] = $message['location']['longitude'];
                        $dbData['content'] = "Lat: {$message['location']['latitude']}, Lng: {$message['location']['longitude']}";
                        break;
                }

                // If no user_id provided, try multiple fallback methods
                if (!$userId) {
                    // Method 1: Try to find user from existing messages with same phone number
                    $existingMessage = Message::where('phone_number', $from)
                        ->whereNotNull('user_id')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    if ($existingMessage) {
                        $userId = $existingMessage->user_id;
                        Log::info('User identified from existing message with same phone number', [
                            'user_id' => $userId,
                            'phone_number' => $from
                        ]);
                    }
                    
                    // Method 2: If still no user, try to find by phone number ID from metadata
                    if (!$userId && $phoneNumberId) {
                        $user = \App\Models\User::where('whatsapp_phone_number_id', $phoneNumberId)->first();
                        if ($user) {
                            $userId = $user->id;
                            Log::info('User identified from phone number ID fallback', [
                                'user_id' => $userId,
                                'phone_number_id' => $phoneNumberId
                            ]);
                        }
                    }
                    
                    // Method 3: If still no user, try to find any user with WhatsApp credentials
                    // (for single-user setups)
                    if (!$userId) {
                        $user = \App\Models\User::whereNotNull('whatsapp_phone_number_id')
                            ->whereNotNull('whatsapp_access_token')
                            ->first();
                        if ($user) {
                            $userId = $user->id;
                            Log::info('User identified from first available WhatsApp user', [
                                'user_id' => $userId
                            ]);
                        }
                    }
                }
                
                // Generate conversation ID (phone number + user_id for grouping)
                $conversationId = $userId ? "{$userId}_{$from}" : $from;
                $dbData['conversation_id'] = $conversationId;
                
                // Save received message to database (even if no user_id - we'll try to assign later)
                $dbData['user_id'] = $userId;
                
                try {
                    Message::create($dbData);
                    
                    Log::info('Received message saved successfully', [
                        'user_id' => $userId,
                        'phone_number' => $from,
                        'message_id' => $messageId,
                        'type' => $type,
                        'conversation_id' => $conversationId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to save received message', [
                        'error' => $e->getMessage(),
                        'message_data' => $dbData,
                        'trace' => $e->getTraceAsString(),
                        'message_index' => $index
                    ]);
                    $errors[] = "Failed to save message #{$index}: " . $e->getMessage();
                    // Continue processing other messages even if one fails
                    continue;
                }
                
                Log::info('Received message saved successfully', [
                    'user_id' => $userId,
                    'phone_number' => $from,
                    'message_id' => $messageId,
                    'type' => $type,
                    'conversation_id' => $conversationId
                ]);

                $processed[] = $messageData;
                } catch (\Exception $e) {
                    Log::error('Error processing individual message', [
                        'message_index' => $index,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'message' => $message
                    ]);
                    $errors[] = "Error processing message #{$index}: " . $e->getMessage();
                }
            }

            $result = [
                'success' => count($processed) > 0 || empty($errors),
                'messages' => $processed,
                'processed_count' => count($processed)
            ];
            
            if (!empty($errors)) {
                $result['errors'] = $errors;
                $result['error_count'] = count($errors);
            }
            
            Log::info('Webhook message processing completed', [
                'processed_count' => count($processed),
                'error_count' => count($errors),
                'user_id' => $userId
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing exception', [
                'message' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check message status from Meta API
     * Note: Meta doesn't provide a direct API to fetch message status,
     * but we can verify webhook connectivity and message delivery
     *
     * @param string $messageId Message ID to check
     * @return array
     */
    public function checkMessageStatus(string $messageId): array
    {
        try {
            // Find message in database
            $message = Message::where('message_id', $messageId)->first();
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Message not found in database'
                ];
            }

            // Return current status from database
            // Note: Meta WhatsApp API doesn't provide a direct endpoint to check message status
            // Status updates are received via webhooks only
            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => $message->status,
                'sent_at' => $message->sent_at?->toIso8601String(),
                'delivered_at' => $message->delivered_at?->toIso8601String(),
                'read_at' => $message->read_at?->toIso8601String(),
                'failed_at' => $message->failed_at?->toIso8601String(),
                'error_message' => $message->error_message,
                'note' => 'Message status is updated via webhooks. Ensure your webhook URL is properly configured in Meta Business Manager.'
            ];
        } catch (\Exception $e) {
            Log::error('Check message status exception', [
                'message' => $e->getMessage(),
                'message_id' => $messageId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sync pending messages status
     * This method can be called periodically to check for messages stuck in 'sent' status
     * Note: Meta doesn't provide a direct API, so this mainly logs warnings
     * For messages older than 1 hour, we can assume they're likely delivered if not failed
     *
     * @param int|null $userId User ID to sync messages for
     * @param int $hoursOld Hours old messages to check (default 24)
     * @param bool $autoUpdate Auto-update old messages to delivered status (default false)
     * @return array
     */
    public function syncPendingMessages(?int $userId = null, int $hoursOld = 24, bool $autoUpdate = false): array
    {
        try {
            $query = Message::where('direction', 'sent')
                ->where('status', 'sent')
                ->whereNull('delivered_at')
                ->whereNull('failed_at')
                ->where('created_at', '>=', now()->subHours($hoursOld));

            if ($userId) {
                $query->where('user_id', $userId);
            }

            $pendingMessages = $query->get();
            $count = $pendingMessages->count();
            $autoUpdated = 0;

            // Auto-update messages older than 1 hour to "delivered" if autoUpdate is enabled
            if ($autoUpdate) {
                $oldMessages = $pendingMessages->filter(function ($msg) {
                    return $msg->created_at->diffInHours(now()) >= 1;
                });

                foreach ($oldMessages as $msg) {
                    $msg->update([
                        'status' => 'delivered',
                        'delivered_at' => $msg->created_at->addMinutes(5) // Assume delivered 5 min after sent
                    ]);
                    $autoUpdated++;
                }

                Log::info('Auto-updated old messages to delivered status', [
                    'count' => $autoUpdated,
                    'user_id' => $userId
                ]);
            }

            if ($count > 0) {
                Log::warning('Found pending messages that may not have received status updates', [
                    'count' => $count,
                    'auto_updated' => $autoUpdated,
                    'user_id' => $userId,
                    'message_ids' => $pendingMessages->pluck('message_id')->toArray(),
                    'webhook_note' => 'Ensure webhook URL is configured: ' . url('/whatsapp/webhook')
                ]);
            }

            return [
                'success' => true,
                'pending_count' => $count,
                'auto_updated' => $autoUpdated,
                'messages' => $pendingMessages->map(function ($msg) {
                    return [
                        'id' => $msg->id,
                        'message_id' => $msg->message_id,
                        'phone_number' => $msg->phone_number,
                        'created_at' => $msg->created_at->toIso8601String(),
                        'status' => $msg->status,
                        'hours_old' => round($msg->created_at->diffInHours(now()), 2)
                    ];
                })->toArray(),
                'note' => 'These messages are still in "sent" status. Status updates should arrive via webhook. Ensure your webhook is properly configured.',
                'webhook_url' => url('/whatsapp/webhook')
            ];
        } catch (\Exception $e) {
            Log::error('Sync pending messages exception', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Manually mark a message as delivered
     * Useful when webhook isn't working but message was actually delivered
     *
     * @param string $messageId Message ID to mark as delivered
     * @return array
     */
    public function markAsDelivered(string $messageId): array
    {
        try {
            $message = Message::where('message_id', $messageId)->first();
            
            if (!$message) {
                return [
                    'success' => false,
                    'error' => 'Message not found'
                ];
            }

            if ($message->status === 'delivered' || $message->status === 'read') {
                return [
                    'success' => true,
                    'message' => 'Message already marked as delivered',
                    'data' => [
                        'message_id' => $messageId,
                        'status' => $message->status
                    ]
                ];
            }

            $updateData = [
                'status' => 'delivered',
                'delivered_at' => now()
            ];

            // If sent_at is not set, set it to created_at
            if (!$message->sent_at) {
                $updateData['sent_at'] = $message->created_at;
            }

            $message->update($updateData);

            Log::info('Message manually marked as delivered', [
                'message_id' => $messageId,
                'user_id' => $message->user_id,
                'phone_number' => $message->phone_number
            ]);

            return [
                'success' => true,
                'message' => 'Message marked as delivered',
                'data' => [
                    'message_id' => $messageId,
                    'status' => 'delivered',
                    'delivered_at' => $updateData['delivered_at']->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Mark as delivered exception', [
                'message' => $e->getMessage(),
                'message_id' => $messageId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

