<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DisputeDecided
{
    use Dispatchable;

    public function __construct(
        public readonly string $disputeId,
        public readonly bool $accepted,
    ) {
    }
}
