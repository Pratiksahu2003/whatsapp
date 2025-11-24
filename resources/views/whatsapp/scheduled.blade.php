@extends('layouts.app')

@section('title', 'Scheduled Messages - WhatsApp')
@section('page-title', 'Scheduled Messages')

@section('content')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Scheduled Messages</h2>
            <p class="text-sm text-gray-500 mt-1">Schedule messages to be sent later</p>
        </div>
        <button onclick="openScheduleModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
            <i class="fas fa-plus"></i>
            <span>Schedule Message</span>
        </button>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Scheduled Messages ({{ $scheduled->total() }})</h3>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($scheduled as $schedule)
                <div class="p-6 hover:bg-gray-50">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if($schedule->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($schedule->status == 'sent') bg-green-100 text-green-800
                                    @elseif($schedule->status == 'failed') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                                <span class="text-sm text-gray-600">
                                    <i class="fas fa-phone mr-1"></i>{{ $schedule->phone_number }}
                                </span>
                            </div>
                            @if($schedule->message)
                                <p class="text-gray-900 mb-2">{{ Str::limit($schedule->message, 150) }}</p>
                            @endif
                            <div class="flex items-center space-x-4 text-xs text-gray-500">
                                <span><i class="fas fa-clock mr-1"></i>Scheduled: {{ $schedule->scheduled_at->format('M d, Y H:i') }}</span>
                                <span><i class="fas fa-tag mr-1"></i>{{ ucfirst($schedule->message_type) }}</span>
                            </div>
                        </div>
                        @if($schedule->status == 'pending')
                            <form method="POST" action="{{ route('whatsapp.scheduled.destroy', $schedule) }}" class="inline" onsubmit="return confirm('Cancel this scheduled message?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <i class="fas fa-clock text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No scheduled messages</p>
                    <button onclick="openScheduleModal()" class="mt-4 inline-block bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition">
                        Schedule Your First Message
                    </button>
                </div>
            @endforelse
        </div>
        @if($scheduled->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $scheduled->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Schedule Message</h3>
            <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('whatsapp.scheduled.store') }}" class="p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                    <input type="text" name="phone_number" required pattern="[0-9]+" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Type *</label>
                    <select name="message_type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <option value="text">Text</option>
                        <option value="media">Media</option>
                        <option value="template">Template</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                    <textarea name="message" rows="4" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Date & Time *</label>
                    <input type="datetime-local" name="scheduled_at" required min="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div>
            <div class="mt-6 flex items-center justify-end space-x-3">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    Schedule Message
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('hidden');
    }
    
    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
    }
</script>
@endpush
@endsection

