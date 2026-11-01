/**
 * TRACK : forme de l'empreinte. Lit le tracé de `semelles.svg` (la paire de semelles dessinée
 * pour la course), le remplit en « evenodd » sur un canevas hors écran, puis y répartit les
 * particules : grille en quinconce légèrement bousculée, sans grille apparente.
 *
 * La graine (le jeton d'un participant, par exemple) fait varier le placement, la taille et
 * les nuances des particules : chaque empreinte est unique. Elle n'encode encore aucune donnée.
 */
import source from '../../svg/track/semelles.svg?raw';

const tracé = source.match(/\sd="([^"]+)"/)[1].replace(/\s+/g, ' ');
const [, , largeurVue, hauteurVue] = source.match(/viewBox="([^"]+)"/)[1].trim().split(/\s+/).map(Number);

const FINESSE = 3; // pixels de masque par unité du SVG, pour tester finement les bords

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
    let x0 = largeur, y0 = hauteur, x1 = 0, y1 = 0;
    for (let y = 0; y < hauteur; y++) {
        for (let x = 0; x < largeur; x++) {
            if (pixels[(y * largeur + x) * 4 + 3] > 127) {
                plein[y * largeur + x] = 1;
                if (x < x0) x0 = x;
                if (x > x1) x1 = x;
                if (y < y0) y0 = y;
                if (y > y1) y1 = y;
            }
        }
    }

    masque = {
        plein, largeur, hauteur,
        boite: { x: x0 / FINESSE, y: y0 / FINESSE, l: (x1 - x0) / FINESSE, h: (y1 - y0) / FINESSE },
    };

    return masque;
}

function dedans(m, x, y) {
    const px = Math.round(x * FINESSE);
    const py = Math.round(y * FINESSE);
    return px >= 0 && py >= 0 && px < m.largeur && py < m.hauteur && m.plein[py * m.largeur + px] === 1;
}

/** Vrai si tout le disque (x, y, r) tient dans la forme : les sillons fins restent lisibles. */
function disqueDedans(m, x, y, r) {
    if (!dedans(m, x, y)) return false;
    for (let i = 0; i < 8; i++) {
        const a = (i / 8) * Math.PI * 2;
        if (!dedans(m, x + Math.cos(a) * r, y + Math.sin(a) * r)) return false;
    }
    return true;
}

/** Générateur pseudo-aléatoire reproductible à partir d'une graine texte. */
export function hasard(graine) {
    let h = 2166136261;
    for (const c of String(graine)) {
        h = Math.imul(h ^ c.codePointAt(0), 16777619);
    }
    let a = h >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * Particules de l'empreinte, en unités du SVG.
 * Chaque particule : { x, y, r, phase, nuance } (nuance 1 = couleur secondaire).
 */
export function particules(graine = 'track', espacement = 3.8) {
    const m = lireMasque();
    const alea = hasard(graine);
    const { boite } = m;

    // Taches douces de couleur secondaire, placées selon la graine.
    const fx = 0.03 + alea() * 0.03, fy = 0.025 + alea() * 0.03;
    const px = alea() * 10, py = alea() * 10;

    const liste = [];
    const pasY = espacement * 0.87; // quinconce : rangées resserrées
    let rangée = 0;
    for (let y = boite.y; y <= boite.y + boite.h; y += pasY, rangée++) {
        const décalage = rangée % 2 ? espacement / 2 : 0;
        for (let x = boite.x + décalage; x <= boite.x + boite.l; x += espacement) {
            const jx = x + (alea() - 0.5) * espacement * 0.55;
            const jy = y + (alea() - 0.5) * espacement * 0.55;
            const r = espacement * (0.28 + alea() * 0.18);
            // Le centre et l'essentiel du disque dans la forme : bords nets sans boucher les sillons.
            if (!disqueDedans(m, jx, jy, r * 0.6)) continue;

            const tache = Math.sin(jx * fx + px) * Math.sin(jy * fy + py);
            liste.push({
                x: jx,
                y: jy,
                r,
                phase: alea() * Math.PI * 2,
                nuance: tache > 0.62 || alea() < 0.03 ? 1 : 0,
            });
        }
    }

    return { liste, boite };
}

/** Boîte englobante de la forme, en unités du SVG. */
export function boiteForme() {
    return lireMasque().boite;
}
