<?php

/*
 * Messages de validation en français, pour les règles employées dans l'application.
 * Les formulaires du tunnel passent leurs propres messages (tutoiement) ; ceux-ci servent
 * surtout au back-office.
 */
return [
    'accepted' => 'Coche cette case pour continuer.',
    'after' => 'Le champ :attribute doit être postérieur à :date.',
    'after_or_equal' => 'Le champ :attribute doit être égal ou postérieur à :date.',
    'before_or_equal' => 'Le champ :attribute doit être égal ou antérieur à :date.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'date' => 'Le champ :attribute n\'est pas une date valide.',
    'date_format' => 'Le champ :attribute doit respecter le format :format.',
    'decimal' => 'Le champ :attribute doit avoir :decimal décimales.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'exists' => 'La valeur choisie pour :attribute est introuvable.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'gte' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
    ],
    'in' => 'La valeur choisie pour :attribute n\'est pas valide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'file' => 'Le fichier :attribute ne doit pas dépasser :max Ko.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'mimes' => 'Le fichier :attribute doit être de type :values.',
    'min' => [
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'regex' => 'Le format du champ :attribute n\'est pas valide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',

    'attributes' => [
        'nom' => 'nom',
        'prenom' => 'prénom',
        'email' => 'email',
        'date_evenement' => 'date de l\'événement',
        'ouverture' => 'ouverture des inscriptions',
        'fermeture' => 'fermeture des inscriptions',
        'statut' => 'statut',
        'distance' => 'distance',
        'heure_depart' => 'heure de départ',
        'age_min' => 'âge minimum',
        'age_max' => 'âge maximum',
        'quota' => 'quota',
        'dossard' => 'dossard',
        'dossard_debut' => 'premier dossard',
        'dossard_fin' => 'dernier dossard',
        'libelle' => 'libellé',
        'montant' => 'montant',
        'prix' => 'prix',
        'valide_du' => 'début de validité',
        'valide_au' => 'fin de validité',
        'stock' => 'stock',
        'type_texte' => 'type de texte',
        'fichier' => 'fichier',
        'motif' => 'motif',
        'date_naissance' => 'date de naissance',
        'id_course' => 'course',
        'niveau_acces' => 'niveau d\'accès',
    ],
];
