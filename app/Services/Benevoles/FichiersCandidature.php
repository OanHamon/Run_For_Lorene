<?php

namespace App\Services\Benevoles;

use App\Models\CandidatureBenevole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fichiers privés d'une candidature bénévole : portrait du badge et autorisation parentale
 * signée. Rangés sur le disque privé, servis seulement au bénévole et aux admins habilités.
 * Un nouveau dépôt remplace l'ancien.
 */
class FichiersCandidature
{
    public const PHOTO_REGLES = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    public const AUTORISATION_REGLES = ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

    /** Colonnes du chemin et de la date, par type de fichier. */
    private const COLONNES = [
        'photo' => ['photo', 'photo_le'],
        'autorisation' => ['autorisation_parentale', 'autorisation_le'],
    ];

    public function deposer(CandidatureBenevole $candidature, string $type, UploadedFile $fichier): void
    {
        [$colonne, $date] = self::COLONNES[$type];
        $ancien = $candidature->{$colonne};

        $extension = strtolower($fichier->guessExtension() ?: $fichier->getClientOriginalExtension());
        $nom = $type.'-'.now()->format('YmdHis').'.'.$extension;
        $chemin = Storage::disk('local')->putFileAs($candidature->dossier(), $fichier, $nom);

        $candidature->forceFill([$colonne => $chemin, $date => now()])->save();

        if ($ancien && $ancien !== $chemin) {
            Storage::disk('local')->delete($ancien);
        }
    }

    public function reponse(CandidatureBenevole $candidature, string $type, bool $telecharger = false): Response
    {
        $chemin = $candidature->{self::COLONNES[$type][0]};
        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404);

        $user = $candidature->user;
        $nom = ($type === 'photo' ? 'Photo' : 'Autorisation parentale')." {$user->prenom} {$user->nom}.".pathinfo($chemin, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($chemin, $nom, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $telecharger ? 'attachment' : 'inline');
    }
}
