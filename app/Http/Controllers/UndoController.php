<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Undo\UndoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UndoController extends Controller
{
    public function __invoke(Request $request, UndoStore $store, string $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $store->restore($token, $user);

        return back();
    }
}
