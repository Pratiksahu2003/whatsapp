@extends('layouts.app')

@section('title', 'Analytics - WhatsApp')
@section('page-title', 'Analytics & Reports')

@section('content')
<div class="p-6">
    <!-- Date Range Filter -->
    <div class="mb-6 bg-white rounded-lg shadow-md p-6">
        <form method="GET" action="{{ route('whatsapp.analytics') }}" class="flex items-end space-x-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            </div>
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
            </div>
            <div>
                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-md transition">
                    <i class="fas fa-filter mr-2"></i>Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Total Messages</p>
            <p class="text-3xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Sent</p>
            <p class="text-3xl font-bold text-blue-600">{{ $stats['sent'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Received</p>
            <p class="text-3xl font-bold text-green-600">{{ $stats['received'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Delivered</p>
            <p class="text-3xl font-bold text-indigo-600">{{ $stats['delivered'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Read</p>
            <p class="text-3xl font-bold text-purple-600">{{ $stats['read'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <p class="text-sm text-gray-600 mb-1">Failed</p>
            <p class="text-3xl font-bold text-red-600">{{ $stats['failed'] }}</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Daily Activity Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Message Activity</h3>
            <canvas id="dailyChart" height="250"></canvas>
        </div>

        <!-- Hourly Distribution Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Hourly Distribution</h3>
            <canvas id="hourlyChart" height="250"></canvas>
        </div>
    </div>

    <!-- Message Type Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Message Type Distribution</h3>
            <canvas id="typeChart" height="250"></canvas>
        </div>

        <!-- Top Contacts -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Contacts</h3>
            <div class="space-y-3">
                @forelse($topContacts as $index => $contact)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                {{ $index + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $contact->phone_number }}</p>
                                <p class="text-xs text-gray-500">{{ $contact->message_count }} messages</p>
                            </div>
                        </div>
                        <a href="{{ route('whatsapp.conversation', $contact->phone_number) }}" class="text-green-600 hover:text-green-700">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-8">No data available</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Daily Chart
    const dailyCtx = document.getElementById('dailyChart');
    if (dailyCtx) {
        const dailyData = @json($dailyStats);
        const labels = Object.keys(dailyData);
        
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: labels.map(d => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Sent',
                    data: labels.map(d => dailyData[d].sent || 0),
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                }, {
                    label: 'Received',
                    data: labels.map(d => dailyData[d].received || 0),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Hourly Chart
    const hourlyCtx = document.getElementById('hourlyChart');
    if (hourlyCtx) {
        const hourlyData = @json($hourlyStats);
        
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: Array.from({length: 24}, (_, i) => i + ':00'),
                datasets: [{
                    label: 'Messages',
                    data: Object.values($hourlyStats),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Type Distribution Chart
    const typeCtx = document.getElementById('typeChart');
    if (typeCtx) {
        const typeData = @json($typeStats);
        
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(typeData),
                datasets: [{
                    data: Object.values(typeData),
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(251, 191, 36, 0.8)',
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    }
</script>
@endpush
@endsection

