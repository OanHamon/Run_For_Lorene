<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\TexteLegal;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Textes légaux publics (règlement, décharge…) : dernière version publiée pour l'événement en cours. */
class TexteLegalController extends Controller
{
    public const LIBELLES = [
        'reglement' => 'Règlement de l\'événement',
        'decharge' => 'Décharge de responsabilité',
        'rgpd' => 'Politique de protection des données',
        'droit_image' => 'Autorisation de droit à l\'image',
    ];

    public function __invoke(string $type): Response
    {
        abort_unless(isset(self::LIBELLES[$type]), 404);

        $texte = TexteLegal::where('id_evenement', Evenement::courant()?->id_evenement)
            ->where('type_texte', $type)
            ->orderByDesc('version')
            ->first();

        if ($texte && Storage::disk('local')->exists($texte->fichier)) {
            return Storage::disk('local')->response($texte->fichier, self::LIBELLES[$type]." v{$texte->version}.pdf", [
                'Content-Type' => 'application/pdf',
            ], 'inline');
        }

        return response()->view('site.texte-indisponible', ['libelle' => self::LIBELLES[$type]], 404);
    }
}
