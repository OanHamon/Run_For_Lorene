<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\CompteCree;
use App\Services\Admin\Journal;
use App\Support\Activation;
use App\Support\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /**
     * Création d'un compte par l'association (niveau 2) : un compte comme ceux du site, que la
     * personne active en choisissant son mot de passe depuis le lien reçu par email.
     */
    public function create(Request $request): View
    {
        return view('admin.utilisateurs.creer', [
            'email' => Str::lower(trim((string) $request->query('email'))),
            'retour' => $request->query('retour') === 'inscription' ? 'inscription' : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'telephone' => Telephone::normaliser((string) $request->input('telephone')),
        ]);

        $donnees = $request->validate([
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:filter', 'max:254', Rule::unique('users', 'email')],
            'telephone' => Telephone::REGLE,
            'retour' => ['nullable', 'in:inscription'],
        ], [
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
            'telephone.regex' => Telephone::MESSAGE,
        ]);

        $user = User::create([
            'prenom' => trim($donnees['prenom']),
            'nom' => trim($donnees['nom']),
            'email' => $donnees['email'],
            'telephone' => $donnees['telephone'] ?: null,
        ]);
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        $user->notify(new CompteCree);
        $this->journal->noter('user.creation', $user, ['email' => $user->email]);

        $message = "Compte créé. {$user->email} a reçu un lien pour choisir son mot de passe (valable ".Activation::VALIDITE_JOURS.' jours).';

        return match (true) {
            ($donnees['retour'] ?? null) === 'inscription' => redirect()->route('admin.inscriptions.create', ['email' => $user->email])->with('statut', $message),
            $request->user()->peutAdmin(3) => redirect()->route('admin.utilisateurs.show', $user)->with('statut', $message),
            default => redirect()->route('admin.inscriptions.index')->with('statut', $message),
        };
    }

    /** Renvoie le lien « Créer mon mot de passe » d'un compte pas encore activé (un envoi par 10 minutes). */
    public function renvoyerActivation(User $utilisateur): RedirectResponse
    {
        if ($utilisateur->mot_de_passe !== null || $utilisateur->email === null) {
            return back()->with('erreur', 'Ce compte a déjà son mot de passe.');
        }

        $cle = "activation:{$utilisateur->id_user}";
        if (RateLimiter::tooManyAttempts($cle, 1)) {
            return back()->with('erreur', 'Un lien vient déjà de partir. Réessaie dans quelques minutes.');
        }
        RateLimiter::hit($cle, 600);

        $utilisateur->notify(new CompteCree);
        $this->journal->noter('user.activation', $utilisateur, ['email' => $utilisateur->email]);

        return back()->with('statut', "Nouveau lien envoyé à {$utilisateur->email}.");
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
     * Corrige l'email d'un compte qui ne s'est jamais connecté (faute de frappe à l'inscription
     * manuelle). Un compte déjà utilisé garde son email : c'est son identifiant de connexion.
     */
    public function email(Request $request, User $utilisateur): RedirectResponse
    {
        if ($utilisateur->hasVerifiedEmail()) {
            return back()->with('erreur', 'Ce compte s\'est déjà connecté : son email ne se change pas ici.');
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $donnees = $request->validateWithBag('email', [
            'email' => ['required', 'email:filter', 'max:254', Rule::unique('users', 'email')->ignore($utilisateur->id_user, 'id_user')],
        ], ['email.unique' => 'Un autre compte utilise déjà cette adresse.']);

        $avant = $utilisateur->email;
        $utilisateur->forceFill(['email' => $donnees['email']])->save();
        $this->journal->noter('user.email', $utilisateur, ['avant' => $avant, 'après' => $donnees['email']]);

        // Le lien parti vers la mauvaise adresse est désormais caduc (la clé dépend de l'email) : on en renvoie un.
        if ($utilisateur->mot_de_passe === null) {
            $utilisateur->notify(new CompteCree);

            return back()->with('statut', "Email corrigé : {$donnees['email']}. Un nouveau lien pour créer le mot de passe vient d'y partir.");
        }

        return back()->with('statut', "Email corrigé : {$donnees['email']}.");
    }

    /**
     * Réinitialise l'accès d'un compte : mot de passe effacé, sessions fermées, anciens liens
     * caducs. La personne reçoit un lien pour choisir un nouveau mot de passe.
     */
    public function reinitialiser(Request $request, User $utilisateur): RedirectResponse
    {
        abort_if($utilisateur->is($request->user()), 403);

        DB::transaction(function () use ($utilisateur) {
            $utilisateur->forceFill(['mot_de_passe' => null, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $utilisateur->id_user)->delete();
        });

        $this->journal->noter('user.reinitialisation', $utilisateur);
        $utilisateur->notify(new CompteCree(reinitialisation: true));

        return back()->with('statut', "Accès réinitialisé : {$utilisateur->email} a reçu un lien pour choisir un nouveau mot de passe.");
    }
}
