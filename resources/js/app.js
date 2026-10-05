import './uploads';

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-cookie-settings]')) {
        const banner = document.querySelector('[data-cookie-banner]');
        banner?.removeAttribute('hidden');
        banner?.querySelector('details')?.setAttribute('open', '');
        banner?.querySelector('input:not([disabled]), button')?.focus();
    }

    const opener = event.target.closest('[data-drawer-open]');
    const closer = event.target.closest('[data-drawer-close]');

    if (opener) {
        const drawer = document.getElementById(opener.dataset.drawerOpen);
        drawer?.classList.add('is-open');
        drawer?.querySelector('a, button')?.focus();
    }

    if (closer) {
        closer.closest('.hm-drawer')?.classList.remove('is-open');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.hm-drawer.is-open').forEach((drawer) => drawer.classList.remove('is-open'));
    }
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();

        return;
    }

    const button = event.target.querySelector('button[type="submit"][data-loading-text]');

    if (button && !button.disabled) {
        button.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        button.querySelector('span:last-child')?.replaceChildren(button.dataset.loadingText);
    }
});
