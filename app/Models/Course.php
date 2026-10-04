<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Course extends Model
{
    protected $table = 'courses';

    protected $primaryKey = 'id_course';

    protected $fillable = [
        'nom',
        'distance',
        'heure_depart',
        'age_min',
        'age_max',
        'quota',
        'questionnaire_requis',
        'dossard_debut',
        'dossard_fin',
        'id_evenement',
    ];

    protected function casts(): array
    {
        return [
            'questionnaire_requis' => 'boolean',
        ];
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(Tarif::class, 'id_course', 'id_course');
    }

    public function accepteAge(int $age): bool
    {
        return ($this->age_min === null || $age >= $this->age_min)
            && ($this->age_max === null || $age <= $this->age_max);
    }

    /**
     * Tarif applicable (spec) : période de validité contenant la date du jour et tranche d'âge
     * correspondante si elle est renseignée. À plusieurs candidats, le moins cher l'emporte.
     */
    public function tarifPour(int $age, ?Carbon $date = null): ?Tarif
    {
        $date ??= today();

        return $this->tarifs()
            ->whereDate('valide_du', '<=', $date)
            ->whereDate('valide_au', '>=', $date)
            ->where(fn ($q) => $q->whereNull('age_min')->orWhere('age_min', '<=', $age))
            ->where(fn ($q) => $q->whereNull('age_max')->orWhere('age_max', '>=', $age))
            ->orderBy('montant')
            ->first();
    }

    /**
     * Places occupées : inscriptions payées (validées ou dossier incomplet) et inscriptions
     * dont une tentative de paiement a démarré il y a moins de 30 minutes (place réservée
     * pendant le paiement, spec). Une tentative plus ancienne ne bloque plus la place.
     */
    public function placesPrises(?int $saufCommande = null): int
    {
        return Inscription::whereIn('id_tarif', $this->tarifs()->select('id_tarif'))
            ->when($saufCommande, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('id_commande')->orWhere('id_commande', '!=', $saufCommande)))
            ->where(function ($q) {
                $q->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])
                    ->orWhere(fn ($q2) => $q2->where('statut', Inscription::EN_ATTENTE_PAIEMENT)
                        ->whereHas('commande.paiements', fn ($p) => $p->where('statut', 'en_attente')
                            ->where('created_at', '>', now()->subMinutes(config('paiement.reservation_minutes')))));
            })
            ->count();
    }

    /** Null = illimité. $saufCommande : ne pas compter les places déjà tenues par cette commande. */
    public function placesRestantes(?int $saufCommande = null): ?int
    {
        if ($this->quota === null) {
            return null;
        }

        return max($this->quota - $this->placesPrises($saufCommande), 0);
    }

    public function estComplete(): bool
    {
        $restantes = $this->placesRestantes();

        return $restantes !== null && $restantes <= 0;
    }
}
