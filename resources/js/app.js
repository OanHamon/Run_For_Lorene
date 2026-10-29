/*
 * Formulaires à envoi unique (paiement) : un double-clic ouvrirait deux sessions de paiement.
 * Au premier envoi, le bouton est désactivé et indique l'attente. Au retour par le bouton
 * « précédent » du navigateur (page restaurée depuis le cache), il redevient utilisable.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!form.matches('form[data-envoi-unique]')) {
        return;
    }

    if (form.dataset.envoye) {
        event.preventDefault();
        return;
    }

    form.dataset.envoye = '1';
    form.querySelectorAll('button[type="submit"]').forEach((bouton) => {
        bouton.dataset.libelle = bouton.textContent;
        bouton.textContent = 'Un instant…';
        bouton.disabled = true;
    });
});

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    document.querySelectorAll('form[data-envoi-unique][data-envoye]').forEach((form) => {
        delete form.dataset.envoye;
        form.querySelectorAll('button[type="submit"]').forEach((bouton) => {
            bouton.textContent = bouton.dataset.libelle ?? bouton.textContent;
            bouton.disabled = false;
        });
    });
});
