<?php

namespace App\Http\Controllers;

use App\Services\Exchange\SampleFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SampleFileController extends Controller
{
    public function show(string $group, SampleFile $sample): StreamedResponse
    {
        abort_unless(in_array($group, SampleFile::GROUPS, true), 404);

        return response()->streamDownload(function () use ($sample, $group) {
            $handle = fopen('php://output', 'wb');
            $sample->write($group, $handle);
            fclose($handle);
        }, 'sample-'.str_replace('_', '-', $group).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
