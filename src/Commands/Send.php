<?php

namespace NineteenNinetyFour\AbandonedCart\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\AbandonedCart\Sender;
use Statamic\Console\RunsInPlease;

class Send extends Command
{
    use RunsInPlease;

    protected $signature = 'abandoned-cart:send';

    protected $description = 'Email the abandoned cart reminders that are due';

    public function handle(Sender $sender): int
    {
        $sent = $sender->sendDue();

        $this->components->info($sent === 1 ? 'Sent 1 reminder.' : "Sent {$sent} reminders.");

        return self::SUCCESS;
    }
}
