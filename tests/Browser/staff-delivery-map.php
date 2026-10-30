<?php

// Render the real POS with isolated data and deterministic map/network responses.
// Usage: php tests/Browser/staff-delivery-map.php <output.html> (or --render-only for the workflow runner)
require dirname(__DIR__, 2).'/vendor/autoload.php';
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('DB_URL=');
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
    throw new RuntimeException('Browser fixtures must use the isolated SQLite :memory: database.');
}
// Match AuthenticationTestCase: legacy purchase-item migrations lack a create migration.
foreach (glob(database_path('migrations/*.php')) as $path) {
    if (!str_contains(basename($path), 'purchase_order_items')) (require $path)->up();
}
Illuminate\Support\Facades\Auth::guard('staff')->setUser(new App\Models\Staff([
    'name' => 'Delivery Test', 'role' => 'Staff', 'permissions' => ($argv[1] ?? '') === '--render-only' ? array_keys(config('modules.staff')) : ['pos'],
]));
$html = view('staff.pos', [
    'errors' => new Illuminate\Support\ViewErrorBag,
    'products' => collect([['id' => 1, 'name' => 'Cement', 'category' => 'Materials', 'price' => 100, 'stock' => 10]]),
    'customers' => collect([(object) ['id' => 1, 'name' => 'Buyer', 'phone' => '', 'address' => 'Silang, Cavite']]),
    'draft' => null,
])->render();
$html = preg_replace_callback('~<link\b[^>]*href="([^"]+)"[^>]*>~', function ($match) {
    $path = parse_url(html_entity_decode($match[1]), PHP_URL_PATH);
    return str_starts_with($path ?? '', '/css/') && is_file(public_path(ltrim($path, '/')))
        ? '<style>'.file_get_contents(public_path(ltrim($path, '/'))).'</style>' : '';
}, $html);
$html = preg_replace('~<script\b[^>]*\bsrc=[^>]*>.*?</script>|<img\b[^>]*>~s', '', $html);
if (($argv[1] ?? '') === '--render-only') {
    echo $html;
    exit;
}
$fixture = <<<'JS'
const errors = [];
window.addEventListener('error', event => errors.push(event.message));
const point = {lat: 14.2, lng: 120.9, wrap() { return this; }};
const mapEvents = {};
let resizeCount = 0;
const fakeMap = {setView() { return this; }, on(name, handler) { mapEvents[name] = handler; }, invalidateSize() { resizeCount++; }, removeLayer() {}, getCenter() { return point; }};
window.L = {
    map: () => fakeMap,
    latLng: () => point,
    tileLayer: () => ({addTo() {}}),
    marker: () => ({addTo() { return this; }, on() {}, setLatLng() {}, dragging: {enable() {}, disable() {}}}),
};
let slowResponse;
let reverseResponse;
let failReverse = false;
let captureReverse = false;
let checkoutPayload;
let checkoutResponse;
let checkoutCount = 0;
window.fetch = async (url, options = {}) => {
    if (String(url).includes('/pos/checkout')) {
        checkoutCount++;
        checkoutPayload = JSON.parse(options.body);
        return new Promise(resolve => { checkoutResponse = resolve; });
    }
    if (!options.body && String(url).includes('/api/')) return new Response(JSON.stringify({features: [{geometry: {coordinates: [120.9, 14.2]}, properties: {name: 'Selected landmark', city: 'Silang', state: 'Cavite'}}]}));
    if (!options.body && failReverse) throw new Error('Service unavailable');
    if (!options.body && captureReverse) return new Promise(resolve => { reverseResponse = resolve; });
    if (!options.body) return new Response(JSON.stringify({features: [{properties: {city: 'Imus', state: 'Cavite'}}]}));
    const address = JSON.parse(options.body).address;
    if (address === 'Slow address') return new Promise(resolve => { slowResponse = resolve; });
    const zone = address.includes('Silang') ? {zone_id: 'silang', zone_name: 'Silang', fee: 500}
        : address.includes('Imus') ? {zone_id: 'imus', zone_name: 'Imus', fee: 900} : null;
    return new Response(JSON.stringify(zone ? {success: true, zone} : {success: false, message: 'Unknown delivery zone'}), {status: zone ? 200 : 422});
};
JS;
$checks = <<<'JS'
(async () => {
    const byId = id => document.getElementById(id);
    const wait = () => new Promise(resolve => setTimeout(resolve, 500));
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const edit = value => { byId('deliveryAddress').value = value; byId('deliveryAddress').dispatchEvent(new Event('input')); };
    const total = () => Number(byId('grandTotal').textContent.replace(/[^0-9.]/g, ''));
    try {
        assert(!errors.length, errors.join('; '));
        assert(byId('deliveryFee').readOnly, 'Fee must be calculated automatically');
        document.querySelector('.catalog-card').click();
        byId('tab-customer').click();
        byId('customerId').value = '1';
        byId('customerId').dispatchEvent(new Event('change'));
        byId('orderType').value = 'Delivery';
        byId('orderType').dispatchEvent(new Event('input'));
        assert(document.querySelector('[data-save]').disabled, 'Checkout blocked during lookup');
        await wait();
        assert(byId('deliveryZoneName').textContent === 'Silang', 'Saved customer address gets a zone');
        assert(total() === 600 && Number(byId('deliveryFee').value) === 500, 'Saved address fee included in total');
        assert(!document.querySelector('[data-save]').disabled, 'Checkout enabled after lookup');

        const savedAddress = byId('deliveryAddress').value;
        byId('expandDeliveryMap').click();
        await wait();
        assert(byId('deliveryMapDialog').open, 'Map expands into a modal');
        assert(byId('deliveryMapDialog').contains(byId('deliveryMap')), 'Same map moves into modal');
        assert(byId('deliveryAddress').value === savedAddress, 'Expansion preserves address');
        assert(resizeCount > 0, 'Map resize runs');
        byId('closeDeliveryMap').click();
        await wait();
        assert(!byId('deliveryMapDialog').open && byId('deliveryMapHome').contains(byId('deliveryMap')), 'Closing restores map');
        assert(byId('deliveryAddress').value === savedAddress, 'Closing preserves address');

        mapEvents.click({latlng: point});
        await wait();
        assert(byId('deliveryZoneName').textContent === 'Imus', 'Pin reverse geocodes and detects zone');
        assert(total() === 1000 && Number(byId('deliveryFee').value) === 900, 'Pinned zone updates fee and total');

        byId('deliveryLocationSearch').value = 'Silang';
        byId('searchDeliveryLocation').click();
        await wait();
        byId('deliverySearchResults').querySelector('button').click();
        await wait();
        assert(byId('deliveryAddress').value === 'Selected landmark, Silang, Cavite', 'Search selection uses selected address');
        edit('Unit 4, Silang, Cavite');
        await wait();
        failReverse = true;
        mapEvents.click({latlng: {...point, lat: 14.3}});
        assert(byId('deliveryAddress').value === 'Unit 4, Silang, Cavite', 'Pin does not erase existing text');
        await wait();
        assert(byId('deliveryAddress').value === 'Unit 4, Silang, Cavite', 'Failed geocoding preserves text');
        failReverse = false;
        captureReverse = true;
        mapEvents.click({latlng: {...point, lat: 14.4}});
        await wait();
        edit('Unit 9, Silang, Cavite');
        reverseResponse(new Response(JSON.stringify({features: [{properties: {city: 'Imus', state: 'Cavite'}}]})));
        await wait();
        assert(byId('deliveryAddress').value === 'Unit 9, Silang, Cavite', 'Late reverse geocoding cannot overwrite manual edit');
        captureReverse = false;

        edit('Unknown');
        assert(total() === 100, 'Editing clears previous fee immediately');
        await wait();
        assert(byId('deliveryZoneFee').textContent === 'Not calculated', 'Unknown zone clears display');

        edit('Slow address');
        await wait();
        edit('Silang, Cavite');
        await wait();
        slowResponse(new Response(JSON.stringify({success: true, zone: {zone_name: 'Old zone', fee: 999}})));
        await wait();
        assert(total() === 600 && byId('deliveryZoneName').textContent === 'Silang', 'Stale lookup cannot overwrite new address');

        byId('orderType').value = 'Pickup';
        byId('orderType').dispatchEvent(new Event('input'));
        assert(total() === 100, 'Pickup excludes delivery fee');
        byId('orderType').value = 'Delivery';
        byId('orderType').dispatchEvent(new Event('input'));
        assert(total() === 600, 'Delivery restores verified fee');
        edit('Unit 12, Silang, Cavite');
        await wait();
        byId('amountTendered').value = '1000';
        byId('amountTendered').dispatchEvent(new Event('input'));
        byId('tab-payment').click();
        document.querySelector('[data-save="checkout"]').click();
        document.querySelector('[data-save="checkout"]').click();
        assert(checkoutCount === 1, 'Duplicate clicks send one request');
        assert(checkoutPayload.address === 'Unit 12, Silang, Cavite', 'Manual address is submitted exactly');
        assert(checkoutPayload.items[0].quantity === 1 && checkoutPayload.payment_method === 'Cash', 'Order payload retains product and payment');
        checkoutResponse(new Response(JSON.stringify({message: 'Test validation failure'}), {status: 422, headers: {'Content-Type': 'application/json'}}));
        await wait();
        assert(byId('posError').textContent === 'Test validation failure', 'Save failure is visible');
        assert(!document.querySelector('[data-save]').disabled, 'Save can retry after failure');
        assert(document.documentElement.scrollWidth <= window.innerWidth, 'Page fits viewport');
        assert(!errors.length, errors.join('; '));
        document.body.innerHTML = '<pre id="result">PASS: expansion, search, manual address submission, failed geocoding, stale responses, totals, duplicate prevention, save errors, responsive layout</pre>';
    } catch (error) {
        document.body.innerHTML = '<pre id="result"></pre>';
        byId('result').textContent = 'FAIL: ' + error.message;
    }
})();
JS;
$scripts = $fixture."\n".file_get_contents(public_path('js/staff-delivery-map.js'))."\n"
    .file_get_contents(public_path('js/staff-pos.js'))."\n".$checks;
file_put_contents($argv[1], str_replace('</body>', '<script>'.$scripts.'</script></body>', $html));
