<?php

namespace App\Services\Retrait;

use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\PosteRetrait;
use App\Models\RemiseDossard;
use App\Models\User;
use App\Support\Billet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retrait des dossards, depuis l'admin (niveau 2) ou un poste de bénévole. On trouve
 * l'inscription par son billet QR, son nom ou son dossard ; seule une inscription validée a un
 * dossard à remettre. Chaque remise ou annulation est gardée avec son auteur ; une remise est
 * atomique (deux postes qui scannent le même billet en même temps : une seule remise).
 *
 * Données montrées : nom, course, dossard, mineur ou non. Ni contact, ni santé.
 */
class Retraits
{
    public const MAX_RESULTATS = 15;

    /** @return Collection<int, Inscription> */
    public function rechercher(Evenement $evenement, string $terme): Collection
    {
        $terme = trim($terme);

        if (mb_strlen($terme) < 2 && ! ctype_digit($terme)) {
            return collect();
        }

        return $this->requete($evenement)
            ->where(function ($q) use ($terme) {
                ctype_digit($terme)
                    ? $q->where('dossard', (int) $terme)
                    : $q->whereHas('participant.user', fn ($u) => $u->whereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"])
                        ->orWhereRaw("concat(nom, ' ', prenom) like ?", ["%{$terme}%"]));
            })
            ->orderBy('dossard')
            ->limit(self::MAX_RESULTATS)
            ->get();
    }

    /**
     * Inscription d'un billet scanné, si elle est de cet événement.
     *
     * @return array{inscription: ?Inscription, erreur: ?string}
     */
    public function parBillet(Evenement $evenement, string $jeton): array
    {
        $inscription = Billet::lire($jeton);

        if (! $inscription || $inscription->id_evenement !== $evenement->id_evenement) {
            return ['inscription' => null, 'erreur' => 'Billet non reconnu pour cet événement. Cherche la personne par son nom.'];
        }

        return ['inscription' => $this->requete($evenement)->find($inscription->id_inscription), 'erreur' => null];
    }

    public function trouver(Evenement $evenement, int $id): ?Inscription
    {
        return $this->requete($evenement)->find($id);
    }

    /** @return array{ok: bool, message: string} */
    public function remettre(Inscription $inscription, string $par, ?PosteRetrait $poste = null, ?User $admin = null): array
    {
        if ($inscription->statut !== Inscription::VALIDEE || ! $inscription->dossard) {
            return ['ok' => false, 'message' => $inscription->statut === Inscription::DOSSIER_INCOMPLET
                ? 'Dossier incomplet : pas de dossard à remettre. Oriente la personne vers l\'accueil.'
                : 'Cette inscription n\'a pas de dossard à remettre.'];
        }

        $fait = DB::transaction(function () use ($inscription, $par, $poste, $admin) {
            $maj = Inscription::whereKey($inscription->id_inscription)
                ->where('statut', Inscription::VALIDEE)
                ->whereNull('date_retrait')
                ->update(['date_retrait' => today()]);

            if ($maj === 1) {
                RemiseDossard::create(['action' => RemiseDossard::REMISE, 'par' => $par, 'id_inscription' => $inscription->id_inscription, 'id_poste' => $poste?->id_poste, 'id_admin' => $admin?->id_user]);
            }

            return $maj === 1;
        });

        if (! $fait) {
            $derniere = $this->derniereRemise($inscription);

            return ['ok' => false, 'message' => "Dossard n° {$inscription->dossard} déjà remis".($derniere ? ' le '.$derniere->created_at->format('d/m à H:i').' par '.$derniere->par : '').'.'];
        }

        return ['ok' => true, 'message' => "Dossard n° {$inscription->dossard} remis à {$inscription->participant->user->name}."];
    }

    /** Erreur de remise (mauvaise personne) : le dossard redevient « à retirer ». */
    public function annuler(Inscription $inscription, string $par, ?PosteRetrait $poste = null, ?User $admin = null): array
    {
        $maj = DB::transaction(function () use ($inscription, $par, $poste, $admin) {
            $maj = Inscription::whereKey($inscription->id_inscription)->whereNotNull('date_retrait')->update(['date_retrait' => null]);

            if ($maj === 1) {
                RemiseDossard::create(['action' => RemiseDossard::ANNULATION, 'par' => $par, 'id_inscription' => $inscription->id_inscription, 'id_poste' => $poste?->id_poste, 'id_admin' => $admin?->id_user]);
            }

            return $maj;
        });

        return $maj === 1
            ? ['ok' => true, 'message' => "Remise du dossard n° {$inscription->dossard} annulée."]
            : ['ok' => false, 'message' => 'Ce dossard n\'était pas marqué comme remis.'];
    }

    /** @return array{retires: int, total: int} dossards remis sur le total des inscriptions validées */
    public function compteur(Evenement $evenement): array
    {
        $validees = Inscription::where('id_evenement', $evenement->id_evenement)->where('statut', Inscription::VALIDEE);

        return ['retires' => (clone $validees)->whereNotNull('date_retrait')->count(), 'total' => $validees->count()];
    }

    public function derniereRemise(Inscription $inscription): ?RemiseDossard
    {
        return RemiseDossard::where('id_inscription', $inscription->id_inscription)->where('action', RemiseDossard::REMISE)->latest('id_remise')->first();
    }

    private function requete(Evenement $evenement)
    {
        return Inscription::with('participant.user', 'tarif.course', 'evenement', 'notes')
            ->where('id_evenement', $evenement->id_evenement)
            ->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET]);
    }
}
