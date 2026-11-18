<?php

namespace App\Support\Session;

use Illuminate\Session\DatabaseSessionHandler as FrameworkSessionHandler;

class DatabaseSessionHandler extends FrameworkSessionHandler
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function addRequestInformation(&$payload): static
    {
        return $this;
    }
}
