<?php

namespace App\Services\Operations\Steps;

use App\Services\Operations\SampleData;

class SampleDataStep
{
    public function __construct(private readonly SampleData $data) {}

    public function __invoke(): void
    {
        $this->data->install();
    }
}
