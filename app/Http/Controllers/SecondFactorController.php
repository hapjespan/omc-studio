<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Accounts\SecondFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecondFactorController extends Controller
{
    public function store(Request $request, SecondFactorService $factor): RedirectResponse
    {
        $factor->begin($this->user($request), $this->password($request));
        $request->session()->put(SecondFactorService::ENROLLING, true);

        return redirect('/account');
    }

    public function confirm(Request $request, SecondFactorService $factor): RedirectResponse
    {
        $validated = $request->validate(
            ['code' => ['required', 'string']],
            ['code.required' => __('second_factor.code_required')],
        );

        $codes = $factor->confirm($this->user($request), trim($validated['code']), $request->session()->getId());
        $request->session()->forget(SecondFactorService::ENROLLING);
        Inertia::flash('recoveryCodes', $codes);

        return redirect('/account')->with('status', __('second_factor.active'));
    }

    public function recoveryCodes(Request $request, SecondFactorService $factor): RedirectResponse
    {
        $codes = $factor->regenerate($this->user($request), $this->password($request), $request->session()->getId());
        Inertia::flash('recoveryCodes', $codes);

        return redirect('/account')->with('status', __('second_factor.codes_renewed'));
    }

    public function destroy(Request $request, SecondFactorService $factor): RedirectResponse
    {
        $factor->disable($this->user($request), $this->password($request), $request->session()->getId());

        return redirect('/account')->with('status', __('second_factor.inactive'));
    }

    private function password(Request $request): string
    {
        $validated = $request->validate(
            ['current_password' => ['required', 'string']],
            ['current_password.required' => __('account.current_password_required')],
        );

        return $validated['current_password'];
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
