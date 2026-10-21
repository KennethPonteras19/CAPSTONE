(function () {
    'use strict';

    window.CustomerWorkspace = {
        setProductImage(container, product) {
            container.querySelector('.product-photo')?.remove();
            if (!product.image_url) return;
            const image = document.createElement('img');
            image.className = 'product-photo';
            image.src = product.image_url;
            image.alt = product.name;
            image.loading = 'lazy';
            image.addEventListener('error', () => image.remove());
            container.append(image);
        },
        escape(value) {
            const element = document.createElement('span');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        },
        async request(url, method, data) {
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok || response.redirected) {
                const errors = Object.values(result.errors || {}).flat();
                throw new Error(errors[0] || (response.status === 419 || response.status === 401 || response.redirected
                    ? 'Your session expired. Reload the page and sign in again.'
                    : result.message || 'Unable to save. Please try again.'));
            }
            return result;
        },
    };

    window.addEventListener('pageshow', event => {
        if (event.persisted) window.location.reload();
    });
}());
