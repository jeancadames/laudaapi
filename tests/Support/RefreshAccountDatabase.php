<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait RefreshAccountDatabase
{
    use RefreshDatabase { refreshDatabase as private refreshFullDatabase; }

    public function refreshDatabase(): void
    {
        if (! IsolatedAccountDatabase::prepare()) {
            $this->refreshFullDatabase();
        }
    }
}
