<?php

namespace App\Http\Controllers;

use App\Models\ScheduledMessage;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduledMessageController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $scheduled = ScheduledMessage::where('user_id', $user->id)
            ->orderBy('scheduled_at', 'asc')
            ->paginate(20);
        
        return view('whatsapp.scheduled', compact('scheduled', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]+$/',
            'message' => 'nullable|string',
            'message_type' => 'required|in:text,media,template',
            'scheduled_at' => 'required|date|after:now',
            'media_url' => 'nullable|url',
            'template_name' => 'nullable|string',
        ]);

        $user = Auth::user();
        ScheduledMessage::create([
            'user_id' => $user->id,
            'phone_number' => $request->phone_number,
            'message' => $request->message,
            'message_type' => $request->message_type,
            'media_url' => $request->media_url,
            'template_name' => $request->template_name,
            'scheduled_at' => $request->scheduled_at,
            'status' => 'pending',
        ]);

        return redirect()->route('whatsapp.scheduled')->with('success', 'Message scheduled successfully!');
    }

    public function destroy(ScheduledMessage $scheduledMessage)
    {
        if ($scheduledMessage->status === 'pending') {
            $scheduledMessage->update(['status' => 'cancelled']);
            return redirect()->route('whatsapp.scheduled')->with('success', 'Scheduled message cancelled!');
        }
        
        return redirect()->route('whatsapp.scheduled')->with('error', 'Cannot cancel message that is already sent or failed.');
    }
}
