<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Inscription;
use App\Models\User;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Comptes, participants gérés, admins et niveaux d'accès (super admin uniquement). */
class UtilisateurController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function index(Request $request): View
    {
        $utilisateurs = User::with('admin')
            ->withCount('participantsGeres')
            ->whereNotNull('email')
            ->when($request->boolean('admins'), fn ($q) => $q->has('admin'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = trim((string) $request->query('q'));
                $q->where(fn ($r) => $r->where('email', 'like', "%{$terme}%")
                    ->orWhereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"])
                    ->orWhereHas('participantsGeres.user', fn ($u) => $u->whereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"])));
            })
            ->orderBy('nom')
            ->orderBy('prenom')
            ->paginate(50)
            ->withQueryString();

        return view('admin.utilisateurs.index', ['utilisateurs' => $utilisateurs]);
    }

    public function show(User $utilisateur): View
    {
        abort_if($utilisateur->email === null, 404, 'Participant géré sans compte : voir son responsable.');

        $utilisateur->load('admin', 'participant', 'participantsGeres.user');
        $ids = $utilisateur->participantsGeres->pluck('id_user')->push($utilisateur->id_user);

        return view('admin.utilisateurs.show', [
            'utilisateur' => $utilisateur,
            'inscriptions' => Inscription::with('participant.user', 'tarif.course', 'evenement')
                ->whereIn('id_participant', $ids)
                ->where('statut', '!=', Inscription::BROUILLON)
                ->orderByDesc('id_inscription')
                ->get(),
        ]);
    }

    /** Promouvoir en admin, changer de niveau ou retirer l'accès (niveau 0). */
    public function niveau(Request $request, User $utilisateur): RedirectResponse
    {
        $niveau = (int) $request->validate(['niveau_acces' => ['required', 'integer', 'in:0,1,2,3']])['niveau_acces'];

        if ($utilisateur->is($request->user())) {
            return back()->with('erreur', 'Tu ne peux pas modifier ton propre niveau : demande à un autre super admin.');
        }

        if (! $utilisateur->hasVerifiedEmail() && $niveau > 0) {
            return back()->with('erreur', "Ce compte n'a pas encore vérifié son email : il ne peut pas devenir admin.");
        }

        $avant = $utilisateur->niveauAdmin();

        if ($avant === Admin::SUPER && $niveau < Admin::SUPER && Admin::where('niveau_acces', Admin::SUPER)->count() <= 1) {
            return back()->with('erreur', 'Il doit rester au moins un super admin.');
        }

        if ($niveau === 0) {
            $utilisateur->admin()->delete();
            $this->journal->noter('admin.retrait', $utilisateur, ['avant' => $avant]);
        } else {
            Admin::updateOrCreate(['id_user' => $utilisateur->id_user], ['niveau_acces' => $niveau]);
            $this->journal->noter('admin.niveau', $utilisateur, ['avant' => $avant, 'après' => $niveau]);
        }

        return back()->with('statut', $niveau === 0 ? 'Accès admin retiré.' : 'Niveau d\'accès : '.Admin::LIBELLES[$niveau].'.');
    }

    /**
     * Réinitialise l'accès d'un compte : mot de passe effacé et sessions fermées. La personne
     * se reconnecte avec un code reçu par email (et peut redéfinir un mot de passe ensuite).
     */
    public function reinitialiser(Request $request, User $utilisateur): RedirectResponse
    {
        abort_if($utilisateur->is($request->user()), 403);

        DB::transaction(function () use ($utilisateur) {
            $utilisateur->forceFill(['mot_de_passe' => null, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $utilisateur->id_user)->delete();
        });

        $this->journal->noter('user.reinitialisation', $utilisateur);

        return back()->with('statut', "Accès réinitialisé : {$utilisateur->email} se reconnectera avec un code reçu par email.");
    }
}
