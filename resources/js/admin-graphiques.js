/*
 * Graphiques de la page Statistiques (admin). Les données arrivent toutes prêtes du serveur
 * dans l'attribut data-graphique de chaque <canvas> ; un tableau de valeurs suit chaque
 * graphique pour les lecteurs d'écran et l'export.
 *
 * Couleurs : lues sur des éléments témoins aux classes de la charte (text-vert…), jamais en dur.
 */
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const couleur = (classe) => {
    const temoin = document.createElement('span');
    temoin.className = classe;
    temoin.hidden = true;
    document.body.appendChild(temoin);
    const valeur = getComputedStyle(temoin).color;
    temoin.remove();
    return valeur;
};

const palette = {
    vert: couleur('text-vert'),
    'vert-doux': couleur('text-vert-doux'),
    'vert-clair': couleur('text-vert-clair'),
    'vert-sombre': couleur('text-vert-sombre'),
    moutarde: couleur('text-moutarde'),
    gris: couleur('text-gris-texte'),
    bordure: couleur('text-bordure'),
};

const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = palette.gris;
Chart.defaults.borderColor = palette.bordure;
Chart.defaults.plugins.legend.labels.usePointStyle = true;

/* Traits verticaux pointillés aux dates clés (ouverture, tarif, relance), avec leur libellé. */
const jalons = {
    id: 'jalons',
    afterDatasetsDraw(chart, _args, options) {
        const { ctx, chartArea, scales } = chart;
        (options.liste || []).forEach((jalon, n) => {
            const x = scales.x.getPixelForValue(chart.data.labels.indexOf(jalon.jour));
            if (Number.isNaN(x) || x < chartArea.left) return;
            ctx.save();
            ctx.strokeStyle = palette.moutarde;
            ctx.setLineDash([4, 4]);
            ctx.beginPath();
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = palette['vert-sombre'];
            ctx.font = `12px ${Chart.defaults.font.family}`;
            ctx.fillText(jalon.libelle, x + 4, chartArea.top + 12 + (n % 3) * 14);
            ctx.restore();
        });
    },
};

document.querySelectorAll('canvas[data-graphique]').forEach((canvas) => {
    const g = JSON.parse(canvas.dataset.graphique);
    const horizontal = g.horizontal === true;

    new Chart(canvas, {
        type: g.type,
        data: {
            labels: g.libelles,
            datasets: g.series.map((s) => ({
                type: s.type,
                label: s.libelle,
                data: s.valeurs,
                backgroundColor: palette[s.couleur],
                borderColor: palette[s.couleur],
                borderWidth: s.type === 'line' || g.type === 'line' ? 2 : 0,
                borderRadius: 6,
                pointRadius: 0,
                tension: 0.25,
                yAxisID: s.axe || 'y',
                order: s.type === 'line' ? 0 : 1,
            })),
        },
        options: {
            indexAxis: horizontal ? 'y' : 'x',
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: g.empile === true, grid: { display: horizontal }, ticks: { maxTicksLimit: 10, callback: horizontal && g.euros ? (v) => euros.format(v) : undefined } },
                y: { stacked: g.empile === true, beginAtZero: true, grid: { display: !horizontal }, ticks: { precision: 0, callback: !horizontal && g.euros ? (v) => euros.format(v) : undefined } },
                ...(g.axeDroit ? { y2: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { precision: 0 } } } : {}),
            },
            plugins: {
                legend: { display: g.series.length > 1, position: 'bottom' },
                tooltip: { callbacks: g.euros ? { label: (c) => `${c.dataset.label} : ${euros.format(c.parsed[horizontal ? 'x' : 'y'])}` } : {} },
                jalons: { liste: g.jalons || [] },
            },
        },
        plugins: [jalons],
    });
});
