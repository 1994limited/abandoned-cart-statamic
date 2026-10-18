<?php

namespace NineteenNinetyFour\AbandonedCart\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\AbandonedCart\AbandonedCart;
use Statamic\Console\RunsInPlease;

class Forget extends Command
{
    use RunsInPlease;

    protected $signature = 'abandoned-cart:forget {email : The email address to forget}';

    protected $description = 'Delete everything kept about an email address';

    public function handle(AbandonedCart $abandonedCart): int
    {
        $count = $abandonedCart->forget((string) $this->argument('email'));

        $this->components->info($count === 1 ? 'Deleted 1 basket.' : "Deleted {$count} baskets.");

        return self::SUCCESS;
    }
}
