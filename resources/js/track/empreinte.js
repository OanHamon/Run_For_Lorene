/**
 * TRACK : rendu animé d'une empreinte, sur un <canvas>. Composant autonome :
 *
 * - vague permanente qui remonte la semelle, du talon vers la pointe (l'élan de la course) ;
 * - attraction douce des particules vers le curseur (souris uniquement) ;
 * - onde qui se propage autour du curseur, ou d'un toucher sur mobile ;
 * - cœur moutarde entre les deux semelles, qui bat (double battement, comme un pouls de coureur) ;
 * - pause hors écran et onglet caché ; aucune animation si la personne a demandé moins de
 *   mouvement (prefers-reduced-motion) ou si l'empreinte est déclarée statique (impression, scan).
 *
 * Les effets ne déplacent que l'affichage : la position d'origine des particules ne change jamais.
 */
import { boiteForme, particules } from './forme';

const VAGUE = { amplitude: 0.7, vitesse: 2.2, longueur: 0.045, taille: 0.12 };
const ATTRACTION = { rayon: 42, force: 0.38, souplesse: 0.1 };
const BATTEMENT = { période: 1.15, ampleur: 0.13, finesse: 0.055 };
const ONDE = { vitesse: 150, largeur: 13, amplitude: 3.6, durée: 1.5, intervalle: 280, distance: 22 };

// Écart visé entre deux particules à l'écran (px CSS), borné en unités du SVG :
// dense en grand, des particules encore visibles en petit.
const DENSITE = { ecartEcran: 5.2, min: 3.6, max: 8 };

const moinsDeMouvement = window.matchMedia('(prefers-reduced-motion: reduce)');

function couleur(element, nom, secours) {
    return getComputedStyle(element).getPropertyValue(nom).trim() || secours;
}

export class Empreinte {
    constructor(element, { graine = 'track', statique = false, espacement } = {}) {
        this.element = element;
        this.canevas = element.querySelector('canvas') ?? element.appendChild(document.createElement('canvas'));
        this.ctx = this.canevas.getContext('2d');
        this.statique = statique;
        this.graine = graine;
        this.espacementImpose = espacement;
        this.espacement = null;
        this.boite = boiteForme();

        this.couleurs = [
            couleur(element, '--color-vert-sombre', '#23463F'),
            couleur(element, '--color-vert-doux', '#5FBAAE'),
            couleur(element, '--color-moutarde', '#D9B21E'),
        ];

        this.pointeur = null; // position de la souris, en unités du SVG
        this.ondes = [];
        this.derniereOnde = { t: 0, x: 0, y: 0 };
        this.visible = true;
        this.enPause = false;
        this.image = null;
        this.temps = 0;
        this.precedent = 0;

        this.mesurer();
        new ResizeObserver(() => this.mesurer()).observe(element);

        if (!this.animé()) return;

        this.ecouter();
        new IntersectionObserver(([e]) => {
            this.visible = e.isIntersecting;
            this.relancer();
        }).observe(element);
        document.addEventListener('visibilitychange', () => this.relancer());
        moinsDeMouvement.addEventListener('change', () => this.relancer());
        this.relancer();
    }

    animé() {
        return !this.statique && !moinsDeMouvement.matches;
    }

    /** Fige l'empreinte (pendant un scan, par exemple). */
    pause() {
        this.enPause = true;
        this.relancer();
    }

    reprendre() {
        this.enPause = false;
        this.relancer();
    }

    relancer() {
        const tourne = this.animé() && this.visible && !this.enPause && !document.hidden;
        if (tourne && !this.image) {
            this.precedent = performance.now();
            this.image = requestAnimationFrame((t) => this.boucle(t));
        } else if (!tourne && this.image) {
            cancelAnimationFrame(this.image);
            this.image = null;
        }
        if (!tourne) this.dessiner(false);
    }

    mesurer() {
        const { width, height } = this.element.getBoundingClientRect();
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        this.canevas.width = Math.round(width * dpr);
        this.canevas.height = Math.round(height * dpr);
        this.canevas.style.width = `${width}px`;
        this.canevas.style.height = `${height}px`;

        // Marge pour que l'onde et l'attraction ne sortent pas du cadre.
        const marge = 8;
        const echelle = Math.min(width / (this.boite.l + marge * 2), height / (this.boite.h + marge * 2));
        this.echelle = echelle * dpr;
        this.origineX = (width * dpr - this.boite.l * this.echelle) / 2 - this.boite.x * this.echelle;
        this.origineY = (height * dpr - this.boite.h * this.echelle) / 2 - this.boite.y * this.echelle;

        // Densité adaptée à la taille affichée ; on ne recalcule que si l'écart change nettement.
        const voulu = this.espacementImpose
            ?? Math.min(DENSITE.max, Math.max(DENSITE.min, DENSITE.ecartEcran / (echelle || 1)));
        if (!this.espacement || Math.abs(voulu / this.espacement - 1) > 0.15) {
            this.espacement = voulu;
            ({ liste: this.liste, coeur: this.coeur } = particules(this.graine, voulu));
            this.déplacements = new Float32Array(this.liste.length * 2);
        }

        if (!this.image) this.dessiner(false);
    }

    /** Position écran → unités du SVG. */
    versForme(evenement) {
        const r = this.canevas.getBoundingClientRect();
        const dpr = this.canevas.width / r.width;
        return {
            x: ((evenement.clientX - r.left) * dpr - this.origineX) / this.echelle,
            y: ((evenement.clientY - r.top) * dpr - this.origineY) / this.echelle,
        };
    }

    ecouter() {
        const c = this.canevas;
        c.addEventListener('pointermove', (e) => {
            if (e.pointerType !== 'mouse') return;
            this.pointeur = this.versForme(e);
            const d = this.derniereOnde;
            const maintenant = performance.now();
            if (maintenant - d.t > ONDE.intervalle && Math.hypot(this.pointeur.x - d.x, this.pointeur.y - d.y) > ONDE.distance) {
                this.lancerOnde(this.pointeur, 0.55);
            }
        });
        c.addEventListener('pointerleave', () => { this.pointeur = null; });
        c.addEventListener('pointerdown', (e) => this.lancerOnde(this.versForme(e), 1));
    }

    lancerOnde({ x, y }, intensité) {
        this.ondes.push({ x, y, depart: this.temps, intensité });
        if (this.ondes.length > 6) this.ondes.shift();
        this.derniereOnde = { t: performance.now(), x, y };
    }

    boucle(maintenant) {
        const dt = Math.min((maintenant - this.precedent) / 1000, 0.05);
        this.precedent = maintenant;
        this.temps += dt;
        this.ondes = this.ondes.filter((o) => this.temps - o.depart < ONDE.durée);
        this.dessiner(true, dt);
        this.image = requestAnimationFrame((t) => this.boucle(t));
    }

    dessiner(animé, dt = 0) {
        const { ctx, liste, déplacements: dep } = this;
        ctx.clearRect(0, 0, this.canevas.width, this.canevas.height);

        const t = this.temps;
        const p = animé ? this.pointeur : null;
        const lissage = 1 - Math.pow(1 - ATTRACTION.souplesse, dt * 60);
        const chemins = [new Path2D(), new Path2D(), new Path2D()];
        const coeur = this.coeur;
        const battement = animé && coeur ? this.battement(t) : 0;

        for (let i = 0; i < liste.length; i++) {
            const q = liste[i];
            let x = q.x, y = q.y, r = q.r;

            if (q.nuance === 2 && battement) {
                // Le cœur se gonfle depuis son centre.
                x = coeur.cx + (x - coeur.cx) * (1 + battement);
                y = coeur.cy + (y - coeur.cy) * (1 + battement);
                r *= 1 + battement * 0.6;
            }

            if (animé) {
                // Vague : remonte la semelle, chaque particule légèrement décalée.
                const v = Math.sin(t * VAGUE.vitesse - q.y * VAGUE.longueur + q.phase * 0.35);
                y += v * VAGUE.amplitude;
                r *= 1 + v * VAGUE.taille;

                // Attraction : cible proportionnelle à la proximité du curseur, rejointe en douceur.
                let cx = 0, cy = 0;
                if (p) {
                    const ax = p.x - q.x, ay = p.y - q.y;
                    const f = Math.exp(-(ax * ax + ay * ay) / (ATTRACTION.rayon * ATTRACTION.rayon)) * ATTRACTION.force;
                    cx = ax * f;
                    cy = ay * f;
                }
                dep[i * 2] += (cx - dep[i * 2]) * lissage;
                dep[i * 2 + 1] += (cy - dep[i * 2 + 1]) * lissage;
                x += dep[i * 2];
                y += dep[i * 2 + 1];

                // Ondes : les particules s'écartent au passage du front, puis reviennent.
                for (const o of this.ondes) {
                    const age = t - o.depart;
                    const ox = q.x - o.x, oy = q.y - o.y;
                    const d = Math.hypot(ox, oy) || 1;
                    const front = (d - age * ONDE.vitesse) / ONDE.largeur;
                    const passage = Math.exp(-front * front) * (1 - age / ONDE.durée) * o.intensité;
                    if (passage < 0.01) continue;
                    x += (ox / d) * passage * ONDE.amplitude;
                    y += (oy / d) * passage * ONDE.amplitude;
                    r *= 1 + passage * 0.3;
                }
            }

            const sx = this.origineX + x * this.echelle;
            const sy = this.origineY + y * this.echelle;
            const sr = r * this.echelle;
            const chemin = chemins[q.nuance];
            chemin.moveTo(sx + sr, sy);
            chemin.arc(sx, sy, sr, 0, Math.PI * 2);
        }

        ctx.fillStyle = this.couleurs[0];
        ctx.fill(chemins[0]);
        ctx.fillStyle = this.couleurs[1];
        ctx.fill(chemins[1]);
        ctx.fillStyle = this.couleurs[2];
        ctx.fill(chemins[2]);
    }

    /** Gonflement du cœur à l'instant t : deux pulsations rapprochées, puis repos. */
    battement(t) {
        const ph = (t % BATTEMENT.période) / BATTEMENT.période;
        const pic = (centre) => Math.exp(-(((ph - centre) / BATTEMENT.finesse) ** 2));
        return BATTEMENT.ampleur * (pic(0.12) + 0.55 * pic(0.32));
    }
}

/** Monte toutes les empreintes `[data-track]` de la page. */
export function monterEmpreintes(racine = document) {
    racine.querySelectorAll('[data-track]:not([data-track-pret])').forEach((element) => {
        element.dataset.trackPret = '';
        element.trackEmpreinte = new Empreinte(element, {
            graine: element.dataset.graine || 'track',
            statique: element.hasAttribute('data-statique'),
        });
    });
}
