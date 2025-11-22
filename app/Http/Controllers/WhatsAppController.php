<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
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

        $result = $this->whatsappService->verifyWebhook($mode, $token, $challenge);

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

        // Process the webhook data
        $result = $this->whatsappService->processWebhook($data);

        // Return 200 OK to acknowledge receipt
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Send a text message
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:4096'
        ]);

        $result = $this->whatsappService->sendTextMessage(
            $request->input('to'),
            $request->input('message')
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $result
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to send message',
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    /**
     * Send a media message
     */
    public function sendMedia(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'media_url' => 'required|url',
            'type' => 'required|string|in:image,video,audio,document',
            'caption' => 'nullable|string|max:1024'
        ]);

        $result = $this->whatsappService->sendMediaMessage(
            $request->input('to'),
            $request->input('media_url'),
            $request->input('type'),
            $request->input('caption')
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
            'to' => 'required|string',
            'template_name' => 'required|string',
            'language_code' => 'nullable|string|max:10',
            'parameters' => 'nullable|array'
        ]);

        $result = $this->whatsappService->sendTemplateMessage(
            $request->input('to'),
            $request->input('template_name'),
            $request->input('language_code', 'en_US'),
            $request->input('parameters', [])
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
}

