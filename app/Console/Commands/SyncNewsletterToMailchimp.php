<?php

namespace App\Console\Commands;

use App\Services\Mailchimp\NewsletterSyncService;
use Illuminate\Console\Command;

class SyncNewsletterToMailchimp extends Command
{
    protected $signature = 'newsletter:sync-mailchimp {--limit=25}';

    protected $description = 'Sync pending newsletter signups that have consent to Mailchimp.';

    public function handle(NewsletterSyncService $sync): int
    {
        $result = $sync->retryPending(max(1, min(25, (int) $this->option('limit'))));
        // Counts only: credentials and contact details never belong in console logs.
        $this->line(json_encode($result));

        return 0;
    }
}
