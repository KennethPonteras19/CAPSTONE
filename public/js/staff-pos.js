(() => {
    const app = document.getElementById('posApp');
    if (!app) return;
    const data = window.staffPosData;
    let products = data.products;
    let cart = (data.draft?.items || []).map(item => ({product_id: item.product_id, quantity: item.quantity, discount_percent: Number(item.discount_percent), name: item.name}));
    let saving = false;
    let changed = false;
    let leaving = false;
    let locatingAddress = false;
    let activeStep = 0;
    const panels = [...app.querySelectorAll('[role="tabpanel"]')];
    const tabs = [...app.querySelectorAll('[role="tab"]')];
    const byId = id => document.getElementById(id);
    const money = cents => new Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'}).format(cents / 100);
    const cents = value => Math.round((Number(value) || 0) * 100);
    const productFor = item => products.find(product => product.id === item.product_id);
    const errorBox = byId('posError');
    const showError = message => {
        errorBox.textContent = message;
        errorBox.hidden = !message;
        if (message) errorBox.scrollIntoView({block: 'nearest'});
    };
    const element = (tag, text, className) => { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; };
    function clearFieldError(control) {
        const feedback = byId(control.getAttribute('aria-errormessage'));
        if (feedback?.classList.contains('field-error')) feedback.remove();
        control.removeAttribute('aria-invalid');
        control.removeAttribute('aria-errormessage');
    }
    function markFieldError(control, message) {
        clearFieldError(control);
        const feedback = element('span', message, 'field-error');
        feedback.id = `${control.id}Error`;
        control.setAttribute('aria-invalid', 'true');
        control.setAttribute('aria-errormessage', feedback.id);
        control.after(feedback);
    }
    function focusField(control) {
        const panel = control?.closest('[role="tabpanel"]');
        if (panel) displayStep(panels.indexOf(panel), false);
        control?.focus();
    }
    function fieldErrors(errors = {}) {
        app.querySelectorAll('.field-error').forEach(node => node.remove());
        app.querySelectorAll('[aria-invalid]').forEach(clearFieldError);
        const fields = {customer_id: 'customerId', address: 'deliveryAddress', notes: 'orderNotes', type: 'orderType', payment_method: 'paymentMethod', amount_tendered: 'amountTendered', reference: 'paymentReference'};
        Object.entries(errors).forEach(([key, messages]) => {
            const itemField = key.match(/^items\.(\d+)\.(quantity|discount_percent)$/);
            const input = itemField ? byId(`cart-${cart[Number(itemField[1])]?.product_id}-${itemField[2]}`) : byId(fields[key]);
            if (!input) return;
            markFieldError(input, [].concat(messages).join(' '));
        });
    }
    function displayStep(step, focus = true) {
        activeStep = step;
        panels.forEach((panel, index) => { panel.hidden = index !== step; });
        tabs.forEach((tab, index) => {
            tab.setAttribute('aria-selected', String(index === step));
            tab.tabIndex = index === step ? 0 : -1;
        });
        if (step === 1) deliveryMap.resize();
        if (focus) tabs[step].focus();
    }

    function invalid(control, message) {
        showError(message);
        if (control) markFieldError(control, message);
        focusField(control);
        return false;
    }

    function validFields(scope, draft = false) {
        for (const input of scope.querySelectorAll('input, textarea, select')) {
            if (draft && ['customerId', 'deliveryAddress', 'paymentReference'].includes(input.id)) continue;
            if (!input.disabled && !input.checkValidity()) return invalid(input, input.validationMessage);
        }
        return true;
    }

    function validateOrder(stock = true) {
        if (!cart.length) return invalid(byId('productSearch'), 'Select at least one product to continue.');
        if (!validFields(panels[0])) return false;
        for (const item of cart) {
            const product = productFor(item);
            if (!product) return invalid(byId('cartItems'), 'Remove unavailable products from the order.');
            if (stock && item.quantity > product.stock) return invalid(byId('cartItems'), `${product.name}: only ${product.stock} available. Adjust the quantity.`);
        }
        return true;
    }

    function validateCustomer() {
        if (!validFields(panels[1])) return false;
        if (byId('orderType').value !== 'Delivery') return true;
        if (!byId('customerId').value) return invalid(byId('customerId'), 'Select a saved customer for delivery.');
        if (!byId('deliveryAddress').value.trim()) return invalid(byId('deliveryAddress'), 'Enter a delivery address or select a location on the map.');
        if (locatingAddress) return invalid(byId('deliveryAddress'), 'Wait for the delivery zone lookup to finish.');
        if (!deliveryMap.getSelectedZone()) return invalid(byId('deliveryAddress'), 'Check the delivery address: a supported delivery zone is required.');
        return true;
    }

    function validatePayment() {
        if (!validFields(panels[2])) return false;
        const method = byId('paymentMethod').value;
        const total = totals().total;
        const received = method === 'Charge' ? 0 : cents(byId('amountTendered').value);
        if (['Online', 'Cheque'].includes(method) && !byId('paymentReference').value.trim()) return invalid(byId('paymentReference'), 'Enter the payment reference.');
        if (method === 'Charge' && !byId('customerId').value) return invalid(byId('customerId'), 'Select a customer for a Charge order.');
        if (method !== 'Charge' && total > 0 && received <= 0) return invalid(byId('amountTendered'), 'Enter the amount received, or select Charge for an unpaid order.');
        if (method !== 'Cash' && received > total) return invalid(byId('amountTendered'), 'Only cash payments can include change.');
        if (received < total && !byId('customerId').value) return invalid(byId('customerId'), 'Select a customer for an outstanding balance.');
        return true;
    }

    function goToStep(step) {
        if (saving) return;
        if (step > activeStep && (!validateOrder() || (step === 2 && !validateCustomer()))) return;
        showError('');
        fieldErrors();
        displayStep(step);
    }

    function updateProgress() {
        const customer = byId('customerId').value;
        tabs[0].querySelector('.step-check').hidden = !cart.length || cart.some(item => !productFor(item) || item.quantity > productFor(item).stock) || ![...panels[0].querySelectorAll('input')].every(input => input.checkValidity());
        tabs[1].querySelector('.step-check').hidden = !cart.length || ![...panels[1].querySelectorAll('input, textarea, select')].every(input => input.disabled || input.checkValidity()) || (byId('orderType').value === 'Delivery' && (!customer || !byId('deliveryAddress').value.trim() || locatingAddress || !deliveryMap.getSelectedZone()));
    }

    app.querySelectorAll('[data-step]').forEach(button => button.addEventListener('click', () => goToStep(Number(button.dataset.step))));
    tabs.forEach((tab, index) => tab.addEventListener('keydown', event => {
        const step = {ArrowRight: (index + 1) % 3, ArrowLeft: (index + 2) % 3, Home: 0, End: 2}[event.key];
        if (step !== undefined) { event.preventDefault(); goToStep(step); }
    }));
    app.addEventListener('input', event => {
        changed = true;
        clearFieldError(event.target);
        updateProgress();
    });
    const deliveryMap = window.createStaffDeliveryMap({
        onChange: () => { changed = true; },
        onZoneChange: zone => {
            byId('deliveryFee').value = zone ? Number(zone.fee).toFixed(2) : '0.00';
            updateTotals();
        },
        onBusyChange: busy => { locatingAddress = busy; updateSaveButtons(); updateProgress(); },
    });

    function updateSaveButtons() {
        document.querySelectorAll('[data-save]').forEach(button => {
            button.disabled = saving || !cart.length || (byId('orderType').value === 'Delivery' && locatingAddress);
        });
    }

    function renderCatalog() {
        const grid = byId('productGrid');
        grid.replaceChildren();
        const search = byId('productSearch').value.toLocaleLowerCase();
        const category = byId('categoryFilter').value;
        const filtered = products.filter(product => product.name.toLocaleLowerCase().includes(search) && (!category || product.category === category));
        filtered.forEach(product => {
            const card = element('button', undefined, 'catalog-card');
            card.type = 'button';
            card.disabled = saving || product.stock <= 0;
            const picture = element('span', '▦', 'product-symbol');
            if (product.image_url) {
                const photo = element('img', undefined, 'catalog-product-photo');
                photo.src = product.image_url;
                photo.alt = product.name;
                photo.loading = 'lazy';
                photo.addEventListener('error', () => photo.replaceWith(element('span', '▦')));
                picture.replaceChildren(photo);
            }
            card.append(picture, element('strong', product.name), element('small', product.category),
                element('span', money(cents(product.price)), 'price'), element('small', product.stock > 0 ? `${product.stock} ${product.stock_unit || 'units'} available` : 'Out of stock'));
            card.addEventListener('click', () => {
                const existing = cart.find(item => item.product_id === product.id);
                if (existing && existing.quantity >= product.stock) { showError(`Only ${product.stock} ${product.name} available.`); return; }
                if (existing) existing.quantity++;
                else cart.push({product_id: product.id, quantity: 1, discount_percent: 0, name: product.name});
                changed = true;
                showError('');
                renderCart();
            });
            grid.append(card);
        });
        if (!filtered.length) grid.append(element('p', 'No products found.', 'empty-state'));
    }

    function renderCart() {
        const container = byId('cartItems');
        container.replaceChildren();
        cart.forEach(item => {
            const product = productFor(item);
            const row = element('div', undefined, 'cart-row');
            const header = element('div', undefined, 'cart-row-header');
            const remove = element('button', '×', 'remove-item');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Remove ${product?.name || item.name}`);
            remove.addEventListener('click', () => { cart = cart.filter(other => other !== item); changed = true; renderCart(); });
            header.append(element('strong', product?.name || `${item.name} (unavailable)`), remove);
            const fields = element('div', undefined, 'cart-row-fields');
            [['Quantity', 'quantity', '1', '1000000', '1'], ['Discount %', 'discount_percent', '0', '100', '0.01']].forEach(([label, key, min, max, step]) => {
                const wrapper = element('label', label);
                const input = document.createElement('input');
                Object.assign(input, {type: 'number', min, max, step, value: item[key], required: true});
                input.id = `cart-${item.product_id}-${key}`;
                input.addEventListener('input', () => { if (!input.checkValidity()) return; item[key] = Number(input.value); changed = true; updateTotals(); });
                wrapper.append(input); fields.append(wrapper);
            });
            row.append(header, element('p', product ? `${money(cents(product.price))} each · ${product.stock} available` : 'Remove this item to continue.', 'sync-note'), fields);
            const lineTotal = element('p', '', 'line-subtotal');
            lineTotal.dataset.productTotal = item.product_id;
            row.append(lineTotal);
            container.append(row);
        });
        if (!cart.length) container.append(element('p', 'Select a product to start an order.', 'empty-state'));
        updateTotals();
    }

    function totals() {
        let subtotal = 0, discount = 0;
        cart.forEach(item => { const gross = cents(productFor(item)?.price) * item.quantity; subtotal += gross; discount += Math.round(gross * item.discount_percent / 100); });
        const fee = byId('orderType').value === 'Delivery' ? cents(byId('deliveryFee').value) : 0;
        return {subtotal, discount, total: subtotal - discount + fee};
    }

    function updateTotals() {
        const values = totals();
        cart.forEach(item => {
            const gross = cents(productFor(item)?.price) * item.quantity;
            const line = app.querySelector(`[data-product-total="${item.product_id}"]`);
            if (line) line.textContent = `Item subtotal: ${money(gross - Math.round(gross * item.discount_percent / 100))}`;
        });
        const isCash = byId('paymentMethod').value === 'Cash';
        const isCharge = byId('paymentMethod').value === 'Charge';
        byId('amountTendered').disabled = isCharge || saving;
        const tendered = isCharge ? 0 : cents(byId('amountTendered').value);
        byId('itemCount').textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
        byId('subtotal').textContent = money(values.subtotal);
        byId('discountTotal').textContent = money(values.discount);
        byId('grandTotal').textContent = money(values.total);
        byId('changeDue').textContent = money(isCash ? Math.max(0, tendered - values.total) : 0);
        byId('balanceDue').textContent = money(Math.max(0, values.total - tendered));
        byId('summaryDeliveryFee').textContent = money(byId('orderType').value === 'Delivery' ? cents(byId('deliveryFee').value) : 0);
        const customer = data.customers.find(customer => customer.id === Number(byId('customerId').value));
        byId('customerName').value = customer?.name || 'Walk-in customer';
        byId('customerPhone').value = customer?.phone || '';
        byId('summaryCustomer').textContent = customer?.name || 'Walk-in customer';
        byId('summaryOrderType').textContent = byId('orderType').value;
        byId('summaryPayment').textContent = `${byId('paymentMethod').value} · Balance ${money(Math.max(0, values.total - tendered))} · Change ${money(isCash ? Math.max(0, tendered - values.total) : 0)}`;
        byId('checkoutItems').replaceChildren(...cart.map(item => element('p', `${item.quantity} × ${productFor(item)?.name || item.name}`, 'checkout-item')));
        byId('summaryItems').replaceChildren(...cart.map(item => element('p', `${item.quantity} × ${productFor(item)?.name || item.name}`, 'checkout-item')));
        const delivery = byId('orderType').value === 'Delivery';
        byId('deliveryDetails').hidden = !delivery;
        byId('deliveryFee').disabled = !delivery || saving;
        byId('deliveryAddress').disabled = !delivery || saving;
        byId('deliveryAddress').required = delivery;
        byId('customerId').required = delivery || isCharge;
        byId('paymentReference').required = ['Online', 'Cheque'].includes(byId('paymentMethod').value);
        byId('orderReview').textContent = `${customer?.name || 'Walk-in customer'} · ${byId('orderType').value} · ${byId('itemCount').textContent} items · ${money(values.total)} · ${byId('paymentMethod').value}${isCharge ? ' (Pay Later)' : ''}. Review the details before confirming.`;
        deliveryMap.setEnabled(delivery && !saving);
        updateProgress();
        updateSaveButtons();
    }

    async function refreshCatalog() {
        if (saving) return;
        try {
            const response = await fetch(app.dataset.catalog, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error();
            const latest = await response.json();
            // Recompute cart totals from the same current catalog used by checkout.
            const priceChanged = cart.some(item => cents(productFor(item)?.price) !== cents(latest.find(product => product.id === item.product_id)?.price));
            products = latest;
            const select = byId('categoryFilter');
            const previous = select.value;
            select.replaceChildren(new Option('All categories', ''));
            [...new Set(products.map(product => product.category))].sort().forEach(category => select.add(new Option(category, category)));
            select.value = previous;
            renderCatalog();
            if (!document.activeElement?.closest('#cartItems')) renderCart();
            else updateTotals();
            byId('catalogStatus').textContent = `Catalog synced at ${new Date().toLocaleTimeString('en-PH')}.`;
            if (priceChanged) showError('Product prices changed. Review the updated order total before completing the order.');
        } catch {
            byId('catalogStatus').textContent = 'Unable to refresh catalog. Check your connection or sign in again. Your cart is still here.';
        }
    }

    async function save(action) {
        if (saving || !cart.length) return;
        fieldErrors();
        if (!validateOrder(action === 'checkout')) return;
        if (action === 'checkout' && (!validateCustomer() || !validatePayment())) return;
        if (action !== 'checkout' && !validFields(app, true)) return;
        if (byId('orderType').value === 'Delivery' && locatingAddress) { invalid(byId('deliveryAddress'), 'Wait for the delivery address lookup to finish.'); return; }
        const payload = {
            request_key: app.dataset.key, order_id: data.draft?.id, version: data.draft?.version,
            action, type: byId('orderType').value, customer_id: byId('customerId').value || null,
            address: byId('deliveryAddress').value || null, notes: byId('orderNotes').value || null,
            delivery_fee: byId('deliveryFee').value || 0, payment_method: byId('paymentMethod').value,
            amount_tendered: byId('paymentMethod').value === 'Charge' ? 0 : (byId('amountTendered').value || 0),
            reference: byId('paymentReference').value || null,
            items: cart.map(item => ({product_id: item.product_id, quantity: item.quantity, discount_percent: item.discount_percent, expected_price: productFor(item).price})),
        };
        saving = true;
        app.setAttribute('aria-busy', 'true');
        const saveButton = app.querySelector(`[data-save="${action}"]`);
        const saveLabel = saveButton.textContent;
        saveButton.textContent = 'Saving…';
        deliveryMap.setEnabled(false);
        showError('');
        app.querySelectorAll('button, input, select, textarea').forEach(control => { control.disabled = true; });
        try {
            const response = await fetch(app.dataset.checkout, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(payload)});
            if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Your session may have expired. Keep this page open and sign in again in another tab.');
            const result = await response.json();
            if (!response.ok) fieldErrors(result.errors);
            if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Could not save this order.'));
            leaving = true;
            window.location.assign(result.redirect);
        } catch (error) {
            showError(error.message || 'Could not save the order. Please try again.');
            saving = false;
            app.setAttribute('aria-busy', 'false');
            saveButton.textContent = saveLabel;
            app.querySelectorAll('button, input, select, textarea').forEach(control => { control.disabled = false; });
            renderCatalog();
            updateTotals();
            focusField(app.querySelector('[aria-invalid="true"]'));
        }
    }

    byId('productSearch').addEventListener('input', renderCatalog);
    byId('categoryFilter').addEventListener('change', renderCatalog);
    byId('refreshCatalog').addEventListener('click', refreshCatalog);
    ['orderType', 'deliveryFee', 'paymentMethod', 'amountTendered'].forEach(id => byId(id).addEventListener('input', () => { changed = true; updateTotals(); }));
    byId('customerId').addEventListener('change', () => {
        clearFieldError(byId('customerId'));
        const customer = data.customers.find(customer => customer.id === Number(byId('customerId').value));
        if (!byId('deliveryAddress').value.trim() || !byId('deliveryAddress').dataset.manual) {
            byId('deliveryAddress').value = customer?.address || '';
        }
        deliveryMap.reset();
        updateTotals();
        changed = true;
    });
    byId('deliveryAddress').addEventListener('input', () => { byId('deliveryAddress').dataset.manual = 'true'; changed = true; });
    byId('orderNotes').addEventListener('input', () => { changed = true; });
    byId('clearCart').addEventListener('click', () => { if (window.confirm('Clear the current cart? Saved orders will remain in Orders.')) { cart = []; changed = false; renderCart(); showError(''); } });
    document.querySelectorAll('[data-save]').forEach(button => button.addEventListener('click', () => save(button.dataset.save)));
    window.addEventListener('beforeunload', event => { if (!leaving && changed && cart.length) { event.preventDefault(); event.returnValue = ''; } });
    setInterval(() => { if (!document.hidden) refreshCatalog(); }, 30000);
    renderCatalog();
    renderCart();
})();
