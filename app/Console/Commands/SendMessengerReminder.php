<?php

namespace App\Console\Commands;

use App\Models\MessengerStaff;
use App\Services\MessengerService;
use Illuminate\Console\Command;

class SendMessengerReminder extends Command
{
    protected $signature   = 'messenger:send-reminder';
    protected $description = 'Send a daily deposit slip reminder to all registered Messenger staff';

    public function handle(MessengerService $messenger): void
    {
        $staff = MessengerStaff::all();

        if ($staff->isEmpty()) {
            $this->info('No staff found.');
            return;
        }

        $message = "Good morning! 👋 Don't forget to send your deposit slip today. Have a great day!";

        foreach ($staff as $member) {
            $messenger->sendText($member->fb_sender_id, $message);
            $this->line("Sent to {$member->fb_name} ({$member->fb_sender_id})");
        }

        $this->info("Reminder sent to {$staff->count()} staff.");
    }
}
