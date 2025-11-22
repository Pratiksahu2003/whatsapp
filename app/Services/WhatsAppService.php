<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $apiUrl;
    protected $phoneNumberId;
    protected $accessToken;
    protected $verifyToken;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->accessToken = config('services.whatsapp.access_token');
        $this->verifyToken = config('services.whatsapp.verify_token');
    }

    /**
     * Send a text message via WhatsApp
     *
     * @param string $to Phone number in international format (e.g., 1234567890)
     * @param string $message Message text
     * @return array
     */
    public function sendTextMessage(string $to, string $message): array
    {
        try {
            $response = Http::withToken($this->accessToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'to' => $to,
                    'message_id' => $response->json('messages.0.id')
                ]);

                return [
                    'success' => true,
                    'message_id' => $response->json('messages.0.id'),
                    'data' => $response->json()
                ];
            }

            Log::error('WhatsApp API error', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp service exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a media message (image, document, etc.)
     *
     * @param string $to Phone number in international format
     * @param string $mediaUrl URL of the media file
     * @param string $type Media type (image, document, audio, video)
     * @param string|null $caption Optional caption for the media
     * @return array
     */
    public function sendMediaMessage(string $to, string $mediaUrl, string $type = 'image', ?string $caption = null): array
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
                return [
                    'success' => true,
                    'message_id' => $response->json('messages.0.id'),
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp media message exception', [
                'message' => $e->getMessage()
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
     * @return array
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US', array $parameters = []): array
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
                return [
                    'success' => true,
                    'message_id' => $response->json('messages.0.id'),
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Unknown error'),
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp template message exception', [
                'message' => $e->getMessage()
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
     * Process incoming webhook
     *
     * @param array $data
     * @return array
     */
    public function processWebhook(array $data): array
    {
        try {
            if (!isset($data['entry'][0]['changes'][0]['value']['messages'])) {
                return ['success' => false, 'message' => 'No messages in webhook data'];
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

                // Handle different message types
                switch ($type) {
                    case 'text':
                        $messageData['text'] = $message['text']['body'];
                        break;
                    case 'image':
                    case 'video':
                    case 'audio':
                    case 'document':
                        $messageData['media_id'] = $message[$type]['id'];
                        $messageData['mime_type'] = $message[$type]['mime_type'] ?? null;
                        if (isset($message[$type]['caption'])) {
                            $messageData['caption'] = $message[$type]['caption'];
                        }
                        break;
                    case 'location':
                        $messageData['latitude'] = $message['location']['latitude'];
                        $messageData['longitude'] = $message['location']['longitude'];
                        break;
                }

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
}

