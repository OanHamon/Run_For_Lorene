<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Pages d'information du pied de page. Leur texte vient de l'association : tant que la vue
 * `site/pages/{page}` n'existe pas, la page « pas encore en ligne » s'affiche (DECISION.md, n° 4).
 */
class PageController extends Controller
{
    public const PAGES = [
        'mentions-legales' => 'Mentions légales',
        'cgv' => 'Conditions générales de vente',
        'cookies' => 'Cookies',
    ];

    public function __invoke(string $page): Response
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        if (view()->exists("site.pages.{$page}")) {
            return response()->view("site.pages.{$page}", ['titre' => self::PAGES[$page]]);
        }

        return response()->view('site.texte-indisponible', ['libelle' => self::PAGES[$page]], 404);
    }
}
