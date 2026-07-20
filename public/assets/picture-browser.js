(function () {
    'use strict';

    const MIN_ZOOM = 0.5;
    const MAX_ZOOM = 3.0;
    const ZOOM_STEP = 0.25;
    const DEFAULT_ZOOM = 1.0;
    const image = document.querySelector('[data-picture-image]');
    const output = document.querySelector('[data-zoom-level]');
    const buttons = document.querySelectorAll('[data-zoom-action]');

    if (!image || !output) {
        return;
    }

    let zoom = DEFAULT_ZOOM;

    function clampZoom(value) {
        return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value));
    }

    function updateZoom() {
        image.style.setProperty('--picture-zoom', String(zoom));
        output.textContent = zoom.toFixed(2) + '×';

        buttons.forEach(function (button) {
            const action = button.getAttribute('data-zoom-action');
            button.disabled = (action === 'decrease' && zoom <= MIN_ZOOM)
                || (action === 'increase' && zoom >= MAX_ZOOM);
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const action = button.getAttribute('data-zoom-action');
            const change = action === 'increase' ? ZOOM_STEP : -ZOOM_STEP;
            zoom = clampZoom(zoom + change);
            updateZoom();
        });
    });

    updateZoom();
}());
