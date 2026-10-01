<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Product photos: added from the product form, shown on the register, the
 * product list and the guest Wi-Fi menu.
 */
class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    // A real 1x1 PNG: UploadedFile::fake()->image() needs GD, which this server lacks.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function photo(string $name = 'latte.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function fields(array $extra = []): array
    {
        return array_merge(['name' => 'Spanish Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active'], $extra);
    }

    public function test_adding_a_product_with_a_photo_stores_it(): void
    {
        $this->actingAs($this->admin())
            ->post(route('inventory.products.store'), $this->fields(['image' => $this->photo()]))
            ->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertStringStartsWith('products/', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertSame('/storage/'.$product->image_path, $product->image_url);
    }

    public function test_a_product_without_a_photo_still_saves(): void
    {
        $this->actingAs($this->admin())->post(route('inventory.products.store'), $this->fields())->assertSessionHasNoErrors();

        $this->assertNull(Product::firstOrFail()->image_url);
    }

    public function test_replacing_and_removing_a_photo_deletes_the_old_file(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('inventory.products.store'), $this->fields(['image' => $this->photo()]));
        $product = Product::firstOrFail();
        $first = $product->image_path;

        $this->actingAs($admin)->put(route('inventory.products.update', $product), $this->fields(['image' => $this->photo('new.png')]));
        $second = $product->fresh()->image_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Editing other details keeps the photo.
        $this->actingAs($admin)->put(route('inventory.products.update', $product), $this->fields(['price' => 130]));
        $this->assertSame($second, $product->fresh()->image_path);

        $this->actingAs($admin)->put(route('inventory.products.update', $product), $this->fields(['remove_image' => 1]));
        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_deleting_a_product_deletes_its_photo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('inventory.products.store'), $this->fields(['image' => $this->photo()]));
        $product = Product::firstOrFail();

        $this->actingAs($admin)->delete(route('inventory.products.destroy', $product));

        Storage::disk('public')->assertMissing($product->image_path);
    }

    public function test_only_photos_under_2mb_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('inventory.products.store'), $this->fields([
            'image' => UploadedFile::fake()->createWithContent('menu.pdf', '%PDF-1.4 not a photo'),
        ]))->assertSessionHasErrors(['image' => 'That file is not a photo.']);

        $this->actingAs($admin)->post(route('inventory.products.store'), $this->fields([
            'image' => UploadedFile::fake()->createWithContent('big.png', base64_decode(self::PNG))->size(3000),
        ]))->assertSessionHasErrors('image');

        $this->assertSame(0, Product::count());
    }

    public function test_the_photo_shows_on_the_register_and_the_guest_menu(): void
    {
        $this->actingAs($this->admin())->post(route('inventory.products.store'), $this->fields(['image' => $this->photo()]));
        $url = Product::firstOrFail()->image_url;

        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'opened_at' => now(), 'starting_cash' => 0, 'status' => 'open']);
        $this->actingAs($staff)->get(route('pos'))->assertOk()->assertSee(basename($url), false);

        auth()->logout();
        $this->get(route('portal.menu'))->assertOk()->assertSee($url, false);
    }

    public function test_staff_cannot_upload_product_photos(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->post(route('inventory.products.store'), $this->fields(['image' => $this->photo()]));

        $this->assertSame(0, Product::count());
    }
}
