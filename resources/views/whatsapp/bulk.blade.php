@extends('layouts.app')

@section('title', 'Bulk Messages - WhatsApp')
@section('page-title', 'Bulk Messages')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Bulk Message Sender</h2>
        <p class="text-sm text-gray-500 mt-1">Send the same message to multiple contacts at once</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Send Bulk Message</h3>
            <form method="POST" action="{{ route('whatsapp.bulk.send') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Numbers *</label>
                        <textarea name="phone_numbers" rows="8" required placeholder="Enter phone numbers, one per line:&#10;1234567890&#10;9876543210&#10;..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Enter phone numbers in international format (one per line, without +)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                        <textarea name="message" rows="6" required placeholder="Enter your message..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message Type *</label>
                        <select name="message_type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="text">Text Message</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-md transition flex items-center justify-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Bulk Message</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Contacts List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Select Contacts</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($contacts as $contact)
                    <label class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-md cursor-pointer">
                        <input type="checkbox" name="selected_contacts[]" value="{{ $contact->phone_number }}" class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $contact->name ?: $contact->phone_number }}</p>
                            <p class="text-xs text-gray-500">{{ $contact->phone_number }}</p>
                        </div>
                    </label>
                @empty
                    <p class="text-sm text-gray-500 text-center py-8">No contacts available. <a href="{{ route('whatsapp.contacts') }}" class="text-green-600 hover:underline">Add contacts</a></p>
                @endforelse
            </div>
            @if($contacts->count() > 0)
                <button onclick="selectAllContacts()" class="mt-4 w-full text-sm text-green-600 hover:text-green-700">
                    Select All
                </button>
            @endif
        </div>
    </div>

    <!-- Info Box -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400 text-xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-900">Bulk Messaging Tips</h3>
                <div class="mt-2 text-sm text-blue-700 space-y-1">
                    <p>• Messages are sent one by one to avoid rate limiting</p>
                    <p>• Large batches may take several minutes to complete</p>
                    <p>• Check your message status in the Dashboard</p>
                    <p>• Make sure all phone numbers are in international format (without +)</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function selectAllContacts() {
        const checkboxes = document.querySelectorAll('input[name="selected_contacts[]"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        checkboxes.forEach(cb => cb.checked = !allChecked);
    }
</script>
@endpush
@endsection

