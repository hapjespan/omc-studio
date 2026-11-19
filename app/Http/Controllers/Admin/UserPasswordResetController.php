<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\UserAdministration;
use Illuminate\Http\RedirectResponse;

class UserPasswordResetController extends Controller
{
    public function __invoke(User $user, UserAdministration $users): RedirectResponse
    {
        return redirect('/admin/users')->with('one_time_password', $users->resetPassword($user));
    }
}
