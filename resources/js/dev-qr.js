/*
 * Banc d'essai des QR codes (/dev/qr, local uniquement) : lecture par caméra ou par photo avec
 * jsQR, comme au poste de retrait, puis vérification du code par le serveur.
 * La caméra demande une page sécurisée : http://localhost sur l'ordinateur, ou https.
 * La photo marche partout, même sur un téléphone en http.
 */
import jsQR from 'jsqr';

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

function montrerErreur(texte) {
    erreur.textContent = texte;
    erreur.classList.toggle('hidden', !texte);
}

/** Décode l'image d'une source (vidéo ou photo), réduite pour rester rapide. */
function decoder(source, largeur, hauteur) {
    const reduction = Math.min(1, 1280 / Math.max(largeur, hauteur));
    canevas.width = Math.round(largeur * reduction);
    canevas.height = Math.round(hauteur * reduction);
    ctx.drawImage(source, 0, 0, canevas.width, canevas.height);
    return jsQR(ctx.getImageData(0, 0, canevas.width, canevas.height).data, canevas.width, canevas.height, { inversionAttempts: 'attemptBoth' })?.data ?? null;
}

async function verifier(code) {
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
    $('resultat-code').textContent = `Code lu : ${code}`;
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
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        const code = decoder(video, video.videoWidth, video.videoHeight);
        // On reste en lecture continue ; le même code n'est revérifié qu'après 3 secondes.
        if (code && (code !== dernier.code || performance.now() - dernier.t > 3000)) {
            dernier = { code, t: performance.now() };
            verifier(code);
        }
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
        flux = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
    } catch {
        montrerErreur('Caméra indisponible ou refusée. Autorise-la dans le navigateur, ou utilise « Lire une photo ».');
        return;
    }
    video.srcObject = flux;
    await video.play();
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
        const code = decoder(image, image.width, image.height);
        image.close();
        if (code) verifier(code);
        else montrerErreur('Aucun QR code trouvé sur la photo. Rapproche-toi et évite les reflets.');
    } catch {
        montrerErreur('Impossible de lire cette image.');
    }
});
