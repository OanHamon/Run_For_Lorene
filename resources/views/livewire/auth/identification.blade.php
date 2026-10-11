<?php

use App\Models\User;
use App\Services\Auth\CodeEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

/*
 * Identification façon Google (écrans 03 et 04) : l'email détermine connexion ou création.
 *
 * - email      : saisie de l'adresse
 * - password   : compte existant → mot de passe, ou « recevoir un code » à la place
 * - creation   : nouveau compte → prénom, nom, mot de passe
 * - code       : code à 6 chiffres reçu par email ; vérifie l'adresse et connecte
 *
 * Un compte n'est connecté qu'une fois son adresse vérifiée. Après connexion, la page est
 * réellement rechargée vers $destination : Auth::login() régénère la session et donc le
 * jeton CSRF déjà chargé dans la page.
 *
 * Les propriétés publiques sont modifiables depuis le navigateur : chaque action relit le
 * compte à partir de l'email normalisé et ne se fie pas à $mode.
 */
new class extends Component {
    #[Locked]
    public string $destination = '';

    /**
     * Compte dont le mot de passe a été prouvé dans ce formulaire (connexion ou création).
     * Permet, au moment du code, de reconnaître le vrai titulaire d'un compte non vérifié.
     */
    #[Locked]
    public ?int $motDePasseProuvePour = null;

    public string $mode = 'email';

    public string $email = '';

    public string $password = '';

    public string $prenom = '';

    public string $nom = '';

    public string $code = '';

    public ?string $info = null;

    public function mount(?string $destination = null, ?string $mode = null): void
    {
        $this->destination = $destination ?: route('espace');

        // Utilisé par la page « vérifie ton email » pour un compte connecté mais non vérifié.
        if ($mode === 'code' && Auth::check()) {
            if (Auth::user()->hasVerifiedEmail()) {
                $this->redirect($this->destination);

                return;
            }

            $this->email = Auth::user()->email;
            $this->mode = 'code';

            if (! app(CodeEmail::class)->aUnCodeActif(Auth::user())) {
                $this->envoyerCode(Auth::user());
            }
        }
    }

    // ------------------------------------------------------------------

    public function continuerEmail(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate(['email' => 'required|email:rfc|max:254'], [], ['email' => 'adresse email']);

        if ($this->limiter('email:'.request()->ip(), 30)) {
            return;
        }

        $this->resetErrorBag();
        $this->info = null;
        $this->motDePasseProuvePour = null;

        $user = $this->utilisateur();

        if (! $user) {
            $this->mode = 'creation';

            return;
        }

        // Compte sans mot de passe (connexion par code uniquement) : inutile de le demander.
        if (! $user->mot_de_passe) {
            $this->recevoirCode();

            return;
        }

        $this->mode = 'password';
    }

    public function changerEmail(): void
    {
        $this->reset('password', 'code', 'info', 'motDePasseProuvePour');
        $this->resetErrorBag();
        $this->mode = 'email';
    }

    public function seConnecter()
    {
        $this->validate(['password' => 'required|string'], [], ['password' => 'mot de passe']);

        $user = $this->utilisateur();

        // Limites par compte (quelle que soit l'adresse IP) et par adresse IP (quel que soit le compte).
        $cleCompte = 'mdp:'.($user?->id_user ?? $this->email);
        $cleIp = 'mdp-ip:'.request()->ip();
        if ($this->limiter($cleCompte, 5, 'password', compter: false) || $this->limiter($cleIp, 20, 'password', compter: false)) {
            $this->password = '';

            return null;
        }

        if (! $user || ! $user->mot_de_passe || ! Hash::check($this->password, $user->mot_de_passe)) {
            RateLimiter::hit($cleCompte, 60);
            RateLimiter::hit($cleIp, 60);
            $this->password = '';
            $this->addError('password', 'Mot de passe incorrect. Tu peux aussi recevoir un code par email.');

            return null;
        }

        RateLimiter::clear($cleCompte);
        $this->password = '';
        $this->motDePasseProuvePour = $user->id_user;

        if (! $user->hasVerifiedEmail()) {
            $this->mode = 'code';
            $this->info = $this->envoyerCode($user)
                ? 'Il reste à confirmer ton adresse : on vient de t\'envoyer un code.'
                : null;

            return null;
        }

        return $this->connecter($user);
    }

    public function recevoirCode(): void
    {
        $user = $this->utilisateur();

        if (! $user) {
            $this->mode = 'creation';

            return;
        }

        $this->resetErrorBag();
        $this->password = '';
        $this->mode = 'code';

        if (! app(CodeEmail::class)->aUnCodeActif($user)) {
            $this->envoyerCode($user);
        }
    }

    public function creerCompte(): void
    {
        $this->email = Str::lower(trim($this->email));

        $valide = $this->validate([
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:254|unique:users,email',
            'password' => ['required', 'string', Password::min(8)],
        ], [
            'email.unique' => 'Un compte existe déjà avec cette adresse.',
        ], [
            'prenom' => 'prénom',
            'password' => 'mot de passe',
            'email' => 'adresse email',
        ]);

        if ($this->limiter('creation:'.request()->ip(), 10)) {
            return;
        }

        $user = User::create([
            'prenom' => trim($valide['prenom']),
            'nom' => trim($valide['nom']),
            'email' => $valide['email'],
            'mot_de_passe' => $valide['password'],
        ]);

        $this->password = '';
        $this->motDePasseProuvePour = $user->id_user;
        $this->envoyerCode($user);
        $this->info = null;
        $this->mode = 'code';
    }

    public function validerCode()
    {
        $this->code = preg_replace('/\D/', '', $this->code);
        $this->validate(['code' => 'required|digits:6'], [
            'code.required' => 'Saisis le code à 6 chiffres reçu par email.',
            'code.digits' => 'Le code contient 6 chiffres.',
        ]);

        // En plus des 5 essais par code : limite par adresse IP, tous comptes confondus.
        if ($this->limiter('code-ip:'.request()->ip(), 20, 'code')) {
            return null;
        }

        $user = $this->utilisateur();

        if (! $user) {
            $this->changerEmail();

            return null;
        }

        $resultat = app(CodeEmail::class)->verifier($user, $this->code);

        if ($resultat === 'invalide') {
            $this->addError('code', 'Ce code ne correspond pas. Vérifie-le et réessaie.');

            return null;
        }

        if ($resultat === 'expire') {
            $this->code = '';
            $this->addError('code', 'Ce code a expiré. Demande-en un nouveau.');

            return null;
        }

        if (! $user->hasVerifiedEmail()) {
            // Le compte a pu être créé par un tiers avec cette adresse : si son mot de passe
            // n'a pas été prouvé ici, on l'efface, sinon ce tiers pourrait se connecter au
            // compte une fois l'adresse vérifiée par son vrai titulaire.
            if ($this->motDePasseProuvePour !== $user->id_user) {
                $user->forceFill(['mot_de_passe' => null]);
                $user->setRememberToken(Str::random(60));
            }

            $user->markEmailAsVerified();
        }

        return $this->connecter($user, parCode: true);
    }

    public function renvoyerCode(): void
    {
        $user = $this->utilisateur();

        if (! $user) {
            return;
        }

        $this->resetErrorBag();
        $this->code = '';

        if ($this->envoyerCode($user)) {
            $this->info = 'Nouveau code envoyé.';
        }
    }

    // ------------------------------------------------------------------

    /** Le compte de l'email saisi, toujours relu à partir de l'adresse normalisée. */
    private function utilisateur(): ?User
    {
        $this->email = Str::lower(trim($this->email));

        return $this->email !== '' ? User::where('email', $this->email)->first() : null;
    }

    /** Vrai si un code vient de partir ; sinon, message d'attente posé sur le champ code. */
    private function envoyerCode(User $user): bool
    {
        $codes = app(CodeEmail::class);

        if ($codes->envoyer($user)) {
            return true;
        }

        $attente = $codes->secondesAvantRenvoi($user);
        $this->addError('code', $codes->aUnCodeActif($user)
            ? "Patiente encore {$attente} s avant de redemander un code."
            : 'Trop de codes demandés. Réessaie dans '.(int) ceil($attente / 60).' min.');

        return false;
    }

    private function connecter(User $user, bool $parCode = false)
    {
        Auth::login($user, remember: true);
        session()->regenerate();
        session()->forget('url.intended');

        // Le code prouve l'accès à la boîte mail : il vaut confirmation du mot de passe et
        // permet de redéfinir celui-ci dans les réglages (cas « mot de passe oublié »).
        if ($parCode) {
            session()->put('auth.password_confirmed_at', time());
        }

        return $this->redirect($this->destination);
    }

    /**
     * Vrai (et message d'erreur posé) si la limite de tentatives est atteinte.
     * Avec $compter, la tentative en cours est décomptée ; sinon l'appelant ne décompte que les échecs.
     */
    private function limiter(string $cle, int $max, string $champ = 'email', bool $compter = true): bool
    {
        if (! RateLimiter::tooManyAttempts($cle, $max)) {
            if ($compter) {
                RateLimiter::hit($cle, 60);
            }

            return false;
        }

        $this->addError($champ, 'Trop de tentatives. Réessaie dans '.RateLimiter::availableIn($cle).' secondes.');

        return true;
    }
}; ?>

<div x-data="{ voir: false }">
    @if ($mode === 'email')
        <form wire:submit="continuerEmail" novalidate>
            <h1 class="font-display text-2xl text-vert">Connecte-toi</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Avec ton adresse email. Pas encore de compte ? On le crée à l'étape suivante.</p>

            <label for="email" class="rfl-label">Adresse email</label>
            <input wire:model="email" id="email" type="email" inputmode="email" autocomplete="email" autofocus required placeholder="camille@exemple.fr" class="rfl-champ" aria-describedby="email-erreur">
            @error('email') <p id="email-erreur" class="rfl-erreur">{{ $message }}</p> @enderror

            <div class="mt-8 flex items-center justify-between">
                <a href="{{ route('home') }}" class="rfl-lien">Retour</a>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Continuer</button>
            </div>
        </form>
    @else
        <div class="rfl-pastille mb-6">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-vert-clair text-sm font-bold text-vert">{{ Str::upper(Str::substr($email, 0, 1)) }}</span>
            <span class="truncate text-sm text-vert-sombre">{{ $email }}</span>
            @unless (auth()->check())
                <button type="button" wire:click="changerEmail" class="ml-1 shrink-0 text-sm rfl-lien">Modifier</button>
            @endunless
        </div>
    @endif

    @if ($mode === 'password')
        <form wire:submit="seConnecter" novalidate>
            <h1 class="font-display text-2xl text-vert">Content de te revoir</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Saisis ton mot de passe pour continuer.</p>

            <label for="password" class="rfl-label">Mot de passe</label>
            <div class="relative">
                <input wire:model="password" id="password" :type="voir ? 'text' : 'password'" autocomplete="current-password" autofocus required class="rfl-champ pr-24" aria-describedby="password-erreur">
                <button type="button" x-on:click="voir = !voir" class="absolute inset-y-0 right-4 text-sm rfl-lien" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
            </div>
            @error('password') <p id="password-erreur" class="rfl-erreur">{{ $message }}</p> @enderror

            <button type="button" wire:click="recevoirCode" class="mt-4 text-sm rfl-lien">Mot de passe oublié ? Recevoir un code par email</button>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="changerEmail" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Se connecter</button>
            </div>
        </form>
    @endif

    @if ($mode === 'creation')
        <form wire:submit="creerCompte" novalidate>
            <h1 class="font-display text-2xl text-vert">Bienvenue !</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">Crée ton compte en quelques secondes. Il te servira à suivre ton inscription.</p>

            <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="prenom" class="rfl-label">Prénom</label>
                    <input wire:model="prenom" id="prenom" type="text" autocomplete="given-name" autofocus required placeholder="Camille" class="rfl-champ">
                    @error('prenom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="nom" class="rfl-label">Nom</label>
                    <input wire:model="nom" id="nom" type="text" autocomplete="family-name" required placeholder="Martin" class="rfl-champ">
                    @error('nom') <p class="rfl-erreur">{{ $message }}</p> @enderror
                </div>
            </div>

            <label for="nouveau-password" class="rfl-label">Mot de passe</label>
            <div class="relative">
                <input wire:model="password" id="nouveau-password" :type="voir ? 'text' : 'password'" autocomplete="new-password" required class="rfl-champ pr-24" aria-describedby="password-aide">
                <button type="button" x-on:click="voir = !voir" class="absolute inset-y-0 right-4 text-sm rfl-lien" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
            </div>
            <p id="password-aide" class="mt-2 text-xs text-gris-texte">8 caractères minimum.</p>
            @error('password') <p class="rfl-erreur">{{ $message }}</p> @enderror
            @error('email') <p class="rfl-erreur">{{ $message }}</p> @enderror

            <p class="mt-6 text-sm text-gris-texte">On t'envoie ensuite un code à 6 chiffres pour confirmer ton adresse.</p>

            <div class="mt-8 flex items-center justify-between">
                <button type="button" wire:click="changerEmail" class="rfl-lien">Retour</button>
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Créer mon compte</button>
            </div>
        </form>
    @endif

    @if ($mode === 'code')
        <form wire:submit="validerCode" novalidate>
            <h1 class="font-display text-2xl text-vert">Vérifie tes emails</h1>
            <p class="mt-1 mb-6 text-sm text-gris-texte">On t'a envoyé un code à 6 chiffres. Il est valable {{ \App\Services\Auth\CodeEmail::VALIDITE_MINUTES }} minutes. Pense à regarder dans les spams.</p>

            @if ($info)
                <p class="mb-4 rounded-2xl bg-vert-clair px-4 py-3 text-sm text-vert" role="status">{{ $info }}</p>
            @endif

            <label for="code" class="rfl-label">Code reçu par email</label>
            <input wire:model="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus required placeholder="123456" class="rfl-champ text-center font-display text-2xl tracking-[0.5em]" aria-describedby="code-erreur">
            @error('code') <p id="code-erreur" class="rfl-erreur">{{ $message }}</p> @enderror

            <button type="button" wire:click="renvoyerCode" class="mt-4 text-sm rfl-lien">Je n'ai rien reçu : renvoyer un code</button>

            <div class="mt-8 flex items-center justify-between">
                @if (auth()->check())
                    <button type="button" x-on:click="document.getElementById('form-deconnexion').submit()" class="rfl-lien">Se déconnecter</button>
                @else
                    <button type="button" wire:click="changerEmail" class="rfl-lien">Retour</button>
                @endif
                <button type="submit" class="rfl-btn" wire:loading.attr="disabled">Valider</button>
            </div>
        </form>

        @if (auth()->check())
            <form id="form-deconnexion" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        @endif
    @endif
</div>
