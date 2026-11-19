<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListEntryRequest;
use App\Models\Application;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\OrganisationLists;
use App\Services\Lists\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(private readonly OrganisationLists $lists) {}

    public function index(Request $request, Organisation $organisation): Response
    {
        $list = (new ListQuery(['name'], ['name']))->run(
            Application::query()->where('organisation_id', $organisation->id)->withCount('costRecords')->orderBy('name_normalised'),
            $request->query(),
        );

        return Inertia::render('Admin/Applications', $list + [
            'organisation' => $organisation->only(['id', 'name', 'archived_at']),
            'organisations' => Organisation::query()->orderBy('name_normalised')->get(['id', 'name', 'archived_at']),
            'others' => Application::query()->where('organisation_id', $organisation->id)->orderBy('name_normalised')->get(['id', 'name']),
        ]);
    }

    public function store(ListEntryRequest $request, Organisation $organisation): RedirectResponse
    {
        $this->lists->create($organisation, Application::class, $request->safe()->all());

        return back()->with('status', __('lists.created'));
    }

    public function update(ListEntryRequest $request, Organisation $organisation, Application $application): RedirectResponse
    {
        $this->lists->update($organisation, $application, $request->safe()->all());

        return back()->with('status', __('lists.saved'));
    }

    public function destroy(Request $request, Organisation $organisation, Application $application): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $this->lists->delete($organisation, $application, $user, $request->string('replace_with')->toString() ?: null);

        return back()->with('status', __('lists.deleted'))->with('undo', $token);
    }
}
