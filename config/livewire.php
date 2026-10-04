<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Layout utilisé par défaut pour les composants Volt sans attribut #[Layout(...)]
    |---------------------------------------------------------------------------
    |
    | Valeur par défaut du package Livewire : 'layouts::app' (vue namespacée,
    | convention Breeze classique). Ce starter kit (Flux) utilise en réalité des
    | composants Blade anonymes sous resources/views/components/layouts/, d'où
    | 'components.layouts.app'. Jamais publié à l'installation, ce qui cassait
    | tout écran Volt sans #[Layout] explicite (ex : settings/profile, .../password)
    | avec "No hint path defined for [layouts]".
    |
    */

    'component_layout' => 'components.layouts.app',

];
