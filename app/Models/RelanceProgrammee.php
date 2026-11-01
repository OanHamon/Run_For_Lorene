<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Relance programmée (Services\Admin\EnvoisProgrammes) :
 * - « date » : une fois, à une date et une heure ;
 * - « avant » : une fois, N jours avant l'événement, à l'heure choisie ;
 * - « repetee » : tous les N jours, du … au …, à l'heure choisie.
 * Les destinataires sont recalculés à chaque envoi : une personne qui a complété son dossier
 * entre-temps ne reçoit plus le rappel.
 */
class RelanceProgrammee extends Model
{
    protected $table = 'relances_programmees';

    protected $primaryKey = 'id_programmee';

    public const QUAND = ['date' => 'À une date précise', 'avant' => 'Avant l\'événement', 'repetee' => 'Régulièrement'];

    protected $fillable = ['modele', 'sujet', 'message', 'cible', 'quand', 'date_envoi', 'jours_avant', 'tous_les', 'repetee_du', 'repetee_au', 'heure', 'actif', 'id_evenement', 'id_admin'];

    protected function casts(): array
    {
        return [
            'cible' => 'array',
            'date_envoi' => 'datetime',
            'repetee_du' => 'date',
            'repetee_au' => 'date',
            'actif' => 'boolean',
            'derniere_execution' => 'datetime',
        ];
    }

    /** Prochain envoi prévu (même dans le passé, s'il n'a pas encore eu lieu), ou null si c'est fini. */
    public function prochaine(): ?CarbonImmutable
    {
        $heure = substr((string) $this->heure, 0, 5);
        $a = fn ($jour) => CarbonImmutable::parse($jour->format('Y-m-d').' '.$heure);
        $derniere = $this->derniere_execution ? CarbonImmutable::parse($this->derniere_execution) : null;

        return match ($this->quand) {
            'date' => $derniere ? null : CarbonImmutable::parse($this->date_envoi),
            'avant' => $derniere ? null : $a($this->evenement->date_evenement->copy()->subDays((int) $this->jours_avant)),
            'repetee' => $this->prochaineRepetee($a, $derniere),
            default => null,
        };
    }

    public function estDue(): bool
    {
        $prochaine = $this->prochaine();

        return $this->actif
            && $prochaine !== null
            && $prochaine->lte(now())
            && ! $this->evenement->date_evenement->isPast();
    }

    /** « Le 18 mars 2027 à 10h00 », « tous les 7 jours à 10h00, du … au … » : pour l'écran. */
    public function enClair(): string
    {
        $heure = CarbonImmutable::parse((string) $this->heure)->format('G\hi');

        return match ($this->quand) {
            'date' => 'Le '.$this->date_envoi->translatedFormat('l j F Y').' à '.$this->date_envoi->format('G\hi'),
            'avant' => "{$this->jours_avant} jour(s) avant l'événement, à {$heure}",
            'repetee' => "Tous les {$this->tous_les} jour(s) à {$heure}, du {$this->repetee_du->translatedFormat('j F')} au {$this->repetee_au->translatedFormat('j F Y')}",
            default => '',
        };
    }

    public function etat(): string
    {
        return match (true) {
            $this->prochaine() === null || $this->evenement->date_evenement->isPast() => 'terminee',
            ! $this->actif => 'pause',
            default => 'active',
        };
    }

    private function prochaineRepetee(callable $a, ?CarbonImmutable $derniere): ?CarbonImmutable
    {
        $pas = max(1, (int) $this->tous_les);

        for ($jour = CarbonImmutable::parse($this->repetee_du); $jour->lte($this->repetee_au); $jour = $jour->addDays($pas)) {
            $moment = $a($jour);
            if (! $derniere || $moment->gt($derniere)) {
                return $moment;
            }
        }

        return null;
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_user');
    }

    public function relances(): HasMany
    {
        return $this->hasMany(Relance::class, 'id_programmee', 'id_programmee');
    }
}
