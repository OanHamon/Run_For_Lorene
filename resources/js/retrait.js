/*
 * Scan du billet au retrait des dossards : caméra arrière, lecture image par image. Deux codes
 * possibles : le QR classique (jsQR, fonctionne aussi sur iPhone, sans BarcodeDetector) et
 * l'empreinte TRACK (les semelles, lecteur maison dans un worker, dès qu'il est libre). Un jeton « RFL-… »
 * trouvé est envoyé au serveur, qui vérifie la signature et affiche la fiche. Sans caméra : la
 * recherche par nom.
 */
import jsQR from 'jsqr';
import { CAMERA, lecteurSemelles } from './track/scan.js';

document.querySelectorAll('[data-scan-form]').forEach((form) => {
    const ouvrir = form.querySelector('[data-scan-ouvrir]');
    const fermer = form.querySelector('[data-scan-fermer]');
    const zone = form.querySelector('[data-scan-zone]');
    const video = form.querySelector('[data-scan-video]');
    const aide = form.querySelector('[data-scan-aide]');
    const erreur = form.querySelector('[data-scan-erreur]');
    const jeton = form.querySelector('[data-scan-jeton]');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    let flux = null;
    const semelles = lecteurSemelles();

    const arreter = () => {
        flux?.getTracks().forEach((piste) => piste.stop());
        flux = null;
        [zone, aide, fermer].forEach((el) => el.classList.add('hidden'));
        ouvrir.classList.remove('hidden');
    };

    const trouve = (code) => {
        if (!code.startsWith('RFL-')) return false;
        navigator.vibrate?.(80);
        jeton.value = code;
        arreter();
        form.submit();
        return true;
    };

    const lire = () => {
        if (!flux) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            // Image réduite à 1280 px : assez pour les deux lecteurs, rapide même en haute définition.
            const reduction = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * reduction);
            canvas.height = Math.round(video.videoHeight * reduction);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const lu = jsQR(image.data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' })?.data ?? null;
            if (lu) {
                if (trouve(lu)) return;
            } else {
                // Les semelles : dans le worker, dès qu'il a fini l'image précédente.
                semelles.lire(image)?.then((r) => { if (r.jeton && flux) trouve(r.jeton); });
            }
        }
        requestAnimationFrame(lire);
    };

    ouvrir.addEventListener('click', async () => {
        erreur.classList.add('hidden');
        try {
            flux = await navigator.mediaDevices.getUserMedia(CAMERA);
        } catch {
            erreur.textContent = "Caméra indisponible ou refusée. Autorise-la dans les réglages du navigateur, ou cherche la personne par son nom.";
            erreur.classList.remove('hidden');
            return;
        }
        video.srcObject = flux;
        await video.play();
        ouvrir.classList.add('hidden');
        [zone, aide, fermer].forEach((el) => el.classList.remove('hidden'));
        requestAnimationFrame(lire);
    });

    fermer.addEventListener('click', arreter);
    window.addEventListener('pagehide', arreter);
});
