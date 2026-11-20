<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UndoRecord;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SignOutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        SecurityLog::record('sign_out');

        UndoRecord::query()->where('user_id', Auth::id())->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return redirect('/sign-in');
    }
}
