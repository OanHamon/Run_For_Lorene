<?php

use App\Notifications\MotDePasseModifie;
use App\Support\Telephone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/*
 * « Mon compte » : nom, téléphone et mot de passe du titulaire. Le mot de passe est
 * facultatif : on peut toujours se connecter avec un code reçu par email. L'email, qui sert
 * d'identifiant, ne se change pas ici.
 */
new #[Layout('components.espace-shell', ['title' => 'Mon compte'])] class extends Component {
    public string $prenom = '';

    public string $nom = '';

    public string $telephone = '';

    public string $actuel = '';

    public string $nouveau = '';

    public string $nouveau_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->prenom = $user->prenom;
        $this->nom = $user->nom;
        $this->telephone = Telephone::lisible($user->telephone);
    }

    public function enregistrerInfos(): void
    {
        $this->telephone = Telephone::normaliser($this->telephone);

        $this->validate([
            'prenom' => 'required|string|max:100',
            'nom' => 'required|string|max:100',
            'telephone' => Telephone::REGLE,
        ], ['telephone.regex' => Telephone::MESSAGE], ['prenom' => 'prénom']);

        Auth::user()->forceFill([
            'prenom' => trim($this->prenom),
            'nom' => trim($this->nom),
            'telephone' => $this->telephone ?: null,
        ])->save();

        $this->telephone = Telephone::lisible($this->telephone);
        session()->flash('statut', 'Informations enregistrées.');
    }

    public function enregistrerMotDePasse(): void
    {
        $user = Auth::user();

        $this->validate([
            // Pas de mot de passe actuel à saisir si le compte n'en a pas, ou juste après une connexion par code.
            'actuel' => $user->doitConfirmerMotDePasse() ? ['required', 'string', 'current_password'] : ['nullable'],
            'nouveau' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'actuel.required' => 'Indique ton mot de passe actuel.',
            'actuel.current_password' => 'Ce n\'est pas ton mot de passe actuel. Tu l\'as oublié ? Déconnecte-toi, puis reçois un code par email.',
            'nouveau.required' => 'Choisis un mot de passe.',
            'nouveau.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'nouveau.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ]);

        $avait = (bool) $user->mot_de_passe;

        // Nouveau mot de passe : les autres sessions et les cookies « rester connecté » des
        // autres appareils sont révoqués ; cet appareil reste connecté.
        $resteConnecte = request()->hasCookie(Auth::guard()->getRecallerName());
        $user->forceFill([
            'mot_de_passe' => Hash::make($this->nouveau),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id_user)
            ->where('id', '!=', session()->getId())
            ->delete();

        Auth::login($user, remember: $resteConnecte);
        session()->put('auth.password_confirmed_at', time());
        $user->notify(new MotDePasseModifie);

        $this->reset('actuel', 'nouveau', 'nouveau_confirmation');
        session()->flash('statut', $avait ? 'Mot de passe changé.' : 'Mot de passe enregistré. Tu peux aussi continuer à te connecter avec un code.');
    }

    public function with(): array
    {
        return ['user' => Auth::user()];
    }
}; ?>

<div class="max-w-2xl">
    <h1 class="font-display text-3xl text-vert">Mon compte</h1>
    <p class="mt-1 text-sm text-gris-texte">Les informations de chaque participant (date de naissance, contact d'urgence…) se modifient sur sa fiche d'inscription.</p>

    @if (session('statut'))
        <p class="mt-6 rfl-alerte-info text-sm text-vert" role="status">{{ session('statut') }}</p>
    @endif

    <form wire:submit="enregistrerInfos" class="mt-6 rfl-carte space-y-5 p-6">
        <h2 class="font-display text-vert">Mes informations</h2>
        <div>
            <p class="rfl-label">Adresse email</p>
            <p class="text-vert-sombre">{{ $user->email }}</p>
            <p class="mt-1 text-xs text-gris-texte">C'est ton identifiant de connexion. Pour la changer, contacte l'association.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="prenom" class="rfl-label">Prénom</label>
                <input id="prenom" wire:model="prenom" type="text" autocomplete="given-name" class="rfl-champ">
                @error('prenom')<p class="rfl-erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nom" class="rfl-label">Nom</label>
                <input id="nom" wire:model="nom" type="text" autocomplete="family-name" class="rfl-champ">
                @error('nom')<p class="rfl-erreur">{{ $message }}</p>@enderror
            </div>
        </div>
        <div>
            <label for="telephone" class="rfl-label">Téléphone <span class="font-normal text-gris-texte">(facultatif)</span></label>
            <input id="telephone" wire:model="telephone" type="tel" autocomplete="tel" placeholder="06 12 34 56 78" class="rfl-champ">
            @error('telephone')<p class="rfl-erreur">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="rfl-btn">Enregistrer</button>
    </form>

    <form wire:submit="enregistrerMotDePasse" class="mt-6 rfl-carte space-y-5 p-6">
        <div>
            <h2 class="font-display text-vert">{{ $user->mot_de_passe ? 'Changer mon mot de passe' : 'Choisir un mot de passe (facultatif)' }}</h2>
            <p class="mt-1 text-sm text-gris-texte">
                @if ($user->mot_de_passe)
                    Tu peux aussi te connecter à tout moment avec un code reçu par email, sans mot de passe.
                @else
                    Tu te connectes aujourd'hui avec un code reçu par email : c'est suffisant. Si tu préfères un mot de passe, choisis-le ici.
                @endif
            </p>
        </div>
        @if ($user->doitConfirmerMotDePasse())
            <div>
                <label for="actuel" class="rfl-label">Mot de passe actuel</label>
                <input id="actuel" wire:model="actuel" type="password" autocomplete="current-password" class="rfl-champ">
                @error('actuel')<p class="rfl-erreur">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nouveau" class="rfl-label">Nouveau mot de passe</label>
                <input id="nouveau" wire:model="nouveau" type="password" autocomplete="new-password" class="rfl-champ" aria-describedby="nouveau-aide">
                <p id="nouveau-aide" class="mt-1 text-xs text-gris-texte">8 caractères au moins.</p>
                @error('nouveau')<p class="rfl-erreur">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="nouveau_confirmation" class="rfl-label">Confirme-le</label>
                <input id="nouveau_confirmation" wire:model="nouveau_confirmation" type="password" autocomplete="new-password" class="rfl-champ">
            </div>
        </div>
        <button type="submit" class="rfl-btn">{{ $user->mot_de_passe ? 'Changer le mot de passe' : 'Enregistrer le mot de passe' }}</button>
    </form>
</div>
