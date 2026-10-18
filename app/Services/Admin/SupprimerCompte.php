<?php

namespace App\Services\Admin;

use App\Models\Commande;
use App\Models\Document;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Suppression d'un compte par un super admin. Seul un compte qui n'a jamais servi part : pas
 * de commande (même abandonnée), pas d'inscription réelle (brouillons exclus) pour lui ou ses
 * participants gérés, pas d'action au journal, pas d'accès admin. Sinon l'historique des
 * paiements, dossards et consentements serait perdu ; on garde le compte (on peut
 * réinitialiser son accès).
 *
 * Partent avec lui : ses participants gérés (enfants sans compte), leurs brouillons
 * d'inscription et les documents de ces brouillons.
 */
class SupprimerCompte
{
    /** @return list<string> raisons qui empêchent la suppression (vide : supprimable) */
    public function obstacles(User $user, User $auteur): array
    {
        $participants = $this->participants($user);

        return array_values(array_filter([
            $user->is($auteur) ? 'tu ne peux pas supprimer ton propre compte' : null,
            $user->admin ? 'ce compte est admin : retire d\'abord son accès admin' : null,
            Commande::where('id_user', $user->id_user)->exists() ? 'ce compte a passé des commandes (historique des paiements à garder)' : null,
            Inscription::whereIn('id_participant', $participants)->where('statut', '!=', Inscription::BROUILLON)->exists()
                ? 'ce compte, ou une personne qu\'il gère, a des inscriptions' : null,
            Document::withTrashed()->where('id_deposant', $user->id_user)
                ->whereNotIn('id_inscription', Inscription::whereIn('id_participant', $participants)->where('statut', Inscription::BROUILLON)->select('id_inscription'))
                ->exists() ? 'ce compte a déposé des documents sur d\'autres inscriptions' : null,
            JournalAdmin::where('id_admin', $user->id_user)->exists() ? 'ce compte a des actions au journal admin' : null,
        ]));
    }

    /** @return array{geres: int} nombre de participants gérés supprimés avec le compte */
    public function __invoke(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $geres = $user->participantsGeres()->pluck('id_user');

            // Brouillons d'inscription (leurs documents partent en cascade), puis les comptes des enfants, puis le compte.
            Inscription::whereIn('id_participant', $this->participants($user))->where('statut', Inscription::BROUILLON)->delete();
            Document::withTrashed()->where('id_deposant', $user->id_user)->forceDelete();
            User::whereIn('id_user', $geres)->delete();
            DB::table('sessions')->where('user_id', $user->id_user)->delete();
            $user->delete();

            return ['geres' => $geres->count()];
        });
    }

    /** Le compte et les participants qu'il gère. */
    private function participants(User $user)
    {
        return $user->participantsGeres()->pluck('id_user')->push($user->id_user);
    }
}
