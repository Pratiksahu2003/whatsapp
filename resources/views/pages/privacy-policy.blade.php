@php
    $isAuthenticated = Auth::check();
@endphp

@if($isAuthenticated)
    @extends('layouts.app')
@else
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Privacy Policy - WhatsApp</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-50">
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('login') }}" class="flex items-center space-x-3">
                            <i class="fab fa-whatsapp text-3xl text-green-500"></i>
                            <span class="text-xl font-bold text-gray-900">WhatsApp</span>
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Login</a>
                        <a href="{{ route('register') }}" class="bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-green-700">Register</a>
                    </div>
                </div>
            </div>
        </nav>
        <main class="min-h-screen">
@endif

@section('title', 'Privacy Policy - WhatsApp')
@section('page-title', 'Privacy Policy')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Privacy Policy</h1>
        
        <div class="prose max-w-none">
            <p class="text-sm text-gray-600 mb-6">
                <strong>Last Updated:</strong> {{ date('F d, Y') }}
            </p>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">1. Introduction</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Welcome to our WhatsApp Messaging Platform ("we," "our," or "us"). We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our WhatsApp messaging service.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">2. Information We Collect</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">2.1 Account Information</h3>
                        <p class="text-gray-700 leading-relaxed">
                            When you register for our service, we collect:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 ml-4 mt-2 space-y-1">
                            <li>Name</li>
                            <li>Email address</li>
                            <li>Password (encrypted)</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">2.2 WhatsApp API Credentials</h3>
                        <p class="text-gray-700 leading-relaxed">
                            To enable messaging functionality, we securely store:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 ml-4 mt-2 space-y-1">
                            <li>Phone Number ID</li>
                            <li>Access Token (encrypted)</li>
                            <li>Verify Token</li>
                            <li>API URL</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">2.3 Message Data</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We store messages and related data including:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 ml-4 mt-2 space-y-1">
                            <li>Message content</li>
                            <li>Recipient phone numbers</li>
                            <li>Message timestamps</li>
                            <li>Delivery status</li>
                            <li>Message metadata</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">2.4 Usage Data</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We automatically collect information about how you use our service, including:
                        </p>
                        <ul class="list-disc list-inside text-gray-700 ml-4 mt-2 space-y-1">
                            <li>IP address</li>
                            <li>Browser type and version</li>
                            <li>Device information</li>
                            <li>Access times and dates</li>
                            <li>Pages viewed</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">3. How We Use Your Information</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    We use the collected information for the following purposes:
                </p>
                <ul class="list-disc list-inside text-gray-700 ml-4 space-y-2">
                    <li><strong>Service Provision:</strong> To provide, maintain, and improve our WhatsApp messaging service</li>
                    <li><strong>Message Delivery:</strong> To send and receive messages through the WhatsApp Business API</li>
                    <li><strong>Account Management:</strong> To manage your account and provide customer support</li>
                    <li><strong>Security:</strong> To protect against fraud, unauthorized access, and other security threats</li>
                    <li><strong>Analytics:</strong> To analyze usage patterns and improve our service</li>
                    <li><strong>Compliance:</strong> To comply with legal obligations and enforce our terms of service</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">4. Data Sharing and Disclosure</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">4.1 WhatsApp/Meta</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We share necessary information with Meta (WhatsApp) to facilitate message delivery through their Business API. This includes message content, recipient information, and delivery status. Your use of our service is also subject to <a href="https://www.whatsapp.com/legal/privacy-policy" target="_blank" class="text-blue-600 hover:underline">WhatsApp's Privacy Policy</a> and <a href="https://www.whatsapp.com/legal/business-policy" target="_blank" class="text-blue-600 hover:underline">Business Terms</a>.
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">4.2 Service Providers</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We may share information with third-party service providers who assist us in operating our service, such as hosting providers, analytics services, and customer support tools.
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">4.3 Legal Requirements</h3>
                        <p class="text-gray-700 leading-relaxed">
                            We may disclose information if required by law, court order, or government regulation, or to protect our rights, property, or safety.
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-medium text-gray-800 mb-2">4.4 Business Transfers</h3>
                        <p class="text-gray-700 leading-relaxed">
                            In the event of a merger, acquisition, or sale of assets, your information may be transferred to the acquiring entity.
                        </p>
                    </div>
                </div>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">5. Data Security</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    We implement appropriate technical and organizational security measures to protect your information, including:
                </p>
                <ul class="list-disc list-inside text-gray-700 ml-4 space-y-2">
                    <li>Encryption of sensitive data (passwords, access tokens)</li>
                    <li>Secure HTTPS connections</li>
                    <li>Regular security audits and updates</li>
                    <li>Access controls and authentication</li>
                    <li>Secure data storage</li>
                </ul>
                <p class="text-gray-700 leading-relaxed mt-4">
                    However, no method of transmission over the internet or electronic storage is 100% secure. While we strive to protect your information, we cannot guarantee absolute security.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">6. Data Retention</h2>
                <p class="text-gray-700 leading-relaxed">
                    We retain your information for as long as necessary to provide our services and comply with legal obligations. Message data is retained according to your account settings and applicable laws. You may request deletion of your data by contacting us or deleting your account.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">7. Your Rights</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    Depending on your location, you may have the following rights regarding your personal information:
                </p>
                <ul class="list-disc list-inside text-gray-700 ml-4 space-y-2">
                    <li><strong>Access:</strong> Request access to your personal information</li>
                    <li><strong>Correction:</strong> Request correction of inaccurate information</li>
                    <li><strong>Deletion:</strong> Request deletion of your information</li>
                    <li><strong>Portability:</strong> Request transfer of your data</li>
                    <li><strong>Objection:</strong> Object to processing of your information</li>
                    <li><strong>Restriction:</strong> Request restriction of processing</li>
                </ul>
                <p class="text-gray-700 leading-relaxed mt-4">
                    To exercise these rights, please contact us using the information provided in the "Contact Us" section below.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">8. Cookies and Tracking Technologies</h2>
                <p class="text-gray-700 leading-relaxed">
                    We use cookies and similar tracking technologies to enhance your experience, analyze usage, and provide personalized content. You can control cookie preferences through your browser settings. However, disabling cookies may limit some functionality of our service.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">9. Children's Privacy</h2>
                <p class="text-gray-700 leading-relaxed">
                    Our service is not intended for individuals under the age of 18. We do not knowingly collect personal information from children. If you believe we have collected information from a child, please contact us immediately.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">10. International Data Transfers</h2>
                <p class="text-gray-700 leading-relaxed">
                    Your information may be transferred to and processed in countries other than your country of residence. These countries may have different data protection laws. By using our service, you consent to the transfer of your information to these countries.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">11. Changes to This Privacy Policy</h2>
                <p class="text-gray-700 leading-relaxed">
                    We may update this Privacy Policy from time to time. We will notify you of any material changes by posting the new Privacy Policy on this page and updating the "Last Updated" date. You are advised to review this Privacy Policy periodically for any changes.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">12. Contact Us</h2>
                <p class="text-gray-700 leading-relaxed mb-4">
                    If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:
                </p>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-700">
                        <strong>Email:</strong> privacy@example.com<br>
                        <strong>Address:</strong> [Your Company Address]<br>
                        <strong>Phone:</strong> [Your Contact Number]
                    </p>
                </div>
                <p class="text-gray-700 leading-relaxed mt-4 text-sm">
                    <em>Note: Please replace the contact information above with your actual contact details.</em>
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold text-gray-900 mb-4">13. Compliance</h2>
                <p class="text-gray-700 leading-relaxed">
                    This Privacy Policy is designed to comply with applicable data protection laws, including but not limited to:
                </p>
                <ul class="list-disc list-inside text-gray-700 ml-4 mt-2 space-y-1">
                    <li>General Data Protection Regulation (GDPR) - European Union</li>
                    <li>California Consumer Privacy Act (CCPA) - California, USA</li>
                    <li>Personal Information Protection and Electronic Documents Act (PIPEDA) - Canada</li>
                    <li>Other applicable regional data protection laws</li>
                </ul>
            </section>

            <div class="mt-8 pt-6 border-t border-gray-200">
                <p class="text-sm text-gray-600">
                    By using our WhatsApp Messaging Platform, you acknowledge that you have read and understood this Privacy Policy and agree to the collection, use, and disclosure of your information as described herein.
                </p>
            </div>
        </div>

        <div class="mt-8 flex justify-end space-x-3">
            @if($isAuthenticated)
                <a href="{{ route('whatsapp.dashboard') }}" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="px-6 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login
                </a>
                <a href="{{ route('register') }}" class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </a>
            @endif
        </div>
    </div>
</div>
@endsection

@if(!$isAuthenticated)
        </main>
        <footer class="bg-white border-t border-gray-200 py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-600">
                <p>&copy; {{ date('Y') }} WhatsApp Messaging Platform. All rights reserved.</p>
            </div>
        </footer>
    </body>
    </html>
@endif
