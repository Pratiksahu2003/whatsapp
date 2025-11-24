@extends('layouts.app')

@section('title', 'Dashboard - WhatsApp')
@section('page-title', 'Dashboard')

@section('content')
<div class="p-6">
            @if(!$user->hasWhatsAppCredentials())
                <div class="mb-6 rounded-md bg-yellow-50 border border-yellow-200 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-yellow-800">
                                WhatsApp credentials not configured
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Please configure your WhatsApp API credentials in <a href="{{ route('settings') }}" class="underline font-medium">Settings</a> to start sending messages.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Webhook Status Info -->
            <div class="mb-6 rounded-md bg-blue-50 border border-blue-200 p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-blue-800">
                            Message Delivery Status
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>Message status updates (delivered, read, failed) are received via webhooks from Meta. 
                            <strong>Ensure your webhook URL is properly configured</strong> in Meta Business Manager to receive real-time status updates.</p>
                            <p class="mt-1">Messages sent via API will appear in Meta's WhatsApp Business Manager. If messages don't show up, check:</p>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Webhook URL is configured: <code class="bg-blue-100 px-1 rounded">{{ url('/whatsapp/webhook') }}</code></li>
                                <li>Webhook is verified and active in Meta Business Manager</li>
                                <li>Phone number status is CONNECTED (not PENDING)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <a href="{{ route('whatsapp.send-page') }}" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition border-2 border-transparent hover:border-green-500">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-paper-plane text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Send Message</h3>
                            <p class="text-sm text-gray-500">Send a new message</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('whatsapp.bulk') }}" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition border-2 border-transparent hover:border-blue-500">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bullhorn text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Bulk Send</h3>
                            <p class="text-sm text-gray-500">Send to multiple contacts</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('whatsapp.scheduled') }}" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition border-2 border-transparent hover:border-purple-500">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Scheduled</h3>
                            <p class="text-sm text-gray-500">Schedule messages</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('whatsapp.contacts') }}" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition border-2 border-transparent hover:border-indigo-500">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-address-book text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900">Contacts</h3>
                            <p class="text-sm text-gray-500">Manage contacts</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Advanced Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-4 mb-8">
                <div class="stat-card rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Total</p>
                            <p class="text-3xl font-bold">{{ $stats['total'] }}</p>
                        </div>
                        <i class="fas fa-comments text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="stat-card info rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Sent</p>
                            <p class="text-3xl font-bold">{{ $stats['sent'] }}</p>
                        </div>
                        <i class="fas fa-paper-plane text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="stat-card rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Received</p>
                            <p class="text-3xl font-bold">{{ $stats['received'] }}</p>
                        </div>
                        <i class="fas fa-inbox text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-blue-500 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Delivered</p>
                            <p class="text-3xl font-bold">{{ $stats['delivered'] }}</p>
                        </div>
                        <i class="fas fa-check-double text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-indigo-500 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Read</p>
                            <p class="text-3xl font-bold">{{ $stats['read'] }}</p>
                        </div>
                        <i class="fas fa-eye text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="stat-card warning rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Failed</p>
                            <p class="text-3xl font-bold">{{ $stats['failed'] }}</p>
                        </div>
                        <i class="fas fa-exclamation-circle text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-yellow-500 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Pending</p>
                            <p class="text-3xl font-bold">{{ $stats['pending'] }}</p>
                        </div>
                        <i class="fas fa-clock text-4xl opacity-50"></i>
                    </div>
                </div>
                <div class="bg-purple-500 rounded-lg shadow-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm opacity-90">Conversations</p>
                            <p class="text-3xl font-bold">{{ $stats['conversations'] }}</p>
                        </div>
                        <i class="fas fa-comments text-4xl opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Charts and Analytics Row -->
            <div class="grid grid-cols-1 gap-6 mb-8">
                <!-- Message Type Distribution -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Message Types</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-font text-blue-500"></i>
                                <span class="text-sm text-gray-700">Text</span>
                            </div>
                            <span class="font-semibold">{{ $stats['by_type']['text'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-image text-green-500"></i>
                                <span class="text-sm text-gray-700">Image</span>
                            </div>
                            <span class="font-semibold">{{ $stats['by_type']['image'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-video text-red-500"></i>
                                <span class="text-sm text-gray-700">Video</span>
                            </div>
                            <span class="font-semibold">{{ $stats['by_type']['video'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-file text-purple-500"></i>
                                <span class="text-sm text-gray-700">Document</span>
                            </div>
                            <span class="font-semibold">{{ $stats['by_type']['document'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-envelope text-yellow-500"></i>
                                <span class="text-sm text-gray-700">Template</span>
                            </div>
                            <span class="font-semibold">{{ $stats['by_type']['template'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Contacts -->
            @if($topContacts->count() > 0)
            <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Top Contacts</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    @foreach($topContacts as $contact)
                    <a href="{{ route('whatsapp.conversation', $contact->phone_number) }}" class="p-4 border border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold">
                                {{ substr($contact->phone_number, -2) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $contact->phone_number }}</p>
                                <p class="text-xs text-gray-500">{{ $contact->message_count }} messages</p>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Advanced Filters -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Filters & Search</h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="syncPendingMessages()" class="text-sm bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2" title="Sync pending messages that haven't received delivery status">
                            <i class="fas fa-sync-alt"></i>
                            <span>Sync Status</span>
                        </button>
                        <a href="{{ route('whatsapp.export', request()->all()) }}" class="text-sm bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
                            <i class="fas fa-download"></i>
                            <span>Export</span>
                        </a>
                    </div>
                </div>
                <form method="GET" action="{{ route('whatsapp.dashboard') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                        <input type="date" name="date_from" value="{{ $dateFrom ?? request('date_from') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                        <input type="date" name="date_to" value="{{ $dateTo ?? request('date_to') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Direction</label>
                        <select name="direction" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" {{ request('direction') == 'all' || !request('direction') ? 'selected' : '' }}>All</option>
                            <option value="sent" {{ request('direction') == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="received" {{ request('direction') == 'received' ? 'selected' : '' }}>Received</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All</option>
                            <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                            <option value="delivered" {{ request('status') == 'delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>Read</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" {{ request('type') == 'all' || !request('type') ? 'selected' : '' }}>All</option>
                            <option value="text" {{ request('type') == 'text' ? 'selected' : '' }}>Text</option>
                            <option value="image" {{ request('type') == 'image' ? 'selected' : '' }}>Image</option>
                            <option value="video" {{ request('type') == 'video' ? 'selected' : '' }}>Video</option>
                            <option value="audio" {{ request('type') == 'audio' ? 'selected' : '' }}>Audio</option>
                            <option value="document" {{ request('type') == 'document' ? 'selected' : '' }}>Document</option>
                            <option value="template" {{ request('type') == 'template' ? 'selected' : '' }}>Template</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Phone or content..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="{{ route('whatsapp.dashboard') }}" class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400 transition">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Messages List -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Messages ({{ $messages->total() }})</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @forelse($messages as $message)
                        <div class="message-card p-6 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        @if($message->direction == 'sent')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <i class="fas fa-paper-plane mr-1"></i>Sent
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-inbox mr-1"></i>Received
                                            </span>
                                        @endif
                                        
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            @if($message->status == 'sent' || $message->status == 'delivered') bg-green-100 text-green-800
                                            @elseif($message->status == 'failed') bg-red-100 text-red-800
                                            @elseif($message->status == 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst($message->status) }}
                                            @if($message->status == 'sent' && $message->created_at->diffInHours(now()) > 1 && !$message->delivered_at)
                                                <i class="fas fa-exclamation-triangle ml-1 text-yellow-600" title="Message sent but no delivery confirmation received. Check webhook configuration."></i>
                                            @endif
                                        </span>
                                        
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            <i class="fas fa-{{ $message->message_type == 'text' ? 'font' : ($message->message_type == 'image' ? 'image' : ($message->message_type == 'video' ? 'video' : ($message->message_type == 'audio' ? 'music' : ($message->message_type == 'document' ? 'file' : 'envelope')))) }} mr-1"></i>
                                            {{ ucfirst($message->message_type) }}
                                        </span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-phone mr-1"></i>
                                            <a href="{{ route('whatsapp.conversation', $message->phone_number) }}" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $message->phone_number }}
                                            </a>
                                        </p>
                                    </div>
                                    
                                    @if($message->content)
                                        <p class="text-gray-900 mb-2">{{ Str::limit($message->content, 200) }}</p>
                                    @endif
                                    
                                    @if($message->template_name)
                                        <p class="text-sm text-gray-600 mb-2">
                                            <i class="fas fa-envelope mr-1"></i>
                                            Template: <span class="font-medium">{{ $message->template_name }}</span>
                                        </p>
                                    @endif
                                    
                                    @if($message->media_url)
                                        <p class="text-sm text-blue-600 mb-2">
                                            <i class="fas fa-link mr-1"></i>
                                            <a href="{{ $message->media_url }}" target="_blank" class="hover:underline">{{ Str::limit($message->media_url, 50) }}</a>
                                        </p>
                                    @endif
                                    
                                    @if($message->error_message)
                                        <p class="text-sm text-red-600 mb-2">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Error: {{ $message->error_message }}
                                        </p>
                                    @endif
                                    
                                    @if($message->status == 'sent' && $message->created_at->diffInHours(now()) > 1 && !$message->delivered_at && $message->direction == 'sent')
                                        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <strong>No delivery confirmation received.</strong> This message was sent {{ $message->created_at->diffForHumans() }} ago but hasn't received a delivery status update. 
                                            Ensure your webhook is properly configured in Meta Business Manager.
                                        </div>
                                    @endif
                                    
                                    <div class="flex items-center space-x-4 text-xs text-gray-500 mt-3">
                                        <span>
                                            <i class="far fa-clock mr-1"></i>
                                            {{ $message->created_at->format('M d, Y H:i:s') }}
                                        </span>
                                        @if($message->sent_at)
                                            <span class="text-green-600">
                                                <i class="fas fa-check mr-1"></i>
                                                Sent: {{ $message->sent_at->format('M d, H:i') }}
                                            </span>
                                        @endif
                                        @if($message->delivered_at)
                                            <span class="text-blue-600">
                                                <i class="fas fa-check-double mr-1"></i>
                                                Delivered: {{ $message->delivered_at->format('M d, H:i') }}
                                            </span>
                                        @endif
                                        @if($message->read_at)
                                            <span class="text-indigo-600">
                                                <i class="fas fa-eye mr-1"></i>
                                                Read: {{ $message->read_at->format('M d, H:i') }}
                                            </span>
                                        @endif
                                        @if($message->message_id)
                                            <span>
                                                <i class="fas fa-hashtag mr-1"></i>
                                                ID: {{ Str::limit($message->message_id, 20) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-12 text-center">
                            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg">No messages found</p>
                            <p class="text-gray-400 text-sm mt-2">Try adjusting your filters</p>
                        </div>
                    @endforelse
                </div>
                
                <!-- Pagination -->
                @if($messages->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $messages->links() }}
                    </div>
                @endif
            </div>
        </div>

<script>
function syncPendingMessages() {
    const button = event.target.closest('button');
    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
    
    fetch('{{ route("whatsapp.sync-pending") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            hours: 24,
            auto_update: false
        })
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        button.innerHTML = originalHtml;
        
        if (data.success) {
            alert(`Sync completed!\n\nFound ${data.data.pending_count} pending messages.\n\nNote: ${data.data.note}\n\nWebhook URL: ${data.data.webhook_url}`);
            // Optionally reload the page to see updated statuses
            if (data.data.pending_count > 0) {
                if (confirm('Would you like to reload the page to see updated message statuses?')) {
                    window.location.reload();
                }
            }
        } else {
            alert('Sync failed: ' + (data.error || data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = originalHtml;
        console.error('Error:', error);
        alert('Error syncing messages: ' + error.message);
    });
}
</script>

@endsection
