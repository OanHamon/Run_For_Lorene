<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\NoteInterne;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Notes internes sur la fiche d'une inscription (niveau 2), visibles de tous les admins. */
class NoteInterneController extends Controller
{
    public function store(Request $request, Inscription $inscription, Journal $journal): RedirectResponse
    {
        $texte = $request->validateWithBag('note', ['texte' => ['required', 'string', 'max:2000']], ['texte.required' => 'Écris la note.'])['texte'];

        NoteInterne::create(['texte' => trim($texte), 'id_inscription' => $inscription->id_inscription, 'id_admin' => $request->user()->id_user]);
        $journal->noter('inscription.note', $inscription);

        return redirect()->to(route('admin.inscriptions.show', $inscription).'#notes')->with('statut', 'Note ajoutée.');
    }
}
