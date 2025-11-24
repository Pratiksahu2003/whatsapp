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
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // For webhook verification, try to find user by verify token
        $user = \App\Models\User::where('whatsapp_verify_token', $token)->first();
        $service = $user ? new WhatsAppService($user) : $this->getWhatsAppService();
        
        $result = $service->verifyWebhook($mode, $token, $challenge);

        if ($result !== false) {
            return response($result, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook endpoint to receive messages from WhatsApp
     */
    public function webhook(Request $request)
    {
        $data = $request->all();

        Log::info('WhatsApp webhook received', ['data' => $data]);

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
            $service = new WhatsAppService($user);
        } else {
            $service = new WhatsAppService();
        }

        $result = $service->processWebhook($data, $userId);

        // Log webhook processing result
        if (isset($result['success']) && $result['success']) {
            if (isset($result['processed'])) {
                Log::info('Webhook status updates processed', [
                    'processed_count' => count($result['processed']),
                    'user_id' => $userId
                ]);
            }
            if (isset($result['messages'])) {
                Log::info('Webhook messages processed', [
                    'messages_count' => count($result['messages']),
                    'user_id' => $userId
                ]);
            }
        } else {
            Log::warning('Webhook processing returned failure', [
                'result' => $result,
                'user_id' => $userId
            ]);
        }

        // Return 200 OK to acknowledge receipt
        return response()->json(['status' => 'success'], 200);
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
}

