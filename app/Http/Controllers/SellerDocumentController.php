<?php

namespace App\Http\Controllers;

use App\Models\SellerDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerDocumentController extends Controller
{
    /**
     * Download or stream the seller document for its owner.
     */
    public function show(Request $request, SellerDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->original_name);
    }
}
