<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Date range
        $dateFrom = $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        
        $baseQuery = Message::where('user_id', $user->id)
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);
        
        // Overall stats
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'sent' => (clone $baseQuery)->where('direction', 'sent')->count(),
            'received' => (clone $baseQuery)->where('direction', 'received')->count(),
            'delivered' => (clone $baseQuery)->where('status', 'delivered')->count(),
            'read' => (clone $baseQuery)->where('status', 'read')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
        ];
        
        // Daily breakdown
        $dailyStats = [];
        $start = \Carbon\Carbon::parse($dateFrom);
        $end = \Carbon\Carbon::parse($dateTo);
        
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $dailyStats[$dateStr] = [
                'sent' => (clone $baseQuery)->where('direction', 'sent')->whereDate('created_at', $dateStr)->count(),
                'received' => (clone $baseQuery)->where('direction', 'received')->whereDate('created_at', $dateStr)->count(),
                'delivered' => (clone $baseQuery)->where('status', 'delivered')->whereDate('created_at', $dateStr)->count(),
                'read' => (clone $baseQuery)->where('status', 'read')->whereDate('created_at', $dateStr)->count(),
            ];
        }
        
        // Hourly distribution
        $hourlyStats = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyStats[$hour] = (clone $baseQuery)
                ->whereRaw('CAST(strftime("%H", created_at) AS INTEGER) = ?', [$hour])
                ->count();
        }
        
        // Top contacts
        $topContacts = (clone $baseQuery)
            ->selectRaw('phone_number, COUNT(*) as message_count')
            ->groupBy('phone_number')
            ->orderBy('message_count', 'desc')
            ->limit(10)
            ->get();
        
        // Message type distribution
        $typeStats = (clone $baseQuery)
            ->selectRaw('message_type, COUNT(*) as count')
            ->groupBy('message_type')
            ->get()
            ->pluck('count', 'message_type');
        
        return view('whatsapp.analytics', compact('stats', 'dailyStats', 'hourlyStats', 'topContacts', 'typeStats', 'dateFrom', 'dateTo', 'user'));
    }
}
