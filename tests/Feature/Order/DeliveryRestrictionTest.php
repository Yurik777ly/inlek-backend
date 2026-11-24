<?php

namespace Tests\Feature\Order;

use App\Models\PharmaciesView;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DeliveryRestrictionTest extends TestCase
{
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

    public function test_pickup_allows_recipe_product(): void
    {
        $this->assertOrderAccepted('self', [11], [11 => 2]);
    }

    public function test_pickup_allows_alcohol_product(): void
    {
        $this->assertOrderAccepted('self', [12], [12 => 2]);
    }

    public function test_pickup_allows_regular_product(): void
    {
        $this->assertOrderAccepted('self', [10], [10 => 2]);
    }

    public function test_delivery_allows_regular_product_and_ignores_client_flags(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 2]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/order/', $this->payload('delivery', [10], [
            'is_recipe' => true,
            'is_alcohol' => 'yes',
        ]));

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(1, DB::table('evo_commerce_orders')->count());
        $this->assertSame(1, DB::table('evo_commerce_order_products')->where('product_id', 10)->count());
        $this->assertNull($this->pivotQuantity($cartId, 10));
    }

    public function test_delivery_rejects_recipe_product_from_server_cache(): void
    {
        $this->assertDeliveryRejected([11], [11 => 2], [
            'is_recipe' => false,
            'is_alcohol' => 'no',
        ]);
    }

    public function test_delivery_rejects_alcohol_product(): void
    {
        $this->assertDeliveryRejected([12], [12 => 2]);
    }

    public function test_delivery_rejects_mixed_cart_with_recipe_product(): void
    {
        $this->assertDeliveryRejected([10, 11], [10 => 2, 11 => 1]);
    }

    public function test_delivery_rejects_mixed_cart_with_alcohol_product(): void
    {
        $this->assertDeliveryRejected([10, 12], [10 => 2, 12 => 3]);
    }

    public function test_delivery_rejects_cart_with_recipe_and_alcohol_products(): void
    {
        $this->assertDeliveryRejected([11, 12], [11 => 1, 12 => 1]);
    }

    public function test_delivery_does_not_include_restricted_product_without_stock(): void
    {
        DB::table('product_pharmacy_cache')
            ->where('product_id', 11)
            ->update(['stock_count' => 0]);
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 2, 11 => 4]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/order/', $this->payload('delivery', [10, 11]));

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(1, DB::table('evo_commerce_order_products')->where('product_id', 10)->count());
        $this->assertSame(0, DB::table('evo_commerce_order_products')->where('product_id', 11)->count());
        $this->assertSame(4, $this->pivotQuantity($cartId, 11));
    }

    /**
     * @param list<int> $ids
     * @param array<int, int> $quantities
     */
    private function assertOrderAccepted(string $delivery, array $ids, array $quantities): void
    {
        $user = $this->makeUser();
        $this->makeCart($user, $quantities);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/order/', $this->payload($delivery, $ids));

        $response->assertOk()->assertJsonPath('status', 'success');
        $this->assertSame(1, DB::table('evo_commerce_orders')->count());
        $this->assertSame(1, DB::table('evo_commerce_order_history')->count());
    }

    /**
     * @param list<int> $ids
     * @param array<int, int> $quantities
     * @param array<string, mixed> $clientFlags
     */
    private function assertDeliveryRejected(array $ids, array $quantities, array $clientFlags = []): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, $quantities);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            '/api/order/',
            $this->payload('delivery', $ids, $clientFlags)
        );

        $response->assertStatus(422)->assertExactJson([
            'status' => 'error',
            'errors' => [
                'delivery' => ['Оформление доставки невозможно: в заказе есть товары, доступные только для самовывоза.'],
            ],
        ]);
        $this->assertSame(0, DB::table('evo_commerce_orders')->count());
        $this->assertSame(0, DB::table('evo_commerce_order_products')->count());
        $this->assertSame(0, DB::table('evo_commerce_order_history')->count());
        foreach ($quantities as $productId => $quantity) {
            $this->assertSame($quantity, $this->pivotQuantity($cartId, $productId));
        }
        $this->assertNull(DB::table('carts')->where('id', $cartId)->value('pharmacy_id'));
        $this->assertSame(1, DB::table('carts')->count());
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function payload(string $delivery, array $ids, array $extra = []): array
    {
        return array_merge([
            'delivery' => $delivery,
            'payment' => 'cash',
            'pharmacy_id' => $delivery === 'delivery' ? 0 : 1,
            'last_name' => 'Иванов',
            'first_name' => 'Иван',
            'phone' => '+375291112233',
            'city' => 'Минск',
            'address' => 'ул. Ленина 1',
            'ids' => $ids,
        ], $extra);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Order',
            'phone' => '+37525-200-00-01',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'email' => 'order@example.com',
            'password' => 'secret',
        ]);
    }

    /**
     * @param array<int, int> $quantities
     */
    private function makeCart(User $user, array $quantities): int
    {
        $cartId = (int) DB::table('carts')->insertGetId([
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
        foreach ($quantities as $productId => $quantity) {
            DB::table('cart_evo_site_content')->insert([
                'cart_id' => $cartId,
                'evo_site_content_id' => $productId,
                'quantity' => $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $cartId;
    }

    private function pivotQuantity(int $cartId, int $productId): ?int
    {
        $quantity = DB::table('cart_evo_site_content')
            ->where('cart_id', $cartId)
            ->where('evo_site_content_id', $productId)
            ->value('quantity');

        return $quantity === null ? null : (int) $quantity;
    }

    private function seedCatalog(): void
    {
        foreach ([10, 11, 12] as $productId) {
            DB::table('evo_site_content')->insert(['id' => $productId]);
        }
        DB::table('product_cache')->insert([
            ['product_id' => 10, 'is_recipe' => 0, 'is_alcohol' => 0],
            ['product_id' => 11, 'is_recipe' => 1, 'is_alcohol' => 0],
            ['product_id' => 12, 'is_recipe' => 0, 'is_alcohol' => 1],
        ]);
        foreach ([10, 11, 12] as $productId) {
            DB::table('product_pharmacy_cache')->insert([
                'product_id' => $productId,
                'pharmacy_id' => PharmaciesView::PHARMACY_ID_FOR_DELIVERY,
                'price' => 10,
                'price_old' => 10,
                'stock_count' => 5,
            ]);
        }
        DB::table('evo_pharmacies_view')->insert([
            'pharmacy_id' => 1,
            'pagetitle' => 'Аптека',
            'address' => 'адрес',
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
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
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
        Schema::create('cart_evo_site_content', function ($table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedInteger('evo_site_content_id');
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();
        });
        Schema::create('evo_site_content', function ($table) {
            $table->unsignedInteger('id')->primary();
        });
        Schema::create('product_cache', function ($table) {
            $table->unsignedInteger('product_id')->primary();
            $table->unsignedTinyInteger('is_recipe')->default(0);
            $table->unsignedTinyInteger('is_alcohol')->default(0);
        });
        Schema::create('product_pharmacy_cache', function ($table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('price_old', 12, 2)->default(0);
            $table->integer('stock_count')->default(0);
        });
        Schema::create('evo_product_pharmacy_view', function ($table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->string('pharmacy_name')->nullable();
            $table->string('coordinates')->nullable();
            $table->string('schedule')->nullable();
            $table->string('address')->nullable();
            $table->integer('stock_count')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('price_old', 12, 2)->default(0);
        });
        Schema::create('evo_pharmacies_view', function ($table) {
            $table->unsignedInteger('pharmacy_id')->primary();
            $table->string('pagetitle')->nullable();
            $table->string('address')->nullable();
        });
        Schema::create('evo_product_info_view_json_opt', function ($table) {
            $table->unsignedInteger('product_id')->primary();
            $table->text('product_charachters')->nullable();
            $table->text('promocodes_json')->nullable();
        });
        Schema::create('evo_site_tmplvars', function ($table) {
            $table->increments('id');
            $table->string('name');
        });
        Schema::create('evo_site_tmplvar_contentvalues', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('tmplvarid');
            $table->unsignedInteger('contentid');
            $table->text('value')->nullable();
        });
        Schema::create('evo_offers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->string('uid')->nullable();
            $table->string('expiration_date')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('stock_count')->nullable();
        });
        Schema::create('evo_commerce_orders', function ($table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('hash')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->text('fields')->nullable();
            $table->string('lang')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('evo_commerce_order_products', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('product_id');
            $table->string('title')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('count')->default(0);
            $table->text('options')->nullable();
            $table->text('meta')->nullable();
            $table->integer('position')->nullable();
        });
        Schema::create('evo_commerce_order_history', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('status_id')->nullable();
            $table->string('comment')->nullable();
            $table->unsignedTinyInteger('notify')->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::statement(<<<'SQL'
            CREATE VIEW evo_carts_view AS
            SELECT
                c.user_id AS user_id,
                JSON_OBJECT('cart_id', c.id, 'user_id', c.user_id) AS cart,
                (
                    SELECT JSON_ARRAYAGG(JSON_OBJECT(
                        'product_id', p.evo_site_content_id,
                        'quantity', p.quantity
                    ))
                    FROM cart_evo_site_content p
                    WHERE p.cart_id = c.id
                ) AS product_info
            FROM carts c
        SQL);
        DB::statement(<<<'SQL'
            CREATE VIEW evo_carts_detailed AS
            SELECT
                c.user_id AS user_id,
                c.pharmacy_id AS pharmacy_id,
                JSON_OBJECT(
                    'cart_id', c.id,
                    'user_id', c.user_id,
                    'products', (
                        SELECT IFNULL(JSON_ARRAYAGG(JSON_OBJECT(
                            'product_id', p.evo_site_content_id,
                            'quantity', p.quantity,
                            'requested_quantity', p.quantity,
                            'product_info', JSON_OBJECT('pagetitle', CONCAT('Товар ', p.evo_site_content_id)),
                            'prices', JSON_OBJECT('price', 10, 'final_price', 10, 'price_old', 10)
                        )), JSON_ARRAY())
                        FROM cart_evo_site_content p
                        WHERE p.cart_id = c.id
                    )
                ) AS cart
            FROM carts c
        SQL);
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
