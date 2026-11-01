/**
 * TRACK : lecteur du code des semelles, sur une image en niveaux de gris (une image de caméra).
 *
 * 1. Encre : ce qui est plus foncé que le papier ou l'écran autour (fond estimé par blocs).
 * 2. Repérage grossier : l'encre floutée forme des taches ; on cherche deux taches de même taille,
 *    côte à côte, à la bonne distance l'une de l'autre : les deux semelles.
 * 3. Calage fin : la forme du gabarit (points dedans / dehors) est ajustée sur l'image par une
 *    homographie (rotation, échelle, perspective), en maximisant le contraste dedans − dehors.
 *    Les deux sens sont essayés (semelle gauche ↔ droite) : la forme des semelles et le cœur
 *    départagent.
 * 4. Lecture : chaque cellule est échantillonnée en son centre ; un seuil automatique sépare
 *    foncées et claires, les cellules entre deux sont déclarées douteuses ; code.js corrige.
 *
 * Pur, sans navigateur : testé dans Node par tests/js/track-code.mjs.
 */
import { gabarit, jetonDesCellules } from './code.js';

const LARGEUR_TRAVAIL = 720;

const { boite, semelles } = gabarit;
const COINS = [[boite.x, boite.y], [boite.x + boite.l, boite.y], [boite.x + boite.l, boite.y + boite.h], [boite.x, boite.y + boite.h]];
const DEDANS = Float32Array.from(gabarit.reperes.dedans.flat());
const DEHORS = Float32Array.from(gabarit.reperes.dehors.flat());
const ECART_SEMELLES = Math.hypot(semelles.droite.cx - semelles.gauche.cx, semelles.droite.cy - semelles.gauche.cy);

/** Réduit l'image (moyenne par blocs) pour que son grand côté ne dépasse pas LARGEUR_TRAVAIL. */
function reduire(gris, l, h) {
    const k = Math.max(1, Math.ceil(Math.max(l, h) / LARGEUR_TRAVAIL));
    const L = Math.floor(l / k), H = Math.floor(h / k);
    const out = new Float32Array(L * H);
    for (let y = 0; y < H; y++) for (let x = 0; x < L; x++) {
        let s = 0;
        for (let j = 0; j < k; j++) for (let i = 0; i < k; i++) s += gris[(y * k + j) * l + x * k + i];
        out[y * L + x] = s / (k * k);
    }
    return { px: out, l: L, h: H, k };
}

/** Flou carré de rayon r (image intégrale). */
function flou(src, l, h, r) {
    if (r < 1) return src;
    const I = new Float64Array((l + 1) * (h + 1));
    for (let y = 0; y < h; y++) {
        let ligne = 0;
        for (let x = 0; x < l; x++) {
            ligne += src[y * l + x];
            I[(y + 1) * (l + 1) + x + 1] = I[y * (l + 1) + x + 1] + ligne;
        }
    }
    const out = new Float32Array(l * h);
    for (let y = 0; y < h; y++) {
        const y0 = Math.max(0, y - r), y1 = Math.min(h, y + r + 1);
        for (let x = 0; x < l; x++) {
            const x0 = Math.max(0, x - r), x1 = Math.min(l, x + r + 1);
            const s = I[y1 * (l + 1) + x1] - I[y0 * (l + 1) + x1] - I[y1 * (l + 1) + x0] + I[y0 * (l + 1) + x0];
            out[y * l + x] = s / ((x1 - x0) * (y1 - y0));
        }
    }
    return out;
}

/** Encre : 0 sur le fond, ~0,4 sur le vert doux, ~0,75 sur le vert sombre. */
function encre({ px, l, h }) {
    const B = 16;
    const bl = Math.ceil(l / B), bh = Math.ceil(h / B);
    const lisse = flou(px, l, h, 1);
    const fond = new Float32Array(bl * bh);
    for (let by = 0; by < bh; by++) for (let bx = 0; bx < bl; bx++) {
        let max = 0;
        for (let y = by * B; y < Math.min(h, by * B + B); y++) for (let x = bx * B; x < Math.min(l, bx * B + B); x++) max = Math.max(max, lisse[y * l + x]);
        fond[by * bl + bx] = max;
    }
    // Un bloc tout entier dans une semelle ne voit pas le fond : on prend le plus clair des voisins.
    const fond2 = new Float32Array(bl * bh);
    for (let by = 0; by < bh; by++) for (let bx = 0; bx < bl; bx++) {
        let max = 0;
        for (let j = -2; j <= 2; j++) for (let i = -2; i <= 2; i++) {
            const x = bx + i, y = by + j;
            if (x >= 0 && y >= 0 && x < bl && y < bh) max = Math.max(max, fond[y * bl + x]);
        }
        fond2[by * bl + bx] = max;
    }
    const d = new Float32Array(l * h);
    for (let y = 0; y < h; y++) for (let x = 0; x < l; x++) {
        const f = fond2[Math.min(bh - 1, Math.floor(y / B)) * bl + Math.min(bl - 1, Math.floor(x / B))];
        d[y * l + x] = f > 8 ? Math.max(0, 1 - px[y * l + x] / f) : 0;
    }
    return d;
}

/** Taches d'encre (composantes connexes du seuil), avec surface et centre. */
function taches(d, l, h, r) {
    const D = flou(d, l, h, r);
    const vu = new Int32Array(l * h).fill(-1);
    const liste = [];
    const pile = new Int32Array(l * h);
    for (let i = 0; i < l * h; i++) {
        if (vu[i] >= 0 || D[i] < 0.12) continue;
        let n = 0, sx = 0, sy = 0, haut = 0;
        pile[haut++] = i;
        vu[i] = liste.length;
        while (haut) {
            const p = pile[--haut];
            const x = p % l, y = (p - x) / l;
            n++; sx += x; sy += y;
            for (const q of [p - 1, p + 1, p - l, p + l]) {
                if (q < 0 || q >= l * h || vu[q] >= 0 || D[q] < 0.12) continue;
                if ((q === p - 1 && x === 0) || (q === p + 1 && x === l - 1)) continue;
                vu[q] = liste.length;
                pile[haut++] = q;
            }
        }
        liste.push({ n, cx: sx / n, cy: sy / n });
    }
    return liste.filter((t) => t.n > l * h * 0.002);
}

/** Paires de taches qui ressemblent aux deux semelles, les plus vraisemblables d'abord. */
function pairesCandidates(d, l, h) {
    const paires = [];
    // Chaque semelle est faite de plusieurs morceaux : il faut un flou assez large pour les réunir,
    // quelle que soit la taille de l'empreinte dans l'image.
    for (const r of [3, 6, 10, 15, 22]) {
        const t = taches(d, l, h, r);
        for (let i = 0; i < t.length; i++) for (let j = i + 1; j < t.length; j++) {
            const a = t[i], b = t[j];
            const rapport = a.n / b.n;
            if (rapport < 0.5 || rapport > 2) continue;
            const echelle = Math.hypot(b.cx - a.cx, b.cy - a.cy) / ECART_SEMELLES;
            const attendu = semelles.gauche.aire * echelle * echelle;
            const q = (a.n + b.n) / 2 / attendu;
            if (q < 0.45 || q > 2.5) continue;
            paires.push({ a, b, echelle, ecart: Math.abs(Math.log(q)) + Math.abs(Math.log(rapport)) });
        }
    }
    return paires.sort((p, q) => p.ecart - q.ecart).slice(0, 4);
}

/** Homographie qui envoie les 4 coins du gabarit sur `coins` (8 coefficients). */
function homographie(coins) {
    const A = [], b = [];
    for (let i = 0; i < 4; i++) {
        const [x, y] = COINS[i], [u, v] = coins[i];
        A.push([x, y, 1, 0, 0, 0, -u * x, -u * y]); b.push(u);
        A.push([0, 0, 0, x, y, 1, -v * x, -v * y]); b.push(v);
    }
    for (let c = 0; c < 8; c++) {
        let p = c;
        for (let r = c + 1; r < 8; r++) if (Math.abs(A[r][c]) > Math.abs(A[p][c])) p = r;
        [A[c], A[p]] = [A[p], A[c]]; [b[c], b[p]] = [b[p], b[c]];
        if (Math.abs(A[c][c]) < 1e-12) return null;
        for (let r = 0; r < 8; r++) {
            if (r === c) continue;
            const f = A[r][c] / A[c][c];
            for (let k = c; k < 8; k++) A[r][k] -= f * A[c][k];
            b[r] -= f * b[c];
        }
    }
    return b.map((v, i) => v / A[i][i]);
}

function echantillon(D, l, h, x, y) {
    if (x < 0 || y < 0 || x > l - 1.001 || y > h - 1.001) return 0;
    const x0 = Math.floor(x), y0 = Math.floor(y), fx = x - x0, fy = y - y0, i = y0 * l + x0;
    return (D[i] * (1 - fx) + D[i + 1] * fx) * (1 - fy) + (D[i + l] * (1 - fx) + D[i + l + 1] * fx) * fy;
}

function contraste(D, l, h, H) {
    if (!H) return -1;
    const moyenne = (P) => {
        let s = 0;
        for (let i = 0; i < P.length; i += 2) {
            const x = P[i], y = P[i + 1], w = H[6] * x + H[7] * y + 1;
            s += echantillon(D, l, h, (H[0] * x + H[1] * y + H[2]) / w, (H[3] * x + H[4] * y + H[5]) / w);
        }
        return s / (P.length / 2);
    };
    return moyenne(DEDANS) - moyenne(DEHORS);
}

/** Ajuste les 4 coins pour coller la forme du gabarit à l'image (descente coordonnée par coordonnée). */
function caler(D, l, h, coins, pas, pasMin) {
    const note = (c) => contraste(D, l, h, homographie(c));
    let meilleur = note(coins);
    const centre = () => [coins.reduce((s, c) => s + c[0], 0) / 4, coins.reduce((s, c) => s + c[1], 0) / 4];
    const mouvements = [
        (c, s) => c.map(([x, y]) => [x + s, y]),
        (c, s) => c.map(([x, y]) => [x, y + s]),
        (c, s) => { const [cx, cy] = centre(); const f = 1 + s / 200; return c.map(([x, y]) => [cx + (x - cx) * f, cy + (y - cy) * f]); },
        (c, s) => { const [cx, cy] = centre(); const a = s / 200, co = Math.cos(a), si = Math.sin(a); return c.map(([x, y]) => [cx + (x - cx) * co - (y - cy) * si, cy + (x - cx) * si + (y - cy) * co]); },
        ...[0, 1, 2, 3].flatMap((k) => [
            (c, s) => c.map((p, i) => (i === k ? [p[0] + s, p[1]] : p)),
            (c, s) => c.map((p, i) => (i === k ? [p[0], p[1] + s] : p)),
        ]),
    ];
    while (pas >= pasMin) {
        let progres = false;
        for (const bouger of mouvements) {
            for (const s of [pas, -pas]) {
                const essai = bouger(coins, s);
                const n = note(essai);
                if (n > meilleur) { meilleur = n; coins = essai; progres = true; break; }
            }
        }
        if (!progres) pas /= 2;
    }
    return { coins, note: meilleur };
}

/** Seuil d'Otsu sur une liste de valeurs. */
function otsu(v) {
    const tri = [...v].sort((a, b) => a - b);
    let meilleur = { t: tri[0], sep: -1 };
    let gauche = 0;
    const total = tri.reduce((s, x) => s + x, 0);
    for (let i = 1; i < tri.length; i++) {
        gauche += tri[i - 1];
        const n0 = i, n1 = tri.length - i;
        const m0 = gauche / n0, m1 = (total - gauche) / n1;
        const sep = n0 * n1 * (m1 - m0) ** 2;
        if (sep > meilleur.sep) meilleur = { t: (tri[i - 1] + tri[i]) / 2, sep, m0, m1 };
    }
    return meilleur;
}

const POSITIONS = [0.22, 0.407, 0.593, 0.78];

function lireCellules(d, l, h, H) {
    const c = gabarit.cellule;
    const valeurs = gabarit.cellules.map(([cx, cy]) => {
        const v = [];
        for (const fy of POSITIONS) for (const fx of POSITIONS) {
            const x = cx + fx * c, y = cy + fy * c, w = H[6] * x + H[7] * y + 1;
            v.push(echantillon(d, l, h, (H[0] * x + H[1] * y + H[2]) / w, (H[3] * x + H[4] * y + H[5]) / w));
        }
        // Les particules sont plus foncées que leurs interstices : on garde les 6 points les plus foncés.
        v.sort((a, b) => b - a);
        return (v[0] + v[1] + v[2] + v[3] + v[4] + v[5]) / 6;
    });
    const { t, m0, m1 } = otsu(valeurs);
    if (!(m1 - m0 > 0.06)) return null;
    const marge = (m1 - m0) * 0.18;
    return jetonDesCellules(valeurs.map((v) => v > t), valeurs.map((v) => Math.abs(v - t) < marge));
}

/**
 * Lit une empreinte dans une image en niveaux de gris (`gris[y * l + x]`, 0 à 255).
 * Renvoie { jeton, coins } (coins de la boîte des semelles, en pixels de l'image), ou null.
 */
export function lireEmpreinte(gris, l, h) {
    const img = reduire(gris, l, h);
    const d = encre(img);
    for (const p of pairesCandidates(d, img.l, img.h)) {
        const essais = [];
        for (const [g, dr] of [[p.a, p.b], [p.b, p.a]]) {
            // Similitude : centre des semelles du gabarit → centres des taches.
            const vx = semelles.droite.cx - semelles.gauche.cx, vy = semelles.droite.cy - semelles.gauche.cy;
            const ux = dr.cx - g.cx, uy = dr.cy - g.cy;
            const s = Math.hypot(ux, uy) / Math.hypot(vx, vy);
            const a = Math.atan2(uy, ux) - Math.atan2(vy, vx);
            const co = Math.cos(a) * s, si = Math.sin(a) * s;
            const coins = COINS.map(([x, y]) => {
                const dx = x - semelles.gauche.cx, dy = y - semelles.gauche.cy;
                return [g.cx + dx * co - dy * si, g.cy + dx * si + dy * co];
            });
            // Grossier sur l'encre floutée (la forme attire de loin), puis fin.
            const grossier = caler(flou(d, img.l, img.h, Math.max(1, Math.round(s * 3))), img.l, img.h, coins, s * 10, s * 1.5);
            const fin = caler(flou(d, img.l, img.h, Math.max(1, Math.round(s * 0.8))), img.l, img.h, grossier.coins, s * 2, Math.max(0.15, s * 0.1));
            essais.push(fin);
        }
        essais.sort((x, y) => y.note - x.note);
        for (const e of essais) {
            if (e.note < 0.08) continue;
            const H = homographie(e.coins);
            const jeton = H && lireCellules(d, img.l, img.h, H);
            if (jeton) return { jeton, coins: e.coins.map(([x, y]) => [x * img.k, y * img.k]) };
        }
    }
    return null;
}

/** Même lecture à partir d'une image RGBA (getImageData d'un canevas). */
export function lireEmpreinteRGBA(rgba, l, h) {
    const gris = new Float32Array(l * h);
    for (let i = 0; i < l * h; i++) gris[i] = 0.299 * rgba[i * 4] + 0.587 * rgba[i * 4 + 1] + 0.114 * rgba[i * 4 + 2];
    return lireEmpreinte(gris, l, h);
}
