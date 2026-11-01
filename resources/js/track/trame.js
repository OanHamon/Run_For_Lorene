/**
 * TRACK : l'empreinte-QR. Un vrai QR code (lisible par n'importe quel appareil photo et par le
 * poste de retrait), dessiné en particules, où la paire de semelles et le cœur apparaissent.
 *
 * Principe de la trame (« halftone QR ») : chaque module du QR est découpé en 3 × 3 cases.
 * La case du centre porte la donnée (un point foncé si le module est foncé, rien s'il est clair) :
 * c'est là qu'un lecteur de QR échantillonne. Les 8 cases autour dessinent l'image : une particule
 * si la case tombe dans une semelle ou dans le cœur, rien sinon. Les repères du QR (les trois
 * grands carrés d'angle et le petit carré d'alignement) restent pleins, en carrés arrondis.
 * La correction d'erreur H (30 %) absorbe ce que les particules décoratives brouillent.
 *
 * Pur (aucun accès au navigateur) : testé dans Node par tests/js/track-qr.mjs.
 */
import { dansCoeur, dedans, hasard } from './masque.js';

export const TRAME = {
    cote: 1.2,          // côté du QR / plus grande dimension de la paire de semelles
    calme: 2,           // marge blanche autour du QR, en modules (zone de calme)
    donnee: 0.3,        // rayon du point de donnée dans les semelles, en module
    donneeHors: 0.3,    // … hors des semelles (plus clair et un peu plus petit : le fond s'efface)
    decorFonce: 0.36,   // rayon des particules décoratives autour d'un module foncé, en case
    decorClair: 0.27,   // … autour d'un module clair (plus petites : le centre reste clair)
    bouge: 0.1,         // décalage aléatoire des particules décoratives, en case
};

/** Centres des motifs d'alignement, par version (norme ISO 18004, tableau E.1). */
const ALIGNEMENTS = {
    1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34],
    7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50],
};

/**
 * @param m masque des semelles (masque.js)
 * @param matrice { taille, version, modules } (App\Support\Billet::matrice)
 * @returns { liste, formes, cadre, coeur } en unités du SVG. Particules : { x, y, r, phase, nuance, fixe }
 *          (nuance 0/1 semelles, 2 cœur ; données : 3 semelle, 4 fond, 5 cœur). Formes : { x, y, l, h, rayon, creux? } carrés arrondis.
 */
export function trame(m, { taille: n, version, modules }, graine = 'track') {
    const alea = hasard(graine);
    const { boite, coeur } = m;

    const cote = Math.max(boite.l, boite.h) * TRAME.cote;
    const module = cote / n;
    const kase = module / 3;
    const ox = boite.x + boite.l / 2 - cote / 2;
    const oy = boite.y + boite.h / 2 - cote / 2;
    const fonce = (ligne, col) => modules[ligne * n + col] === '1';

    // Zones réservées aux repères : grands carrés d'angle (avec leur liseré blanc) et alignements.
    const reserve = new Uint8Array(n * n);
    const reserver = (l0, c0, cote) => {
        for (let l = Math.max(0, l0); l < Math.min(n, l0 + cote); l++)
            for (let c = Math.max(0, c0); c < Math.min(n, c0 + cote); c++) reserve[l * n + c] = 1;
    };
    const formes = [];
    const carre = (ligne, col, cote, rayon, creux = false) => formes.push({
        x: ox + col * module, y: oy + ligne * module, l: cote * module, h: cote * module, rayon: rayon * module, creux,
    });

    for (const [l, c] of [[0, 0], [0, n - 7], [n - 7, 0]]) {
        reserver(l - 1, c - 1, 9);
        carre(l, c, 7, 2.2, true);          // anneau extérieur (épaisseur 1 module)
        carre(l + 2, c + 2, 3, 1);          // carré central
    }
    const centres = ALIGNEMENTS[version] ?? [];
    for (const l of centres) {
        for (const c of centres) {
            const surRepere = (l < 9 && c < 9) || (l < 9 && c > n - 10) || (l > n - 10 && c < 9);
            if (surRepere) continue;
            reserver(l - 2, c - 2, 5);
            carre(l - 2, c - 2, 5, 1.6, true);
            carre(l, c, 1, 0.5);
        }
    }

    // Taches de couleur secondaire sur les semelles, placées selon la graine.
    const fx = 0.03 + alea() * 0.03, fy = 0.025 + alea() * 0.03;
    const px = alea() * 10, py = alea() * 10;

    const liste = [];
    for (let l = 0; l < n; l++) {
        for (let c = 0; c < n; c++) {
            if (reserve[l * n + c]) continue;
            const cx = ox + (c + 0.5) * module;
            const cy = oy + (l + 0.5) * module;
            const plein = fonce(l, c);

            if (plein) {
                // La donnée prend la couleur de ce qu'elle dessine : foncée dans les semelles,
                // moutarde dans le cœur, vert doux autour. Les semelles se détachent du fond.
                const zone = coeur && dansCoeur(coeur, cx, cy) ? 5 : dedans(m, cx, cy) ? 3 : 4;
                liste.push({ x: cx, y: cy, r: module * (zone === 4 ? TRAME.donneeHors : TRAME.donnee), phase: 0, nuance: zone, fixe: true });
            }

            for (let dl = -1; dl <= 1; dl++) {
                for (let dc = -1; dc <= 1; dc++) {
                    if (!dl && !dc) continue;
                    const x = cx + dc * kase + (alea() - 0.5) * kase * TRAME.bouge * 2;
                    const y = cy + dl * kase + (alea() - 0.5) * kase * TRAME.bouge * 2;
                    const r = kase * (plein ? TRAME.decorFonce : TRAME.decorClair) * (0.9 + alea() * 0.2);
                    let nuance;
                    if (coeur && dansCoeur(coeur, x, y)) nuance = 2;
                    else if (dedans(m, x, y)) {
                        const tache = Math.sin(x * fx + px) * Math.sin(y * fy + py);
                        nuance = tache > 0.62 || alea() < 0.03 ? 1 : 0;
                    } else continue;
                    liste.push({ x, y, r, phase: alea() * Math.PI * 2, nuance, fixe: false });
                }
            }
        }
    }

    const marge = TRAME.calme * module;

    return { liste, formes, coeur, module, cadre: { x: ox - marge, y: oy - marge, l: cote + 2 * marge, h: cote + 2 * marge } };
}
