/**
 * Génère le gabarit du code TRACK (resources/js/track/gabarit.json) à partir de semelles.svg :
 *
 *   node scripts/track-gabarit.mjs
 *
 * Le gabarit fige tout ce que l'encodeur (l'empreinte affichée) et le lecteur (le poste de
 * retrait) doivent partager à l'identique : la grille des cellules de données dans les semelles,
 * leur ordre de lecture, le cœur, et les points de repérage de la forme. Le régénérer change le
 * code : une empreinte dessinée avec l'ancien gabarit ne se lirait plus. D'où le numéro de version.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { dansCoeur, dedans, pixelliser } from '../resources/js/track/masque.js';

const VERSION = 1;
const CELLULE = 11;        // côté d'une cellule, en unités du SVG
const PAS_REPERES = 5;     // espacement des points de repérage

const racine = join(dirname(fileURLToPath(import.meta.url)), '..');
const m = pixelliser(readFileSync(join(racine, 'resources/svg/track/semelles.svg'), 'utf8'));
const { boite, coeur } = m;
const r1 = (v) => Math.round(v * 10) / 10;
const dansForme = (x, y) => dedans(m, x, y) || (coeur && dansCoeur(coeur, x, y));

// Cellules : carrés entièrement dans une semelle (hors cœur), sur la grille qui en loge le plus.
function cellules(ox, oy) {
    const liste = [];
    for (let y = boite.y + oy; y + CELLULE <= boite.y + boite.h + 1; y += CELLULE) {
        for (let x = boite.x + ox; x + CELLULE <= boite.x + boite.l + 1; x += CELLULE) {
            let ok = true;
            for (let yy = y + 1; yy <= y + CELLULE - 1 && ok; yy++) {
                for (let xx = x + 1; xx <= x + CELLULE - 1; xx++) {
                    if (!dedans(m, xx, yy) || (coeur && dansCoeur(coeur, xx, yy))) { ok = false; break; }
                }
            }
            if (ok) liste.push([x, y]);
        }
    }
    return liste;
}
let grille = { ox: 0, oy: 0, liste: [] };
for (let ox = 0; ox < CELLULE; ox++) for (let oy = 0; oy < CELLULE; oy++) {
    const liste = cellules(ox, oy);
    if (liste.length > grille.liste.length) grille = { ox, oy, liste };
}

// Ordre de lecture : semelle gauche puis droite, rangée par rangée en serpentin. Les 8 bits d'un
// octet tombent ainsi sur des cellules voisines : une tache ou un reflet abîme peu d'octets, que
// le code correcteur répare.
const milieu = coeur ? coeur.cx : boite.x + boite.l / 2;
const ordonner = (liste) => {
    const rangées = new Map();
    for (const c of liste) rangées.set(c[1], [...(rangées.get(c[1]) ?? []), c]);
    return [...rangées.keys()].sort((a, b) => a - b).flatMap((y, i) => {
        const r = rangées.get(y).sort((a, b) => a[0] - b[0]);
        return i % 2 ? r.reverse() : r;
    });
};
const ordre = [
    ...ordonner(grille.liste.filter((c) => c[0] + CELLULE / 2 < milieu)),
    ...ordonner(grille.liste.filter((c) => c[0] + CELLULE / 2 >= milieu)),
];

// Repérage : points sûrement dans la forme (semelles et cœur, à 1,5 unité du bord) et points
// sûrement dehors (bande de 2,5 à 9 unités autour). Le lecteur cale la forme sur l'image avec eux.
const anneau = (x, y, r) => Array.from({ length: 12 }, (_, i) => [x + Math.cos((i * Math.PI) / 6) * r, y + Math.sin((i * Math.PI) / 6) * r]);
const repDedans = [], repDehors = [];
for (let y = boite.y - 10; y <= boite.y + boite.h + 10; y += PAS_REPERES) {
    for (let x = boite.x - 10; x <= boite.x + boite.l + 10; x += PAS_REPERES) {
        if (dansForme(x, y)) {
            if (anneau(x, y, 1.5).every(([a, b]) => dansForme(a, b))) repDedans.push([r1(x), r1(y)]);
        } else if (anneau(x, y, 2.5).every(([a, b]) => !dansForme(a, b)) && [4, 6.5, 9].some((r) => anneau(x, y, r).some(([a, b]) => dansForme(a, b)))) {
            repDehors.push([r1(x), r1(y)]);
        }
    }
}

// Semelles vues de loin (taches pleines) : centre et surface, pour les trouver dans l'image.
const semelle = (gauche) => {
    let n = 0, sx = 0, sy = 0;
    for (let py = 0; py < m.hauteur; py += 2) for (let px = 0; px < m.largeur; px += 2) {
        const x = px / 3, y = py / 3;
        if (m.plein[py * m.largeur + px] && (x < milieu) === gauche) { n++; sx += x; sy += y; }
    }
    return { cx: r1(sx / n), cy: r1(sy / n), aire: Math.round(n * (2 / 3) ** 2) };
};

const gabarit = {
    version: VERSION,
    cellule: CELLULE,
    boite: { x: r1(boite.x), y: r1(boite.y), l: r1(boite.l), h: r1(boite.h) },
    coeur: coeur && { cx: r1(coeur.cx), cy: r1(coeur.cy), unite: Math.round(coeur.unite * 1000) / 1000 },
    semelles: { gauche: semelle(true), droite: semelle(false) },
    cellules: ordre.map(([x, y]) => [r1(x), r1(y)]),
    reperes: { dedans: repDedans, dehors: repDehors },
};
writeFileSync(join(racine, 'resources/js/track/gabarit.json'), JSON.stringify(gabarit) + '\n');
console.log(`Gabarit v${VERSION} : ${ordre.length} cellules (${Math.floor(ordre.length / 8)} octets), ${repDedans.length} + ${repDehors.length} points de repérage.`);
console.log('Semelles :', gabarit.semelles, 'cœur :', gabarit.coeur);
