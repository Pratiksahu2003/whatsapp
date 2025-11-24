<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('settings', compact('user'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_access_token' => 'nullable|string',
            'whatsapp_verify_token' => 'nullable|string|max:255',
            'whatsapp_api_url' => 'nullable|url|max:255',
        ]);

        $user = Auth::user();
        $user->update([
            'whatsapp_phone_number_id' => $request->whatsapp_phone_number_id,
            'whatsapp_access_token' => $request->whatsapp_access_token,
            'whatsapp_verify_token' => $request->whatsapp_verify_token,
            'whatsapp_api_url' => $request->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0',
        ]);

        return redirect()->route('settings')->with('success', 'WhatsApp credentials updated successfully!');
    }

    public function testConnection(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasWhatsAppCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Please configure your WhatsApp credentials first.'
            ], 400);
        }

        try {
            $apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0';
            $phoneNumberId = $user->whatsapp_phone_number_id;
            $accessToken = $user->whatsapp_access_token;

            // Test connection by fetching phone number details
            $response = Http::withToken($accessToken)
                ->get("{$apiUrl}/{$phoneNumberId}?fields=verified_name,display_phone_number,quality_rating");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful!',
                    'data' => [
                        'verified_name' => $data['verified_name'] ?? 'N/A',
                        'display_phone_number' => $data['display_phone_number'] ?? 'N/A',
                        'quality_rating' => $data['quality_rating']['status'] ?? 'N/A',
                        'phone_number_id' => $phoneNumberId,
                    ]
                ]);
            }

            // If phone number endpoint fails, try a simpler test
            $testResponse = Http::withToken($accessToken)
                ->get("{$apiUrl}/me");

            if ($testResponse->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Access token is valid!',
                    'data' => [
                        'phone_number_id' => $phoneNumberId,
                        'app_info' => $testResponse->json(),
                    ]
                ]);
            }

            $error = $response->json('error', $testResponse->json('error', []));
            return response()->json([
                'success' => false,
                'message' => $error['message'] ?? 'Connection failed',
                'error_code' => $error['code'] ?? null,
                'error_type' => $error['type'] ?? null,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPhoneNumber(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasWhatsAppCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Please configure your WhatsApp credentials first.'
            ], 400);
        }

        try {
            $apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0';
            $phoneNumberId = $user->whatsapp_phone_number_id;
            $accessToken = $user->whatsapp_access_token;

            // Verify phone number registration status
            $response = Http::withToken($accessToken)
                ->get("{$apiUrl}/{$phoneNumberId}?fields=verified_name,display_phone_number,quality_rating,code_verification_status,account_mode,status");

            if ($response->successful()) {
                $data = $response->json();
                
                // Check if phone number is registered
                $isRegistered = isset($data['verified_name']) || isset($data['display_phone_number']);
                $accountMode = $data['account_mode'] ?? 'UNKNOWN';
                $status = $data['status'] ?? 'UNKNOWN';
                $codeVerificationStatus = $data['code_verification_status'] ?? 'UNKNOWN';
                
                $verificationStatus = 'registered';
                $statusMessage = 'Phone number is registered and verified!';
                
                if ($accountMode === 'SANDBOX' || $status === 'PENDING') {
                    $verificationStatus = 'pending';
                    $statusMessage = 'Phone number is registered but may be in sandbox/test mode. For production use, ensure your number is approved.';
                } elseif (!$isRegistered) {
                    $verificationStatus = 'not_registered';
                    $statusMessage = 'Phone number is not registered. Please register it in Meta Business Manager.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $statusMessage,
                    'verification_status' => $verificationStatus,
                    'data' => [
                        'verified_name' => $data['verified_name'] ?? 'Not Available',
                        'display_phone_number' => $data['display_phone_number'] ?? 'Not Available',
                        'phone_number_id' => $phoneNumberId,
                        'account_mode' => $accountMode,
                        'status' => $status,
                        'code_verification_status' => $codeVerificationStatus,
                        'quality_rating' => $data['quality_rating']['status'] ?? 'Not Available',
                        'quality_rating_score' => $data['quality_rating']['score'] ?? null,
                    ]
                ]);
            }

            // If the endpoint fails, check the error
            $error = $response->json('error', []);
            $errorCode = $error['code'] ?? null;
            $errorMessage = $error['message'] ?? 'Unable to verify phone number';

            // Check for specific error codes
            if ($errorCode === 133010 || $errorCode === 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number is not registered with WhatsApp Business API.',
                    'verification_status' => 'not_registered',
                    'error_code' => $errorCode,
                    'error_type' => $error['type'] ?? null,
                    'help_text' => 'Please register your phone number in Meta Business Manager > WhatsApp > API Setup. Complete the phone number registration and verification process.'
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'error_type' => $error['type'] ?? null,
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function registerPhoneNumber(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasWhatsAppCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Please configure your WhatsApp credentials first.'
            ], 400);
        }

        $request->validate([
            'cc' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'method' => 'required|in:sms,voice',
            'pin' => 'nullable|string|size:6',
        ], [
            'cc.required' => 'Country code is required.',
            'phone_number.required' => 'Phone number is required.',
            'method.required' => 'Verification method is required.',
            'method.in' => 'Method must be either "sms" or "voice".',
            'pin.size' => 'PIN must be exactly 6 digits.',
        ]);

        try {
            $apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0';
            $accessToken = $user->whatsapp_access_token;

            // Build the payload (without cert as per user request)
            $payload = [
                'cc' => $request->cc,
                'phone_number' => $request->phone_number,
                'method' => $request->method,
            ];

            // Add PIN only if provided
            if ($request->filled('pin')) {
                $payload['pin'] = $request->pin;
            }

            // The /v1/account endpoint - construct the endpoint URL
            // Remove version from API URL and add /v1/account
            // Example: https://graph.facebook.com/v18.0 -> https://graph.facebook.com/v1/account
            $baseUrl = preg_replace('/\/v\d+\.\d+$/', '', $apiUrl); // Remove version like /v18.0
            $endpoint = rtrim($baseUrl, '/') . '/v1/account';
            
            Log::info('Attempting phone number registration', [
                'endpoint' => $endpoint,
                'phone_number' => $request->phone_number,
                'method' => $request->method
            ]);
            
            $response = Http::withToken($accessToken)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('WhatsApp phone number registration initiated', [
                    'phone_number' => $request->phone_number,
                    'method' => $request->method,
                    'response' => $data
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Verification code sent successfully! Please check your ' . ($request->method === 'sms' ? 'SMS' : 'phone call') . ' for the verification code.',
                    'data' => $data
                ]);
            }

            $error = $response->json('error', []);
            $errorCode = $error['code'] ?? null;
            $errorMessage = $error['message'] ?? 'Failed to register phone number';

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'error_type' => $error['type'] ?? null,
                'error_subcode' => $error['error_subcode'] ?? null,
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('WhatsApp phone number registration error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approvePhoneNumber(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasWhatsAppCredentials()) {
            return response()->json([
                'success' => false,
                'message' => 'Please configure your WhatsApp credentials first.'
            ], 400);
        }

        $request->validate([
            'cert' => 'required|string',
        ], [
            'cert.required' => 'Cert token is required.',
        ]);

        try {
            $apiUrl = $user->whatsapp_api_url ?? 'https://graph.facebook.com/v18.0';
            $phoneNumberId = $user->whatsapp_phone_number_id;
            $accessToken = $user->whatsapp_access_token;

            // Submit cert token to approve/verify phone number
            // The endpoint is typically POST to /{phone-number-id}/register with cert
            $endpoint = "{$apiUrl}/{$phoneNumberId}/register";
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'cert' => $request->cert,
            ];

            // Add PIN if provided (for two-step verification)
            if ($request->filled('pin')) {
                $payload['pin'] = $request->pin;
            }

            Log::info('Attempting phone number approval with cert token', [
                'endpoint' => $endpoint,
                'phone_number_id' => $phoneNumberId
            ]);
            
            $response = Http::withToken($accessToken)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('WhatsApp phone number approval successful', [
                    'phone_number_id' => $phoneNumberId,
                    'response' => $data
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Phone number approved successfully! Your phone number status should update shortly.',
                    'data' => $data
                ]);
            }

            $error = $response->json('error', []);
            $errorCode = $error['code'] ?? null;
            $errorMessage = $error['message'] ?? 'Failed to approve phone number';
            $errorSubcode = $error['error_subcode'] ?? null;

            Log::error('WhatsApp phone number approval failed', [
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'error_subcode' => $errorSubcode,
                'response' => $response->json()
            ]);

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode,
                'error_type' => $error['type'] ?? null,
                'error_subcode' => $errorSubcode,
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('WhatsApp phone number approval error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Approval error: ' . $e->getMessage()
            ], 500);
        }
    }
}
