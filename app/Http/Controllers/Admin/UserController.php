<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\Accounts\UserAdministration;
use App\Services\Accounts\UserDeletion;
use App\Services\Lists\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $password = $request->session()->get('one_time_password');
        $password = is_string($password) ? $password : null;

        if ($password !== null) {
            Inertia::encryptHistory();
            Inertia::clearHistory();
        }

        $list = (new ListQuery(['name', 'email'], ['name', 'email', 'role'], [], null))->run(
            User::query()->select(['id', 'name', 'email', 'role'])->selectRaw('CASE WHEN two_factor_confirmed_at IS NULL THEN 0 ELSE 1 END as second_factor')->orderBy('name'),
            $request->query(),
        );

        $self = $request->user()?->getKey();
        $list['rows'] = array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'isSelf' => $row['id'] === $self,
            'hasSecondFactor' => (bool) $row['second_factor'],
        ], $list['rows']);

        return Inertia::render('Admin/Users', [...$list, 'oneTimePassword' => $password]);
    }

    public function store(UserRequest $request, UserAdministration $users): RedirectResponse
    {
        [, $password] = $users->create($this->fields($request));

        return redirect('/admin/users')
            ->with('one_time_password', $password)
            ->with('status', __('users.created'));
    }

    public function update(UserRequest $request, User $user, UserAdministration $users): RedirectResponse
    {
        $users->update($user, $this->fields($request));

        return back()->with('status', __('users.updated'));
    }

    public function destroy(User $user, UserDeletion $deletion): RedirectResponse
    {
        $deletion->delete($user);

        return redirect('/admin/users')->with('status', __('users.deleted'));
    }

    /** @return array{name: string, email: string, role: string} */
    private function fields(UserRequest $request): array
    {
        $validated = $request->safe();

        return [
            'name' => (string) $validated->string('name'),
            'email' => (string) $validated->string('email'),
            'role' => (string) $validated->string('role'),
        ];
    }
}
