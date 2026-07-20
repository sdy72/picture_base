(function () {
    'use strict';

    const openButton = document.querySelector('[data-picture-open]');
    const lightbox = document.querySelector('[data-picture-lightbox]');
    const closeButton = document.querySelector('[data-picture-close]');

    if (!openButton || !lightbox || !closeButton) {
        return;
    }

    let previouslyFocusedElement = null;

    function closeLightbox() {
        lightbox.hidden = true;
        openButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('picture-lightbox-open');

        if (previouslyFocusedElement instanceof HTMLElement) {
            previouslyFocusedElement.focus();
        }

        previouslyFocusedElement = null;
    }

    function openLightbox() {
        previouslyFocusedElement = document.activeElement;
        lightbox.hidden = false;
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('picture-lightbox-open');
        closeButton.focus();
    }

    openButton.addEventListener('click', openLightbox);
    closeButton.addEventListener('click', closeLightbox);

    lightbox.addEventListener('click', function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !lightbox.hidden) {
            closeLightbox();
        }
    });
}());
