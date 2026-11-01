/**
 * Banc de lecture du code TRACK (semelles et cœur), sans navigateur :
 *
 *   npm run test:track                         # résumé, sort en erreur sous le seuil attendu
 *   node tests/js/track-code.mjs --png DIR     # écrit aussi une image par condition
 *
 * Dessine de vraies empreintes codées (même code que le site : masque.js, code.js), les
 * « photographie » (rotation quelconque, perspective, taille, flou, bruit, contraste, lumière
 * inégale, objets autour) et les fait lire par le lecteur du poste de retrait (lecteur.js).
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateSync } from 'node:zlib';
import { cellulesDuJeton, colorerParticules } from '../../resources/js/track/code.js';
import { lireEmpreinte } from '../../resources/js/track/lecteur.js';
import { particules, pixelliser } from '../../resources/js/track/masque.js';

const racine = join(dirname(fileURLToPath(import.meta.url)), '../..');
const dossierImages = process.argv.includes('--png') ? process.argv[process.argv.indexOf('--png') + 1] : null;

let graineAlea = 20270403;
const alea = () => {
    graineAlea ^= graineAlea << 13; graineAlea ^= graineAlea >>> 17; graineAlea ^= graineAlea << 5;
    return (graineAlea >>> 0) / 4294967296;
};

const gris = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
    return 0.299 * r + 0.587 * g + 0.114 * b;
};
// Couleurs de empreinte.js par nuance : contour vert, —, cœur moutarde, cellule foncée, cellule claire.
const NUANCES = ['#006B61', '#5FBAAE', '#D9B21E', '#23463F', '#5FBAAE'].map(gris);

const masque = pixelliser(readFileSync(join(racine, 'resources/svg/track/semelles.svg'), 'utf8'));
const VUE = 447, RES = 3; // empreinte dessinée à 3 px par unité

function dessinerEmpreinte(jeton, sansCode = false) {
    const { liste } = particules(masque, jeton, 3.8);
    if (!sansCode) colorerParticules(liste, cellulesDuJeton(jeton));
    const L = VUE * RES;
    const img = new Float32Array(L * L).fill(255);
    for (const q of liste) {
        const x = q.x * RES, y = q.y * RES, r = q.r * RES, g = NUANCES[q.nuance];
        for (let py = Math.floor(y - r); py <= y + r; py++) for (let px = Math.floor(x - r); px <= x + r; px++) {
            const d = Math.hypot(px + 0.5 - x, py + 0.5 - y);
            if (d > r + 0.5) continue;
            const a = Math.min(1, r + 0.5 - d); // bord adouci
            const i = py * L + px;
            img[i] = img[i] * (1 - a) + g * a;
        }
    }
    return img;
}

/** Homographie 4 points → 4 points (coefficients h0…h7). */
function homographie(de, vers) {
    const A = [], b = [];
    for (let i = 0; i < 4; i++) {
        const [x, y] = de[i], [u, v] = vers[i];
        A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]); b.push(u);
        A.push([0, 0, 0, x, y, 1, -v * x, -v * y]); b.push(v);
    }
    for (let c = 0; c < 8; c++) {
        let p = c;
        for (let r = c + 1; r < 8; r++) if (Math.abs(A[r][c]) > Math.abs(A[p][c])) p = r;
        [A[c], A[p]] = [A[p], A[c]]; [b[c], b[p]] = [b[p], b[c]];
        for (let r = 0; r < 8; r++) {
            if (r === c) continue;
            const f = A[r][c] / A[c][c];
            for (let k = c; k < 8; k++) A[r][k] -= f * A[c][k];
            b[r] -= f * b[c];
        }
    }
    return b.map((v, i) => v / A[i][i]);
}

/** « Photographie » l'empreinte : W × H pixels, l'empreinte (carré de 447 unités) en `taille` px. */
function photographier(empreinte, c) {
    const W = 960, H = 720;
    const angle = c.angle ?? alea() * Math.PI * 2;
    const t = c.taille, cx = W / 2 + (alea() - 0.5) * (W - t) * 0.5, cy = H / 2 + (alea() - 0.5) * (H - t) * 0.4;
    const carre = [[0, 0], [VUE, 0], [VUE, VUE], [0, VUE]];
    const coins = carre.map(([x, y]) => {
        const dx = (x - VUE / 2) / VUE * t, dy = (y - VUE / 2) / VUE * t;
        return [cx + dx * Math.cos(angle) - dy * Math.sin(angle) + (alea() - 0.5) * 2 * c.perspective * t,
            cy + dx * Math.sin(angle) + dy * Math.cos(angle) + (alea() - 0.5) * 2 * c.perspective * t];
    });
    const inv = homographie(coins, carre);
    const L = VUE * RES;
    const scene = new Float32Array(W * H);
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
        let s = 0;
        for (const [ox, oy] of [[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]]) {
            const X = x + ox, Y = y + oy, w = inv[6] * X + inv[7] * Y + 1;
            const u = ((inv[0] * X + inv[1] * Y + inv[2]) / w) * RES, v = ((inv[3] * X + inv[4] * Y + inv[5]) / w) * RES;
            if (u < 0 || v < 0 || u >= L - 1 || v >= L - 1) { s += 200; continue; } // autour de l'écran : gris
            const u0 = Math.floor(u), v0 = Math.floor(v), fu = u - u0, fv = v - v0, i = v0 * L + u0;
            s += (empreinte[i] * (1 - fu) + empreinte[i + 1] * fu) * (1 - fv) + (empreinte[i + L] * (1 - fu) + empreinte[i + L + 1] * fu) * fv;
        }
        scene[y * W + x] = s / 4;
    }
    // Objets parasites : lignes de texte, un pouce.
    for (let k = 0; k < (c.parasites ?? 0); k++) {
        const x0 = Math.floor(alea() * W * 0.8), y0 = alea() < 0.5 ? Math.floor(alea() * 60) : H - 60 + Math.floor(alea() * 50);
        for (let y = y0; y < Math.min(H, y0 + 12); y++) for (let x = x0; x < Math.min(W, x0 + 150); x++) if ((x >> 2) % 3) scene[y * W + x] = 40;
    }
    // Lumière inégale, contraste réduit, bruit, puis flou de l'objectif.
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
        const i = y * W + x;
        scene[i] = (128 + (scene[i] - 128) * (c.contraste ?? 1)) * (1 - (c.ombre ?? 0) * (x / W)) + (alea() - 0.5) * 2 * (c.bruit ?? 0);
    }
    const r = c.flou ?? 0;
    const flou = (src, dx, dy) => {
        if (!r) return src;
        const out = new Float32Array(W * H);
        for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
            let s = 0, n = 0;
            for (let k = -r; k <= r; k++) {
                const X = x + k * dx, Y = y + k * dy;
                if (X >= 0 && Y >= 0 && X < W && Y < H) { s += src[Y * W + X]; n++; }
            }
            out[y * W + x] = s / n;
        }
        return out;
    };
    const finale = flou(flou(scene, 1, 0), 0, 1);
    return { px: Uint8ClampedArray.from(finale), l: W, h: H };
}

function png(nom, { px, l, h }) {
    if (!dossierImages) return;
    mkdirSync(dossierImages, { recursive: true });
    const lignes = Buffer.alloc((l + 1) * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) lignes[y * (l + 1) + 1 + x] = px[y * l + x];
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
    const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(l, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8;
    writeFileSync(join(dossierImages, `${nom}.png`), Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        bloc('IHDR', ihdr), bloc('IDAT', deflateSync(lignes)), bloc('IEND', Buffer.alloc(0)),
    ]));
}

const jeton = () => `RFL-${Math.floor(alea() * 100000)}-${Array.from({ length: 20 }, () => '0123456789abcdef'[Math.floor(alea() * 16)]).join('')}`;

// `attendu` : part minimale de lectures réussies (le jour J : un téléphone en scanne un autre de près).
const conditions = [
    { nom: 'de face, net, grand', taille: 600, perspective: 0, angle: 0, attendu: 1 },
    { nom: 'tournée au hasard, net', taille: 520, perspective: 0, attendu: 1 },
    { nom: 'photo : flou, bruit, contraste', taille: 520, perspective: 0.03, flou: 1, bruit: 14, contraste: 0.8, attendu: 0.9 },
    { nom: 'photo penchée (perspective)', taille: 520, perspective: 0.08, flou: 1, bruit: 10, contraste: 0.85, attendu: 0.8 },
    { nom: 'petite (360 px), flou', taille: 360, perspective: 0.03, flou: 1, bruit: 10, contraste: 0.85, attendu: 0.7 },
    { nom: 'ombre et objets autour', taille: 480, perspective: 0.04, flou: 1, bruit: 10, contraste: 0.85, ombre: 0.35, parasites: 4, attendu: 0.7 },
    { nom: 'difficile : 280 px, flou 2, bruit fort', taille: 280, perspective: 0.05, flou: 2, bruit: 22, contraste: 0.7, attendu: 0 },
    { nom: 'limite : très penchée', taille: 480, perspective: 0.15, flou: 1, bruit: 10, contraste: 0.85, attendu: 0 },
    { nom: 'limite : 200 px', taille: 200, perspective: 0.03, flou: 1, bruit: 10, contraste: 0.85, attendu: 0 },
    { nom: 'limite : flou 3 (mise au point ratée)', taille: 480, perspective: 0.03, flou: 3, bruit: 10, contraste: 0.85, attendu: 0 },
    { nom: 'empreinte décorative, sans code', taille: 520, perspective: 0.03, flou: 1, bruit: 10, sansCode: true },
];

const ESSAIS = Number(process.env.ESSAIS ?? 8);
let echec = false;

// Le code correcteur seul : tout ce qui est dans sa capacité se corrige, au-delà il refuse.
{
    const { encoder, decoder } = await import('../../resources/js/track/rs.js');
    let ok = 0, refus = 0;
    for (let k = 0; k < 400; k++) {
        const msg = Array.from({ length: 15 }, () => Math.floor(alea() * 256));
        const recu = encoder(msg, 17);
        const ordre = [...recu.keys()].sort(() => alea() - 0.5);
        const e = Math.floor(alea() * 9), f = Math.floor(alea() * (18 - 2 * e));
        ordre.slice(0, e).forEach((p) => { recu[p] ^= 1 + Math.floor(alea() * 255); });
        if (decoder(recu, 17, ordre.slice(e, e + f))?.join() === msg.join()) ok++;
        const trop = encoder(msg, 17);
        ordre.slice(0, 12).forEach((p) => { trop[p] ^= 1 + Math.floor(alea() * 255); });
        if (decoder(trop, 17) === null) refus++;
    }
    const bon = ok === 400 && refus === 400;
    if (!bon) echec = true;
    console.log(`${bon ? 'ok  ' : 'BAS '} Reed–Solomon : ${ok}/400 corrigés, ${refus}/400 refus au-delà de la capacité`);
}
for (const c of conditions) {
    let ok = 0, temps = 0;
    for (let k = 0; k < ESSAIS; k++) {
        const j = jeton();
        const photo = photographier(dessinerEmpreinte(j, c.sansCode), c);
        if (k === 0) png(c.nom.replace(/[^a-z0-9]+/gi, '-'), photo);
        const t0 = performance.now();
        const lu = lireEmpreinte(photo.px, photo.l, photo.h);
        temps += performance.now() - t0;
        if (c.sansCode) {
            // Rien à lire : le lecteur doit se taire, jamais inventer un jeton.
            if (lu) { console.log(`  FAUSSE ALERTE : ${lu.jeton}`); echec = true; } else ok++;
        } else if (lu?.jeton === j) ok++;
        else if (lu) { console.log(`  FAUX : ${lu.jeton} au lieu de ${j}`); echec = true; }
    }
    if (c.sansCode) c.attendu = 1;
    const part = ok / ESSAIS;
    if (part < c.attendu) echec = true;
    console.log(`${part >= c.attendu ? 'ok  ' : 'BAS '} ${String(ok).padStart(2)}/${ESSAIS}  ${c.nom.padEnd(42)} ${Math.round(temps / ESSAIS)} ms/lecture`);
}
process.exit(echec ? 1 : 0);
