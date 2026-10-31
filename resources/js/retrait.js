/*
 * Scan du billet QR au retrait des dossards : caméra arrière, lecture image par image avec
 * jsQR (fonctionne aussi sur iPhone, sans BarcodeDetector). Un code « RFL-… » trouvé est envoyé
 * au serveur, qui vérifie la signature et affiche la fiche. Sans caméra : la recherche par nom.
 */
import jsQR from 'jsqr';

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

    const arreter = () => {
        flux?.getTracks().forEach((piste) => piste.stop());
        flux = null;
        [zone, aide, fermer].forEach((el) => el.classList.add('hidden'));
        ouvrir.classList.remove('hidden');
    };

    const lire = () => {
        if (!flux) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const code = jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' });
            if (code && code.data.startsWith('RFL-')) {
                navigator.vibrate?.(80);
                jeton.value = code.data;
                arreter();
                form.submit();
                return;
            }
        }
        requestAnimationFrame(lire);
    };

    ouvrir.addEventListener('click', async () => {
        erreur.classList.add('hidden');
        try {
            flux = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
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
