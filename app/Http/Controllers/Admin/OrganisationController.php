<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganisationRequest;
use App\Models\Organisation;
use App\Services\Canvas\LogoStore;
use App\Services\Canvas\OrganisationService;
use App\Services\Lists\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function __construct(private readonly OrganisationService $organisations, private readonly LogoStore $logos) {}

    public function index(Request $request): Response
    {
        $list = (new ListQuery(['name'], ['name'], [], 'archived_at'))->run(
            Organisation::query()->select(['id', 'name', 'slug', 'archived_at'])->selectRaw('logo_path IS NOT NULL as has_logo')->orderBy('name_normalised'),
            $request->query(),
        );

        return Inertia::render('Admin/Organisations', $list);
    }

    public function store(OrganisationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $organisation = $this->organisations->create($request->string('name')->toString());
            $this->storeLogo($request, $organisation);
        });

        return back();
    }

    public function update(OrganisationRequest $request, Organisation $organisation): RedirectResponse
    {
        DB::transaction(function () use ($request, $organisation) {
            $this->organisations->rename($organisation, $request->string('name')->toString());
            $this->storeLogo($request, $organisation);
        });

        return back();
    }

    public function destroy(Organisation $organisation): RedirectResponse
    {
        $this->organisations->delete($organisation);

        return back();
    }

    public function archive(Organisation $organisation): RedirectResponse
    {
        $this->organisations->archive($organisation);

        return back();
    }

    public function restore(Organisation $organisation): RedirectResponse
    {
        $this->organisations->restore($organisation);

        return back();
    }

    private function storeLogo(OrganisationRequest $request, Organisation $organisation): void
    {
        if ($request->hasFile('logo')) {
            $this->logos->store($organisation, $request->file('logo'));
        }
    }
}
