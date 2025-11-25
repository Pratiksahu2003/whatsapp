@extends('layouts.app')

@section('title', 'Send Message - WhatsApp')
@section('page-title', 'Send Message')

@section('content')
<div class="p-6">
            <!-- Message Type Selection -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Select Message Type</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button onclick="selectMessageType('text')" id="btn-text" class="message-type-btn p-6 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition text-left">
                        <div class="flex items-center space-x-3 mb-2">
                            <i class="fas fa-font text-2xl text-green-500"></i>
                            <h3 class="font-semibold text-gray-900">Text Message</h3>
                        </div>
                        <p class="text-sm text-gray-600">Send a simple text message</p>
                    </button>
                    <button onclick="selectMessageType('media')" id="btn-media" class="message-type-btn p-6 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition text-left">
                        <div class="flex items-center space-x-3 mb-2">
                            <i class="fas fa-image text-2xl text-green-500"></i>
                            <h3 class="font-semibold text-gray-900">Media Message</h3>
                        </div>
                        <p class="text-sm text-gray-600">Send images, videos, audio, or documents</p>
                    </button>
                    <button onclick="selectMessageType('template')" id="btn-template" class="message-type-btn p-6 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 transition text-left">
                        <div class="flex items-center space-x-3 mb-2">
                            <i class="fas fa-envelope text-2xl text-green-500"></i>
                            <h3 class="font-semibold text-gray-900">Template Message</h3>
                        </div>
                        <p class="text-sm text-gray-600">Send pre-approved template messages</p>
                    </button>
                </div>
            </div>

            <!-- Message Forms -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <!-- Text Message Form -->
                <form id="textMessageForm" class="message-form hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center space-x-2">
                        <i class="fas fa-font text-green-500"></i>
                        <span>Text Message</span>
                    </h2>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="to" required placeholder="1234567890" value="{{ $prefillPhone ?? '' }}" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> Enter phone number in international format (without +)
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Message <span class="text-red-500">*</span>
                        </label>
                        <textarea name="message" rows="6" required placeholder="Enter your message here..." 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Maximum 4096 characters</p>
                        <div class="mt-2 rounded-md bg-yellow-50 border border-yellow-200 p-3">
                            <p class="text-xs text-yellow-800">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Important:</strong> Free-form messages only work if the recipient has messaged you in the last 24 hours. 
                                For new contacts or contacts outside this window, use <strong>Template Messages</strong> instead.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3">
                        <a href="{{ route('whatsapp.dashboard') }}" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition flex items-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Message</span>
                        </button>
                    </div>
                </form>

                <!-- Media Message Form -->
                <form id="mediaMessageForm" class="message-form hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center space-x-2">
                        <i class="fas fa-image text-green-500"></i>
                        <span>Media Message</span>
                    </h2>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="to" required placeholder="1234567890" value="{{ $prefillPhone ?? '' }}" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> Enter phone number in international format (without +)
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Media Type <span class="text-red-500">*</span>
                        </label>
                        <select name="type" required class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="">Select media type</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                            <option value="audio">Audio</option>
                            <option value="document">Document</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Media URL <span class="text-red-500">*</span>
                        </label>
                        <input type="url" name="media_url" required placeholder="https://example.com/image.jpg" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> The media file must be publicly accessible via HTTPS
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Caption (Optional)
                        </label>
                        <textarea name="caption" rows="3" placeholder="Optional caption for your media..." 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                        <p class="text-xs text-gray-500 mt-1">Maximum 1024 characters</p>
                    </div>


                    <div class="flex items-center justify-end space-x-3">
                        <a href="{{ route('whatsapp.dashboard') }}" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition flex items-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Media</span>
                        </button>
                    </div>
                </form>

                <!-- Template Message Form -->
                <form id="templateMessageForm" class="message-form hidden">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6 flex items-center space-x-2">
                        <i class="fas fa-envelope text-green-500"></i>
                        <span>Template Message</span>
                    </h2>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="to" required placeholder="1234567890" value="{{ $prefillPhone ?? '' }}" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> Enter phone number in international format (without +)
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Template Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="template_name" required placeholder="hello_world" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> Use the exact template name as approved in Meta Business Manager
                        </p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Language Code
                        </label>
                        <input type="text" name="language_code" value="en_US" placeholder="en_US" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">Default: en_US</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Template Parameters
                        </label>
                        <input type="text" name="parameters" placeholder="John, Doe, 123" 
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle"></i> Separate multiple parameters with commas (e.g., "John, Doe")
                        </p>
                    </div>


                    <div class="flex items-center justify-end space-x-3">
                        <a href="{{ route('whatsapp.dashboard') }}" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition flex items-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Template</span>
                        </button>
                    </div>
                </form>

                <!-- Default View -->
                <div id="defaultView" class="text-center py-12">
                    <i class="fas fa-paper-plane text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">Select a message type above to get started</p>
                </div>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function selectMessageType(type) {
            // Hide all forms and default view
            document.querySelectorAll('.message-form').forEach(form => {
                form.classList.add('hidden');
            });
            document.getElementById('defaultView').classList.add('hidden');

            // Remove active state from all buttons
            document.querySelectorAll('.message-type-btn').forEach(btn => {
                btn.classList.remove('border-green-500', 'bg-green-50');
                btn.classList.add('border-gray-200');
            });

            // Show selected form
            if (type === 'text') {
                document.getElementById('textMessageForm').classList.remove('hidden');
                document.getElementById('btn-text').classList.add('border-green-500', 'bg-green-50');
            } else if (type === 'media') {
                document.getElementById('mediaMessageForm').classList.remove('hidden');
                document.getElementById('btn-media').classList.add('border-green-500', 'bg-green-50');
            } else if (type === 'template') {
                document.getElementById('templateMessageForm').classList.remove('hidden');
                document.getElementById('btn-template').classList.add('border-green-500', 'bg-green-50');
            }
        }

        // Handle Text Message Form
        document.getElementById('textMessageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            await submitForm(this, '{{ route("whatsapp.send") }}');
        });

        // Handle Media Message Form
        document.getElementById('mediaMessageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const payload = {
                to: formData.get('to'),
                media_url: formData.get('media_url'),
                type: formData.get('type'),
                caption: formData.get('caption') || null
            };
            await submitForm(this, '{{ route("whatsapp.send-media") }}', null, payload);
        });

        // Handle Template Message Form
        document.getElementById('templateMessageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const params = formData.get('parameters');
            const payload = {
                to: formData.get('to'),
                template_name: formData.get('template_name'),
                language_code: formData.get('language_code') || 'en_US',
                parameters: params ? params.split(',').map(p => p.trim()) : []
            };
            await submitForm(this, '{{ route("whatsapp.send-template") }}', null, payload);
        });

        // Clear validation errors
        function clearValidationErrors(form) {
            // Remove existing error messages
            form.querySelectorAll('.error-message').forEach(el => el.remove());
            // Remove error styling from inputs
            form.querySelectorAll('.border-red-500').forEach(el => {
                el.classList.remove('border-red-500');
                el.classList.add('border-gray-300');
            });
        }

        // Display validation errors
        function displayValidationErrors(form, errors) {
            clearValidationErrors(form);
            
            Object.keys(errors).forEach(field => {
                const input = form.querySelector(`[name="${field}"]`);
                if (input) {
                    // Add error styling
                    input.classList.remove('border-gray-300');
                    input.classList.add('border-red-500');
                    
                    // Create error message element
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message mt-1 text-sm text-red-600';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>' + errors[field][0];
                    
                    // Insert error message after input
                    input.parentNode.insertBefore(errorDiv, input.nextSibling);
                }
            });
        }

        async function submitForm(form, url, alertId = null, customPayload = null) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Clear previous errors
            clearValidationErrors(form);
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            let payload;
            if (customPayload) {
                payload = customPayload;
            } else {
                const formData = new FormData(form);
                payload = Object.fromEntries(formData);
            }

            try {
                const response = await axios.post(url, payload);
                const data = response.data;
                
                if (data.success) {
                    // Build message with warnings/notes if present
                    let messageText = data.message || 'Message sent successfully!';
                    let hasWarning = data.warning || data.note;
                    
                    if (hasWarning) {
                        messageText += '\n\n⚠️ IMPORTANT:\n';
                        if (data.warning) {
                            messageText += data.warning + '\n';
                        }
                        if (data.note) {
                            messageText += data.note + '\n';
                        }
                        messageText += '\n💡 Tip: For new contacts or contacts outside the 24-hour window, use Template Messages instead.';
                    }
                    
                    // Show success with SweetAlert (warning icon if there's a warning)
                    Swal.fire({
                        icon: hasWarning ? 'warning' : 'success',
                        title: hasWarning ? 'Message Sent (Check Warning)' : 'Success!',
                        text: messageText,
                        confirmButtonColor: hasWarning ? '#f59e0b' : '#22c55e',
                        confirmButtonText: 'OK',
                        timer: hasWarning ? 8000 : 2000,
                        timerProgressBar: true,
                        allowOutsideClick: true
                    }).then(() => {
                        form.reset();
                        clearValidationErrors(form);
                        window.location.href = '{{ route("whatsapp.dashboard") }}';
                    });
                } else {
                    // Show error with SweetAlert
                    const errorMsg = data.error || data.message || 'Failed to send message';
                    const isAccountError = data.error_code === 133010 || errorMsg.includes('not registered');
                    
                    Swal.fire({
                        icon: 'error',
                        title: isAccountError ? 'Account Not Registered' : 'Failed to Send',
                        html: isAccountError 
                            ? `<div style="text-align: left;"><p style="margin-bottom: 10px;">${errorMsg}</p><p style="margin-top: 10px; font-size: 0.9em; color: #666;">Please check your Settings and ensure your phone number is properly registered in Meta Business Manager.</p></div>`
                            : errorMsg,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: isAccountError ? 'Go to Settings' : 'OK',
                        showCancelButton: isAccountError,
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed && isAccountError) {
                            window.location.href = '{{ route("settings") }}';
                        }
                    });
                }
            } catch (error) {
                // Handle validation errors (422 status)
                if (error.response && error.response.status === 422) {
                    const validationErrors = error.response.data.errors || {};
                    displayValidationErrors(form, validationErrors);
                    
                    // Build error message list for SweetAlert
                    let errorMessages = [];
                    Object.keys(validationErrors).forEach(field => {
                        validationErrors[field].forEach(msg => {
                            errorMessages.push(`• ${msg}`);
                        });
                    });
                    
                    // Show validation errors with SweetAlert
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        html: '<div style="text-align: left;"><p style="margin-bottom: 10px; font-weight: 600;">Please correct the following errors:</p><ul style="list-style-type: disc; padding-left: 20px; margin: 0;">' + 
                              errorMessages.map(msg => `<li style="margin-bottom: 5px;">${msg}</li>`).join('') + 
                              '</ul></div>',
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: 'OK',
                        width: '500px',
                        customClass: {
                            popup: 'swal2-popup-custom'
                        }
                    });
                } else {
                    // Handle other errors
                    const errorData = error.response?.data || {};
                    const errorMessage = errorData.error || errorData.message || error.message || 'Failed to send message';
                    const isAccountError = errorData.error_code === 133010 || errorMessage.includes('not registered');
                    
                    Swal.fire({
                        icon: 'error',
                        title: isAccountError ? 'Account Not Registered' : 'Error',
                        html: isAccountError 
                            ? `<div style="text-align: left;"><p style="margin-bottom: 10px;">${errorMessage}</p><p style="margin-top: 10px; font-size: 0.9em; color: #666;">Please check your Settings and ensure your phone number is properly registered in Meta Business Manager.</p></div>`
                            : errorMessage,
                        confirmButtonColor: '#ef4444',
                        confirmButtonText: isAccountError ? 'Go to Settings' : 'OK',
                        showCancelButton: isAccountError,
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed && isAccountError) {
                            window.location.href = '{{ route("settings") }}';
                        }
                    });
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    </script>
@endsection

