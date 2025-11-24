<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Contact::where('user_id', $user->id);
        
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('phone_number', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        
        $contacts = $query->orderBy('name')->orderBy('phone_number')->paginate(20);
        return view('whatsapp.contacts', compact('contacts', 'user'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string|regex:/^[0-9]+$/',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);

        $user = Auth::user();
        
        // Check if contact exists
        $contact = Contact::where('user_id', $user->id)
            ->where('phone_number', $request->phone_number)
            ->first();
        
        if ($contact) {
            $contact->update($request->only(['name', 'email', 'notes', 'tags']));
        } else {
            Contact::create([
                'user_id' => $user->id,
                'phone_number' => $request->phone_number,
                'name' => $request->name,
                'email' => $request->email,
                'notes' => $request->notes,
                'tags' => $request->tags ? explode(',', $request->tags) : [],
            ]);
        }

        return redirect()->route('whatsapp.contacts')->with('success', 'Contact saved successfully!');
    }

    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'tags' => 'nullable|string',
        ]);

        $contact->update([
            'name' => $request->name,
            'email' => $request->email,
            'notes' => $request->notes,
            'tags' => $request->tags ? explode(',', $request->tags) : [],
        ]);

        return redirect()->route('whatsapp.contacts')->with('success', 'Contact updated successfully!');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return redirect()->route('whatsapp.contacts')->with('success', 'Contact deleted successfully!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'contacts' => 'required|string',
        ]);

        $user = Auth::user();
        $lines = explode("\n", $request->contacts);
        $imported = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode(',', $line);
            $phone = trim($parts[0]);
            $name = isset($parts[1]) ? trim($parts[1]) : null;
            
            if (preg_match('/^[0-9]+$/', $phone)) {
                Contact::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'phone_number' => $phone,
                    ],
                    [
                        'name' => $name,
                    ]
                );
                $imported++;
            }
        }

        return redirect()->route('whatsapp.contacts')->with('success', "Successfully imported {$imported} contacts!");
    }
}
