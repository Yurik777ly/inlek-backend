<?php

namespace Tests\Feature\Order;

use App\Http\Dto\Cart\CartDTO;
use App\Models\Cart;
use App\Models\PharmaciesView;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ConcurrentCheckoutTest extends TestCase
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

    public function test_single_checkout_creates_one_order(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/order/', $this->payload('self', [10]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(1, DB::table('evo_commerce_orders')->count());
        $this->assertSame(1, DB::table('evo_commerce_order_products')->where('product_id', 10)->count());
        $this->assertNull($this->pivotQuantity($cartId, 10));
    }

    public function test_parallel_checkouts_do_not_duplicate_the_same_cart(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 2]);

        $race = $this->runTwoCheckouts($user->id, $this->payload('delivery', [10]));

        $this->assertSame(['entering', 'entering'], $race['while_locked'], 'Оба запроса должны ждать блокировку до создания заказа: ' . implode(' | ', $race['while_locked']));
        $this->assertSame(0, $race['orders_while_locked']);
        sort($race['final']);
        $this->assertSame(['created', 'empty'], $race['final'], implode(' | ', $race['final']));
        $this->assertSame(1, DB::table('evo_commerce_orders')->count());
        $this->assertSame(1, DB::table('evo_commerce_order_products')->count());
        $this->assertSame(2, (int) DB::table('evo_commerce_order_products')->where('product_id', 10)->value('count'));
        $this->assertNull($this->pivotQuantity($cartId, 10));
    }

    public function test_second_checkout_uses_quantity_left_after_the_first_lock(): void
    {
        DB::table('product_pharmacy_cache')->where('product_id', 10)->update(['stock_count' => 2]);
        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 5]);

        $race = $this->runTwoCheckouts($user->id, $this->payload('delivery', [10]));

        $this->assertSame(['entering', 'entering'], $race['while_locked'], implode(' | ', $race['while_locked']));
        $this->assertSame(0, $race['orders_while_locked']);
        $this->assertSame(['created', 'created'], $race['final'], implode(' | ', $race['final']));
        $this->assertSame(2, DB::table('evo_commerce_orders')->count());
        $this->assertSame([2, 2], DB::table('evo_commerce_order_products')->orderBy('id')->pluck('count')->map(fn ($count) => (int) $count)->all());
        $this->assertSame(1, $this->pivotQuantity($cartId, 10));
    }

    public function test_checkout_rolls_back_order_and_cart_when_delivery_is_rejected(): void
    {
        $user = $this->makeUser(['email' => null]);
        $cartId = $this->makeCart($user, [11 => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/order/', $this->payload('delivery', [11], ['email' => 'new@example.com']))
            ->assertStatus(422);

        $this->assertSame(0, DB::table('evo_commerce_orders')->count());
        $this->assertSame(0, DB::table('evo_commerce_order_products')->count());
        $this->assertSame(0, DB::table('evo_commerce_order_history')->count());
        $this->assertSame(2, $this->pivotQuantity($cartId, 11));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('email'));
        $this->assertNull(DB::table('carts')->where('id', $cartId)->value('pharmacy_id'));
    }

    public function test_pickup_and_regular_delivery_still_checkout(): void
    {
        $pickupUser = $this->makeUser(['phone' => '+37525-200-00-11', 'email' => 'pickup@example.com']);
        $this->makeCart($pickupUser, [12 => 1]);
        $this->actingAs($pickupUser, 'sanctum')
            ->postJson('/api/order/', $this->payload('self', [12]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $deliveryUser = $this->makeUser(['phone' => '+37525-200-00-12', 'email' => 'delivery@example.com']);
        $this->makeCart($deliveryUser, [10 => 1]);
        $this->actingAs($deliveryUser, 'sanctum')
            ->postJson('/api/order/', $this->payload('delivery', [10]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame(2, DB::table('evo_commerce_orders')->count());
    }

    public function test_add_to_cart_still_replaces_quantity(): void
    {
        Schema::create('offer_cache', function ($table) {
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('pharmacy_id');
            $table->integer('stock_count')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('price_old', 12, 2)->default(0);
        });
        DB::table('offer_cache')->insert([
            'product_id' => 10,
            'pharmacy_id' => 1,
            'stock_count' => 8,
            'price' => 10,
            'price_old' => 10,
        ]);

        $user = $this->makeUser();
        $cartId = $this->makeCart($user, [10 => 1]);
        $this->actingAs($user);

        app(CartService::class)->addToCart(new CartDTO(
            user: $user,
            cart: Cart::query()->findOrFail($cartId),
            product_id: 10,
            quantity: 4,
            pharmacy_id: 1,
        ));

        $this->assertSame(4, $this->pivotQuantity($cartId, 10));
    }

    public function test_checkout_keeps_the_lowest_cart_id(): void
    {
        $user = $this->makeUser();
        $low = $this->makeCart($user, []);
        $high = $this->makeCart($user, [10 => 3]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/order/', $this->payload('self', [10]))
            ->assertStatus(400);

        $this->assertSame(0, DB::table('evo_commerce_orders')->count());
        $this->assertSame(3, $this->pivotQuantity($high, 10));
        $this->assertNull($this->pivotQuantity($low, 10));
        $this->assertSame($low, (int) DB::table('carts')->where('user_id', $user->id)->min('id'));
    }

    public function test_cart_contents_are_read_after_user_and_cart_locks(): void
    {
        $user = $this->makeUser();
        $this->makeCart($user, [10 => 1]);
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/order/', $this->payload('delivery', [10]))
            ->assertOk();

        $userLock = $this->lockPosition($sql, 'users');
        $cartLock = $this->lockPosition($sql, 'carts');
        $cartRead = $this->firstPosition($sql, 'evo_carts_view');
        $pivotRead = $this->firstPosition($sql, 'cart_evo_site_content');

        $this->assertNotNull($userLock);
        $this->assertNotNull($cartLock);
        $this->assertLessThan($cartLock, $userLock);
        $this->assertNotNull($cartRead);
        $this->assertLessThan($cartRead, $cartLock);
        $this->assertNotNull($pivotRead);
        $this->assertLessThan($pivotRead, $cartLock);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{while_locked: list<string>, orders_while_locked: int, final: list<string>}
     */
    private function runTwoCheckouts(int $userId, array $payload): array
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Конкурентный тест требует блокировки InnoDB.');
        }
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork недоступен.');
        }

        DB::disconnect();
        $files = [
            tempnam(sys_get_temp_dir(), 'order-a-'),
            tempnam(sys_get_temp_dir(), 'order-b-'),
        ];
        $pids = [];

        foreach ($files as $file) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Не удалось запустить процесс оформления.');
            }
            if ($pid === 0) {
                try {
                    usleep(200000);
                    DB::purge();
                    DB::reconnect();
                    DB::statement('SET innodb_lock_wait_timeout = 15');
                    Auth::login(User::query()->findOrFail($userId));
                    file_put_contents($file, 'entering');
                    $data = app(OrderService::class)->create($payload);
                    file_put_contents($file, $data === false ? 'empty' : 'created');
                } catch (\Throwable $e) {
                    file_put_contents($file, 'error:' . $e->getMessage());
                    exit(1);
                }
                DB::disconnect();
                exit(0);
            }
            $pids[] = $pid;
        }

        DB::purge();
        DB::reconnect();
        DB::beginTransaction();
        $ordersWhileLocked = null;
        $statesWhileLocked = [];
        try {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $deadline = microtime(true) + 4;
            do {
                $states = array_map(static fn ($file) => (string) @file_get_contents($file), $files);
                $entered = count(array_filter($states, static fn ($state) => $state !== ''));
                if ($entered === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);

            usleep(700000);
            $statesWhileLocked = array_map(static fn ($file) => (string) @file_get_contents($file), $files);
            $ordersWhileLocked = (int) DB::table('evo_commerce_orders')->count();
        } finally {
            DB::rollBack();
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();
        $final = array_map(static fn ($file) => (string) @file_get_contents($file), $files);
        foreach ($files as $file) {
            @unlink($file);
        }

        return [
            'while_locked' => $statesWhileLocked,
            'orders_while_locked' => (int) $ordersWhileLocked,
            'final' => $final,
        ];
    }

    /**
     * @param list<string> $sql
     */
    private function lockPosition(array $sql, string $table): ?int
    {
        foreach ($sql as $index => $statement) {
            if (str_contains($statement, 'for update') && str_contains($statement, $table)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $sql
     */
    private function firstPosition(array $sql, string $table): ?int
    {
        foreach ($sql as $index => $statement) {
            if (str_contains($statement, $table)) {
                return $index;
            }
        }

        return null;
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Order',
            'phone' => '+37525-200-00-01',
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'email' => 'order@example.com',
            'password' => 'secret',
        ], $overrides));
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
