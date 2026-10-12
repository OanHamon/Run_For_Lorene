<?php

return [
    /*
    | Options d'un événement (t-shirt et tailles) : masquées tant que l'étape Options du
    | tunnel (écran 07) n'est pas en service. Les données restent en base et sont dupliquées
    | avec l'événement ; seuls les écrans et les chiffres de l'admin disparaissent.
    */
    'options' => (bool) env('OPTIONS_ACTIVES', false),
];
