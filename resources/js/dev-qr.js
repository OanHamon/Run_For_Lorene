/*
 * Banc d'essai des billets (/dev/qr, local uniquement) : lecture par caméra ou par photo, comme
 * au poste de retrait (QR classique avec jsQR, empreinte TRACK avec le lecteur des semelles),
 * puis vérification du jeton par le serveur.
 * La caméra demande une page sécurisée : http://localhost sur l'ordinateur, ou https.
 * La photo marche partout, même sur un téléphone en http.
 */
import jsQR from 'jsqr';
import { CAMERA, lecteurSemelles } from './track/scan.js';

const racine = document.querySelector('[data-lecteur]');
const $ = (nom) => racine.querySelector(`[data-${nom}]`);
const video = $('lecteur-video');
const erreur = $('lecteur-erreur');
const resultat = $('lecteur-resultat');
const ouvrir = $('lecteur-camera');
const arreterBouton = $('lecteur-arreter');
const canevas = document.createElement('canvas');
const ctx = canevas.getContext('2d', { willReadFrequently: true });
let flux = null;
let dernier = { code: null, t: 0 };
const semelles = lecteurSemelles();

/** État du lecteur des semelles, pour comprendre ce qui coince (voir `diagnostic` dans lecteur.js). */
function montrerEtat({ diagnostic: d, ms }) {
    $('lecteur-etat').textContent = `Semelles : ${d.etape} · image ${d.image} · départs ${d.paires}`
        + ` · calage ${d.calage ?? '—'} (lisible dès 0,03) · écart des cellules ${d.ecartCellules ?? '—'} (lisible dès 0,06)`
        + ` · hors image ${d.horsImage ?? '—'} · ${Math.round(ms)} ms`;
    $('lecteur-etat').classList.remove('hidden');
}

function montrerErreur(texte) {
    erreur.textContent = texte;
    erreur.classList.toggle('hidden', !texte);
}

/**
 * Image d'une source (vidéo ou photo), réduite pour rester rapide. QR classique lu tout de suite ;
 * sinon les semelles, dans le worker s'il est libre. Renvoie une promesse { code, par } ou null.
 */
async function decoder(source, largeur, hauteur) {
    const reduction = Math.min(1, 1600 / Math.max(largeur, hauteur));
    canevas.width = Math.round(largeur * reduction);
    canevas.height = Math.round(hauteur * reduction);
    ctx.drawImage(source, 0, 0, canevas.width, canevas.height);
    const image = ctx.getImageData(0, 0, canevas.width, canevas.height);
    const qr = jsQR(image.data, canevas.width, canevas.height, { inversionAttempts: 'attemptBoth' })?.data;
    if (qr) return { code: qr, par: 'QR classique' };
    const r = await semelles.lire(image);
    if (!r) return null;
    montrerEtat(r);
    return r.jeton ? { code: r.jeton, par: `semelles (${Math.round(r.ms)} ms)` } : null;
}

async function verifier(code, par) {
    navigator.vibrate?.(80);
    const reponse = await fetch(racine.dataset.verifier, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': racine.dataset.csrf },
        body: JSON.stringify({ code }),
    });
    const r = await reponse.json();
    resultat.classList.remove('hidden', 'ring-vert', 'bg-vert-clair/40', 'ring-erreur-bord', 'bg-erreur-fond');
    resultat.classList.add(...(r.ok ? ['ring-vert', 'bg-vert-clair/40'] : ['ring-erreur-bord', 'bg-erreur-fond']));
    $('resultat-message').textContent = r.message ?? 'Réponse inattendue du serveur.';
    $('resultat-fiche').textContent = r.fiche ? `${r.fiche.nom} · ${r.fiche.course} · dossard ${r.fiche.dossard ?? '—'} · ${r.fiche.evenement}` : '';
    $('resultat-code').textContent = `Lu par ${par} : ${code}`;
}

function arreter() {
    flux?.getTracks().forEach((piste) => piste.stop());
    flux = null;
    video.classList.add('hidden');
    arreterBouton.classList.add('hidden');
    ouvrir.classList.remove('hidden');
}

function lireVideo() {
    if (!flux) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA && !semelles.occupe()) {
        decoder(video, video.videoWidth, video.videoHeight).then((lu) => {
            // On reste en lecture continue ; le même code n'est revérifié qu'après 3 secondes.
            if (lu && flux && (lu.code !== dernier.code || performance.now() - dernier.t > 3000)) {
                dernier = { code: lu.code, t: performance.now() };
                verifier(lu.code, lu.par);
            }
        });
    }
    requestAnimationFrame(lireVideo);
}

ouvrir.addEventListener('click', async () => {
    montrerErreur('');
    if (!window.isSecureContext || !navigator.mediaDevices) {
        montrerErreur('La caméra demande une page sécurisée (http://localhost ou https). Sur téléphone en http, utilise « Lire une photo ».');
        return;
    }
    try {
        flux = await navigator.mediaDevices.getUserMedia(CAMERA);
    } catch {
        montrerErreur('Caméra indisponible ou refusée. Autorise-la dans le navigateur, ou utilise « Lire une photo ».');
        return;
    }
    video.srcObject = flux;
    await video.play();
    const { width, height } = flux.getVideoTracks()[0].getSettings();
    $('lecteur-camera-taille').textContent = `Caméra : ${width} × ${height}. Fais entrer les deux semelles en entier dans l'image.`;
    video.classList.remove('hidden');
    arreterBouton.classList.remove('hidden');
    ouvrir.classList.add('hidden');
    requestAnimationFrame(lireVideo);
});

arreterBouton.addEventListener('click', arreter);

$('lecteur-photo').addEventListener('change', async (e) => {
    const fichier = e.target.files?.[0];
    e.target.value = '';
    if (!fichier) return;
    montrerErreur('');
    try {
        const image = await createImageBitmap(fichier);
        while (semelles.occupe()) await new Promise((r) => setTimeout(r, 50));
        const lu = await decoder(image, image.width, image.height);
        image.close();
        if (lu) verifier(lu.code, lu.par);
        else montrerErreur(`Aucun billet trouvé sur la photo (ni QR ni semelles). Rapproche-toi et évite les reflets. ${$('lecteur-etat').textContent}`);
    } catch {
        montrerErreur('Impossible de lire cette image.');
    }
});

// Plein écran d'une empreinte : plus grande, plus facile à viser avec un autre téléphone.
document.querySelectorAll('[data-plein-ecran]').forEach((bouton) => {
    bouton.addEventListener('click', () => {
        const cible = document.getElementById(bouton.dataset.pleinEcran);
        if (document.fullscreenElement) document.exitFullscreen();
        else cible.requestFullscreen?.() ?? cible.webkitRequestFullscreen?.();
    });
});

