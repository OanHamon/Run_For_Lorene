/**
 * Banc de lecture de l'empreinte-QR (TRACK), sans navigateur :
 *
 *   node tests/js/track-qr.mjs            # résumé
 *   node tests/js/track-qr.mjs --png DIR  # écrit aussi les images testées
 *
 * Pixellise semelles.svg (même règle « evenodd » que le navigateur), calcule la trame avec le
 * vrai code (masque.js, trame.js) et la grille du QR avec le vrai serveur (Billet::matrice),
 * dessine l'empreinte en niveaux de gris, la dégrade comme une photo (taille, flou, bruit) et la
 * fait lire par jsQR, la bibliothèque du poste de retrait. Sort en erreur si une lecture échoue
 * dans les conditions « normales ».
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { deflateSync } from 'node:zlib';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import jsQR from 'jsqr';
import { FINESSE, construireMasque } from '../../resources/js/track/masque.js';
import { trame } from '../../resources/js/track/trame.js';

const racine = join(dirname(fileURLToPath(import.meta.url)), '../..');
const dossierImages = process.argv.includes('--png') ? process.argv[process.argv.indexOf('--png') + 1] : null;

// Luminance des couleurs de la charte (ce que voit un lecteur de QR en niveaux de gris).
const gris = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
    return Math.round(0.299 * r + 0.587 * g + 0.114 * b);
};
// Mêmes couleurs que empreinte.js, par nuance de particule.
const NUANCES = ['#006B61', '#5FBAAE', '#D9B21E', '#23463F', '#5FBAAE', '#D9B21E'].map(gris);
const REPERES = gris('#23463F');

/** Masque des semelles : aplatit les courbes de Bézier et remplit en « evenodd », rangée par rangée. */
function masqueSemelles() {
    const source = readFileSync(join(racine, 'resources/svg/track/semelles.svg'), 'utf8');
    const d = source.match(/\sd="([^"]+)"/)[1];
    const vue = source.match(/viewBox="([^"]+)"/)[1].trim().split(/\s+/).map(Number);
    const polygones = d.split(/(?=M)/).filter((p) => p.trim()).map((p) => {
        const n = p.match(/-?\d+\.?\d*/g).map(Number);
        const pts = [[n[0], n[1]]];
        let cur = pts[0];
        for (let i = 2; i + 6 <= n.length; i += 6) {
            const [x1, y1, x2, y2, x3, y3] = n.slice(i, i + 6);
            for (let k = 1; k <= 16; k++) {
                const t = k / 16, a = 1 - t;
                pts.push([a ** 3 * cur[0] + 3 * a * a * t * x1 + 3 * a * t * t * x2 + t ** 3 * x3,
                    a ** 3 * cur[1] + 3 * a * a * t * y1 + 3 * a * t * t * y2 + t ** 3 * y3]);
            }
            cur = [x3, y3];
        }
        return pts;
    });

    const largeur = Math.ceil(vue[2] * FINESSE), hauteur = Math.ceil(vue[3] * FINESSE);
    const plein = new Uint8Array(largeur * hauteur);
    for (let py = 0; py < hauteur; py++) {
        const y = (py + 0.5) / FINESSE;
        const xs = [];
        for (const P of polygones) {
            for (let j = 0; j < P.length; j++) {
                const [xa, ya] = P[j], [xb, yb] = P[(j + 1) % P.length];
                if ((ya <= y) !== (yb <= y)) xs.push(xa + ((y - ya) * (xb - xa)) / (yb - ya));
            }
        }
        xs.sort((a, b) => a - b);
        for (let k = 0; k + 1 < xs.length; k += 2) {
            for (let px = Math.max(0, Math.round(xs[k] * FINESSE)); px < Math.min(largeur, Math.round(xs[k + 1] * FINESSE)); px++) {
                plein[py * largeur + px] = 1;
            }
        }
    }

    return construireMasque(plein, largeur, hauteur);
}

/** Dessine l'empreinte en niveaux de gris, `module` pixels par module du QR (suréchantillonné 3 ×). */
function dessiner(e, pxModule) {
    const ss = 3;
    const echelle = (pxModule / e.module) * ss;
    const L = Math.ceil(e.cadre.l * echelle), H = Math.ceil(e.cadre.h * echelle);
    const img = new Uint8Array(L * H).fill(255);
    const X = (x) => (x - e.cadre.x) * echelle, Y = (y) => (y - e.cadre.y) * echelle;

    const dansRond = (px, py, x, y, l, h, r) => {
        const qx = Math.max(x + r - px, 0, px - (x + l - r)), qy = Math.max(y + r - py, 0, py - (y + h - r));
        return qx * qx + qy * qy <= r * r;
    };
    for (const f of e.formes) {
        const x = X(f.x), y = Y(f.y), l = f.l * echelle, h = f.h * echelle, r = f.rayon * echelle, m = e.module * echelle;
        for (let py = Math.floor(y); py < y + h; py++) {
            for (let px = Math.floor(x); px < x + l; px++) {
                const c = [px + 0.5, py + 0.5];
                if (!dansRond(...c, x, y, l, h, r)) continue;
                if (f.creux && dansRond(...c, x + m, y + m, l - 2 * m, h - 2 * m, Math.max(0, r - m))) continue;
                img[py * L + px] = Math.min(img[py * L + px], REPERES);
            }
        }
    }
    for (const q of e.liste) {
        const x = X(q.x), y = Y(q.y), r = q.r * echelle, g = NUANCES[q.nuance];
        for (let py = Math.floor(y - r); py <= y + r; py++) {
            for (let px = Math.floor(x - r); px <= x + r; px++) {
                if (px < 0 || py < 0 || px >= L || py >= H) continue;
                if ((px + 0.5 - x) ** 2 + (py + 0.5 - y) ** 2 <= r * r) img[py * L + px] = Math.min(img[py * L + px], g);
            }
        }
    }

    // Réduction (moyenne des blocs ss × ss) : anticrénelage, comme un vrai écran.
    const l = Math.floor(L / ss), h = Math.floor(H / ss);
    const out = new Float32Array(l * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) {
        let s = 0;
        for (let j = 0; j < ss; j++) for (let i = 0; i < ss; i++) s += img[(y * ss + j) * L + x * ss + i];
        out[y * l + x] = s / (ss * ss);
    }

    return { l, h, px: out };
}

function flouter({ l, h, px }, rayon) {
    if (!rayon) return { l, h, px };

    const passe = (src, dx, dy) => {
        const dst = new Float32Array(l * h);
        for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) {
            let s = 0, n = 0;
            for (let k = -rayon; k <= rayon; k++) {
                const xx = x + k * dx, yy = y + k * dy;
                if (xx >= 0 && yy >= 0 && xx < l && yy < h) { s += src[yy * l + xx]; n++; }
            }
            dst[y * l + x] = s / n;
        }
        return dst;
    };
    return { l, h, px: passe(passe(px, 1, 0), 0, 1) };
}

/**
 * Simule une photo : l'image posée sur un fond gris clair, contraste réduit, pente de lumière,
 * bruit, puis le flou de l'objectif (qui adoucit aussi le bruit, comme un vrai capteur).
 */
function photo({ l, h, px }, { bruit = 0, contraste = 1, bord = 30, flou = 0 }, alea) {
    const L = l + 2 * bord, H = h + 2 * bord;
    const scene = new Float32Array(L * H);
    for (let y = 0; y < H; y++) for (let x = 0; x < L; x++) {
        const dedans = x >= bord && y >= bord && x < bord + l && y < bord + h;
        const v = dedans ? px[(y - bord) * l + (x - bord)] : 235;
        scene[y * L + x] = 128 + (v - 128) * contraste + (x / L - 0.5) * 30 + (alea() - 0.5) * 2 * bruit;
    }
    const net = flouter({ l: L, h: H, px: scene }, flou).px;
    const rgba = new Uint8ClampedArray(L * H * 4);
    for (let i = 0; i < L * H; i++) {
        rgba[i * 4] = rgba[i * 4 + 1] = rgba[i * 4 + 2] = net[i];
        rgba[i * 4 + 3] = 255;
    }
    return { l: L, h: H, rgba };
}

/** PNG en niveaux de gris, sans dépendance. */
function png(nom, { l, h, rgba }) {
    if (!dossierImages) return;
    mkdirSync(dossierImages, { recursive: true });
    const lignes = Buffer.alloc((l + 1) * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) lignes[y * (l + 1) + 1 + x] = rgba[(y * l + x) * 4];
    const crc = (buf) => {
        let c = ~0;
        for (const o of buf) { c ^= o; for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1)); }
        return ~c >>> 0;
    };
    const bloc = (type, data) => {
        const t = Buffer.concat([Buffer.from(type), data]);
        const b = Buffer.alloc(4); b.writeUInt32BE(data.length);
        const c = Buffer.alloc(4); c.writeUInt32BE(crc(t));
        return Buffer.concat([b, t, c]);
    };
    const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(l, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 0;
    writeFileSync(join(dossierImages, `${nom}.png`), Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        bloc('IHDR', ihdr), bloc('IDAT', deflateSync(lignes)), bloc('IEND', Buffer.alloc(0)),
    ]));
}

/** Témoin : le même QR en carrés pleins classiques, pour juger la difficulté des conditions. */
function temoin({ taille: n, modules }) {
    const formes = [];
    for (let i = 0; i < n * n; i++) if (modules[i] === '1') formes.push({ x: (i % n) * 10, y: Math.floor(i / n) * 10, l: 10, h: 10, rayon: 0 });
    return { liste: [], formes, module: 10, cadre: { x: -20, y: -20, l: n * 10 + 40, h: n * 10 + 40 } };
}

let a = 12345;
const alea = () => ((a = (a * 1103515245 + 12345) % 2147483648) / 2147483648);

const masque = masqueSemelles();
const jetons = ['RFL-12-3f9a0c77d1e24b5a6c80', 'RFL-1042-0b1c2d3e4f5a6b7c8d9e', 'RFL-9999999-ffffffffffffffffffff'];
const conditions = [
    { nom: 'net, 8 px/module', px: 8, flou: 0, normal: true },
    { nom: 'net, 5 px/module', px: 5, flou: 0, normal: true },
    { nom: 'photo, 6 px/module, flou 1, bruit', px: 6, flou: 1, bruit: 18, contraste: 0.8, normal: true },
    { nom: 'loin, 4 px/module, flou 1, bruit', px: 4, flou: 1, bruit: 18, contraste: 0.8, normal: false },
    { nom: 'difficile, 3 px/module, flou 1', px: 3, flou: 1, bruit: 12, contraste: 0.75, normal: false },
    { nom: 'difficile, 6 px/module, flou 2, bruit fort', px: 6, flou: 2, bruit: 30, contraste: 0.7, normal: false },
];

let echecs = 0;
for (const jeton of jetons) {
    const matrice = JSON.parse(execFileSync('php', ['-r', 'require "vendor/autoload.php"; echo json_encode(App\\Support\\Billet::matrice($argv[1]));', jeton], { cwd: racine }));
    const e = trame(masque, matrice, jeton);
    const t = temoin(matrice);
    for (const c of conditions) {
        const lire = (empreinte, nom) => {
            const image = photo(dessiner(empreinte, c.px), c, alea);
            png(`${nom}-${jeton.slice(4, 8)}-${c.px}px-flou${c.flou}`, image);
            return jsQR(image.rgba, image.l, image.h, { inversionAttempts: 'dontInvert' })?.data === jeton;
        };
        const ok = lire(e, 'track');
        const okTemoin = lire(t, 'temoin');
        if (!ok && c.normal) echecs++;
        console.log(`${ok ? 'lu  ' : 'RATÉ'}  ${jeton.padEnd(34)} ${c.nom.padEnd(42)} QR classique : ${okTemoin ? 'lu' : 'raté'}${!ok && !c.normal ? '  (toléré)' : ''}`);
    }
}
console.log(`\nParticules : ${trame(masque, JSON.parse(execFileSync('php', ['-r', 'require "vendor/autoload.php"; echo json_encode(App\\Support\\Billet::matrice("x"));'], { cwd: racine })), 'x').liste.length}, cœur : ${masque.coeur ? 'oui' : 'non'}`);
process.exit(echecs ? 1 : 0);
