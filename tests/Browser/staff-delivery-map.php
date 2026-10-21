<?php

// Render the real POS with isolated data and deterministic map/network responses.
// Usage: php tests/Browser/staff-delivery-map.php <output.html>
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::guard('staff')->setUser(new App\Models\Staff([
    'name' => 'Delivery Test', 'role' => 'Staff', 'permissions' => ['pos'],
]));
$html = view('staff.pos', [
    'errors' => new Illuminate\Support\ViewErrorBag,
    'products' => collect([['id' => 1, 'name' => 'Cement', 'category' => 'Materials', 'price' => 100, 'stock' => 10]]),
    'customers' => collect([(object) ['id' => 1, 'name' => 'Buyer', 'phone' => '', 'address' => 'Silang, Cavite']]),
    'draft' => null,
])->render();
$html = preg_replace('~<script\b[^>]*\bsrc=[^>]*>.*?</script>|<link\b[^>]*>|<img\b[^>]*>~s', '', $html);
$fixture = <<<'JS'
const errors = [];
window.addEventListener('error', event => errors.push(event.message));
const point = {lat: 14.2, lng: 120.9, wrap() { return this; }};
const mapEvents = {};
const fakeMap = {setView() { return this; }, on(name, handler) { mapEvents[name] = handler; }, invalidateSize() {}, removeLayer() {}, getCenter() { return point; }};
window.L = {
    map: () => fakeMap,
    tileLayer: () => ({addTo() {}}),
    marker: () => ({addTo() { return this; }, on() {}, setLatLng() {}, dragging: {enable() {}, disable() {}}}),
};
let slowResponse;
window.fetch = async (url, options = {}) => {
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
        byId('customerId').value = '1';
        byId('customerId').dispatchEvent(new Event('change'));
        byId('orderType').value = 'Delivery';
        byId('orderType').dispatchEvent(new Event('input'));
        assert(document.querySelector('[data-save]').disabled, 'Checkout blocked during lookup');
        await wait();
        assert(byId('deliveryZoneName').textContent === 'Silang', 'Saved customer address gets a zone');
        assert(total() === 600 && Number(byId('deliveryFee').value) === 500, 'Saved address fee included in total');
        assert(!document.querySelector('[data-save]').disabled, 'Checkout enabled after lookup');

        mapEvents.click({latlng: point});
        await wait();
        assert(byId('deliveryZoneName').textContent === 'Imus', 'Pin reverse geocodes and detects zone');
        assert(total() === 1000 && Number(byId('deliveryFee').value) === 900, 'Pinned zone updates fee and total');

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
        assert(!errors.length, errors.join('; '));
        document.body.innerHTML = '<pre id="result">PASS: saved address, map pin, fee totals, manual edits, stale responses, pickup, checkout state</pre>';
    } catch (error) {
        document.body.innerHTML = '<pre id="result"></pre>';
        byId('result').textContent = 'FAIL: ' + error.message;
    }
})();
JS;
$scripts = $fixture."\n".file_get_contents(public_path('js/staff-delivery-map.js'))."\n"
    .file_get_contents(public_path('js/staff-pos.js'))."\n".$checks;
file_put_contents($argv[1], str_replace('</body>', '<script>'.$scripts.'</script></body>', $html));
