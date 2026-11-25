(() => {
    const select = document.getElementById('theme');
    const frame = document.getElementById('theme-preview');
    const fullSize = document.getElementById('theme-full-size');
    const links = document.querySelectorAll('[data-preview-theme]');
    const inputs = [
        [document.getElementById('site-name'), '.site-name'],
        [document.getElementById('site-tagline'), '.site-tagline'],
    ];
    let originals = [];

    function copyIdentity() {
        for (const [input, node, original] of originals) {
            const value = input.value.trim();
            if (value === '' || value === original.textContent) {
                if (node.textContent !== original.textContent) {
                    node.replaceChildren(...[...original.childNodes].map(child => child.cloneNode(true)));
                }
            } else {
                node.textContent = value;
            }
            if (node.tagName === 'H1' && select.value === 'borne-02') {
                node.classList.toggle('wide', [...node.textContent].length > 13);
            }
        }
    }

    frame.addEventListener('load', () => {
        originals = inputs.flatMap(([input, selector]) =>
            [...(frame.contentDocument?.querySelectorAll(selector) ?? [])].map(node => [input, node, node.cloneNode(true)]));
        copyIdentity();
    });
    for (const [input] of inputs) input.addEventListener('input', copyIdentity);

    function preview(theme) {
        const url = new URL(frame.src);
        url.searchParams.set('theme', theme);
        originals = [];
        frame.src = url;
        fullSize.href = url;
    }

    select.addEventListener('change', () => preview(select.value));
    for (const link of links) {
        link.addEventListener('click', event => {
            event.preventDefault();
            select.value = link.dataset.previewTheme;
            preview(select.value);
        });
    }
})();
