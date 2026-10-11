<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JournalAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Journal des actions admin sensibles, du plus récent au plus ancien. */
class JournalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $journal = JournalAdmin::with('auteur')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->orderByDesc('id_journal')
            ->paginate(50)
            ->withQueryString();

        return view('admin.journal', ['journal' => $journal]);
    }
}
