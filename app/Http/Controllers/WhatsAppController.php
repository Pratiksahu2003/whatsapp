<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    protected function getWhatsAppService()
    {
        $user = Auth::user();
        if (!$user->hasWhatsAppCredentials()) {
            abort(403, 'Please configure your WhatsApp credentials in settings.');
        }
        return new WhatsAppService($user);
    }

    /**
     * Webhook verification endpoint
     * This is called by Meta when setting up the webhook
     * Note: This endpoint must work without authentication
     * Meta sends parameters as GET query parameters: ?hub_mode=subscribe&hub_verify_token=TOKEN&hub_challenge=CHALLENGE
     * 
     * According to Meta documentation:
     * - Must accept GET requests
     * - Must check hub_mode === 'subscribe'
     * - Must verify hub_verify_token matches stored token
     * - Must return hub_challenge as plain text if verification succeeds
     * - Must return 403 if verification fails
     */
    public function verify(Request $request)
    {
        // Meta sends these as GET query parameters (not POST body)
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Log all query parameters for debugging
        Log::info('Webhook verification attempt', [
            'method' => $request->method(),
            'mode' => $mode,
            'token_received' => $token ? 'yes' : 'no',
            'token_length' => $token ? strlen($token) : 0,
            'challenge_received' => $challenge ? 'yes' : 'no',
            'challenge_length' => $challenge ? strlen($challenge) : 0,
            'all_query_params' => $request->query(),
            'all_params' => $request->all(),
            'url' => $request->fullUrl(),
            'has_auth' => Auth::check(),
            'ip' => $request->ip()
        ]);

        // Meta requires mode to be 'subscribe' and token to match
        if ($mode !== 'subscribe') {
            Log::warning('Webhook verification failed: Invalid mode', [
                'mode' => $mode,
                'expected' => 'subscribe'
            ]);
            return response('Forbidden: Invalid mode', 403);
        }

        if (!$token || !$challenge) {
            Log::warning('Webhook verification failed: Missing token or challenge', [
                'has_token' => !empty($token),
                'has_challenge' => !empty($challenge),
                'token_value' => $token ? substr($token, 0, 10) . '...' : null
            ]);
            return response('Forbidden: Missing token or challenge', 403);
        }

        // Trim token to handle any whitespace issues
        $token = trim($token);
        
        // Get all users with verify tokens for matching
        $allUsers = \App\Models\User::whereNotNull('whatsapp_verify_token')->get();
        
        $user = null;
        $matchMethod = null;
        
        // Try multiple matching strategies
        foreach ($allUsers as $u) {
            $storedToken = trim($u->whatsapp_verify_token ?? '');
            
            // Strategy 1: Exact match (case-sensitive)
            if ($storedToken === $token) {
                $user = $u;
                $matchMethod = 'exact';
                break;
            }
            
            // Strategy 2: Case-insensitive match (fallback)
            if (strtolower($storedToken) === strtolower($token)) {
                $user = $u;
                $matchMethod = 'case_insensitive';
                break;
            }
        }
        
        if ($user) {
            $userVerifyToken = trim($user->whatsapp_verify_token ?? '');
            
            // Final verification - use exact match for Meta compliance
            if ($userVerifyToken === $token || strtolower($userVerifyToken) === strtolower($token)) {
                Log::info('Webhook verification successful', [
                    'user_id' => $user->id,
                    'challenge_returned' => $challenge,
                    'token_matched' => true,
                    'mode' => $mode,
                    'match_method' => $matchMethod,
                    'token_length' => strlen($token)
                ]);
                // Return the challenge as plain text (required by Meta)
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain')
                    ->header('X-Content-Type-Options', 'nosniff');
            } else {
                Log::warning('Webhook verification failed: Token mismatch after user found', [
                    'user_id' => $user->id,
                    'user_verify_token_length' => strlen($userVerifyToken),
                    'provided_token_length' => strlen($token),
                    'user_token_preview' => substr($userVerifyToken, 0, 15) . '...',
                    'provided_token_preview' => substr($token, 0, 15) . '...',
                    'tokens_equal_exact' => $userVerifyToken === $token,
                    'tokens_equal_case_insensitive' => strtolower($userVerifyToken) === strtolower($token)
                ]);
            }
        } else {
            // Log all available verify tokens (first 5 chars) for debugging
            $availableTokens = \App\Models\User::whereNotNull('whatsapp_verify_token')
                ->pluck('whatsapp_verify_token')
                ->map(function($t) {
                    return substr(trim($t), 0, 5) . '...';
                })
                ->toArray();
                
            Log::warning('Webhook verification failed: No user found with matching verify token', [
                'token_provided' => substr($token, 0, 10) . '...',
                'token_length' => strlen($token),
                'available_tokens_count' => count($availableTokens),
                'available_tokens_preview' => $availableTokens
            ]);
        }

        // If no user found or token doesn't match, return 403
        // According to Meta docs, we should return 403 if verification fails
        Log::error('Webhook verification failed: No matching verify token found', [
            'token_provided_length' => strlen($token),
            'token_provided_preview' => substr($token, 0, 10) . '...',
            'mode' => $mode,
            'challenge_received' => !empty($challenge)
        ]);
        
        return response('Forbidden: Verify token does not match', 403)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Webhook endpoint to receive messages from WhatsApp
     */
    public function webhook(Request $request)
    {
        $data = $request->all();

        // Log webhook receipt (but don't log full data in production to avoid sensitive info)
        Log::info('WhatsApp webhook received', [
            'has_entry' => isset($data['entry']),
            'entry_count' => isset($data['entry']) ? count($data['entry']) : 0,
            'has_changes' => isset($data['entry'][0]['changes']),
            'has_messages' => isset($data['entry'][0]['changes'][0]['value']['messages']),
            'has_statuses' => isset($data['entry'][0]['changes'][0]['value']['statuses']),
            'phone_number_id' => $data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null
        ]);

        // Identify user from webhook data using phone number ID
        $userId = null;
        if (isset($data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'])) {
            $phoneNumberId = $data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'];
            $user = \App\Models\User::where('whatsapp_phone_number_id', $phoneNumberId)->first();
            if ($user) {
                $userId = $user->id;
                Log::info('User identified from webhook', [
                    'user_id' => $userId,
                    'phone_number_id' => $phoneNumberId
                ]);
            } else {
                Log::warning('No user found for phone number ID', ['phone_number_id' => $phoneNumberId]);
            }
        } else {
            // Fallback: try to find user from existing messages if we have a message ID
            if (isset($data['entry'][0]['changes'][0]['value']['messages'][0]['id'])) {
                $messageId = $data['entry'][0]['changes'][0]['value']['messages'][0]['id'];
                $existingMessage = Message::where('message_id', $messageId)->first();
                if ($existingMessage && $existingMessage->user_id) {
                    $userId = $existingMessage->user_id;
                    Log::info('User identified from existing message', ['user_id' => $userId]);
                }
            }
            
            // If still no user, try to find by status update message ID
            if (!$userId && isset($data['entry'][0]['changes'][0]['value']['statuses'][0]['id'])) {
                $messageId = $data['entry'][0]['changes'][0]['value']['statuses'][0]['id'];
                $existingMessage = Message::where('message_id', $messageId)->first();
                if ($existingMessage && $existingMessage->user_id) {
                    $userId = $existingMessage->user_id;
                    Log::info('User identified from status update message', ['user_id' => $userId]);
                }
            }
        }

        // Use the identified user's service or fallback to default
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if ($user && $user->hasWhatsAppCredentials()) {
                $service = new WhatsAppService($user);
            } else {
                Log::warning('User found but missing WhatsApp credentials', ['user_id' => $userId]);
                $service = new WhatsAppService();
            }
        } else {
            // Try to find any user with WhatsApp credentials as fallback
            $fallbackUser = \App\Models\User::whereNotNull('whatsapp_phone_number_id')
                ->whereNotNull('whatsapp_access_token')
                ->first();
            if ($fallbackUser) {
                Log::info('Using fallback user for webhook processing', ['user_id' => $fallbackUser->id]);
                $service = new WhatsAppService($fallbackUser);
                $userId = $fallbackUser->id;
            } else {
                Log::warning('No user found for webhook, using default service');
                $service = new WhatsAppService();
            }
        }

        try {
            $result = $service->processWebhook($data, $userId);

            // Log webhook processing result with detailed information
            if (isset($result['success']) && $result['success']) {
                if (isset($result['processed'])) {
                    Log::info('Webhook status updates processed successfully', [
                        'processed_count' => count($result['processed']),
                        'user_id' => $userId,
                        'processed' => $result['processed']
                    ]);
                }
                if (isset($result['messages'])) {
                    Log::info('Webhook messages processed successfully', [
                        'messages_count' => count($result['messages']),
                        'user_id' => $userId,
                        'processed_count' => $result['processed_count'] ?? count($result['messages'])
                    ]);
                }
                
                // Log any errors that occurred during processing
                if (isset($result['errors']) && !empty($result['errors'])) {
                    Log::warning('Webhook processed with some errors', [
                        'error_count' => $result['error_count'] ?? count($result['errors']),
                        'errors' => $result['errors'],
                        'user_id' => $userId
                    ]);
                }
            } else {
                Log::warning('Webhook processing failed', [
                    'error' => $result['error'] ?? $result['message'] ?? 'Unknown error',
                    'user_id' => $userId,
                    'result' => $result
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in webhook processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
                'webhook_data_keys' => array_keys($data)
            ]);
        }

        // Always return 200 OK to acknowledge receipt (even if processing failed)
        // Meta will retry if we return error codes
        return response()->json([
            'status' => 'success',
            'processed' => isset($result['success']) && $result['success']
        ], 200);
    }

    /**
     * Test webhook endpoint - for debugging
     */
    public function testWebhook(Request $request)
    {
        $user = Auth::user();
        
        // Get all users with verify tokens for comparison
        $allUsers = \App\Models\User::whereNotNull('whatsapp_verify_token')
            ->select('id', 'email', 'whatsapp_verify_token')
            ->get()
            ->map(function($u) {
                return [
                    'id' => $u->id,
                    'email' => $u->email,
                    'token_preview' => substr($u->whatsapp_verify_token, 0, 10) . '...',
                    'token_length' => strlen($u->whatsapp_verify_token),
                    'token_trimmed_length' => strlen(trim($u->whatsapp_verify_token))
                ];
            });
        
        return response()->json([
            'webhook_url' => url('/whatsapp/webhook'),
            'verify_url' => url('/whatsapp/verify'),
            'user_id' => $user->id,
            'has_credentials' => $user->hasWhatsAppCredentials(),
            'phone_number_id' => $user->whatsapp_phone_number_id,
            'verify_token_set' => !empty($user->whatsapp_verify_token),
            'verify_token_preview' => $user->whatsapp_verify_token ? substr($user->whatsapp_verify_token, 0, 10) . '...' : null,
            'verify_token_length' => $user->whatsapp_verify_token ? strlen($user->whatsapp_verify_token) : 0,
            'all_users_with_tokens' => $allUsers,
            'instructions' => [
                '1' => 'Configure webhook URL in Meta Business Manager: ' . url('/whatsapp/webhook'),
                '2' => 'Use verify token from settings (must match exactly)',
                '3' => 'Subscribe to: messages, message_status',
                '4' => 'Check logs for webhook activity',
                '5' => 'Verify token must match exactly (no extra spaces)'
            ],
            'test_verify_url' => url('/whatsapp/verify') . '?hub_mode=subscribe&hub_verify_token=' . urlencode($user->whatsapp_verify_token ?? '') . '&hub_challenge=test123'
        ], 200);
    }

    /**
     * Diagnostic endpoint for webhook verification
     * Shows what tokens are configured
     */
    public function verifyDiagnostics(Request $request)
    {
        $token = $request->query('token');
        
        $users = \App\Models\User::whereNotNull('whatsapp_verify_token')->get();
        
        $diagnostics = [
            'verify_url' => url('/whatsapp/verify'),
            'test_token_provided' => $token ? 'yes' : 'no',
            'test_token_length' => $token ? strlen($token) : 0,
            'users_with_tokens' => $users->map(function($user) use ($token) {
                $userToken = trim($user->whatsapp_verify_token);
                return [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'token_length' => strlen($userToken),
                    'token_preview' => substr($userToken, 0, 10) . '...',
                    'matches_provided' => $token ? (trim($token) === $userToken) : false,
                    'has_credentials' => $user->hasWhatsAppCredentials()
                ];
            }),
            'instructions' => [
                '1' => 'Go to Meta Business Manager → WhatsApp → Configuration → Webhook',
                '2' => 'Set Callback URL: ' . url('/whatsapp/webhook'),
                '3' => 'Set Verify Token: (use one from above)',
                '4' => 'Click Verify and Save',
                '5' => 'Select subscription fields: messages, message_status'
            ]
        ];
        
        return response()->json($diagnostics, 200);
    }

    /**
     * Send a text message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string|regex:/^[0-9]+$/',
            'message' => 'required|string|max:4096'
        ], [
            'to.required' => 'Phone number is required.',
            'to.regex' => 'Phone number must contain only digits (without + or spaces).',
            'message.required' => 'Message text is required.',
            'message.max' => 'Message cannot exceed 4096 characters.',
        ]);

        $service = $this->getWhatsAppService();
        $result = $service->sendTextMessage(
            $request->input('to'),
            $request->input('message'),
            Auth::id()
        );

        if ($result['success']) {
            $response = [
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $result
            ];
            
            // Add warning if present
            if (isset($result['warning'])) {
                $response['warning'] = $result['warning'];
            }
            
            return response()->json($response, 200);
        }

        // Add helpful message about PENDING status
        $errorMessage = $result['error'] ?? 'Unknown error';
        if (strpos($errorMessage, 'not registered') !== false || $result['error_code'] == 133010) {
            $errorMessage .= ' Note: Your phone number status is PENDING. Messages may not be delivered until the phone number is fully approved. Please use the "Approve with Cert Token" option in Settings to complete the verification.';
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send message',
            'error' => $errorMessage,
            'error_code' => $result['error_code'] ?? null,
            'error_subcode' => $result['error_subcode'] ?? null,
            'help_text' => 'Common issues: 1) Phone number must be in international format (country code + number, e.g., 919873105808), 2) Recipient must have WhatsApp installed, 3) Your phone number status must be CONNECTED (not PENDING)'
        ], 400);
    }

    /**
     * Send a media message
     */
    public function sendMedia(Request $request)
    {
        $request->validate([
            'to' => 'required|string|regex:/^[0-9]+$/',
            'media_url' => 'required|url',
            'type' => 'required|string|in:image,video,audio,document',
            'caption' => 'nullable|string|max:1024'
        ], [
            'to.required' => 'Phone number is required.',
            'to.regex' => 'Phone number must contain only digits (without + or spaces).',
            'media_url.required' => 'Media URL is required.',
            'media_url.url' => 'Please provide a valid URL for the media file.',
            'type.required' => 'Media type is required.',
            'type.in' => 'Media type must be one of: image, video, audio, or document.',
            'caption.max' => 'Caption cannot exceed 1024 characters.',
        ]);

        $service = $this->getWhatsAppService();
        $result = $service->sendMediaMessage(
            $request->input('to'),
            $request->input('media_url'),
            $request->input('type'),
            $request->input('caption'),
            Auth::id()
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Media message sent successfully',
                'data' => $result
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send media message',
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    /**
     * Send a template message
     */
    public function sendTemplate(Request $request)
    {
        $request->validate([
            'to' => 'required|string|regex:/^[0-9]+$/',
            'template_name' => 'required|string|max:255',
            'language_code' => 'nullable|string|max:10|regex:/^[a-z]{2}_[A-Z]{2}$/',
            'parameters' => 'nullable|array'
        ], [
            'to.required' => 'Phone number is required.',
            'to.regex' => 'Phone number must contain only digits (without + or spaces).',
            'template_name.required' => 'Template name is required.',
            'template_name.max' => 'Template name cannot exceed 255 characters.',
            'language_code.regex' => 'Language code must be in format: en_US (lowercase_language_UPPERCASE_COUNTRY).',
            'language_code.max' => 'Language code cannot exceed 10 characters.',
            'parameters.array' => 'Parameters must be an array.',
        ]);

        $service = $this->getWhatsAppService();
        $result = $service->sendTemplateMessage(
            $request->input('to'),
            $request->input('template_name'),
            $request->input('language_code', 'en_US'),
            $request->input('parameters', []),
            Auth::id()
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Template message sent successfully',
                'data' => $result
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send template message',
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    /**
     * Display dashboard to track all messages
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $query = Message::where('user_id', $user->id)->latest();
        
        // Get date range filter
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Filter by direction
        if ($request->has('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by message type
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('message_type', $request->type);
        }

        // Search by phone number or content
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', '%' . $search . '%')
                  ->orWhere('content', 'like', '%' . $search . '%');
            });
        }

        $messages = $query->paginate(50);

        // Advanced Statistics
        $baseQuery = Message::where('user_id', $user->id);
        if ($dateFrom) {
            $baseQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseQuery->whereDate('created_at', '<=', $dateTo);
        }
        
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'sent' => (clone $baseQuery)->where('direction', 'sent')->count(),
            'received' => (clone $baseQuery)->where('direction', 'received')->count(),
            'delivered' => (clone $baseQuery)->where('status', 'delivered')->count(),
            'read' => (clone $baseQuery)->where('status', 'read')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
        ];
        
        // Get unique conversations count
        $stats['conversations'] = (clone $baseQuery)->distinct('conversation_id')->count('conversation_id');
        
        // Get messages by type
        $stats['by_type'] = [
            'text' => (clone $baseQuery)->where('message_type', 'text')->count(),
            'image' => (clone $baseQuery)->where('message_type', 'image')->count(),
            'video' => (clone $baseQuery)->where('message_type', 'video')->count(),
            'audio' => (clone $baseQuery)->where('message_type', 'audio')->count(),
            'document' => (clone $baseQuery)->where('message_type', 'document')->count(),
            'template' => (clone $baseQuery)->where('message_type', 'template')->count(),
        ];
        
        // Get daily message count for last 7 days (for chart)
        $dailyStats = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyStats[$date] = [
                'sent' => (clone $baseQuery)->where('direction', 'sent')->whereDate('created_at', $date)->count(),
                'received' => (clone $baseQuery)->where('direction', 'received')->whereDate('created_at', $date)->count(),
            ];
        }
        
        // Get top contacts
        $topContacts = (clone $baseQuery)
            ->selectRaw('phone_number, COUNT(*) as message_count')
            ->groupBy('phone_number')
            ->orderBy('message_count', 'desc')
            ->limit(10)
            ->get();

        return view('whatsapp.dashboard', compact('messages', 'stats', 'user', 'dailyStats', 'topContacts', 'dateFrom', 'dateTo'));
    }
    
    /**
     * View conversation with a specific phone number
     */
    public function conversation(Request $request, $phoneNumber)
    {
        $user = Auth::user();
        $conversationId = "{$user->id}_{$phoneNumber}";
        
        $messages = Message::where('user_id', $user->id)
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get();
        
        return view('whatsapp.conversation', compact('messages', 'phoneNumber', 'user'));
    }
    
    /**
     * Export messages
     */
    public function export(Request $request)
    {
        $user = Auth::user();
        $query = Message::where('user_id', $user->id);
        
        // Apply filters
        if ($request->has('direction') && $request->direction !== 'all') {
            $query->where('direction', $request->direction);
        }
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('message_type', $request->type);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $messages = $query->orderBy('created_at', 'desc')->get();
        
        $filename = 'whatsapp_messages_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];
        
        $callback = function() use ($messages) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'ID', 'Direction', 'Phone Number', 'Message Type', 'Content', 
                'Status', 'Sent At', 'Delivered At', 'Read At', 'Created At'
            ]);
            
            // Add data rows
            foreach ($messages as $message) {
                fputcsv($file, [
                    $message->id,
                    $message->direction,
                    $message->phone_number,
                    $message->message_type,
                    $message->content ?? '',
                    $message->status,
                    $message->sent_at ? $message->sent_at->format('Y-m-d H:i:s') : '',
                    $message->delivered_at ? $message->delivered_at->format('Y-m-d H:i:s') : '',
                    $message->read_at ? $message->read_at->format('Y-m-d H:i:s') : '',
                    $message->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    /**
     * List all conversations
     */
    public function conversations(Request $request)
    {
        $user = Auth::user();
        
        $conversations = Message::where('user_id', $user->id)
            ->selectRaw('conversation_id, phone_number, MAX(created_at) as last_message_at, COUNT(*) as message_count')
            ->groupBy('conversation_id', 'phone_number')
            ->orderBy('last_message_at', 'desc')
            ->paginate(20);
        
        return view('whatsapp.conversations', compact('conversations', 'user'));
    }

    /**
     * Display send message page
     */
    public function sendPage(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasWhatsAppCredentials()) {
            return redirect()->route('settings')->with('error', 'Please configure your WhatsApp credentials first.');
        }
        $prefillPhone = $request->query('to');
        return view('whatsapp.send', compact('user', 'prefillPhone'));
    }

    /**
     * Sync pending messages status
     */
    public function syncPending(Request $request)
    {
        $user = Auth::user();
        $service = $this->getWhatsAppService();
        
        $hoursOld = $request->input('hours', 24);
        $autoUpdate = $request->boolean('auto_update', false);
        $result = $service->syncPendingMessages($user->id, $hoursOld, $autoUpdate);
        
        if ($result['success']) {
            $message = "Found {$result['pending_count']} pending messages";
            if ($result['auto_updated'] > 0) {
                $message .= " and auto-updated {$result['auto_updated']} old messages to delivered";
            }
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ], 200);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to sync pending messages',
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    /**
     * Check message status
     */
    public function checkMessageStatus(Request $request, $messageId)
    {
        $user = Auth::user();
        $service = $this->getWhatsAppService();
        
        $result = $service->checkMessageStatus($messageId);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Manually mark message as delivered
     */
    public function markAsDelivered(Request $request, $messageId)
    {
        $user = Auth::user();
        
        // Verify message belongs to user
        $message = Message::where('message_id', $messageId)
            ->where('user_id', $user->id)
            ->first();
        
        if (!$message) {
            return response()->json([
                'success' => false,
                'error' => 'Message not found or access denied'
            ], 404);
        }
        
        $service = $this->getWhatsAppService();
        $result = $service->markAsDelivered($messageId);
        
        return response()->json($result, $result['success'] ? 200 : 400);
    }
}

