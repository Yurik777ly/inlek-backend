<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    private int $phoneSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.' . config('database.default') . '.database');
        if (!in_array($database, [':memory:', 'inlek_api_cart_lock_test'], true)) {
            $this->fail('Тест отказался подключаться к базе «' . $database . '».');
        }

        if (config('database.default') === 'mysql') {
            $this->createMysqlTestDatabase($database);
        }

        $this->createSchema();
        $this->seedCatalog();
    }

    public function test_guest_can_open_daily_products(): void
    {
        $response = $this->getJson('/api/product/daily');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(10, (int) $response->json('data.0.product_id'));
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_guest_can_search_products(): void
    {
        $response = $this->getJson('/api/product/search');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(10, (int) $response->json('data.data.0.product_id'));
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_guest_can_open_product_card(): void
    {
        $response = $this->getJson('/api/product/10');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(10, (int) $response->json('data.product_id'));
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_guest_can_open_pharmacies_without_coordinates(): void
    {
        $response = $this->getJson('/api/product/10/pharmacies');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_guest_coordinates_sort_pharmacies_and_do_not_create_a_cart(): void
    {
        $response = $this->getJson('/api/product/10/pharmacies?geo_lat=53.9&geo_long=27.56');

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.0.pharmacy_id'));
        $this->assertSame(2, (int) $response->json('data.1.pharmacy_id'));
        $this->assertLessThan(
            (float) $response->json('data.1.distance_m'),
            (float) $response->json('data.0.distance_m')
        );
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_bearer_null_is_a_guest_and_does_not_save_geo(): void
    {
        $response = $this->getJson('/api/product/10/pharmacies?geo_lat=53.9&geo_long=27.56', [
            'Authorization' => 'Bearer null',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.0.pharmacy_id'));
        $this->assertNoCartOrUserWasCreated();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_invalid_bearer_token_is_a_guest_and_does_not_save_geo(): void
    {
        $response = $this->getJson('/api/product/10/pharmacies?geo_lat=53.9&geo_long=27.56', [
            'Authorization' => 'Bearer not-a-real-token',
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.0.pharmacy_id'));
        $this->assertNoCartOrUserWasCreated();
    }

    public function test_authorized_user_geo_is_saved_on_the_canonical_cart_only(): void
    {
        $user = $this->makeUser();
        $lowCartId = $this->makeCart($user);
        $highCartId = $this->makeCart($user);
        $token = $user->createToken('catalog')->plainTextToken;

        $response = $this->getJson('/api/product/10/pharmacies?geo_lat=53.9&geo_long=27.56', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $this->assertSame('53.9', (string) DB::table('carts')->where('id', $lowCartId)->value('geo_lat'));
        $this->assertSame('27.56', (string) DB::table('carts')->where('id', $lowCartId)->value('geo_long'));
        $this->assertNull(DB::table('carts')->where('id', $highCartId)->value('geo_lat'));
        $this->assertNull(DB::table('carts')->where('id', $highCartId)->value('geo_long'));
        $this->assertSame(2, DB::table('carts')->count());
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_authorized_user_without_a_cart_still_receives_pharmacies(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('catalog')->plainTextToken;

        $response = $this->getJson('/api/product/10/pharmacies?geo_lat=53.9&geo_long=27.56', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertOk();
        $this->assertSame(1, (int) $response->json('data.0.pharmacy_id'));
        $this->assertSame(0, DB::table('carts')->count());
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_product_notification_stays_behind_authentication(): void
    {
        $this->postJson('/api/product/10/notification', ['id' => 10])->assertUnauthorized();
        $this->postJson('/api/product/10/notification', ['id' => 10], [
            'Authorization' => 'Bearer null',
        ])->assertUnauthorized();
        $this->postJson('/api/product/10/notification', ['id' => 10], [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertUnauthorized();
        $this->assertSame(0, DB::table('carts')->count());
        $this->assertSame(0, DB::table('users')->count());
    }

    private function assertNoCartOrUserWasCreated(): void
    {
        $this->assertSame(0, DB::table('carts')->count());
        $this->assertSame(0, DB::table('users')->count());
    }

    private function makeUser(): User
    {
        $this->phoneSequence++;

        return User::query()->create([
            'name' => 'Catalog',
            'phone' => '+37525-100-00-' . str_pad((string) $this->phoneSequence, 2, '0', STR_PAD_LEFT),
            'password' => 'secret',
        ]);
    }

    private function makeCart(User $user): int
    {
        return (int) DB::table('carts')->insertGetId([
            'user_id' => $user->id,
            'promo' => '1',
            'pharmacy_id' => null,
            'promocodes' => null,
            'delivery_zone' => null,
            'geo_lat' => null,
            'geo_long' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedCatalog(): void
    {
        DB::table('evo_daily_products_view')->insert(['product_id' => 10]);
        DB::table('evo_product_info_view_json_opt')->insert([
            'id' => 1,
            'product_id' => 10,
            'product_price_from' => 5,
            'product_charachters' => json_encode(['product_price_from' => 5], JSON_UNESCAPED_UNICODE),
            'is_available' => 1,
        ]);
        DB::table('evo_product_info_view_json_detailed')->insert([
            'id' => 1,
            'product_id' => 10,
            'mnn' => '',
            'form' => '',
        ]);
        DB::table('evo_product_pharmacy_json')->insert([
            [
                'product_id' => 10,
                'pharmacy_id' => 2,
                'pharmacy_delivery' => 'Самовывоз',
                'coordinates' => '54.50,27.56',
            ],
            [
                'product_id' => 10,
                'pharmacy_id' => 1,
                'pharmacy_delivery' => 'Самовывоз',
                'coordinates' => '53.90,27.56',
            ],
        ]);
    }

    private function createSchema(): void
    {
        foreach ([
            'evo_carts_view',
            'evo_carts_detailed',
            'evo_cart_product_pharmacy_view',
        ] as $view) {
            DB::statement('DROP VIEW IF EXISTS `' . $view . '`');
        }

        Schema::dropAllTables();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('carts', function ($table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('promo');
            $table->integer('pharmacy_id')->nullable();
            $table->string('promocodes', 400)->nullable();
            $table->string('delivery_zone', 100)->nullable();
            $table->string('geo_lat', 20)->nullable();
            $table->string('geo_long', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function ($table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('evo_daily_products_view', function ($table) {
            $table->unsignedInteger('product_id');
        });

        Schema::create('evo_product_info_view_json_opt', function ($table) {
            $table->unsignedInteger('id');
            $table->unsignedInteger('product_id')->primary();
            $table->decimal('product_price_from', 12, 2)->default(0);
            $table->text('product_charachters')->nullable();
            $table->text('action_json')->nullable();
            $table->text('promocodes_json')->nullable();
            $table->text('categories_json')->nullable();
            $table->unsignedTinyInteger('is_available')->default(0);
        });

        Schema::create('evo_product_info_view_json_detailed', function ($table) {
            $table->unsignedInteger('id');
            $table->unsignedInteger('product_id')->primary();
            $table->string('mnn')->nullable();
            $table->string('form')->nullable();
            $table->text('product_charachters')->nullable();
            $table->text('action_json')->nullable();
            $table->text('promocodes_json')->nullable();
            $table->text('categories_json')->nullable();
            $table->text('brand_products')->nullable();
            $table->text('related_products')->nullable();
            $table->text('category_products')->nullable();
            $table->text('instruction')->nullable();
        });

        Schema::create('evo_product_pharmacy_json', function ($table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->string('pharmacy_delivery')->nullable();
            $table->string('coordinates')->nullable();
            $table->text('product_pharmacy_json')->nullable();
        });

        Schema::create('product_pharmacy_cache', function ($table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->integer('stock_count')->default(0);
        });

        Schema::create('offer_cache', function ($table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->integer('stock_count')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('price_old', 12, 2)->default(0);
        });
    }

    private function createMysqlTestDatabase(string $database): void
    {
        $connection = config('database.connections.mysql');
        $hosts = array_values(array_unique(array_filter([
            $connection['host'] ?? null,
            ($connection['host'] ?? null) === 'host.docker.internal' ? '127.0.0.1' : null,
        ])));

        $lastError = null;
        foreach ($hosts as $host) {
            try {
                $pdo = new \PDO(
                    sprintf('mysql:host=%s;port=%s', $host, $connection['port'] ?? '3306'),
                    (string) $connection['username'],
                    (string) $connection['password'],
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                $pdo->exec(
                    'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $database) . '` '
                    . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
                config(['database.connections.mysql.host' => $host]);
                DB::purge();
                DB::reconnect();

                $current = DB::select('select database() as db');
                $selected = $current[0]->db ?? null;
                if ($selected !== $database) {
                    throw new RuntimeException('Тестовое соединение открыло базу «' . $selected . '».');
                }

                return;
            } catch (\PDOException $e) {
                $lastError = $e;
            }
        }

        throw $lastError ?? new RuntimeException('Не удалось подключиться к тестовой базе.');
    }
}
