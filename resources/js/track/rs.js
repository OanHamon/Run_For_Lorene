/**
 * TRACK : code correcteur Reed–Solomon sur GF(256) (polynôme 0x11d, racines α^0…α^(n−1)),
 * le même principe que les QR codes. Corrige e octets faux et f octets illisibles tant que
 * 2e + f ≤ nombre d'octets de correction. Pur, sans dépendance.
 */
const EXP = new Uint8Array(512);
const LOG = new Uint8Array(256);
{
    let x = 1;
    for (let i = 0; i < 255; i++) {
        EXP[i] = x;
        LOG[x] = i;
        x <<= 1;
        if (x & 0x100) x ^= 0x11d;
    }
    for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
}

const mul = (x, y) => (x && y ? EXP[LOG[x] + LOG[y]] : 0);
const div = (x, y) => {
    if (!y) throw new Error('division par zéro');
    return x ? EXP[(LOG[x] + 255 - LOG[y]) % 255] : 0;
};
const puissance = (x, p) => EXP[(((LOG[x] * p) % 255) + 255) % 255];
const inverse = (x) => EXP[255 - LOG[x]];

const echelle = (p, x) => p.map((c) => mul(c, x));
function ajouter(p, q) {
    const r = new Array(Math.max(p.length, q.length)).fill(0);
    for (let i = 0; i < p.length; i++) r[i + r.length - p.length] = p[i];
    for (let i = 0; i < q.length; i++) r[i + r.length - q.length] ^= q[i];
    return r;
}
function multiplier(p, q) {
    const r = new Array(p.length + q.length - 1).fill(0);
    for (let j = 0; j < q.length; j++) for (let i = 0; i < p.length; i++) r[i + j] ^= mul(p[i], q[j]);
    return r;
}
function evaluer(p, x) {
    let y = p[0];
    for (let i = 1; i < p.length; i++) y = mul(y, x) ^ p[i];
    return y;
}
function reste(dividende, diviseur) {
    const out = [...dividende];
    for (let i = 0; i < dividende.length - (diviseur.length - 1); i++) {
        const c = out[i];
        if (c) for (let j = 1; j < diviseur.length; j++) if (diviseur[j]) out[i + j] ^= mul(diviseur[j], c);
    }
    return out.slice(out.length - (diviseur.length - 1));
}

function generateur(n) {
    let g = [1];
    for (let i = 0; i < n; i++) g = multiplier(g, [1, puissance(2, i)]);
    return g;
}

/** Ajoute `n` octets de correction à `message` (tableau d'octets). */
export function encoder(message, n) {
    const gen = generateur(n);
    const out = [...message, ...new Array(n).fill(0)];
    for (let i = 0; i < message.length; i++) {
        const c = out[i];
        if (c) for (let j = 1; j < gen.length; j++) out[i + j] ^= mul(gen[j], c);
    }
    return [...message, ...out.slice(message.length)];
}

const syndromes = (msg, n) => [0, ...Array.from({ length: n }, (_, i) => evaluer(msg, puissance(2, i)))];

function localisateurErrata(positions) {
    let loc = [1];
    for (const p of positions) loc = multiplier(loc, ajouter([1], [puissance(2, p), 0]));
    return loc;
}

function corrigerErrata(msg, synd, positions) {
    const coef = positions.map((p) => msg.length - 1 - p);
    const loc = localisateurErrata(coef);
    const eval_ = reste(multiplier([...synd].reverse(), loc), [1, ...new Array(loc.length).fill(0)]).reverse();
    const X = coef.map((c) => puissance(2, -(255 - c)));
    const E = new Array(msg.length).fill(0);
    X.forEach((Xi, i) => {
        const Xinv = inverse(Xi);
        let derivee = 1;
        X.forEach((Xj, j) => { if (j !== i) derivee = mul(derivee, 1 ^ mul(Xinv, Xj)); });
        if (!derivee) throw new Error('correction impossible');
        const y = mul(Xi, evaluer([...eval_].reverse(), Xinv));
        E[positions[i]] = div(y, derivee);
    });
    return ajouter(msg, E);
}

function localisateurErreurs(synd, n, effacements) {
    let loc = [1], ancien = [1];
    const decalage = synd.length > n ? synd.length - n : 0;
    for (let i = 0; i < n - effacements; i++) {
        // Les syndromes de Forney ont déjà retiré les effacements : on part du premier.
        const K = i + decalage;
        let delta = synd[K];
        for (let j = 1; j < loc.length; j++) delta ^= mul(loc[loc.length - 1 - j], synd[K - j]);
        ancien = [...ancien, 0];
        if (delta) {
            if (ancien.length > loc.length) {
                const nouveau = echelle(ancien, delta);
                ancien = echelle(loc, inverse(delta));
                loc = nouveau;
            }
            loc = ajouter(loc, echelle(ancien, delta));
        }
    }
    while (loc.length && loc[0] === 0) loc.shift();
    if ((loc.length - 1) * 2 + effacements > n) throw new Error("trop d'erreurs");
    return loc;
}

function trouverErreurs(loc, longueur) {
    const pos = [];
    for (let i = 0; i < longueur; i++) if (evaluer(loc, puissance(2, i)) === 0) pos.push(longueur - 1 - i);
    if (pos.length !== loc.length - 1) throw new Error('erreurs introuvables');
    return pos;
}

function syndromesForney(synd, positions, longueur) {
    const f = synd.slice(1);
    for (const p of positions) {
        const x = puissance(2, longueur - 1 - p);
        for (let j = 0; j < f.length - 1; j++) f[j] = mul(f[j], x) ^ f[j + 1];
    }
    return f;
}

/**
 * Corrige un bloc reçu (message + `n` octets de correction). `illisibles` : positions des octets
 * dont on sait qu'ils sont douteux. Renvoie le message corrigé, ou null si c'est impossible.
 *
 * `marge` : octets de correction gardés en réserve pour détecter, pas pour corriger. Sans marge,
 * un bloc très abîmé finit toujours par « se corriger » vers un message quelconque ; on refuse
 * donc toute correction qui demande plus de n − marge (2 × erreurs + illisibles).
 */
export function decoder(recu, n, illisibles = [], marge = 0) {
    if (illisibles.length > n - marge) return null;
    try {
        let msg = [...recu];
        for (const p of illisibles) msg[p] = 0;
        let synd = syndromes(msg, n);
        if (Math.max(...synd) === 0) return msg.slice(0, -n);
        const fsynd = syndromesForney(synd, illisibles, msg.length);
        const loc = localisateurErreurs(fsynd, n, illisibles.length);
        const erreurs = trouverErreurs([...loc].reverse(), msg.length);
        if (2 * erreurs.length + illisibles.length > n - marge) return null;
        msg = corrigerErrata(msg, synd, [...illisibles, ...erreurs]);
        synd = syndromes(msg, n);
        return Math.max(...synd) === 0 ? msg.slice(0, -n) : null;
    } catch {
        return null;
    }
}
