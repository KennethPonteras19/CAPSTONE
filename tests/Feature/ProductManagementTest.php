<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Staff;
use App\Services\CustomerOrderService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AuthenticationTestCase;

class ProductManagementTest extends AuthenticationTestCase
{
    private function enableCsrfValidation(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function productFormToken(): string
    {
        $response = $this->get(route('product.management'))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());

        return (new \DOMXPath($document))->query('//form[@id="productForm"]//input[@name="_token"]')
            ->item(0)->getAttribute('value');
    }

    public function test_product_form_can_save_with_csrf_validation_enabled(): void
    {
        $this->enableCsrfValidation();
        $token = $this->productFormToken();

        $this->post(route('products.store'), $this->productData([
            '_token' => $token, 'image' => $this->image('hammer.png'),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('product.management'));

        $this->assertDatabaseCount('products', 1);
        Storage::disk('public')->assertExists(Product::firstOrFail()->image_path);
    }

    public function test_expired_product_form_preserves_details_and_requires_a_fresh_submission(): void
    {
        $this->enableCsrfValidation();
        $expiredToken = $this->productFormToken();
        $this->app['session.store']->regenerateToken();

        $this->post(route('products.store'), $this->productData([
            '_token' => $expiredToken, 'image' => $this->image('hammer.png'),
        ]))->assertRedirect(route('product.management'))->assertSessionHasErrors('session')
            ->assertSessionHasInput('name', 'Hammer');
        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertArrayNotHasKey('_token', session()->getOldInput());

        $freshToken = $this->productFormToken();
        $this->assertNotSame($expiredToken, $freshToken);
        $this->post(route('products.store'), $this->productData(['_token' => $freshToken]))
            ->assertRedirect(route('product.management'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_expired_edit_form_does_not_modify_a_product(): void
    {
        $this->enableCsrfValidation();
        $product = Product::create($this->productData());

        $this->post(route('products.store'), $this->productData([
            'id' => $product->id, 'name' => 'Unsaved edit', '_token' => 'stale-token',
        ]))->assertRedirect(route('product.management'))->assertSessionHasErrors('session')
            ->assertSessionHasInput('id', $product->id)->assertSessionHasInput('name', 'Unsaved edit');
        $this->assertSame('Hammer', $product->fresh()->name);
        $this->get(route('product.management'))->assertOk()->assertSee('Unsaved edit')->assertSee('This form expired.');
    }

    public function test_json_product_requests_still_reject_invalid_csrf_tokens(): void
    {
        $this->enableCsrfValidation();

        $this->postJson(route('products.store'), $this->productData(['_token' => 'stale-token']))->assertStatus(419);
        $this->assertDatabaseCount('products', 0);
    }

    private function image(string $name): File
    {
        // A real 1x1 PNG, so upload tests do not require the optional GD extension.
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function productData(array $overrides = []): array
    {
        return array_merge(['name' => 'Hammer', 'category' => 'Tools', 'brand' => 'Acme',
            'stock' => 12, 'stock_unit' => 'piece', 'price' => 125], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(Admin::create(['name' => 'Product Admin', 'username' => 'product.admin',
            'email' => 'admin@example.test', 'password' => 'password123', 'is_active' => true,
            'permissions' => ['products']]), 'admin');
    }

    public function test_edit_button_contains_intact_product_data_with_quotes_and_html(): void
    {
        $product = Product::create($this->productData(['name' => '12" Builder\'s <Hammer> & Tools', 'brand' => 'A "B" & C']));
        $response = $this->get(route('product.management'))->assertOk()
            ->assertSee('Insert Image')->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('up to 10 MB')->assertSee('data-max-size="10485760"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $button = (new \DOMXPath($document))->query('//button[@data-product]')->item(0);
        $this->assertNotNull($button);
        $payload = json_decode($button->getAttribute('data-product'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($product->name, $payload['name']);
        $this->assertSame($product->brand, $payload['brand']);
        $this->assertSame($product->id, $payload['id']);
        $this->assertFalse($button->hasAttribute('onclick'));
    }

    public function test_admin_can_create_and_edit_a_product_with_an_image(): void
    {
        $this->post(route('products.store'), $this->productData([
            'image' => $this->image('hammer.png')->size(10240),
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $product = Product::firstOrFail();
        $originalImage = $product->image_path;
        Storage::disk('public')->assertExists($originalImage);

        $this->post(route('products.store'), $this->productData([
            'id' => $product->id, 'name' => 'Updated Hammer', 'image' => $this->image('new.jpg'),
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $product->refresh();
        $this->assertSame('Updated Hammer', $product->name);
        $this->assertNotSame($originalImage, $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertMissing($originalImage);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_editing_without_an_upload_preserves_the_image(): void
    {
        Storage::disk('public')->put('products/existing.png', 'existing photo');
        $product = Product::create($this->productData(['image_path' => 'products/existing.png']));
        $this->post(route('products.store'), $this->productData(['id' => $product->id, 'price' => 150]))
            ->assertSessionHasNoErrors();
        $this->assertSame('products/existing.png', $product->fresh()->image_path);
        $this->assertEquals(150, $product->fresh()->price);
        Storage::disk('public')->assertExists('products/existing.png');
    }

    public function test_invalid_and_oversized_uploads_do_not_change_the_product(): void
    {
        $product = Product::create($this->productData());
        foreach ([UploadedFile::fake()->create('document.txt', 10, 'text/plain'),
            $this->image('large.png')->size(10241)] as $image) {
            $this->from(route('product.management'))->post(route('products.store'), $this->productData([
                'id' => $product->id, 'name' => 'Unsaved name', 'image' => $image,
            ]))->assertSessionHasErrors('image')->assertRedirect(route('product.management'));
            $this->get(route('product.management'))->assertOk()->assertSee('Unsaved name');
            $this->assertSame('Hammer', $product->fresh()->name);
            $this->assertNull($product->fresh()->image_path);
        }
        $this->assertSame([], Storage::disk('public')->allFiles('products'));
    }

    public function test_a_missing_product_id_cannot_create_an_accidental_duplicate(): void
    {
        $this->post(route('products.store'), $this->productData(['id' => 99999]))->assertSessionHasErrors('id');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_uploaded_image_is_shared_with_staff_and_customer_catalogs_and_cart(): void
    {
        $this->post(route('products.store'), $this->productData(['image' => $this->image('hammer.png')]))
            ->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $url = $product->image_url;
        $staff = Staff::create(['name' => 'Staff', 'username' => 'image.staff', 'role' => 'Staff',
            'password' => 'password123', 'is_active' => true, 'permissions' => ['pos', 'inventory']]);
        $this->actingAs($staff, 'staff');
        $this->get(route('pos'))->assertOk()->assertViewHas('products', fn ($products) => $products->first()->image_url === $url);
        $this->getJson(route('staff.catalog'))->assertOk()->assertJsonPath('0.image_url', $url);
        $this->get(route('staff.inventory'))->assertOk()->assertSee('src="'.$url.'"', false);

        $customer = Customer::create(['name' => 'Customer', 'username' => 'image.customer',
            'email' => 'customer@example.test', 'password' => 'password123', 'is_active' => true,
            'permissions' => ['order', 'cart']]);
        $this->actingAs($customer, 'customer');
        $this->get(route('customer.order'))->assertOk()->assertViewHas('products', fn ($products) => $products->first()['image_url'] === $url);
        $this->putJson('/customer/order/cart', ['version' => 1, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertOk();
        $this->get(route('customer.cart'))->assertOk()->assertViewHas('customerCart', fn ($cart) => $cart['items'][0]['image_url'] === $url);
    }

    public function test_products_without_images_remain_available_to_catalogs(): void
    {
        $product = Product::create($this->productData());
        $this->assertNull($product->image_url);
        $this->get(route('product.management'))->assertOk();
        $this->assertNull(app(CustomerOrderService::class)->product($product)['image_url']);
    }

    public function test_deleting_a_product_removes_its_image(): void
    {
        $this->post(route('products.store'), $this->productData(['image' => $this->image('hammer.png')]));
        $product = Product::firstOrFail();
        $this->delete(route('products.delete', $product))->assertRedirect();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing($product->image_path);
    }
}
