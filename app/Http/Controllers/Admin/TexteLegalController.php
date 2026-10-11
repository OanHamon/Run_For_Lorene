<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TexteLegalController as TextePublic;
use App\Models\Evenement;
use App\Models\TexteLegal;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Textes légaux versionnés (PDF) : publier une nouvelle version crée une ligne et un fichier,
 * sans toucher aux consentements déjà donnés (ils pointent sur la version acceptée).
 */
class TexteLegalController extends Controller
{
    /** Types publiés par l'admin (pas d'autorisation parentale : décision de l'association). */
    public const TYPES = TextePublic::LIBELLES;

    public function store(Request $request, Evenement $evenement, Journal $journal): RedirectResponse
    {
        $donnees = $request->validateWithBag('texte', [
            'type_texte' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'fichier' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $texte = DB::transaction(function () use ($evenement, $donnees, $request) {
            $version = (int) TexteLegal::where('id_evenement', $evenement->id_evenement)
                ->where('type_texte', $donnees['type_texte'])
                ->lockForUpdate()
                ->max('version') + 1;

            $chemin = TexteLegal::chemin($evenement, $donnees['type_texte'], $version);
            Storage::disk('local')->putFileAs(dirname($chemin), $request->file('fichier'), basename($chemin));

            return $evenement->texteLegals()->create([
                'type_texte' => $donnees['type_texte'],
                'version' => $version,
                'date_publication' => today(),
                'fichier' => $chemin,
            ]);
        });

        $journal->noter('texte.publication', $texte, ['type' => $texte->type_texte, 'version' => $texte->version]);

        return redirect()->route('admin.evenements.show', [$evenement, 'onglet' => 'textes'])
            ->with('statut', self::TYPES[$texte->type_texte]." : version {$texte->version} publiée. Les prochains consentements porteront sur elle.");
    }

    /** Téléchargement d'une version précise (disque privé, réservé aux admins). */
    public function show(TexteLegal $texte): Response
    {
        abort_unless(Storage::disk('local')->exists($texte->fichier), 404, 'Fichier introuvable sur le serveur.');

        return Storage::disk('local')->response($texte->fichier, (self::TYPES[$texte->type_texte] ?? $texte->type_texte)." v{$texte->version}.pdf", [
            'Content-Type' => 'application/pdf',
        ], 'inline');
    }
}
