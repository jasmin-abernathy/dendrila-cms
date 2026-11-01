/* Optional enhancement: opening one choice closes siblings.
 * Native <details> works without JavaScript, accounts, AI or remote calls. */
(() => {
  'use strict';
  for (const guide of document.querySelectorAll('[data-dendrila-orientation]')) {
    const choices = Array.from(guide.querySelectorAll('details[data-orientation-choice]'));
    choices.forEach((choice) => {
      choice.addEventListener('toggle', () => {
        if (!choice.open) return;
        choices.forEach((other) => { if (other !== choice) other.open = false; });
      });
    });
  }
})();
