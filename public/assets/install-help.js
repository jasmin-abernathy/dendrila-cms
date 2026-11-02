/* Progressive enhancement of the installation proof walkthrough.
   Without JavaScript the generic tutorial and server-side selection remain. */
(() => {
  'use strict';
  const selector = document.getElementById('hosting-provider');
  const cards = Array.from(document.querySelectorAll('[data-hosting-guide]'));
  if (selector && cards.length) {
    const update = () => {
      const chosen = selector.value;
      const valid = cards.some(card => card.dataset.hostingGuide === chosen);
      cards.forEach(card => { card.hidden = card.dataset.hostingGuide !== (valid ? chosen : 'other'); });
    };
    selector.addEventListener('change', update);
    update();
  }

  const copy = document.getElementById('copy-ownership-proof');
  const secret = document.getElementById('ownership-challenge');
  const feedback = document.getElementById('copy-proof-status');
  if (copy && secret && feedback) {
    copy.addEventListener('click', async () => {
      const value = secret.textContent.trim();
      try {
        if (!navigator.clipboard || !window.isSecureContext) throw new Error('No secure clipboard');
        await navigator.clipboard.writeText(value);
        feedback.textContent = 'Code copié. Collez-le dans le fichier ownership-proof.txt du dossier config.';
      } catch (_) {
        feedback.textContent = 'La copie automatique est indisponible ici. Sélectionnez le code pour le copier, ou utilisez le téléchargement du fichier.';
      }
    });
  }
})();
