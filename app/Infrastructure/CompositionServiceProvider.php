<?php

namespace App\Infrastructure;

use Illuminate\Support\ServiceProvider;

final class CompositionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Future application ports will be bound to their adapters here.
    }
}
