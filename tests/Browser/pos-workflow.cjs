// Browser regression checks using the real POS markup, scripts and styles.
// Network/Leaflet fixtures isolate UI behavior; this does not replace Laravel tests.
// Run: node tests/Browser/pos-workflow.cjs (Chrome installed, or CHROME_PATH set).
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const http = require('node:http');
const {spawn, spawnSync} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const render = spawnSync(process.env.PHP_PATH || 'php', ['tests/Browser/staff-delivery-map.php', '--render-only'], {cwd: root, encoding: 'utf8', windowsHide: true});
if (render.status !== 0 || !render.stdout.includes('id="posApp"')) throw new Error(render.stderr || render.stdout || 'POS Blade render failed');
const markup = render.stdout.replace(/<script\b[^>]*>[\s\S]*?<\/script>/g, '')
    .replace(/data-checkout="[^"]*"/, 'data-checkout="/checkout"').replace(/data-catalog="[^"]*"/, 'data-catalog="/catalog"')
    .replace(/data-zone-url="[^"]*"/, 'data-zone-url="/zone"').replace(/data-reverse-url="[^"]*"/, 'data-reverse-url="https://fixture.test/reverse"');
const fixture = `
window.errors = []; addEventListener('error', e => errors.push(e.message));
window.staffPosData = {products: Array.from({length: 30}, (_, i) => ({id: i + 1, name: 'Product ' + (i + 1), category: 'Materials', price: 100, stock: 10, stock_unit: 'bags'})), customers: [{id: 1, name: 'Buyer', phone: '09171234567', address: 'Silang, Cavite'}], draft: null};
if (location.search.includes('draft')) staffPosData.draft = {id: 12, version: 2, items: [{product_id: 1, name: 'Product 1', quantity: 2, discount_percent: 10}]};
window.mapResizes = 0; window.mapEvents = {}; window.saves = []; window.saveErrors = null;
const point = {lat: 14.2, lng: 120.9, wrap() {return this;}};
const map = {setView() {return this;}, on(n, fn) {mapEvents[n] = fn;}, invalidateSize() {mapResizes++;}, removeLayer() {}, getCenter() {return point;}};
window.L = {map: () => map, tileLayer: () => ({addTo() {}}), latLng: () => point, marker: () => ({addTo() {return this;}, on() {}, setLatLng() {}, dragging: {enable() {}, disable() {}}})};
window.fetch = async (url, options = {}) => {
 const json = data => new Response(JSON.stringify(data), {headers: {'Content-Type': 'application/json'}});
 if (url === '/catalog') return json(staffPosData.products);
 if (url === '/checkout') {saves.push(JSON.parse(options.body)); await new Promise(r => setTimeout(r, 150)); return new Response(JSON.stringify({message: 'Fixture save rejected for retry testing.', errors: window.saveErrors}), {status: 422, headers: {'Content-Type': 'application/json'}});}
 if (url === '/zone') return json({success: true, zone: {zone_name: 'Silang', fee: 500}});
 return json({features: [{geometry: {coordinates: [120.9, 14.2]}, properties: {city: 'Silang', state: 'Cavite'}}]});
};`;
const html = markup.replace('</body>', `<script>${fixture}</script><script>${read('public/js/staff-delivery-map.js')}</script><script>${read('public/js/staff-pos.js')}</script></body>`);
const mapFixturePath = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'tmta-pos-fixture-')), 'delivery.html');
const mapRender = spawnSync(process.env.PHP_PATH || 'php', ['tests/Browser/staff-delivery-map.php', mapFixturePath], {cwd: root, encoding: 'utf8', windowsHide: true});
if (mapRender.status !== 0) throw new Error(mapRender.stderr || mapRender.stdout);
const server = http.createServer((req, res) => {res.setHeader('Content-Type', 'text/html'); res.end(req.url === '/delivery-check' ? fs.readFileSync(mapFixturePath) : html);});
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'tmta-pos-browser-'));
let browser;
let socket;
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], {windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
    const endpoint = await new Promise((resolve, reject) => {
        let output = '';
        browser.on('error', reject);
        browser.stderr.on('data', chunk => {output += chunk; const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/); if (match) resolve(match[1]);});
        setTimeout(() => reject(new Error('Chrome launch timed out')), 15000).unref();
    });
    const targets = await fetch(new URL('/json/list', endpoint.replace('ws:', 'http:'))).then(r => r.json());
    socket = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once: true}));
    let id = 0; const pending = new Map();
    socket.addEventListener('message', e => {const value = JSON.parse(e.data); if (pending.has(value.id)) {pending.get(value.id)(value); pending.delete(value.id);}});
    const send = (method, params = {}) => new Promise(resolve => {const key = ++id; pending.set(key, resolve); socket.send(JSON.stringify({id: key, method, params}));});
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true});
        if (result.result.exceptionDetails) throw new Error(result.result.exceptionDetails.exception?.description || result.result.exceptionDetails.text);
        return result.result.result.value;
    };
    const navigate = async (suffix = '') => {
        await send('Page.navigate', {url: 'http://127.0.0.1:' + server.address().port + '/' + suffix});
        for (let attempt = 0; attempt < 100; attempt++) {
            if (await evaluate('Boolean(document.querySelector(".catalog-card"))')) return;
            await new Promise(r => setTimeout(r, 30));
        }
        throw new Error('POS did not initialize');
    };
    await send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 900, deviceScaleFactor: 1, mobile: false});
    await navigate();
    console.log(await evaluate(`(async () => {
        const assert = (value, message) => {if (!value) throw new Error(message);};
        const byId = id => document.getElementById(id);
        const click = selector => document.querySelector(selector).click();
        const edit = (id, value) => {byId(id).value = value; byId(id).dispatchEvent(new Event('input', {bubbles: true}));};
        const wait = () => new Promise(r => setTimeout(r, 550));
        assert(!errors.length, errors.join('; '));
        assert(document.querySelectorAll('[id]').length === new Set([...document.querySelectorAll('[id]')].map(n => n.id)).size, 'Duplicate IDs');
        click('#tab-payment'); assert(!byId('step-order').hidden && !byId('posError').hidden, 'Empty cart navigation validation');
        click('.catalog-card'); click('.catalog-card');
        assert(byId('itemCount').textContent === '2', 'Cart quantity');
        edit('cart-1-quantity', '0'); click('#tab-customer');
        assert(!byId('step-order').hidden && byId('cart-1-quantityError').textContent && document.activeElement.id === 'cart-1-quantity', 'Invalid quantity stays visible with inline feedback');
        assert(byId('tab-order').querySelector('.step-check').hidden, 'Invalid quantity clears completed indicator');
        edit('cart-1-quantity', '2'); assert(!byId('cart-1-quantityError'), 'Editing clears stale field feedback');
        edit('cart-1-quantity', '11'); click('#tab-customer'); assert(!byId('step-order').hidden && byId('posError').textContent.includes('only 10'), 'Stock validation before advancing'); edit('cart-1-quantity', '2');
        edit('cart-1-discount_percent', '10'); assert(byId('grandTotal').textContent.includes('180.00'), 'Live discount total'); edit('cart-1-discount_percent', '0');
        document.querySelectorAll('.catalog-card')[1].click(); click('[aria-label="Remove Product 2"]'); assert(byId('itemCount').textContent === '2', 'Remove item recalculates totals');
        edit('productSearch', 'Product 30'); assert(document.querySelectorAll('.catalog-card').length === 1, 'Catalog search'); edit('productSearch', '');
        click('#tab-customer'); assert(!byId('step-customer').hidden, 'Customer tab');
        assert(byId('deliveryDetails').hidden, 'Walk-in delivery fields hidden');
        byId('customerId').value = '1'; byId('customerId').dispatchEvent(new Event('change'));
        assert(byId('customerPhone').value === '09171234567', 'Saved customer contact');
        edit('orderType', 'Delivery'); await wait();
        assert(!byId('deliveryDetails').hidden && byId('deliveryFee').value === '500.00', 'Saved address zone lookup');
        const address = byId('deliveryAddress').value;
        click('#expandDeliveryMap'); assert(byId('deliveryMapDialog').open && byId('deliveryMapDialog').contains(byId('deliveryMap')), 'Expanded delivery map');
        click('#closeDeliveryMap'); await wait(); assert(!byId('deliveryMapDialog').open && byId('deliveryMapHome').contains(byId('deliveryMap')) && byId('deliveryAddress').value === address, 'Map restored with address preserved');
        assert(byId('grandTotal').textContent.includes('700.00'), 'Shared delivery total');
        click('#tab-payment'); edit('paymentMethod', 'Online'); edit('amountTendered', '700');
        click('[data-save="checkout"]'); assert(byId('paymentReference').getAttribute('aria-invalid') === 'true', 'Online reference validation');
        edit('paymentReference', 'REF-123'); edit('orderNotes', 'Keep this note');
        click('#tab-order'); click('#tab-payment');
        assert(byId('orderNotes').value === 'Keep this note' && byId('amountTendered').value === '700', 'Payment retained across tabs');
        assert(saves.length === 0, 'Tab switches never save');
        click('#tab-customer'); const resizes = mapResizes; click('#tab-payment'); click('#tab-customer'); await wait(); assert(mapResizes > resizes, 'Map resized on tab return');
        edit('deliveryAddress', 'Manual Silang address'); await wait(); assert(byId('deliveryZoneName').textContent === 'Silang', 'Manual address zone');
        mapEvents.click({latlng: point}); await wait(); assert(byId('deliveryAddress').value.includes('Silang'), 'Map pin fills address');
        click('#tab-payment'); edit('paymentMethod', 'Cash'); edit('amountTendered', '800'); assert(byId('changeDue').textContent.includes('100.00'), 'Cash change');
        click('[data-save="checkout"]'); click('[data-save="checkout"]'); await wait(); assert(saves.length === 1, 'Duplicate save prevented');
        assert(saves[0].reference === 'REF-123' && saves[0].customer_id === '1' && saves[0].items[0].quantity === 2, 'Original checkout payload retained');
        edit('paymentMethod', 'Charge'); assert(byId('amountTendered').disabled, 'Charge disables amount');
        click('[data-save="hold"]'); await wait(); assert(saves[1].action === 'hold', 'Hold order action');
        click('[data-save="quote"]'); await wait(); assert(saves[2].action === 'quote', 'Quotation action');
        saveErrors = {customer_id: ['Select an active customer.']};
        click('[data-save="checkout"]'); await wait();
        assert(!byId('step-customer').hidden && document.activeElement.id === 'customerId' && byId('customerIdError').textContent === 'Select an active customer.', 'Server validation opens hidden customer panel and focuses field');
        saveErrors = null; byId('customerId').dispatchEvent(new Event('change'));
        click('#refreshCatalog'); await wait(); assert(byId('itemCount').textContent === '2', 'Catalog refresh retains cart');
        assert(!errors.length, errors.join('; '));
        return 'PASS: cart, navigation, saved customer, delivery, map pin/resize, cash/online/charge, persistence, duplicate save, hold, quotation, refresh';
    })()`));
    if (process.env.POS_SCREENSHOT) {
        await evaluate('document.getElementById("tab-order").click()');
        const capture = await send('Page.captureScreenshot', {format: 'png'});
        fs.writeFileSync(process.env.POS_SCREENSHOT, Buffer.from(capture.result.data, 'base64'));
    }
    for (const width of [1440, 1024, 768, 390]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height: 900, deviceScaleFactor: 1, mobile: width < 600});
        console.log(await evaluate(`(() => {const app = document.getElementById('posApp'); const box = app.getBoundingClientRect(); if (box.height < 300 || box.width < 200 || app.scrollWidth > app.clientWidth + 1 || box.bottom > 901) throw new Error('Layout overflow or collapsed POS at ${width}'); return 'PASS: ${width}px layout';})()`));
        if (process.env.POS_SCREENSHOT) {
            const capture = await send('Page.captureScreenshot', {format: 'png'});
            fs.writeFileSync(process.env.POS_SCREENSHOT.replace(/\.png$/, '-' + width + '.png'), Buffer.from(capture.result.data, 'base64'));
        }
    }
    // Avoid the unsaved-changes prompt during fixture reload.
    await evaluate('document.getElementById("clearCart").click = () => {}; window.onbeforeunload = null;');
    await send('Page.handleJavaScriptDialog', {accept: true});
    await send('Page.addScriptToEvaluateOnNewDocument', {source: 'window.confirm = () => true;'});
    await evaluate('window.confirm = () => true; document.getElementById("clearCart").dispatchEvent(new MouseEvent("click"));');
    await navigate('?draft');
    console.log(await evaluate(`(() => {if (document.getElementById('itemCount').textContent !== '2' || !document.getElementById('discountTotal').textContent.includes('20.00')) throw new Error('Draft not restored'); return 'PASS: resumed draft and discount totals';})()`));
    await evaluate('window.confirm = () => true; document.getElementById("clearCart").click();');
    await send('Page.navigate', {url: 'http://127.0.0.1:' + server.address().port + '/delivery-check'});
    let deliveryResult;
    for (let attempt = 0; attempt < 150; attempt++) {
        deliveryResult = await evaluate('document.getElementById("result")?.textContent');
        if (deliveryResult) break;
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    if (!deliveryResult?.startsWith('PASS:')) throw new Error(deliveryResult || 'Delivery map regression timed out');
    console.log(deliveryResult);
})().catch(error => {console.error(error); process.exitCode = 1;}).finally(() => {
    socket?.close(); browser?.kill(); server.close();
    // Chrome may still hold profile files briefly; the OS temp directory owns them.
});
