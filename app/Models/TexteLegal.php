<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TexteLegal extends Model
{
    protected $table = 'texte_legals';

    protected $primaryKey = 'id_texte';

    public $timestamps = false;

    /** PDF indispensables : le tunnel fait accepter le règlement et la RGPD. */
    public const OBLIGATOIRES = ['reglement', 'rgpd'];

    /** Acceptés au volet « Conditions » du tunnel ; la décharge l'est à son propre volet. */
    public const AVANT_PAIEMENT = ['reglement', 'rgpd'];

    /**
     * PDF facultatifs. La décharge se fait par les affirmations à cocher du tunnel ; si un PDF
     * est publié, son acceptation est enregistrée en plus (trace de la version).
     */
    public const FACULTATIFS = ['decharge', 'droit_image'];

    protected $fillable = [
        'type_texte',
        'version',
        'date_publication',
        'fichier',
        'id_evenement',
    ];

    protected function casts(): array
    {
        return [
            'date_publication' => 'date',
        ];
    }

    /** Emplacement du PDF d'une version, sur le disque privé. */
    public static function chemin(Evenement $evenement, string $type, int $version): string
    {
        return "textes-legaux/evenement-{$evenement->id_evenement}/{$type}-v{$version}.pdf";
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'id_evenement', 'id_evenement');
    }
}
