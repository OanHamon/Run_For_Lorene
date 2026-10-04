<?php

namespace App\Services\Inscriptions;

use App\Models\Consentement;
use App\Models\Document;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\TexteLegal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consentements d'une inscription : chacun est un document horodaté avec l'IP et la version
 * du texte accepté. Une nouvelle réponse ajoute une ligne, l'historique n'est jamais modifié.
 */
class Consentements
{
    /** @return Collection<string, TexteLegal> dernière version publiée de chaque texte, par type */
    public function textes(Evenement $evenement): Collection
    {
        return TexteLegal::where('id_evenement', $evenement->id_evenement)
            ->orderByDesc('version')
            ->get()
            ->unique('type_texte')
            ->keyBy('type_texte');
    }

    /** Enregistre les consentements obligatoires manquants et la réponse au droit à l'image. */
    public function enregistrer(Inscription $inscription, bool $droitImage, int $deposant, ?string $ip): void
    {
        $textes = $this->textes($inscription->evenement);

        DB::transaction(function () use ($inscription, $textes, $droitImage, $deposant, $ip) {
            $dejaAcceptes = Consentement::whereHas('document', fn ($q) => $q->where('id_inscription', $inscription->id_inscription))
                ->where('accepte', true)
                ->pluck('id_texte')
                ->all();

            foreach (TexteLegal::OBLIGATOIRES as $type) {
                if ($textes->has($type) && ! in_array($textes[$type]->id_texte, $dejaAcceptes, true)) {
                    $this->ajouter($inscription, $textes[$type], true, $deposant, $ip);
                }
            }

            $this->droitImage($inscription, $droitImage, $deposant, $ip);
        });
    }

    /** Droit à l'image : facultatif, mais le oui comme le non est enregistré (spec). */
    public function droitImage(Inscription $inscription, bool $accepte, int $deposant, ?string $ip): void
    {
        $texte = $this->textes($inscription->evenement)->get('droit_image');

        if ($texte && $this->dernierDroitImage($inscription) !== $accepte) {
            $this->ajouter($inscription, $texte, $accepte, $deposant, $ip);
        }
    }

    public function dernierDroitImage(Inscription $inscription): ?bool
    {
        $dernier = $this->historique($inscription)->firstWhere('type_texte', 'droit_image');

        return $dernier === null ? null : (bool) $dernier->accepte;
    }

    /**
     * @return Collection<int, object{type_texte: string, version: int, accepte: bool, date_depot: string, ip: string}>
     *         du plus récent au plus ancien
     */
    public function historique(Inscription $inscription): Collection
    {
        return Consentement::query()
            ->join('documents', 'documents.id_document', '=', 'consentements.id_document')
            ->join('texte_legals', 'texte_legals.id_texte', '=', 'consentements.id_texte')
            ->where('documents.id_inscription', $inscription->id_inscription)
            ->whereNull('documents.deleted_at')
            ->orderByDesc('documents.id_document')
            ->get(['texte_legals.type_texte', 'texte_legals.version', 'consentements.accepte', 'documents.date_depot', 'consentements.ip']);
    }

    private function ajouter(Inscription $inscription, TexteLegal $texte, bool $accepte, int $deposant, ?string $ip): void
    {
        $document = Document::create([
            'date_depot' => now(),
            'statut' => 'valide',
            'id_deposant' => $deposant,
            'id_inscription' => $inscription->id_inscription,
        ]);

        Consentement::create([
            'id_document' => $document->id_document,
            'accepte' => $accepte,
            'ip' => $ip ?? '0.0.0.0',
            'id_texte' => $texte->id_texte,
        ]);
    }
}
