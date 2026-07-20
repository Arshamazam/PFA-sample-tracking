<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DisputeFiled
{
    use Dispatchable;

    public function __construct(public readonly string $disputeId)
    {
    }
}
