<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WhatsApp Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col transition-all duration-300">
            <!-- Logo -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <i class="fab fa-whatsapp text-3xl text-green-500"></i>
                    <span class="text-xl font-bold text-gray-900">WhatsApp</span>
                </div>
                <button id="sidebarToggle" class="lg:hidden text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-3 space-y-1">
                    <a href="{{ route('whatsapp.dashboard') }}" class="nav-item {{ request()->routeIs('whatsapp.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('whatsapp.send-page') }}" class="nav-item {{ request()->routeIs('whatsapp.send-page') ? 'active' : '' }}">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Message</span>
                    </a>
                    <a href="{{ route('whatsapp.conversations') }}" class="nav-item {{ request()->routeIs('whatsapp.conversations') ? 'active' : '' }}">
                        <i class="fas fa-comments"></i>
                        <span>Conversations</span>
                    </a>
                    <a href="{{ route('whatsapp.contacts') }}" class="nav-item {{ request()->routeIs('whatsapp.contacts') ? 'active' : '' }}">
                        <i class="fas fa-address-book"></i>
                        <span>Contacts</span>
                    </a>
                    <a href="{{ route('whatsapp.templates') }}" class="nav-item {{ request()->routeIs('whatsapp.templates') ? 'active' : '' }}">
                        <i class="fas fa-file-alt"></i>
                        <span>Templates</span>
                    </a>
                    <a href="{{ route('whatsapp.scheduled') }}" class="nav-item {{ request()->routeIs('whatsapp.scheduled') ? 'active' : '' }}">
                        <i class="fas fa-clock"></i>
                        <span>Scheduled</span>
                    </a>
                    <a href="{{ route('whatsapp.bulk') }}" class="nav-item {{ request()->routeIs('whatsapp.bulk') ? 'active' : '' }}">
                        <i class="fas fa-bullhorn"></i>
                        <span>Bulk Messages</span>
                    </a>
                    <a href="{{ route('whatsapp.analytics') }}" class="nav-item {{ request()->routeIs('whatsapp.analytics') ? 'active' : '' }}">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </a>
                    <a href="{{ route('settings') }}" class="nav-item {{ request()->routeIs('settings') ? 'active' : '' }}">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </nav>

            <!-- User Section -->
            <div class="border-t border-gray-200 p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold">
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name ?? 'User' }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $user->email ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="w-full mb-2">
                    @csrf
                    <button type="submit" class="w-full flex items-center space-x-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-md transition">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </button>
                </form>
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <a href="{{ route('privacy-policy') }}" class="flex items-center space-x-2 px-3 py-2 text-xs text-gray-500 hover:text-gray-700 rounded-md transition">
                        <i class="fas fa-shield-alt"></i>
                        <span>Privacy Policy</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <div class="flex items-center space-x-4">
                    <button id="mobileSidebarToggle" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <h1 class="text-xl font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        <i class="far fa-clock mr-1"></i>
                        <span id="current-time"></span>
                    </div>
                    @if($user->hasWhatsAppCredentials())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i>Connected
                        </span>
                    @else
                        <a href="{{ route('settings') }}" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Setup Required
                        </a>
                    @endif
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50">
                @if(session('success'))
                    <div class="mx-6 mt-4 rounded-md bg-green-50 border border-green-200 p-4">
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
                    <div class="mx-6 mt-4 rounded-md bg-red-50 border border-red-200 p-4">
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

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Mobile Sidebar Overlay -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-40 lg:hidden hidden"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = now.toLocaleString();
            }
        }
        updateTime();
        setInterval(updateTime, 1000);

        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        }

        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
        if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar on window resize (desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
            }
        });
    </script>
    @stack('scripts')
</body>
</html>

