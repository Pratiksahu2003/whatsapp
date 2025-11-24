@extends('layouts.app')

@section('title', 'Conversations - WhatsApp')
@section('page-title', 'Conversations')

@section('content')
<div class="p-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">All Conversations ({{ $conversations->total() }})</h2>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($conversations as $conv)
                <a href="{{ route('whatsapp.conversation', $conv->phone_number) }}" class="block p-6 hover:bg-gray-50 transition">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                            {{ substr($conv->phone_number, -2) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $conv->phone_number }}
                                </p>
                                <span class="text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($conv->last_message_at)->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                {{ $conv->message_count }} {{ Str::plural('message', $conv->message_count) }}
                            </p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400"></i>
                    </div>
                </a>
            @empty
                <div class="p-12 text-center">
                    <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No conversations yet</p>
                    <a href="{{ route('whatsapp.send-page') }}" class="mt-4 inline-block text-green-600 hover:text-green-700">
                        Start a conversation
                    </a>
                </div>
            @endforelse
        </div>
        @if($conversations->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $conversations->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

