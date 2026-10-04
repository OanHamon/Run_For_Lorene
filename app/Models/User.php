<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $primaryKey = 'id_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'mot_de_passe',
        'nom',
        'prenom',
        'telephone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verifie_le' => 'datetime',
            'mot_de_passe' => 'hashed',
        ];
    }

    /**
     * Le mot de passe est stocké dans `mot_de_passe` (spec) et non `password` (défaut Laravel).
     */
    public function getAuthPassword(): ?string
    {
        return $this->mot_de_passe;
    }

    /**
     * La date de vérification est stockée dans `email_verifie_le` (spec) et non
     * `email_verified_at` (défaut du trait MustVerifyEmail).
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verifie_le);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['email_verifie_le' => $this->freshTimestamp()])->save();
    }

    /**
     * Nom complet, pour l'affichage (en-tête, menu) — prenom/nom restent la source de vérité.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->prenom} {$this->nom}"),
        );
    }

    public function participant(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Participant::class, 'id_user', 'id_user');
    }

    /** Participants gérés (enfants…) : sans email ni mot de passe, rattachés à ce compte. */
    public function participantsGeres(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Participant::class, 'id_responsable', 'id_user');
    }

    public function commandes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Commande::class, 'id_user', 'id_user');
    }

    /**
     * La vérification se fait par un code à 6 chiffres (et non par lien signé).
     */
    public function sendEmailVerificationNotification(): void
    {
        app(\App\Services\Auth\CodeEmail::class)->envoyer($this);
    }

    public function admin(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Admin::class, 'id_user', 'id_user');
    }

    public function benevole(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Benevole::class, 'id_user', 'id_user');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->prenom.' '.$this->nom)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
