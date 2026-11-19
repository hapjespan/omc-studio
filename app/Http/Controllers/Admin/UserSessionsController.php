<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\UserAdministration;
use Illuminate\Http\RedirectResponse;

class UserSessionsController extends Controller
{
    public function __invoke(User $user, UserAdministration $users): RedirectResponse
    {
        $users->endSessions($user);

        return redirect('/admin/users')->with('status', __('users.sessions_ended'));
    }
}
