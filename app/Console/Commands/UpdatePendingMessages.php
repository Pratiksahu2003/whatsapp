<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePendingMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:update-pending {--hours=2 : Hours old messages to check} {--auto-update : Auto-update old messages to delivered}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update pending WhatsApp messages that haven\'t received delivery status updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $autoUpdate = $this->option('auto-update');

        $this->info("Checking for messages older than {$hours} hours without delivery confirmation...");

        // Get all users with pending messages
        $pendingMessages = Message::where('direction', 'sent')
            ->where('status', 'sent')
            ->whereNull('delivered_at')
            ->whereNull('failed_at')
            ->where('created_at', '<=', now()->subHours($hours))
            ->with('user')
            ->get();

        $count = $pendingMessages->count();
        $this->info("Found {$count} pending messages");

        if ($count === 0) {
            $this->info('No pending messages found.');
            return 0;
        }

        // Group by user
        $byUser = $pendingMessages->groupBy('user_id');

        foreach ($byUser as $userId => $messages) {
            $user = $messages->first()->user;
            
            if (!$user || !$user->hasWhatsAppCredentials()) {
                $this->warn("Skipping user {$userId} - no WhatsApp credentials");
                continue;
            }

            $service = new WhatsAppService($user);
            $result = $service->syncPendingMessages($userId, $hours * 2, $autoUpdate);

            if ($result['success']) {
                $this->info("User {$userId}: Found {$result['pending_count']} pending messages");
                if ($autoUpdate && isset($result['auto_updated'])) {
                    $this->info("User {$userId}: Auto-updated {$result['auto_updated']} messages");
                }
            } else {
                $this->error("User {$userId}: Error - " . ($result['error'] ?? 'Unknown'));
            }
        }

        if ($autoUpdate) {
            $this->info('✅ Completed auto-update of pending messages');
        } else {
            $this->warn('⚠️  Run with --auto-update flag to automatically update old messages to delivered status');
            $this->info('💡 Note: Ensure webhook is configured at: ' . url('/whatsapp/webhook'));
        }

        return 0;
    }
}

