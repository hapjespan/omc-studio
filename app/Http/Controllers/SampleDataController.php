<?php

namespace App\Http\Controllers;

use App\Services\Operations\SampleData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SampleDataController extends Controller
{
    public function show(SampleData $data): Response
    {
        $sample = $data->sample();

        return Inertia::render('Admin/SampleData', ['present' => $sample !== null, 'name' => $sample?->name]);
    }

    public function remove(Request $request, SampleData $data): RedirectResponse
    {
        $data->remove($request->user());

        return redirect('/admin/sample-data')->with('status', __('sample.removed'));
    }
}
