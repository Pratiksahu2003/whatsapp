<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkMessageController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $contacts = Contact::where('user_id', $user->id)->orderBy('name')->get();
        return view('whatsapp.bulk', compact('contacts', 'user'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'phone_numbers' => 'required|string',
            'message' => 'required|string',
            'message_type' => 'required|in:text,media,template',
        ]);

        $user = Auth::user();
        if (!$user->hasWhatsAppCredentials()) {
            return back()->with('error', 'Please configure your WhatsApp credentials first.');
        }

        $phoneNumbers = array_filter(array_map('trim', explode("\n", $request->phone_numbers)));
        $service = new WhatsAppService($user);
        $success = 0;
        $failed = 0;

        foreach ($phoneNumbers as $phone) {
            if (preg_match('/^[0-9]+$/', $phone)) {
                $result = $service->sendTextMessage($phone, $request->message, $user->id);
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                }
            }
        }

        return redirect()->route('whatsapp.bulk')->with('success', "Bulk send completed! Success: {$success}, Failed: {$failed}");
    }
}
