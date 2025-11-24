@extends('layouts.app')

@section('title', 'Contacts - WhatsApp')
@section('page-title', 'Contacts')

@section('content')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Contacts</h2>
            <p class="text-sm text-gray-500 mt-1">Manage your WhatsApp contacts</p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openImportModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
                <i class="fas fa-upload"></i>
                <span>Import</span>
            </button>
            <button onclick="openContactModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Contact</span>
            </button>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-6">
        <form method="GET" action="{{ route('whatsapp.contacts') }}" class="flex items-center space-x-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search contacts..." 
                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md transition">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>

    <!-- Contacts Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($contacts as $contact)
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                        {{ $contact->name ? strtoupper(substr($contact->name, 0, 1)) : substr($contact->phone_number, -2) }}
                    </div>
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('whatsapp.send-page') }}?to={{ $contact->phone_number }}" class="text-green-600 hover:text-green-800" title="Send Message">
                            <i class="fas fa-paper-plane"></i>
                        </a>
                        <button onclick="editContact({{ $contact->id }})" class="text-blue-600 hover:text-blue-800" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('whatsapp.contacts.destroy', $contact) }}" class="inline" onsubmit="return confirm('Delete this contact?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">{{ $contact->name ?: $contact->phone_number }}</h3>
                <p class="text-sm text-gray-600 mb-2">{{ $contact->phone_number }}</p>
                @if($contact->email)
                    <p class="text-xs text-gray-500 mb-2"><i class="fas fa-envelope mr-1"></i>{{ $contact->email }}</p>
                @endif
                <div class="flex items-center justify-between text-xs text-gray-500 mt-4 pt-4 border-t">
                    <span><i class="fas fa-comments mr-1"></i>{{ $contact->message_count }} messages</span>
                    @if($contact->last_contacted_at)
                        <span>{{ $contact->last_contacted_at->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 bg-white rounded-lg shadow-md">
                <i class="fas fa-address-book text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No contacts yet</p>
                <button onclick="openContactModal()" class="mt-4 inline-block bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition">
                    Add Your First Contact
                </button>
            </div>
        @endforelse
    </div>

    @if($contacts->hasPages())
        <div class="mt-6">
            {{ $contacts->links() }}
        </div>
    @endif
</div>

<!-- Contact Modal -->
<div id="contactModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Add Contact</h3>
            <button onclick="closeContactModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="contactForm" method="POST" action="{{ route('whatsapp.contacts.store') }}" class="p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                    <input type="text" name="phone_number" required pattern="[0-9]+" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" rows="3" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeContactModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    Save Contact
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Import Contacts</h3>
            <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('whatsapp.contacts.import') }}" class="p-6">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Contacts (CSV Format)</label>
                <textarea name="contacts" rows="10" required placeholder="1234567890, John Doe&#10;9876543210, Jane Smith" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 font-mono text-sm"></textarea>
                <p class="text-xs text-gray-500 mt-1">Format: phone_number, name (one per line)</p>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" onclick="closeImportModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    Import Contacts
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openContactModal() {
        document.getElementById('contactModal').classList.remove('hidden');
    }
    
    function closeContactModal() {
        document.getElementById('contactModal').classList.add('hidden');
        document.getElementById('contactForm').reset();
    }
    
    function openImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }
    
    function closeImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }
    
    function editContact(id) {
        // Load contact and edit - can be enhanced with AJAX
        openContactModal();
    }
</script>
@endpush
@endsection

