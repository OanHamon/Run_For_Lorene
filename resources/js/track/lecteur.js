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
// Contraste minimal du calage pour tenter la lecture. Bas exprès : une caméra qui filme un écran
// donne peu de contraste, et une lecture tentée pour rien ne coûte que du calcul (le code
// correcteur et la signature vérifiée par le serveur écartent toute fausse lecture).
const CALAGE_MIN = 0.03;

/**
 * Ce qui s'est passé à la dernière lecture, pour comprendre un échec (banc d'essai /dev/qr) :
 * image de travail, paires de semelles candidates, meilleur calage (contraste dedans − dehors),
 * écart entre cellules claires et foncées, résultat.
 */
export const diagnostic = {};

const { boite, semelles } = gabarit;
const COINS = [[boite.x, boite.y], [boite.x + boite.l, boite.y], [boite.x + boite.l, boite.y + boite.h], [boite.x, boite.y + boite.h]];
const DEDANS = Float32Array.from(gabarit.reperes.dedans.flat());
const DEHORS = Float32Array.from(gabarit.reperes.dehors.flat());
const ECART_SEMELLES = Math.hypot(semelles.droite.cx - semelles.gauche.cx, semelles.droite.cy - semelles.gauche.cy);
// L'empreinte entière vue de loin : centre et « rayon » (écart type) des points de la forme.
const FORME = (() => {
    const n = DEDANS.length / 2;
    let sx = 0, sy = 0;
    for (let i = 0; i < DEDANS.length; i += 2) { sx += DEDANS[i]; sy += DEDANS[i + 1]; }
    const cx = sx / n, cy = sy / n;
    let v = 0;
    for (let i = 0; i < DEDANS.length; i += 2) v += (DEDANS[i] - cx) ** 2 + (DEDANS[i + 1] - cy) ** 2;
    return { cx, cy, rayon: Math.sqrt(v / n) };
})();

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

/**
 * Encre : 0 sur le fond, ~0,4 sur le vert doux, ~0,75 sur le vert sombre.
 * Le fond est estimé par une « fermeture » sur une grille de blocs : le plus clair des environs,
 * puis le plus sombre de ces maxima. Il suit ainsi les variations lentes de lumière (un écran
 * photographié n'est jamais uniformément blanc) sans avaler les semelles.
 */
function encre({ px, l, h }) {
    const B = 8, K = 3;
    const bl = Math.ceil(l / B), bh = Math.ceil(h / B);
    const lisse = flou(px, l, h, 1);
    let grille = new Float32Array(bl * bh);
    for (let by = 0; by < bh; by++) for (let bx = 0; bx < bl; bx++) {
        let max = 0;
        for (let y = by * B; y < Math.min(h, by * B + B); y++) for (let x = bx * B; x < Math.min(l, bx * B + B); x++) max = Math.max(max, lisse[y * l + x]);
        grille[by * bl + bx] = max;
    }
    const voisinage = (src, plusClair) => {
        const out = new Float32Array(bl * bh);
        for (let by = 0; by < bh; by++) for (let bx = 0; bx < bl; bx++) {
            let v = plusClair ? 0 : 255;
            for (let j = -K; j <= K; j++) for (let i = -K; i <= K; i++) {
                const x = bx + i, y = by + j;
                if (x < 0 || y < 0 || x >= bl || y >= bh) continue;
                v = plusClair ? Math.max(v, src[y * bl + x]) : Math.min(v, src[y * bl + x]);
            }
            out[by * bl + bx] = v;
        }
        return out;
    };
    grille = voisinage(voisinage(grille, true), false);

    // Fond lissé (interpolation entre les centres des blocs), puis encre relative au fond.
    const d = new Float32Array(l * h);
    for (let y = 0; y < h; y++) {
        const gy = Math.min(bh - 1, Math.max(0, (y + 0.5) / B - 0.5)), y0 = Math.floor(gy), y1 = Math.min(bh - 1, y0 + 1), fy = gy - y0;
        for (let x = 0; x < l; x++) {
            const gx = Math.min(bl - 1, Math.max(0, (x + 0.5) / B - 0.5)), x0 = Math.floor(gx), x1 = Math.min(bl - 1, x0 + 1), fx = gx - x0;
            const f = (grille[y0 * bl + x0] * (1 - fx) + grille[y0 * bl + x1] * fx) * (1 - fy) + (grille[y1 * bl + x0] * (1 - fx) + grille[y1 * bl + x1] * fx) * fy;
            d[y * l + x] = f > 8 ? Math.max(0, 1 - px[y * l + x] / f) : 0;
        }
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
        let n = 0, sx = 0, sy = 0, sxx = 0, haut = 0;
        pile[haut++] = i;
        vu[i] = liste.length;
        while (haut) {
            const p = pile[--haut];
            const x = p % l, y = (p - x) / l;
            n++; sx += x; sy += y; sxx += x * x + y * y;
            for (const q of [p - 1, p + 1, p - l, p + l]) {
                if (q < 0 || q >= l * h || vu[q] >= 0 || D[q] < 0.12) continue;
                if ((q === p - 1 && x === 0) || (q === p + 1 && x === l - 1)) continue;
                vu[q] = liste.length;
                pile[haut++] = q;
            }
        }
        const cx = sx / n, cy = sy / n;
        liste.push({ n, cx, cy, rayon: Math.sqrt(Math.max(0, sxx / n - cx * cx - cy * cy)) });
    }
    return liste.filter((t) => t.n > l * h * 0.002);
}

/** Coins du gabarit déplacés par une similitude : point (gx, gy) du gabarit → (ix, iy), échelle s, angle a. */
function similitude(gx, gy, ix, iy, s, a) {
    const co = Math.cos(a) * s, si = Math.sin(a) * s;
    return COINS.map(([x, y]) => [ix + (x - gx) * co - (y - gy) * si, iy + (x - gx) * si + (y - gy) * co]);
}

/**
 * Positions de départ du calage : { coins, s } (s : pixels par unité du gabarit).
 * - Deux taches pareilles côte à côte : les deux semelles (position et sens presque exacts).
 * - Une tache qui a la taille de toute l'empreinte (ses morceaux réunis par le flou) : position et
 *   taille connues, sens inconnu ; on essaie 24 orientations. Plus sûr en petit et sur une vraie
 *   photo, où les morceaux d'une même semelle se prennent facilement pour une paire.
 */
function departs(d, l, h) {
    const liste = [];
    for (const r of [3, 6, 10, 15, 22]) {
        const t = taches(d, l, h, r);
        for (let i = 0; i < t.length; i++) for (let j = i + 1; j < t.length; j++) {
            const a = t[i], b = t[j];
            const rapport = a.n / b.n;
            if (rapport < 0.5 || rapport > 2) continue;
            const s = Math.hypot(b.cx - a.cx, b.cy - a.cy) / ECART_SEMELLES;
            const q = (a.n + b.n) / 2 / (semelles.gauche.aire * s * s);
            if (q < 0.45 || q > 2.5) continue;
            for (const [g, dr] of [[a, b], [b, a]]) {
                const angle = Math.atan2(dr.cy - g.cy, dr.cx - g.cx) - Math.atan2(semelles.droite.cy - semelles.gauche.cy, semelles.droite.cx - semelles.gauche.cx);
                liste.push({ coins: similitude(semelles.gauche.cx, semelles.gauche.cy, g.cx, g.cy, s, angle), s });
            }
        }
        for (const e of t) {
            if (e.n > l * h * 0.7 || e.n < l * h * 0.01) continue;
            const s = e.rayon / FORME.rayon;
            for (let k = 0; k < 24; k++) liste.push({ coins: similitude(FORME.cx, FORME.cy, e.cx, e.cy, s, (k * Math.PI) / 12), s });
        }
    }
    return liste;
}

/**
 * Encre utile à la recherche : on efface ce qui touche le bord de l'image (cadre d'écran, doigt,
 * table) et les très grandes masses. Il reste l'empreinte (si elle est entière) et du menu fretin.
 * Renvoie aussi le centre et le « rayon » de cette encre : la position et la taille de l'empreinte.
 */
function nettoyer(d, l, h) {
    const D = flou(d, l, h, 2);
    const seuil = 0.15;
    const propre = new Float32Array(l * h);
    const vu = new Uint8Array(l * h);
    const pile = new Int32Array(l * h);
    const membres = new Int32Array(l * h);
    let n = 0, sx = 0, sy = 0, sxx = 0;
    for (let i = 0; i < l * h; i++) {
        if (vu[i] || D[i] < seuil) continue;
        let haut = 0, taille = 0, bord = false;
        pile[haut++] = i;
        vu[i] = 1;
        while (haut) {
            const q = pile[--haut];
            membres[taille++] = q;
            const x = q % l, y = (q - x) / l;
            if (x === 0 || y === 0 || x === l - 1 || y === h - 1) bord = true;
            if (x > 0 && !vu[q - 1] && D[q - 1] >= seuil) { vu[q - 1] = 1; pile[haut++] = q - 1; }
            if (x < l - 1 && !vu[q + 1] && D[q + 1] >= seuil) { vu[q + 1] = 1; pile[haut++] = q + 1; }
            if (y > 0 && !vu[q - l] && D[q - l] >= seuil) { vu[q - l] = 1; pile[haut++] = q - l; }
            if (y < h - 1 && !vu[q + l] && D[q + l] >= seuil) { vu[q + l] = 1; pile[haut++] = q + l; }
        }
        if (bord || taille > l * h * 0.4 || taille < 12) continue;
        for (let k = 0; k < taille; k++) {
            const q = membres[k], x = q % l, y = (q - x) / l;
            propre[q] = d[q];
            n++; sx += x; sy += y; sxx += x * x + y * y;
        }
    }
    const cx = sx / n, cy = sy / n;
    return { propre, n, cx, cy, rayon: Math.sqrt(Math.max(0, sxx / n - cx * cx - cy * cy)) };
}

/**
 * Départs tirés de l'encre nettoyée : l'empreinte entière, centre et taille connus, orientation
 * inconnue (36 essais, tous les 10°), trois tailles autour de l'estimation.
 */
function departsEncre(net) {
    if (net.n < 50) return [];
    const liste = [];
    // Autour du centre estimé (décalé si l'empreinte est en partie coupée par le bord).
    for (const f of [0.8, 0.92, 1.05, 1.2]) {
        const s = (net.rayon / FORME.rayon) * f;
        const pas = net.rayon * 0.18;
        for (const [dx, dy] of [[0, 0], [0, -1], [0, 1], [-1, 0], [1, 0], [0, -2], [0, 2], [-2, 0], [2, 0]]) {
            for (let k = 0; k < 36; k++) liste.push({ coins: similitude(FORME.cx, FORME.cy, net.cx + dx * pas, net.cy + dy * pas, s, (k * Math.PI) / 18), s });
        }
    }
    return liste;
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

function contraste(D, l, h, H, pas = 1) {
    if (!H) return -1;
    // Les points hors de l'image ne comptent pas (sinon un bord d'image passerait pour du « blanc ») ;
    // une forme dont plus d'un tiers sort du cadre n'est pas retenue.
    const moyenne = (P) => {
        let s = 0, n = 0, total = 0;
        for (let i = 0; i < P.length; i += 2 * pas) {
            const x = P[i], y = P[i + 1], w = H[6] * x + H[7] * y + 1;
            const u = (H[0] * x + H[1] * y + H[2]) / w, v = (H[3] * x + H[4] * y + H[5]) / w;
            total++;
            if (u < 0 || v < 0 || u > l - 1.001 || v > h - 1.001) continue;
            s += echantillon(D, l, h, u, v);
            n++;
        }
        return n >= total * 0.66 ? s / n : NaN;
    };
    const r = moyenne(DEDANS) - moyenne(DEHORS);
    return Number.isNaN(r) ? -1 : r;
}

/** Surface (signée) du quadrilatère des coins. */
const surface = (c) => c.reduce((s, [x, y], i) => s + x * c[(i + 1) % 4][1] - c[(i + 1) % 4][0] * y, 0) / 2;

function caler(D, l, h, coins, pas, pasMin, echantillonnage = 1) {
    // Le calage ne peut ni retourner, ni trop grossir ou rétrécir la forme, ni la déformer au-delà
    // d'une photo prise de biais : sinon il finit par se loger sur un seul morceau de semelle.
    const surface0 = surface(coins);
    const plausible = (c) => {
        const q = surface(c) / surface0;
        if (!(q > 0.55 && q < 1.8)) return false;
        const cotes = c.map(([x, y], i) => Math.hypot(c[(i + 1) % 4][0] - x, c[(i + 1) % 4][1] - y));
        return Math.max(...cotes) / Math.min(...cotes) < 1.7;
    };
    const note = (c) => (plausible(c) ? contraste(D, l, h, homographie(c), echantillonnage) : -1);
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
    const dehors = new Uint8Array(gabarit.cellules.length);
    const valeurs = gabarit.cellules.map(([cx, cy], i) => {
        const v = [];
        for (const fy of POSITIONS) for (const fx of POSITIONS) {
            const x = cx + fx * c, y = cy + fy * c, w = H[6] * x + H[7] * y + 1;
            const u = (H[0] * x + H[1] * y + H[2]) / w, t = (H[3] * x + H[4] * y + H[5]) / w;
            if (u < 1 || t < 1 || u > l - 2 || t > h - 2) dehors[i] = 1;
            v.push(echantillon(d, l, h, u, t));
        }
        // Les particules sont plus foncées que leurs interstices : on garde les 6 points les plus foncés.
        v.sort((a, b) => b - a);
        return (v[0] + v[1] + v[2] + v[3] + v[4] + v[5]) / 6;
    });
    // Une cellule hors de l'image (empreinte coupée par le bord) est illisible, pas « claire ».
    const { t, m0, m1 } = otsu(valeurs.filter((_, i) => !dehors[i]));
    diagnostic.ecartCellules = Math.round((m1 - m0) * 1000) / 1000;
    diagnostic.horsImage = dehors.reduce((s, x) => s + x, 0);
    if (!(m1 - m0 > 0.06)) return null;

    const marge = (m1 - m0) * 0.18;
    return jetonDesCellules(valeurs.map((v) => v > t), valeurs.map((v, i) => dehors[i] || Math.abs(v - t) < marge));
}

/**
 * Lit une empreinte dans une image en niveaux de gris (`gris[y * l + x]`, 0 à 255).
 * Renvoie { jeton, coins } (coins de la boîte des semelles, en pixels de l'image), ou null.
 */
export function lireEmpreinte(gris, l, h) {
    const img = reduire(gris, l, h);
    const d = encre(img);
    const net = nettoyer(d, img.l, img.h);
    const cache = new Map();
    const flouDe = (src, nom) => (r) => {
        r = Math.max(1, Math.round(r));
        const cle = `${nom}${r}`;
        if (!cache.has(cle)) cache.set(cle, flou(src, img.l, img.h, r));
        return cache.get(cle);
    };
    const floue = flouDe(d, 'd');
    const floueNette = flouDe(net.propre, 'n');

    // 1. Départs : l'empreinte entière dans l'encre nettoyée (toutes orientations), et les paires
    //    de taches qui ressemblent aux deux semelles ; notés tels quels, on garde les meilleurs.
    const noter = (p) => ({ ...p, note: contraste(floueNette(p.s * 6), img.l, img.h, homographie(p.coins), 3) });
    const meilleurs = (l, n) => l.map(noter).sort((a, b) => b.note - a.note).slice(0, n);
    const liste = [
        ...meilleurs(departsEncre(net), 8),
        ...meilleurs(departs(net.propre, img.l, img.h), 3),
    ];
    Object.assign(diagnostic, { image: `${img.l} × ${img.h}`, paires: liste.length, calage: null, coins: null, ecartCellules: null, horsImage: null, etape: liste.length ? 'calage' : 'empreinte introuvable' });

    // 2. Calage grossier des meilleurs départs, puis fin des meilleurs calages, puis lecture.
    // Le calage se fait sur toute l'encre : le nettoyage ne sert qu'à trouver où commencer
    // (une empreinte coupée par le bord de l'image garde ainsi tous ses morceaux).
    const grossiers = liste.map((p) => ({ ...caler(floue(p.s * 3), img.l, img.h, p.coins, p.s * 10, p.s * 1.5, 3), s: p.s }))
        .sort((a, b) => b.note - a.note);
    for (const g of grossiers.slice(0, 3)) {
        const e = caler(floue(g.s * 0.8), img.l, img.h, g.coins, g.s * 2, Math.max(0.15, g.s * 0.1));
        if (e.note > (diagnostic.calage ?? -1)) {
            diagnostic.calage = Math.round(e.note * 1000) / 1000;
            diagnostic.coins = e.coins.map(([x, y]) => [x * img.k, y * img.k]);
        }
        if (e.note < CALAGE_MIN) continue;
        diagnostic.etape = 'lecture des cellules';
        const H = homographie(e.coins);
        const jeton = H && lireCellules(d, img.l, img.h, H);
        if (jeton) {
            diagnostic.etape = 'lu';
            return { jeton, coins: diagnostic.coins };
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
