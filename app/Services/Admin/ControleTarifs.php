<?php

namespace App\Services\Admin;

use App\Models\Course;
use App\Models\Tarif;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Garde-fous des tarifs d'une course :
 * - un tarif reste dans la période d'inscription de l'événement ;
 * - deux tarifs ne se recouvrent jamais à la fois en dates et en âges (sinon, lequel appliquer ?) ;
 * - sa tranche d'âge croise celle de la course ;
 * - couverture : chaque jour d'inscription et chaque âge accepté par la course a un tarif.
 *
 * Les âges sont ceux du participant le jour de l'événement ; une borne vide vaut 0 ou AGE_MAX.
 */
class ControleTarifs
{
    public const AGE_MAX = 120;

    /**
     * Tarif de la course qui recouvre déjà la période et la tranche d'âge données.
     *
     * @param  array{valide_du: string, valide_au: string, age_min: ?int, age_max: ?int}  $periode
     */
    public function chevauchement(Course $course, array $periode, ?Tarif $sauf = null): ?Tarif
    {
        [$min, $max] = self::ages($periode['age_min'] ?? null, $periode['age_max'] ?? null);

        return $course->tarifs()
            ->when($sauf, fn ($q) => $q->whereKeyNot($sauf->id_tarif))
            ->whereDate('valide_du', '<=', $periode['valide_au'])
            ->whereDate('valide_au', '>=', $periode['valide_du'])
            ->get()
            ->first(function (Tarif $t) use ($min, $max) {
                [$tMin, $tMax] = self::ages($t->age_min, $t->age_max);

                return $tMin <= $max && $tMax >= $min;
            });
    }

    /** La tranche d'âge du tarif croise-t-elle celle de la course ? */
    public function ageCompatible(Course $course, ?int $ageMin, ?int $ageMax): bool
    {
        [$min, $max] = self::ages($ageMin, $ageMax);
        [$cMin, $cMax] = self::ages($course->age_min, $course->age_max);

        return $min <= $cMax && $max >= $cMin;
    }

    /**
     * Trous de couverture : périodes et âges, d'aujourd'hui (ou de l'ouverture) à la fermeture des inscriptions,
     * pour lesquels aucun tarif ne s'applique. Les jours consécutifs avec les mêmes âges
     * manquants sont regroupés.
     *
     * @return Collection<int, object{du: Carbon, au: Carbon, age_min: int, age_max: int}>
     */
    public function trous(Course $course): Collection
    {
        $evenement = $course->evenement;
        // Seuls les jours à venir comptent : un trou passé ne bloque plus personne.
        $debut = $evenement->ouverture->copy()->startOfDay()->max(today());
        $fin = $evenement->fermeture->copy()->startOfDay();
        [$cMin, $cMax] = self::ages($course->age_min, $course->age_max);

        $tarifs = $course->tarifs()->get()->map(fn (Tarif $t) => [
            'du' => $t->valide_du->copy()->startOfDay(),
            'au' => $t->valide_au->copy()->startOfDay(),
            'ages' => self::ages($t->age_min, $t->age_max),
        ]);

        $trous = collect();
        $courant = null;

        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $tranches = $tarifs->filter(fn ($t) => $jour->between($t['du'], $t['au']))->pluck('ages')->all();

            foreach ($this->agesNonCouverts($cMin, $cMax, $tranches) as [$min, $max]) {
                $cle = "{$min}-{$max}";

                // Prolonge le trou de la veille s'il porte sur les mêmes âges.
                $precedent = $trous->first(fn ($t) => $t->cle === $cle && $t->au->copy()->addDay()->eq($jour));

                if ($precedent) {
                    $precedent->au = $jour->copy();
                } else {
                    $trous->push((object) ['cle' => $cle, 'du' => $jour->copy(), 'au' => $jour->copy(), 'age_min' => $min, 'age_max' => $max]);
                }
            }
        }

        return $trous->sortBy([fn ($a, $b) => $a->du <=> $b->du, fn ($a, $b) => $a->age_min <=> $b->age_min])->values();
    }

    /** Libellé lisible d'un trou : « du 01/01/2027 au 14/01/2027, 18 ans et plus ». */
    public static function libelle(object $trou): string
    {
        $periode = $trou->du->eq($trou->au)
            ? 'le '.$trou->du->format('d/m/Y')
            : 'du '.$trou->du->format('d/m/Y').' au '.$trou->au->format('d/m/Y');

        return "{$periode}, ".self::libelleAges($trou->age_min, $trou->age_max);
    }

    public static function libelleAges(?int $min, ?int $max): string
    {
        [$min, $max] = self::ages($min, $max);

        return match (true) {
            $min === 0 && $max === self::AGE_MAX => 'tous âges',
            $max === self::AGE_MAX => "{$min} ans et plus",
            $min === 0 => "jusqu'à {$max} ans",
            $min === $max => "{$min} ans",
            default => "{$min} à {$max} ans",
        };
    }

    /** @return array{0: int, 1: int} bornes d'âge, une borne vide valant 0 ou AGE_MAX */
    public static function ages(?int $min, ?int $max): array
    {
        return [$min ?? 0, $max ?? self::AGE_MAX];
    }

    /**
     * Âges de [$min, $max] qu'aucune tranche ne couvre, en intervalles.
     *
     * @param  list<array{0: int, 1: int}>  $tranches
     * @return list<array{0: int, 1: int}>
     */
    private function agesNonCouverts(int $min, int $max, array $tranches): array
    {
        usort($tranches, fn ($a, $b) => $a[0] <=> $b[0]);

        $manquants = [];
        $prochain = $min;

        foreach ($tranches as [$tMin, $tMax]) {
            if ($tMax < $prochain) {
                continue;
            }
            if ($tMin > $prochain) {
                $manquants[] = [$prochain, min($tMin - 1, $max)];
            }
            $prochain = max($prochain, $tMax + 1);
            if ($prochain > $max) {
                break;
            }
        }

        if ($prochain <= $max) {
            $manquants[] = [$prochain, $max];
        }

        return array_values(array_filter($manquants, fn ($m) => $m[0] <= $m[1]));
    }
}
