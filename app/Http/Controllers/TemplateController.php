<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $templates = MessageTemplate::where('user_id', $user->id)->latest()->get();
        return view('whatsapp.templates', compact('templates', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:text,media,template',
            'media_url' => 'nullable|url',
            'media_type' => 'nullable|string',
        ]);

        $user = Auth::user();
        MessageTemplate::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'content' => $request->content,
            'type' => $request->type,
            'media_url' => $request->media_url,
            'media_type' => $request->media_type,
            'variables' => $request->variables ?? [],
        ]);

        return redirect()->route('whatsapp.templates')->with('success', 'Template created successfully!');
    }

    public function update(Request $request, MessageTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|in:text,media,template',
        ]);

        $template->update($request->only(['name', 'content', 'type', 'media_url', 'media_type', 'is_active']));

        return redirect()->route('whatsapp.templates')->with('success', 'Template updated successfully!');
    }

    public function destroy(MessageTemplate $template)
    {
        $template->delete();
        return redirect()->route('whatsapp.templates')->with('success', 'Template deleted successfully!');
    }
}
