/**
 * TRACK : outils communs aux scanners (poste de retrait, banc d'essai /dev/qr).
 *
 * - CAMERA : caméra arrière en bonne définition. Sans consigne, beaucoup de téléphones donnent
 *   480 × 640, trop peu pour lire les cellules des semelles d'un peu loin.
 * - lecteurSemelles() : le lecteur des semelles tourne dans un worker (un fil à part), pour ne pas
 *   figer l'écran pendant les quelques centaines de millisecondes d'une lecture. Une seule image
 *   à la fois : tant qu'il travaille, les images suivantes sont ignorées.
 */
export const CAMERA = { video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false };

export function lecteurSemelles() {
    const worker = new Worker(new URL('./lecteur-worker.js', import.meta.url), { type: 'module' });
    let attente = null;
    worker.onmessage = ({ data }) => {
        const fin = attente;
        attente = null;
        fin?.(data);
    };

    return {
        occupe: () => attente !== null,
        /** Lit une ImageData ; renvoie une promesse { jeton, diagnostic, ms }, ou null si déjà occupé. */
        lire(image) {
            if (attente) return null;
            return new Promise((resoudre) => {
                attente = resoudre;
                worker.postMessage({ rgba: image.data.buffer, l: image.width, h: image.height }, [image.data.buffer]);
            });
        },
    };
}
