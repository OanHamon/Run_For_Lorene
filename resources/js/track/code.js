/**
 * TRACK : le code des semelles. Transforme le jeton d'un billet (« RFL-{n°}-{signature} », voir
 * App\Support\Billet) en une couleur par cellule des semelles, et inversement.
 *
 *   jeton → 15 octets (version du code, n° d'inscription sur 4 octets, signature sur 10 octets)
 *         → + 17 octets Reed–Solomon (32 octets, 256 cellules ; les cellules en trop sont fixes)
 *         → bits brouillés par une suite fixe (autant de cellules claires que foncées, pas de
 *           grande plage unie quand le numéro contient des zéros)
 *         → 1 = cellule vert sombre, 0 = cellule vert doux.
 *
 * Le code ne contient que le jeton, déjà signé : le serveur le vérifie comme un QR de billet.
 * Pur, sans navigateur.
 */
import gabarit from './gabarit.json' with { type: 'json' };
import { decoder, encoder } from './rs.js';

export { gabarit };

const DONNEES = 15;
const OCTETS = Math.floor(gabarit.cellules.length / 8);
const CORRECTION = OCTETS - DONNEES;
// Octets de correction gardés pour la détection (voir rs.js) : une lecture très abîmée est
// refusée plutôt que « corrigée » vers un faux jeton. Il reste 13 octets pour corriger.
const RESERVE = 4;

/** Suite de brouillage fixe, une valeur par cellule (même suite à l'encodage et à la lecture). */
const BROUILLAGE = (() => {
    let a = 0x52464c33; // « RFL3 »
    return Uint8Array.from(gabarit.cellules, () => {
        a ^= a << 13; a ^= a >>> 17; a ^= a << 5;
        return (a >>> 0) & 1;
    });
})();

const hex = (octets) => octets.map((o) => o.toString(16).padStart(2, '0')).join('');

/** Octets utiles d'un jeton de billet, ou null s'il n'a pas le bon format. */
export function octetsDuJeton(jeton) {
    const m = /^RFL-(\d{1,10})-([a-f0-9]{20})$/.exec(String(jeton).trim());
    if (!m || Number(m[1]) > 0xffffffff) return null;
    const id = Number(m[1]);
    return [gabarit.version, id >>> 24 & 255, id >>> 16 & 255, id >>> 8 & 255, id & 255,
        ...m[2].match(/../g).map((h) => parseInt(h, 16))];
}

export function jetonDesOctets(o) {
    if (!o || o.length !== DONNEES || o[0] !== gabarit.version) return null;
    const id = ((o[1] << 24) >>> 0) + (o[2] << 16) + (o[3] << 8) + o[4];
    return `RFL-${id}-${hex(o.slice(5))}`;
}

/** Couleur de chaque cellule du gabarit (1 foncée, 0 claire), ou null si le jeton n'est pas codable. */
export function cellulesDuJeton(jeton) {
    const donnees = octetsDuJeton(jeton);
    if (!donnees) return null;
    const bloc = encoder(donnees, CORRECTION);
    return Uint8Array.from(gabarit.cellules, (_, i) => {
        const bit = i < OCTETS * 8 ? (bloc[i >> 3] >> (7 - (i & 7))) & 1 : i & 1;
        return bit ^ BROUILLAGE[i];
    });
}

/**
 * Jeton lu à partir des cellules : `foncees[i]` vrai si la cellule i paraît foncée, `doutes[i]`
 * vrai si sa couleur est incertaine (ses octets sont alors traités comme illisibles, ce qui coûte
 * deux fois moins cher au code correcteur qu'une erreur). Renvoie le jeton, ou null.
 */
export function jetonDesCellules(foncees, doutes = null) {
    const bloc = new Array(OCTETS).fill(0);
    const douteux = new Set();
    for (let i = 0; i < OCTETS * 8; i++) {
        const bit = (foncees[i] ? 1 : 0) ^ BROUILLAGE[i];
        bloc[i >> 3] |= bit << (7 - (i & 7));
        if (doutes?.[i]) douteux.add(i >> 3);
    }

    // D'abord avec les octets douteux déclarés illisibles (s'ils ne sont pas trop nombreux), puis sans.
    const essais = douteux.size && douteux.size <= CORRECTION - RESERVE ? [[...douteux], []] : [[]];
    for (const illisibles of essais) {
        const jeton = jetonDesOctets(decoder(bloc, CORRECTION, illisibles, RESERVE));
        if (jeton) return jeton;
    }

    return null;
}

/**
 * Couleur des particules d'une empreinte codée : cœur (2), cellule foncée (3) ou claire (4),
 * contour des semelles hors cellule (0). `liste` : particules de masque.js (modifiées sur place).
 */
export function colorerParticules(liste, cellules) {
    const c = gabarit.cellule;
    const [x0, y0] = gabarit.cellules[0];
    const index = new Map(gabarit.cellules.map(([x, y], i) => [`${Math.round((x - x0) / c)},${Math.round((y - y0) / c)}`, i]));
    for (const q of liste) {
        if (q.nuance === 2) continue;
        const i = index.get(`${Math.floor((q.x - x0) / c)},${Math.floor((q.y - y0) / c)}`);
        q.nuance = i === undefined ? 0 : cellules[i] ? 3 : 4;
    }
    return liste;
}

