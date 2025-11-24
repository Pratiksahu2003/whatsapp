@extends('layouts.app')

@section('title', 'Settings - WhatsApp')
@section('page-title', 'Settings')

@section('content')
<div class="p-6">
            @if(session('success'))
                <div class="mb-6 rounded-md bg-green-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-md bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $user->email }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                    <i class="fab fa-whatsapp text-green-500"></i>
                    <span>WhatsApp API Credentials</span>
                </h2>
                
                @if($user->hasWhatsAppCredentials())
                    <div class="mb-4 rounded-md bg-green-50 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-green-800">Your WhatsApp credentials are configured!</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button type="button" onclick="testConnection()" id="testConnectionBtn" 
                                    class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 transition flex items-center space-x-2">
                                    <i class="fas fa-plug"></i>
                                    <span>Test Connection</span>
                                </button>
                                <button type="button" onclick="verifyPhoneNumber()" id="verifyPhoneBtn" 
                                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition flex items-center space-x-2">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Verify Phone Number</span>
                                </button>
                                <button type="button" onclick="openRegisterPhoneModal()" id="registerPhoneBtn" 
                                    class="px-4 py-2 bg-purple-600 text-white text-sm rounded-md hover:bg-purple-700 transition flex items-center space-x-2">
                                    <i class="fas fa-phone-alt"></i>
                                    <span>Register Phone Number</span>
                                </button>
                                <button type="button" onclick="openApprovePhoneModal()" id="approvePhoneBtn" 
                                    class="px-4 py-2 bg-orange-600 text-white text-sm rounded-md hover:bg-orange-700 transition flex items-center space-x-2">
                                    <i class="fas fa-certificate"></i>
                                    <span>Approve with Cert Token</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connection Test Results -->
                    <div id="connectionResult" class="hidden mb-4"></div>
                    
                    <!-- Phone Number Verification Results -->
                    <div id="verificationResult" class="hidden mb-4"></div>
                    
                    <!-- Phone Number Registration Results -->
                    <div id="registrationResult" class="hidden mb-4"></div>
                    
                    <!-- Phone Number Approval Results -->
                    <div id="approvalResult" class="hidden mb-4"></div>
                @else
                    <div class="mb-4 rounded-md bg-yellow-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-yellow-800">Please configure your WhatsApp credentials to start sending messages.</p>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    <div class="space-y-6">
                        <div>
                            <label for="whatsapp_phone_number_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number ID <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id" 
                                value="{{ old('whatsapp_phone_number_id', $user->whatsapp_phone_number_id) }}"
                                placeholder="Your WhatsApp Phone Number ID"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Get this from Meta Business Manager > WhatsApp > API Setup
                            </p>
                            @error('whatsapp_phone_number_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="whatsapp_access_token" class="block text-sm font-medium text-gray-700 mb-2">
                                Access Token <span class="text-red-500">*</span>
                            </label>
                            <textarea id="whatsapp_access_token" name="whatsapp_access_token" rows="3"
                                placeholder="Your WhatsApp Access Token"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">{{ old('whatsapp_access_token', $user->whatsapp_access_token) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Get this from Meta Business Manager > WhatsApp > API Setup
                            </p>
                            @error('whatsapp_access_token')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="whatsapp_verify_token" class="block text-sm font-medium text-gray-700 mb-2">
                                Verify Token
                            </label>
                            <input type="text" id="whatsapp_verify_token" name="whatsapp_verify_token" 
                                value="{{ old('whatsapp_verify_token', $user->whatsapp_verify_token) }}"
                                placeholder="Your custom verify token (for webhook)"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Create a random string for webhook verification
                            </p>
                            @error('whatsapp_verify_token')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="whatsapp_api_url" class="block text-sm font-medium text-gray-700 mb-2">
                                API URL
                            </label>
                            <input type="url" id="whatsapp_api_url" name="whatsapp_api_url" 
                                value="{{ old('whatsapp_api_url', $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0') }}"
                                placeholder="https://graph.facebook.com/v18.0"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> Default: https://graph.facebook.com/v18.0
                            </p>
                            @error('whatsapp_api_url')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end space-x-3 pt-4 border-t">
                            <a href="{{ route('whatsapp.dashboard') }}" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition flex items-center space-x-2">
                                <i class="fas fa-save"></i>
                                <span>Save Settings</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-6 bg-blue-50 rounded-lg p-6">
                <h3 class="text-sm font-semibold text-blue-900 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>How to Get Your Credentials
                </h3>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Go to <a href="https://developers.facebook.com/" target="_blank" class="underline">Meta for Developers</a></li>
                    <li>Create a new app or use an existing one</li>
                    <li>Add WhatsApp product to your app</li>
                    <li>Get your Phone Number ID and Access Token from the WhatsApp API setup</li>
                    <li>Create a Verify Token (any random string) for webhook verification</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Register Phone Number Modal -->
    <div id="registerPhoneModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Register Phone Number</h3>
                    <button onclick="closeRegisterPhoneModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="registerPhoneForm" onsubmit="registerPhoneNumber(event)">
                    <div class="space-y-4">
                        <div>
                            <label for="register_cc" class="block text-sm font-medium text-gray-700 mb-1">
                                Country Code <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="register_cc" name="cc" required
                                placeholder="e.g., 1, 91, 44" maxlength="5"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <p class="mt-1 text-xs text-gray-500">Enter country code without + (e.g., 1 for USA, 91 for India)</p>
                        </div>

                        <div>
                            <label for="register_phone_number" class="block text-sm font-medium text-gray-700 mb-1">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="register_phone_number" name="phone_number" required
                                placeholder="e.g., 1234567890" maxlength="20"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <p class="mt-1 text-xs text-gray-500">Enter phone number without country code (digits only)</p>
                        </div>

                        <div>
                            <label for="register_method" class="block text-sm font-medium text-gray-700 mb-1">
                                Verification Method <span class="text-red-500">*</span>
                            </label>
                            <select id="register_method" name="method" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                                <option value="sms">SMS</option>
                                <option value="voice">Voice Call</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Choose how you want to receive the verification code</p>
                        </div>

                        <div>
                            <label for="register_pin" class="block text-sm font-medium text-gray-700 mb-1">
                                PIN (Optional)
                            </label>
                            <input type="text" id="register_pin" name="pin"
                                placeholder="6-digit PIN" maxlength="6" pattern="[0-9]{6}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500">
                            <p class="mt-1 text-xs text-gray-500">Required only if two-step verification is enabled on your WhatsApp account</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                        <button type="button" onclick="closeRegisterPhoneModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="submit" id="registerPhoneSubmitBtn"
                            class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition flex items-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Verification Code</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        async function testConnection() {
            const btn = document.getElementById('testConnectionBtn');
            const resultDiv = document.getElementById('connectionResult');
            
            if (!btn || !resultDiv) return;
            
            const originalBtnHtml = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            resultDiv.classList.add('hidden');
            
            try {
                const response = await axios.post('{{ route("settings.test-connection") }}');
                const data = response.data;
                
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    resultDiv.className = 'mb-4 rounded-md bg-green-50 border border-green-200 p-4';
                    let html = '<div class="flex"><div class="flex-shrink-0"><i class="fas fa-check-circle text-green-400"></i></div><div class="ml-3 flex-1">';
                    html += '<h3 class="text-sm font-medium text-green-800 mb-2">Connection Successful!</h3>';
                    
                    if (data.data) {
                        html += '<div class="mt-2 text-sm text-green-700 space-y-1">';
                        if (data.data.verified_name) {
                            html += `<p><strong>Verified Name:</strong> ${data.data.verified_name}</p>`;
                        }
                        if (data.data.display_phone_number) {
                            html += `<p><strong>Phone Number:</strong> ${data.data.display_phone_number}</p>`;
                        }
                        if (data.data.quality_rating) {
                            html += `<p><strong>Quality Rating:</strong> ${data.data.quality_rating}</p>`;
                        }
                        if (data.data.phone_number_id) {
                            html += `<p><strong>Phone Number ID:</strong> ${data.data.phone_number_id}</p>`;
                        }
                        html += '</div>';
                    }
                    
                    html += '</div></div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    resultDiv.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-times-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Connection Failed</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p>${data.message || 'Unable to connect. Please check your credentials.'}</p>
                                    ${data.error_code ? `<p class="mt-1"><strong>Error Code:</strong> ${data.error_code}</p>` : ''}
                                    ${data.error_type ? `<p><strong>Error Type:</strong> ${data.error_type}</p>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                const errorMessage = error.response?.data?.message || error.message || 'Connection error occurred';
                resultDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Connection Error</h3>
                            <p class="mt-2 text-sm text-red-700">${errorMessage}</p>
                        </div>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        }

        async function verifyPhoneNumber() {
            const btn = document.getElementById('verifyPhoneBtn');
            const resultDiv = document.getElementById('verificationResult');
            
            if (!btn || !resultDiv) return;
            
            const originalBtnHtml = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            resultDiv.classList.add('hidden');
            
            try {
                const response = await axios.post('{{ route("settings.verify-phone") }}');
                const data = response.data;
                
                resultDiv.classList.remove('hidden');
                
                if (data.success) {
                    const status = data.verification_status || 'unknown';
                    let bgColor = 'bg-green-50';
                    let borderColor = 'border-green-200';
                    let icon = 'fa-check-circle';
                    let iconColor = 'text-green-400';
                    let titleColor = 'text-green-800';
                    let textColor = 'text-green-700';
                    
                    if (status === 'not_registered') {
                        bgColor = 'bg-red-50';
                        borderColor = 'border-red-200';
                        icon = 'fa-times-circle';
                        iconColor = 'text-red-400';
                        titleColor = 'text-red-800';
                        textColor = 'text-red-700';
                    } else if (status === 'pending') {
                        bgColor = 'bg-yellow-50';
                        borderColor = 'border-yellow-200';
                        icon = 'fa-exclamation-triangle';
                        iconColor = 'text-yellow-400';
                        titleColor = 'text-yellow-800';
                        textColor = 'text-yellow-700';
                    }
                    
                    resultDiv.className = `mb-4 rounded-md ${bgColor} border ${borderColor} p-4`;
                    let html = `<div class="flex"><div class="flex-shrink-0"><i class="fas ${icon} ${iconColor}"></i></div><div class="ml-3 flex-1">`;
                    html += `<h3 class="text-sm font-medium ${titleColor} mb-2">${data.message || 'Phone Number Verification'}</h3>`;
                    
                    if (data.data) {
                        html += `<div class="mt-2 text-sm ${textColor} space-y-1">`;
                        if (data.data.verified_name && data.data.verified_name !== 'Not Available') {
                            html += `<p><strong>Verified Name:</strong> ${data.data.verified_name}</p>`;
                        }
                        if (data.data.display_phone_number && data.data.display_phone_number !== 'Not Available') {
                            html += `<p><strong>Phone Number:</strong> ${data.data.display_phone_number}</p>`;
                        }
                        if (data.data.phone_number_id) {
                            html += `<p><strong>Phone Number ID:</strong> ${data.data.phone_number_id}</p>`;
                        }
                        if (data.data.account_mode && data.data.account_mode !== 'UNKNOWN') {
                            html += `<p><strong>Account Mode:</strong> <span class="font-semibold">${data.data.account_mode}</span></p>`;
                        }
                        if (data.data.status && data.data.status !== 'UNKNOWN') {
                            html += `<p><strong>Status:</strong> ${data.data.status}</p>`;
                        }
                        if (data.data.code_verification_status && data.data.code_verification_status !== 'UNKNOWN') {
                            html += `<p><strong>Verification Status:</strong> ${data.data.code_verification_status}</p>`;
                        }
                        if (data.data.quality_rating && data.data.quality_rating !== 'Not Available') {
                            html += `<p><strong>Quality Rating:</strong> ${data.data.quality_rating}`;
                            if (data.data.quality_rating_score) {
                                html += ` (Score: ${data.data.quality_rating_score})`;
                            }
                            html += `</p>`;
                        }
                        html += '</div>';
                    }
                    
                    if (status === 'not_registered' && data.help_text) {
                        html += `<div class="mt-3 p-3 bg-white rounded border border-red-200"><p class="text-sm ${textColor}"><strong>How to fix:</strong> ${data.help_text}</p></div>`;
                    }
                    
                    html += '</div></div>';
                    resultDiv.innerHTML = html;
                } else {
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    let html = `<div class="flex"><div class="flex-shrink-0"><i class="fas fa-times-circle text-red-400"></i></div><div class="ml-3">`;
                    html += `<h3 class="text-sm font-medium text-red-800">Verification Failed</h3>`;
                    html += `<div class="mt-2 text-sm text-red-700">`;
                    html += `<p>${data.message || 'Unable to verify phone number. Please check your credentials.'}</p>`;
                    if (data.error_code) {
                        html += `<p class="mt-1"><strong>Error Code:</strong> ${data.error_code}</p>`;
                    }
                    if (data.error_type) {
                        html += `<p><strong>Error Type:</strong> ${data.error_type}</p>`;
                    }
                    if (data.help_text) {
                        html += `<div class="mt-3 p-3 bg-white rounded border border-red-200"><p><strong>How to fix:</strong> ${data.help_text}</p></div>`;
                    }
                    html += `</div></div></div>`;
                    resultDiv.innerHTML = html;
                }
            } catch (error) {
                resultDiv.classList.remove('hidden');
                resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                const errorMessage = error.response?.data?.message || error.message || 'Verification error occurred';
                resultDiv.innerHTML = `
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Verification Error</h3>
                            <p class="mt-2 text-sm text-red-700">${errorMessage}</p>
                        </div>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        }

        function openRegisterPhoneModal() {
            document.getElementById('registerPhoneModal').classList.remove('hidden');
        }

        function closeRegisterPhoneModal() {
            document.getElementById('registerPhoneModal').classList.add('hidden');
            document.getElementById('registerPhoneForm').reset();
            const resultDiv = document.getElementById('registrationResult');
            if (resultDiv) {
                resultDiv.classList.add('hidden');
            }
        }

        async function registerPhoneNumber(event) {
            event.preventDefault();
            
            const form = document.getElementById('registerPhoneForm');
            const submitBtn = document.getElementById('registerPhoneSubmitBtn');
            const resultDiv = document.getElementById('registrationResult');
            
            if (!form || !submitBtn) return;
            
            const originalBtnHtml = submitBtn.innerHTML;
            const formData = new FormData(form);
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            if (resultDiv) {
                resultDiv.classList.add('hidden');
            }
            
            try {
                const payload = {
                    cc: formData.get('cc'),
                    phone_number: formData.get('phone_number'),
                    method: formData.get('method'),
                };
                
                // Add PIN only if provided
                const pin = formData.get('pin');
                if (pin && pin.trim() !== '') {
                    payload.pin = pin.trim();
                }
                
                const response = await axios.post('{{ route("settings.register-phone") }}', payload);
                const data = response.data;
                
                if (resultDiv) {
                    resultDiv.classList.remove('hidden');
                }
                
                if (data.success) {
                    resultDiv.className = 'mb-4 rounded-md bg-green-50 border border-green-200 p-4';
                    let html = '<div class="flex"><div class="flex-shrink-0"><i class="fas fa-check-circle text-green-400"></i></div><div class="ml-3 flex-1">';
                    html += `<h3 class="text-sm font-medium text-green-800 mb-2">${data.message || 'Verification code sent successfully!'}</h3>`;
                    
                    if (data.data) {
                        html += '<div class="mt-2 text-sm text-green-700">';
                        html += '<p class="font-semibold">Next Steps:</p>';
                        html += '<ul class="list-disc list-inside mt-1 space-y-1">';
                        html += '<li>Check your ' + (payload.method === 'sms' ? 'SMS messages' : 'phone') + ' for the verification code</li>';
                        html += '<li>Use the verification code to complete the registration process</li>';
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    html += '</div></div>';
                    resultDiv.innerHTML = html;
                    
                    // Close modal after 3 seconds
                    setTimeout(() => {
                        closeRegisterPhoneModal();
                    }, 3000);
                } else {
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    let html = '<div class="flex"><div class="flex-shrink-0"><i class="fas fa-times-circle text-red-400"></i></div><div class="ml-3">';
                    html += '<h3 class="text-sm font-medium text-red-800">Registration Failed</h3>';
                    html += '<div class="mt-2 text-sm text-red-700">';
                    html += `<p>${data.message || 'Unable to register phone number. Please check your credentials and try again.'}</p>`;
                    if (data.error_code) {
                        html += `<p class="mt-1"><strong>Error Code:</strong> ${data.error_code}</p>`;
                    }
                    if (data.error_type) {
                        html += `<p><strong>Error Type:</strong> ${data.error_type}</p>`;
                    }
                    html += '</div></div></div>';
                    resultDiv.innerHTML = html;
                }
            } catch (error) {
                if (resultDiv) {
                    resultDiv.classList.remove('hidden');
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    const errorMessage = error.response?.data?.message || error.response?.data?.error || error.message || 'Registration error occurred';
                    resultDiv.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Registration Error</h3>
                                <p class="mt-2 text-sm text-red-700">${errorMessage}</p>
                            </div>
                        </div>
                    `;
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('registerPhoneModal');
            if (event.target === modal) {
                closeRegisterPhoneModal();
            }
            const approveModal = document.getElementById('approvePhoneModal');
            if (event.target === approveModal) {
                closeApprovePhoneModal();
            }
        }

        function openApprovePhoneModal() {
            document.getElementById('approvePhoneModal').classList.remove('hidden');
        }

        function closeApprovePhoneModal() {
            document.getElementById('approvePhoneModal').classList.add('hidden');
            document.getElementById('approvePhoneForm').reset();
            const resultDiv = document.getElementById('approvalResult');
            if (resultDiv) {
                resultDiv.classList.add('hidden');
            }
        }

        async function approvePhoneNumber(event) {
            event.preventDefault();
            
            const form = document.getElementById('approvePhoneForm');
            const submitBtn = document.getElementById('approvePhoneSubmitBtn');
            const resultDiv = document.getElementById('approvalResult');
            
            if (!form || !submitBtn) return;
            
            const originalBtnHtml = submitBtn.innerHTML;
            const formData = new FormData(form);
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';
            
            if (resultDiv) {
                resultDiv.classList.add('hidden');
            }
            
            try {
                const payload = {
                    cert: formData.get('cert'),
                };
                
                // Add PIN only if provided
                const pin = formData.get('pin');
                if (pin && pin.trim() !== '') {
                    payload.pin = pin.trim();
                }
                
                const response = await axios.post('{{ route("settings.approve-phone") }}', payload);
                const data = response.data;
                
                if (resultDiv) {
                    resultDiv.classList.remove('hidden');
                }
                
                if (data.success) {
                    resultDiv.className = 'mb-4 rounded-md bg-green-50 border border-green-200 p-4';
                    let html = '<div class="flex"><div class="flex-shrink-0"><i class="fas fa-check-circle text-green-400"></i></div><div class="ml-3 flex-1">';
                    html += `<h3 class="text-sm font-medium text-green-800 mb-2">${data.message || 'Phone number approved successfully!'}</h3>`;
                    
                    if (data.data) {
                        html += '<div class="mt-2 text-sm text-green-700">';
                        html += '<p class="font-semibold">Your phone number status should update shortly. Please verify the status by clicking "Verify Phone Number" button.</p>';
                        html += '</div>';
                    }
                    
                    html += '</div></div>';
                    resultDiv.innerHTML = html;
                    
                    // Close modal after 3 seconds
                    setTimeout(() => {
                        closeApprovePhoneModal();
                    }, 3000);
                } else {
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    let html = '<div class="flex"><div class="flex-shrink-0"><i class="fas fa-times-circle text-red-400"></i></div><div class="ml-3">';
                    html += '<h3 class="text-sm font-medium text-red-800">Approval Failed</h3>';
                    html += '<div class="mt-2 text-sm text-red-700">';
                    html += `<p>${data.message || 'Unable to approve phone number. Please check your cert token and try again.'}</p>`;
                    if (data.error_code) {
                        html += `<p class="mt-1"><strong>Error Code:</strong> ${data.error_code}</p>`;
                    }
                    if (data.error_type) {
                        html += `<p><strong>Error Type:</strong> ${data.error_type}</p>`;
                    }
                    if (data.error_subcode) {
                        html += `<p><strong>Error Subcode:</strong> ${data.error_subcode}</p>`;
                    }
                    html += '</div></div></div>';
                    resultDiv.innerHTML = html;
                }
            } catch (error) {
                if (resultDiv) {
                    resultDiv.classList.remove('hidden');
                    resultDiv.className = 'mb-4 rounded-md bg-red-50 border border-red-200 p-4';
                    const errorMessage = error.response?.data?.message || error.response?.data?.error || error.message || 'Approval error occurred';
                    resultDiv.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Approval Error</h3>
                                <p class="mt-2 text-sm text-red-700">${errorMessage}</p>
                            </div>
                        </div>
                    `;
                }
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        }
    </script>

    <!-- Approve Phone Number Modal -->
    <div id="approvePhoneModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Approve Phone Number</h3>
                    <button onclick="closeApprovePhoneModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="mb-4 rounded-md bg-blue-50 border border-blue-200 p-3">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Use this to approve your phone number that's in PENDING status. Enter the cert token (base64 encoded certificate) you received.
                    </p>
                </div>
                
                <form id="approvePhoneForm" onsubmit="approvePhoneNumber(event)">
                    <div class="space-y-4">
                        <div>
                            <label for="approve_cert" class="block text-sm font-medium text-gray-700 mb-1">
                                Cert Token (Base64) <span class="text-red-500">*</span>
                            </label>
                            <textarea id="approve_cert" name="cert" required rows="4"
                                placeholder="Paste your base64 encoded cert token here..."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 font-mono text-xs"></textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle"></i> This is the base64 encoded certificate token you received during phone number registration
                            </p>
                        </div>

                        <div>
                            <label for="approve_pin" class="block text-sm font-medium text-gray-700 mb-1">
                                PIN (Optional)
                            </label>
                            <input type="text" id="approve_pin" name="pin"
                                placeholder="6-digit PIN" maxlength="6" pattern="[0-9]{6}"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500">
                            <p class="mt-1 text-xs text-gray-500">Required only if two-step verification is enabled on your WhatsApp account</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 mt-6 pt-4 border-t">
                        <button type="button" onclick="closeApprovePhoneModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="submit" id="approvePhoneSubmitBtn"
                            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition flex items-center space-x-2">
                            <i class="fas fa-certificate"></i>
                            <span>Approve Phone Number</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

