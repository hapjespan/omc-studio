<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;

class ListLandingController extends Controller
{
    public function __invoke(string $kind): RedirectResponse
    {
        $first = Organisation::query()->orderBy('name_normalised')->first(['id']);

        return redirect($first === null ? '/admin/organisations' : "/admin/organisations/{$first->id}/{$kind}");
    }
}
