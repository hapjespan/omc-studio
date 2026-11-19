<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Services\Canvas\LogoStore;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class OrganisationLogoController extends Controller
{
    public function __invoke(Organisation $organisation): Response
    {
        $path = (string) $organisation->logo_path;
        $disk = Storage::disk(LogoStore::DISK);

        abort_if($path === '' || ! $disk->exists($path), 404);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => LogoStore::contentType($path),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
