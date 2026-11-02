/** Worker du lecteur des semelles (voir scan.js) : reçoit une image RGBA, renvoie le jeton lu. */
import { diagnostic, lireEmpreinteRGBA } from './lecteur.js';

self.onmessage = ({ data }) => {
    const t0 = performance.now();
    let jeton = null;
    try {
        jeton = lireEmpreinteRGBA(new Uint8ClampedArray(data.rgba), data.l, data.h)?.jeton ?? null;
    } catch {
        diagnostic.etape = 'erreur du lecteur';
    }
    self.postMessage({ jeton, diagnostic: { ...diagnostic }, ms: performance.now() - t0 });
};
