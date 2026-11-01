/**
 * TRACK : forme de l'empreinte. Lit le tracé de `semelles.svg` (la paire de semelles dessinée
 * pour la course), le remplit en « evenodd » sur un canevas hors écran, puis y répartit les
 * particules : grille en quinconce légèrement bousculée, sans grille apparente.
 *
 * Un cœur, en particules lui aussi, bat entre les deux semelles : il prend la plus grande place
 * libre au milieu de la paire, calculée sur le masque (aucune coordonnée en dur).
 *
 * La graine fait varier le placement, la taille et les nuances des particules. Avec une matrice
 * de QR (particulesQr), l'empreinte devient un vrai QR code : voir trame.js.
 * La géométrie pure (cœur, tests de forme) est dans masque.js, testable sans navigateur.
 */
import source from '../../svg/track/semelles.svg?raw';
import { FINESSE, construireMasque, particules as particulesLibres } from './masque.js';
import { trame } from './trame.js';

const tracé = source.match(/\sd="([^"]+)"/)[1].replace(/\s+/g, ' ');
const [, , largeurVue, hauteurVue] = source.match(/viewBox="([^"]+)"/)[1].trim().split(/\s+/).map(Number);

let masque = null;

/** Masque de remplissage (calculé une fois pour toutes les empreintes de la page). */
function lireMasque() {
    if (masque) return masque;

    const largeur = Math.ceil(largeurVue * FINESSE);
    const hauteur = Math.ceil(hauteurVue * FINESSE);
    const canevas = document.createElement('canvas');
    canevas.width = largeur;
    canevas.height = hauteur;
    const ctx = canevas.getContext('2d', { willReadFrequently: true });
    ctx.scale(FINESSE, FINESSE);
    ctx.fill(new Path2D(tracé), 'evenodd');

    const pixels = ctx.getImageData(0, 0, largeur, hauteur).data;
    const plein = new Uint8Array(largeur * hauteur);
    for (let i = 0; i < plein.length; i++) plein[i] = pixels[i * 4 + 3] > 127 ? 1 : 0;

    masque = construireMasque(plein, largeur, hauteur);

    return masque;
}

/** Empreinte décorative (sans donnée) : voir masque.js. */
export function particules(graine = 'track', espacement = 3.8) {
    return particulesLibres(lireMasque(), graine, espacement);
}

/** Empreinte-QR : le QR de `matrice` dessiné en particules, les semelles en filigrane (trame.js). */
export function particulesQr(graine, matrice) {
    return trame(lireMasque(), matrice, graine);
}

/** Boîte englobante de la forme, en unités du SVG. */
export function boiteForme() {
    return lireMasque().boite;
}
