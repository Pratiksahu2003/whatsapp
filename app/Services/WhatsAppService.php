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
            // Use user-specific credentials
            $this->apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0';
            $this->phoneNumberId = $user->whatsapp_phone_number_id;
            $this->accessToken = $user->whatsapp_access_token;
            $this->verifyToken = $user->whatsapp_verify_token;
        } else {
            // Fallback to config (for backward compatibility)
            $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
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

            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
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
                
                Log::info('WhatsApp message sent successfully', [
                    'to' => $normalizedTo,
                    'message_id' => $messageId,
                    'contact_status' => $contactStatus,
                    'full_response' => $responseData
                ]);

                // Generate conversation ID
                $conversationId = $userId ? "{$userId}_{$normalizedTo}" : $normalizedTo;
                
                // Save to database
                Message::create([
                    'user_id' => $userId,
                    'direction' => 'sent',
                    'message_id' => $messageId,
                    'phone_number' => $normalizedTo,
                    'conversation_id' => $conversationId,
                    'message_type' => 'text',
                    'content' => $message,
                    'status' => 'sent',
                    'sent_at' => now(),
                    'metadata' => $responseData,
                ]);

                Log::info('WhatsApp message saved to database', [
                    'message_id' => $messageId,
                    'to' => $normalizedTo,
                    'status' => 'sent'
                ]);

                // Check if message was actually accepted by WhatsApp
                if (!$messageId) {
                    Log::warning('WhatsApp API returned success but no message_id', [
                        'response' => $responseData
                    ]);
                    return [
                        'success' => false,
                        'error' => 'Message was sent but may not be delivered. Check if recipient has messaged you first (24-hour window) or use a template message for new contacts.',
                        'error_code' => 'NO_MESSAGE_ID',
                        'data' => $responseData
                    ];
                }

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'contact_status' => $contactStatus,
                    'data' => $responseData,
                    'warning' => empty($contacts) ? 'Message sent but contact verification status unknown. If recipient hasn\'t messaged you in last 24 hours, use a template message instead.' : null
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

            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

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

            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

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
        if ($mode === 'subscribe' && $token === $this->verifyToken) {
            return $challenge;
        }

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
            if (!isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                return ['success' => false, 'message' => 'No status updates in webhook data'];
            }

            $statuses = $data['entry'][0]['changes'][0]['value']['statuses'];
            $processed = [];

            foreach ($statuses as $status) {
                $messageId = $status['id'] ?? null;
                if (!$messageId) {
                    Log::warning('Status update received without message ID', ['status' => $status]);
                    continue;
                }

                $statusValue = $status['status'] ?? null; // sent, delivered, read, failed
                if (!$statusValue) {
                    Log::warning('Status update received without status value', ['message_id' => $messageId]);
                    continue;
                }

                $timestamp = $status['timestamp'] ?? time();
                $recipientId = $status['recipient_id'] ?? null;

                // Find message by message_id (try exact match first)
                $message = Message::where('message_id', $messageId)->first();

                // If not found, try to find by recipient phone number and recent timestamp
                if (!$message && $recipientId) {
                    $normalizedRecipient = preg_replace('/[^0-9]/', '', $recipientId);
                    $message = Message::where('phone_number', $normalizedRecipient)
                        ->where('direction', 'sent')
                        ->whereNull('delivered_at')
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($message) {
                        // Update message_id if it was missing
                        $message->update(['message_id' => $messageId]);
                        Log::info('Message ID updated from status webhook', [
                            'message_id' => $messageId,
                            'phone_number' => $normalizedRecipient
                        ]);
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
                    Log::warning('Status update received for unknown message', [
                        'message_id' => $messageId,
                        'status' => $statusValue,
                        'recipient_id' => $recipientId,
                        'full_status' => $status,
                        'available_messages' => Message::where('direction', 'sent')
                            ->whereNull('delivered_at')
                            ->orderBy('created_at', 'desc')
                            ->limit(5)
                            ->pluck('message_id', 'phone_number')
                            ->toArray()
                    ]);
                }
            }

            return ['success' => true, 'processed' => $processed];
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
            // Check if this is a status update
            if (isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                return $this->processStatusUpdate($data);
            }

            // Check if this is a message
            if (!isset($data['entry'][0]['changes'][0]['value']['messages'])) {
                return ['success' => false, 'message' => 'No messages or status updates in webhook data'];
            }

            $messages = $data['entry'][0]['changes'][0]['value']['messages'];
            $processed = [];

            foreach ($messages as $message) {
                $from = $message['from'];
                $messageId = $message['id'];
                $type = $message['type'];
                $timestamp = $message['timestamp'];

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

                // If no user_id provided, try to find user from existing messages with same phone number
                if (!$userId) {
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
                }
                
                // Generate conversation ID (phone number + user_id for grouping)
                $conversationId = $userId ? "{$userId}_{$from}" : $from;
                $dbData['conversation_id'] = $conversationId;
                
                // Save received message to database
                $dbData['user_id'] = $userId;
                Message::create($dbData);
                
                Log::info('Received message saved', [
                    'user_id' => $userId,
                    'phone_number' => $from,
                    'message_id' => $messageId,
                    'type' => $type
                ]);

                $processed[] = $messageData;

                Log::info('WhatsApp webhook message processed', $messageData);
            }

            return [
                'success' => true,
                'messages' => $processed
            ];
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
}

