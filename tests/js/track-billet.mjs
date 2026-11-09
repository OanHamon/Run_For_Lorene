/**
 * Banc du billet imprimé (PDF), sans navigateur :
 *
 *   npm run test:billet                       # sort en erreur si une vérification échoue
 *   node tests/js/track-billet.mjs --png DIR  # écrit aussi une image par condition
 *
 * 1. Le serveur (App\Support\Empreinte, PHP) et le navigateur (code.js, masque.js) donnent
 *    exactement les mêmes cellules et les mêmes particules pour un même jeton.
 * 2. L'image PNG produite par le serveur pour le PDF est lue par le lecteur du poste de retrait
 *    (lecteur.js) après « impression » (couleur, noir et blanc tramé, encre pâle) et photo de la
 *    feuille (rotation, perspective, flou, bruit, lumière inégale).
 */
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
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
const jeton = () => `RFL-${Math.floor(alea() * 100000)}-${Array.from({ length: 20 }, () => '0123456789abcdef'[Math.floor(alea() * 16)]).join('')}`;

const ESSAIS = Number(process.env.ESSAIS ?? 6);
const jetons = [...Array.from({ length: ESSAIS }, jeton), 'RFL-1-00000000000000000000', 'RFL-4294967295-ffffffffffffffffffff'];

const travail = mkdtempSync(join(tmpdir(), 'rfl-billet-'));
const php = JSON.parse(execFileSync('php', [join(racine, 'tests/js/empreinte-php.php'), '--dossier', travail, ...jetons], { maxBuffer: 1 << 28 }).toString());

let echec = false;
const verdict = (bon, texte) => {
    if (!bon) echec = true;
    console.log(`${bon ? 'ok  ' : 'BAS '} ${texte}`);
};

// 1. Même empreinte côté serveur et côté navigateur.
const masque = pixelliser(readFileSync(join(racine, 'resources/svg/track/semelles.svg'), 'utf8'));
{
    let cellulesOk = 0, particulesOk = 0, ecart = 0;
    for (const j of jetons) {
        const cellules = cellulesDuJeton(j);
        if (php[j].cellules.join() === Array.from(cellules).join()) cellulesOk++;

        const { liste } = particules(masque, j, 3.8);
        colorerParticules(liste, cellules);
        const p = php[j].particules;
        let pareil = p.length === liste.length;
        for (let i = 0; pareil && i < p.length; i++) {
            const q = liste[i];
            const d = Math.max(Math.abs(q.x - p[i][0]), Math.abs(q.y - p[i][1]), Math.abs(q.r - p[i][2]));
            ecart = Math.max(ecart, d);
            if (d > 1e-9 || q.nuance !== p[i][3]) pareil = false;
        }
        if (pareil) particulesOk++;
    }
    verdict(cellulesOk === jetons.length, `cellules identiques PHP / JS : ${cellulesOk}/${jetons.length}`);
    verdict(particulesOk === jetons.length, `particules identiques PHP / JS : ${particulesOk}/${jetons.length} (écart max ${ecart.toExponential(1)})`);
    const ms = jetons.map((j) => php[j].ms), ko = jetons.map((j) => php[j].octetsPng / 1024);
    console.log(`     image PHP : ${Math.max(...ms)} ms au plus, ${Math.round(Math.max(...ko))} Ko au plus`);
}

// 2. Lecture de la feuille imprimée.
const BAYER = [0, 8, 2, 10, 12, 4, 14, 6, 3, 11, 1, 9, 15, 7, 13, 5].map((v) => (v + 0.5) / 16);

/** « Imprime » l'image : l'encre assombrit le papier selon `encre` ; `trame` : noir et blanc tramé (pas de la trame en px). */
function imprimer(src, l, h, c) {
    const out = new Float32Array(l * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) {
        const i = y * l + x;
        const papier = 248;
        let v = papier - (255 - src[i]) * (c.encre ?? 1) * (papier / 255);
        if (c.trame) {
            const s = BAYER[(Math.floor(y / c.trame) % 4) * 4 + (Math.floor(x / c.trame) % 4)];
            v = v / papier > s ? papier : 25;
        }
        out[i] = v;
    }
    return out;
}

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

/** Photo de la feuille : l'empreinte occupe `taille` px de large dans une image W × H, autour la feuille blanche. */
function photographier(src, l, h, c) {
    const W = 960, H = 720;
    const angle = c.angle ?? (alea() - 0.5) * 0.8;
    const t = c.taille, ht = t * h / l, cx = W / 2 + (alea() - 0.5) * 80, cy = H / 2 + (alea() - 0.5) * 60;
    const carre = [[0, 0], [l, 0], [l, h], [0, h]];
    const coins = carre.map(([x, y]) => {
        const dx = (x / l - 0.5) * t, dy = (y / h - 0.5) * ht;
        return [cx + dx * Math.cos(angle) - dy * Math.sin(angle) + (alea() - 0.5) * 2 * c.perspective * t,
            cy + dx * Math.sin(angle) + dy * Math.cos(angle) + (alea() - 0.5) * 2 * c.perspective * t];
    });
    const inv = homographie(coins, carre);
    const scene = new Float32Array(W * H);
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
        let s = 0;
        for (const [ox, oy] of [[0.25, 0.25], [0.75, 0.25], [0.25, 0.75], [0.75, 0.75]]) {
            const X = x + ox, Y = y + oy, w = inv[6] * X + inv[7] * Y + 1;
            const u = (inv[0] * X + inv[1] * Y + inv[2]) / w, v = (inv[3] * X + inv[4] * Y + inv[5]) / w;
            if (u < 0 || v < 0 || u >= l - 1 || v >= h - 1) { s += 245; continue; } // reste de la feuille
            // Moyenne de la zone couverte par le pixel de la photo (l'image source est bien plus fine).
            const pas = Math.max(1, Math.round(l / t / 2));
            let somme = 0, n = 0;
            for (let dv = 0; dv < pas; dv++) for (let du = 0; du < pas; du++) {
                const uu = Math.min(l - 1, Math.floor(u) + du), vv = Math.min(h - 1, Math.floor(v) + dv);
                somme += src[vv * l + uu]; n++;
            }
            s += somme / n;
        }
        scene[y * W + x] = s / 4;
    }
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
        const i = y * W + x;
        scene[i] = (128 + (scene[i] - 128) * (c.contraste ?? 1)) * (1 - (c.ombre ?? 0) * (x / W)) + (alea() - 0.5) * 2 * (c.bruit ?? 0);
    }
    const r = c.flou ?? 0;
    const flou = (src2, dx, dy) => {
        if (!r) return src2;
        const out = new Float32Array(W * H);
        for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
            let s = 0, n = 0;
            for (let k = -r; k <= r; k++) {
                const X = x + k * dx, Y = y + k * dy;
                if (X >= 0 && Y >= 0 && X < W && Y < H) { s += src2[Y * W + X]; n++; }
            }
            out[y * W + x] = s / n;
        }
        return out;
    };
    return { px: Uint8ClampedArray.from(flou(flou(scene, 1, 0), 0, 1)), l: W, h: H };
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

const conditions = [
    { nom: 'imprimante couleur, photo de face', taille: 520, perspective: 0, angle: 0, attendu: 1 },
    { nom: 'imprimante couleur, photo penchée, flou', taille: 480, perspective: 0.06, flou: 1, bruit: 10, contraste: 0.85, attendu: 0.8 },
    { nom: 'noir et blanc tramé (laser)', trame: 2, taille: 480, perspective: 0.03, flou: 1, bruit: 8, attendu: 0.8 },
    { nom: 'noir et blanc, trame grossière', trame: 4, taille: 480, perspective: 0.03, flou: 1, bruit: 8, attendu: 0.8 },
    { nom: 'encre pâle (cartouche presque vide)', encre: 0.55, taille: 480, perspective: 0.03, flou: 1, bruit: 8, contraste: 0.85, attendu: 0.8 },
    { nom: 'feuille à l\'ombre, de loin (300 px)', taille: 300, perspective: 0.04, flou: 1, bruit: 12, contraste: 0.8, ombre: 0.35, attendu: 0.6 },
];

for (const c of conditions) {
    let ok = 0;
    jetons.slice(0, ESSAIS).forEach((j, k) => {
        const { fichier, l, h } = php[j].image;
        const gris = Float32Array.from(readFileSync(fichier));
        const photo = photographier(imprimer(gris, l, h, c), l, h, c);
        if (k === 0) png(c.nom.replace(/[^a-z0-9]+/gi, '-'), photo);
        const lu = lireEmpreinte(photo.px, photo.l, photo.h);
        if (lu?.jeton === j) ok++;
        else if (lu) { console.log(`  FAUX : ${lu.jeton} au lieu de ${j}`); echec = true; }
    });
    const part = ok / ESSAIS;
    verdict(part >= c.attendu, `${String(ok).padStart(2)}/${ESSAIS}  ${c.nom}`);
}

rmSync(travail, { recursive: true, force: true });
process.exit(echec ? 1 : 0);
