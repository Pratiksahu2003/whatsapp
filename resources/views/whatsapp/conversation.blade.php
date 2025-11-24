@extends('layouts.app')

@section('title', 'Conversation - ' . $phoneNumber)
@section('page-title', 'Conversation')

@section('content')
<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                {{ substr($phoneNumber, -2) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $phoneNumber }}</h2>
                <p class="text-sm text-gray-500">{{ $messages->count() }} messages</p>
            </div>
        </div>
        <a href="{{ route('whatsapp.send-page') }}?to={{ $phoneNumber }}" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md transition flex items-center space-x-2">
            <i class="fas fa-paper-plane"></i>
            <span>Send Message</span>
        </a>
    </div>

    <div class="max-w-4xl mx-auto">
            <!-- Messages -->
            <div id="messagesContainer" class="space-y-4 pb-32">
                @forelse($messages as $message)
                    <div class="flex {{ $message->direction == 'sent' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-xs lg:max-w-md">
                            <div class="rounded-lg p-4 {{ $message->direction == 'sent' ? 'bg-green-500 text-white' : 'bg-white shadow-md' }}">
                                @if($message->direction == 'sent')
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs opacity-75">You</span>
                                        @if($message->status == 'read')
                                            <i class="fas fa-check-double text-blue-200" title="Read"></i>
                                        @elseif($message->status == 'delivered')
                                            <i class="fas fa-check-double" title="Delivered"></i>
                                        @elseif($message->status == 'sent')
                                            <i class="fas fa-check" title="Sent"></i>
                                        @elseif($message->status == 'failed')
                                            <i class="fas fa-exclamation-circle text-red-200" title="Failed"></i>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-xs {{ $message->direction == 'sent' ? 'text-white opacity-75' : 'text-gray-500' }}">{{ $phoneNumber }}</span>
                                    </div>
                                @endif
                                
                                @if($message->content)
                                    <p class="text-sm {{ $message->direction == 'sent' ? 'text-white' : 'text-gray-900' }}">{{ $message->content }}</p>
                                @endif
                                
                                @if($message->media_url)
                                    <div class="mt-2">
                                        <a href="{{ $message->media_url }}" target="_blank" class="text-sm underline {{ $message->direction == 'sent' ? 'text-blue-200' : 'text-blue-600' }}">
                                            <i class="fas fa-link mr-1"></i>View Media
                                        </a>
                                    </div>
                                @endif
                                
                                @if($message->template_name)
                                    <div class="mt-2 text-xs {{ $message->direction == 'sent' ? 'text-white opacity-75' : 'text-gray-500' }}">
                                        <i class="fas fa-envelope mr-1"></i>Template: {{ $message->template_name }}
                                    </div>
                                @endif
                                
                                <div class="mt-2 text-xs {{ $message->direction == 'sent' ? 'text-white opacity-75' : 'text-gray-500' }}">
                                    {{ $message->created_at->format('H:i') }}
                                    @if($message->read_at && $message->direction == 'sent')
                                        <span class="ml-2">Read {{ $message->read_at->diffForHumans() }}</span>
                                    @elseif($message->delivered_at && $message->direction == 'sent')
                                        <span class="ml-2">Delivered {{ $message->delivered_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-12">
                        <i class="fas fa-comments text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">No messages in this conversation</p>
                        <a href="{{ route('whatsapp.send-page') }}?to={{ $phoneNumber }}" class="mt-4 inline-block bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-md transition">
                            Send First Message
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Quick Send Form - Fixed at bottom -->
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-lg z-10">
            <div class="max-w-4xl mx-auto px-6 py-4">
                <div class="mb-2 rounded-md bg-blue-50 border border-blue-200 p-2">
                    <p class="text-xs text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        <strong>Note:</strong> Free-form messages work within 24 hours of recipient's last message. 
                        For new contacts, use <a href="{{ route('whatsapp.send-page') }}?to={{ $phoneNumber }}" class="underline font-semibold">Template Messages</a>.
                    </p>
                </div>
                <form id="quickSendForm" class="flex items-center space-x-3">
                    <input type="hidden" name="to" value="{{ $phoneNumber }}">
                    <input type="text" id="messageInput" name="message" placeholder="Type a message..." required autofocus
                        class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 px-4 py-2">
                    <button type="submit" id="sendButton" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition flex items-center space-x-2">
                        <i class="fas fa-paper-plane"></i>
                        <span class="hidden sm:inline">Send</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        const form = document.getElementById('quickSendForm');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');
        const messagesContainer = document.getElementById('messagesContainer');
        
        // Auto-scroll to bottom on load
        function scrollToBottom() {
            window.scrollTo({
                top: document.body.scrollHeight,
                behavior: 'smooth'
            });
        }
        
        window.addEventListener('load', function() {
            setTimeout(scrollToBottom, 100);
        });
        
        // Handle form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const message = formData.get('message').trim();
            
            if (!message) {
                return;
            }
            
            const originalButtonHtml = sendButton.innerHTML;
            sendButton.disabled = true;
            sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="hidden sm:inline">Sending...</span>';
            messageInput.disabled = true;
            
            try {
                const response = await axios.post('{{ route("whatsapp.send") }}', {
                    to: formData.get('to'),
                    message: message
                });
                
                if (response.data.success) {
                    // Show success feedback
                    messageInput.value = '';
                    messageInput.focus();
                    
                    // Show warning if present (24-hour window issue)
                    if (response.data.warning) {
                        setTimeout(() => {
                            alert('⚠️ Message Sent\n\n' + response.data.warning + '\n\n💡 Tip: For new contacts who haven\'t messaged you, use Template Messages instead.');
                        }, 100);
                    }
                    
                    // Reload page to show new message
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    const errorMsg = response.data.error || 'Unknown error';
                    let helpText = response.data.help_text || '';
                    
                    // Show error with better formatting
                    if (response.data.error_code === 133010 || errorMsg.includes('not registered')) {
                        let fullMessage = '⚠️ WhatsApp Account Not Registered\n\n' + errorMsg;
                        if (errorMsg.includes('PENDING')) {
                            fullMessage += '\n\n🔧 Solution: Go to Settings and use "Approve with Cert Token" to complete phone number verification.';
                        } else {
                            fullMessage += '\n\nPlease check your Settings and ensure your phone number is properly registered in Meta Business Manager.';
                        }
                        alert(fullMessage);
                    } else {
                        let fullMessage = '❌ Failed to send message\n\n' + errorMsg;
                        if (helpText) {
                            fullMessage += '\n\n💡 Tips:\n' + helpText;
                        }
                        alert(fullMessage);
                    }
                    messageInput.focus();
                }
            } catch (error) {
                const errorMsg = error.response?.data?.error || error.response?.data?.message || error.message;
                alert('❌ Error: ' + errorMsg);
                messageInput.focus();
            } finally {
                sendButton.disabled = false;
                sendButton.innerHTML = originalButtonHtml;
                messageInput.disabled = false;
            }
        });
        
        // Allow sending with Enter key (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.dispatchEvent(new Event('submit'));
            }
        });
    </script>
@endpush
@endsection

