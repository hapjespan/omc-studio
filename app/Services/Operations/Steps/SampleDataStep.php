<?php

namespace App\Services\Operations\Steps;

use App\Services\Audit\SecurityLog;
use App\Services\Operations\SampleData;
use Throwable;

class SampleDataStep
{
    public function __construct(private readonly SampleData $data) {}

    public function __invoke(): void
    {
        try {
            $this->data->install();
        } catch (Throwable $failure) {
            SecurityLog::record('sample_data_failed', ['error' => $failure::class]);
        }
    }
}
