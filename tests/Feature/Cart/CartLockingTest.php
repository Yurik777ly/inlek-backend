<?php

namespace Tests\Feature\Cart;

use App\Http\Dto\Cart\CartDetailedDTO;
use App\Http\Dto\Cart\CartDTO;
use App\Http\Dto\Cart\CartPharmaciesDTO;
use App\Http\Dto\Cart\CartProductItemDTO;
use App\Models\CartsView;
use App\Models\Cart;
use App\Models\EVO\EvoCommerceOrderHistory;
use App\Models\EVO\EvoCommerceOrderPayments;
use App\Models\EVO\EvoCommerceOrderProducts;
use App\Models\EVO\EvoCommerceOrders;
use App\Models\EVO\EvoCommerceOrderStatuses;
use App\Models\OrderViewJson;
use App\Models\PharmaciesView;
use App\Models\ProductInfoViewJson;
use App\Models\User;
use App\Services\Cart\CartDuplicateService;
use App\Services\Cart\CartService;
use App\Services\Firebase\FirebaseService;
use App\Services\Order\OrderService;
use App\Services\Pharmacy\PharmacyService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

class CartLockingTest extends TestCase
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
    }

    public function test_add_to_cart_stores_absolute_quantity(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->setStock(10, 1, 8);
        $this->actingAs($user);

        $this->cartService()->addToCart($this->cartDto($user, $cartId, 10, 2, 1));

        $this->assertSame(2, $this->pivotQuantity($cartId, 10));
    }

    public function test_add_to_cart_replaces_existing_quantity(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->addPivot($cartId, 10, 2);
        $this->setStock(10, 1, 8);
        $this->actingAs($user);

        $this->cartService()->addToCart($this->cartDto($user, $cartId, 10, 5, 1));

        $this->assertSame(1, $this->pivotCount($cartId, 10));
        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
    }

    public function test_zero_quantity_removes_product(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->addPivot($cartId, 10, 2);
        $this->setStock(10, 1, 8);
        $this->actingAs($user);

        $this->cartService()->addToCart($this->cartDto($user, $cartId, 10, 0, 1));

        $this->assertNull($this->pivotQuantity($cartId, 10));
    }

    public function test_quantity_above_stock_does_not_change_cart_and_ignores_delivery_pharmacy(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->addPivot($cartId, 10, 2);
        $this->setStock(10, PharmaciesView::PHARMACY_ID_FOR_DELIVERY, 50);
        $this->setStock(10, 1, 4);
        $this->actingAs($user);

        try {
            $this->cartService()->addToCart($this->cartDto($user, $cartId, 10, 5, null));
            $this->fail('Запрос больше остатка должен быть отклонён.');
        } catch (UnprocessableEntityHttpException $e) {
            $this->assertSame('Больше нет в наличии', $e->getMessage());
        }

        $this->assertSame(2, $this->pivotQuantity($cartId, 10));
    }

    public function test_clear_cart_removes_products_from_canonical_cart_only(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCart($user);
        $second = $this->makeCart($user);
        $this->addPivot($first, 10, 2);
        $this->addPivot($second, 11, 3);
        $this->actingAs($user);

        $this->cartService()->clearCart($user);

        $this->assertNull($this->pivotQuantity($first, 10));
        $this->assertSame(3, $this->pivotQuantity($second, 11));
    }

    public function test_repeat_order_writes_absolute_quantities_under_cart_lock(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->addPivot($cartId, 10, 2);
        DB::table('evo_site_content')->insert(['id' => 10]);

        $orders = Mockery::mock(OrderService::class);
        $orders->shouldReceive('getDetailed')->once()->andReturn(collect([
            (object) [
                'order_products_json' => [
                    ['product_id' => 10, 'count' => 4, 'title' => 'Товар'],
                ],
            ],
        ]));
        $this->app->instance(OrderService::class, $orders);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/order/15/repeat');

        $response->assertOk();
        $this->assertSame(4, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->pivotCount($cartId, 10));
    }

    public function test_order_cart_adjustment_keeps_remainder_and_removes_fulfilled_lines(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCart($user);
        $second = $this->makeCart($user);
        $this->addPivot($first, 10, 5);
        $this->addPivot($first, 11, 3);
        $this->addPivot($first, 12, 1);
        $this->addPivot($second, 10, 9);
        $this->actingAs($user);

        $this->invoke($this->orderService(), 'clearCartProductsV2', [[
            ['product_id' => 10, 'count' => 2, 'requested_quantity' => 5],
            ['product_id' => 11, 'count' => 3, 'requested_quantity' => 3],
        ]]);

        $this->assertSame(3, $this->pivotQuantity($first, 10));
        $this->assertNull($this->pivotQuantity($first, 11));
        $this->assertSame(1, $this->pivotQuantity($first, 12));
        $this->assertSame(9, $this->pivotQuantity($second, 10));
    }

    public function test_clear_cart_products_detaches_only_requested_ids_from_canonical_cart(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCart($user);
        $second = $this->makeCart($user);
        $this->addPivot($first, 10, 2);
        $this->addPivot($first, 11, 1);
        $this->addPivot($second, 10, 8);
        $this->actingAs($user);

        $this->invoke($this->orderService(), 'clearCartProducts', [[10]]);

        $this->assertNull($this->pivotQuantity($first, 10));
        $this->assertSame(1, $this->pivotQuantity($first, 11));
        $this->assertSame(8, $this->pivotQuantity($second, 10));
    }

    public function test_pay_detaches_order_products_from_canonical_cart_and_marks_payment(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCart($user);
        $second = $this->makeCart($user);
        $this->addPivot($first, 10, 2);
        $this->addPivot($first, 11, 1);
        $this->addPivot($second, 10, 7);

        $orderId = DB::table('evo_commerce_orders')->insertGetId([
            'customer_id' => $user->id,
            'fields' => json_encode(['payment_method' => 'bepaid']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('evo_commerce_order_products')->insert([
            'order_id' => $orderId,
            'product_id' => 10,
        ]);
        DB::table('evo_commerce_order_payments')->insert([
            'order_id' => $orderId,
            'hash' => 'pay-hash-1',
            'amount' => 12.50,
            'paid' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('send');
        $paid = $this->orderService($messaging)->pay('pay-hash-1');

        $this->assertTrue($paid);
        $this->assertSame(2, $this->pivotQuantity($first, 10));
        $this->assertSame(1, $this->pivotQuantity($first, 11));
        $this->assertSame(7, $this->pivotQuantity($second, 10));
        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'pay-hash-1')->value('paid'));
        $this->assertNull(DB::table('evo_commerce_order_payments')->where('hash', 'pay-hash-1')->value('meta'));

        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $orderId)->value('fields'), true);
        $this->assertTrue($fields['is_paid']);
        $this->assertSame(12.5, $fields['online_payment_price']);
    }

    public function test_payment_failed_does_not_change_payment_fields_cart_or_firebase(): void
    {
        $user = $this->makeUser();
        $seed = $this->seedOnlinePayment($user, 'failed-hash');
        $fieldsBefore = DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields');
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('send');
        $this->bindMessaging($messaging);

        $response = $this->postJson('/api/payments/payment-failed?token=failed-hash');

        $response->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(0, (int) DB::table('evo_commerce_order_payments')->where('hash', 'failed-hash')->value('paid'));
        $this->assertSame($fieldsBefore, DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'));
        $this->assertSame(2, $this->pivotQuantity($seed['cart_id'], 10));
    }

    public function test_payment_success_is_available_without_bearer_token(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-success');
        $seed = $this->seedOnlinePayment($user, 'success-hash');
        $this->bindMessaging($this->messagingThatSends());

        $withoutToken = $this->postJson('/api/payments/payment-success');
        $withoutToken->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(0, (int) DB::table('evo_commerce_order_payments')->where('hash', 'success-hash')->value('paid'));

        $response = $this->postJson('/api/payments/payment-success?token=success-hash');

        $response->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'success-hash')->value('paid'));
        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true);
        $this->assertTrue($fields['is_paid']);
        $this->assertSame(12.5, $fields['online_payment_price']);
        $this->assertSame(2, $this->pivotQuantity($seed['cart_id'], 10));
        $this->assertSame('sent', $this->paymentMeta('success-hash')['push_notification']['state']);
    }

    public function test_payment_process_is_available_without_bearer_token(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-process');
        $seed = $this->seedOnlinePayment($user, 'process-hash', method: 'oplati');
        $this->bindMessaging($this->messagingThatSends());

        $response = $this->getJson('/api/payments/payment-process?token=process-hash');

        $response->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'process-hash')->value('paid'));
        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true);
        $this->assertTrue($fields['is_paid']);
        $this->assertSame(2, $this->pivotQuantity($seed['cart_id'], 10));
    }

    public function test_repeated_success_callback_does_not_rewrite_payment_or_cart(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-repeat');
        $seed = $this->seedOnlinePayment($user, 'repeat-hash');

        $this->assertTrue($this->orderService($this->messagingThatSends())->pay('repeat-hash'));
        $rewritten = json_encode([
            'payment_method' => 'bepaid',
            'is_paid' => true,
            'online_payment_price' => 1,
            'marker' => 'keep',
        ], JSON_UNESCAPED_UNICODE);
        DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->update(['fields' => $rewritten]);

        $second = Mockery::mock(Messaging::class);
        $second->shouldNotReceive('send');
        $this->assertTrue($this->orderService($second)->pay('repeat-hash'));

        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'repeat-hash')->value('paid'));
        $this->assertSame($rewritten, DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'));
        $this->assertSame(2, $this->pivotQuantity($seed['cart_id'], 10));
        $this->assertSame('sent', $this->paymentMeta('repeat-hash')['push_notification']['state']);
    }

    public function test_failed_push_is_retried_by_the_next_callback(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-retry');
        $seed = $this->seedOnlinePayment($user, 'retry-hash', meta: json_encode(['provider' => ['existing' => 'data']], JSON_UNESCAPED_UNICODE));

        $failed = Mockery::mock(Messaging::class);
        $failed->shouldReceive('send')->once()->andThrow(NotFound::becauseTokenNotFound('device-retry'));
        $this->assertTrue($this->orderService($failed)->pay('retry-hash'));

        $meta = $this->paymentMeta('retry-hash');
        $this->assertSame('failed', $meta['push_notification']['state']);
        $this->assertNotSame('', $meta['push_notification']['error']);
        $this->assertSame(['existing' => 'data'], $meta['provider']);
        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'retry-hash')->value('paid'));
        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true);
        $this->assertTrue($fields['is_paid']);

        $this->assertTrue($this->orderService($this->messagingThatSends())->pay('retry-hash'));
        $retried = $this->paymentMeta('retry-hash');
        $this->assertSame('sent', $retried['push_notification']['state']);
        $this->assertNull($retried['push_notification']['error']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $retried['push_notification']['sent_at']);
        $this->assertSame(['existing' => 'data'], $retried['provider']);
        $this->assertSame($fields, json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true));
    }

    public function test_payment_without_fcm_token_is_saved_and_does_not_call_firebase(): void
    {
        $user = $this->makeUser();
        $seed = $this->seedOnlinePayment($user, 'no-token-hash', meta: json_encode(['provider' => ['existing' => 'data']]));
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('send');

        $this->assertTrue($this->orderService($messaging)->pay('no-token-hash'));

        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'no-token-hash')->value('paid'));
        $this->assertSame(['provider' => ['existing' => 'data']], $this->paymentMeta('no-token-hash'));
        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true);
        $this->assertTrue($fields['is_paid']);
    }

    public function test_payment_with_null_meta_records_sent_push(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-null-meta');
        $this->seedOnlinePayment($user, 'null-meta-hash');

        $this->assertTrue($this->orderService($this->messagingThatSends())->pay('null-meta-hash'));

        $meta = $this->paymentMeta('null-meta-hash');
        $this->assertSame(['push_notification'], array_keys($meta));
        $this->assertSame('sent', $meta['push_notification']['state']);
        $this->assertNull($meta['push_notification']['error']);
    }

    public function test_invalid_payment_meta_is_preserved_when_push_is_recorded(): void
    {
        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-invalid-meta');
        $this->seedOnlinePayment($user, 'invalid-meta-hash', meta: '{not-json');

        $this->assertTrue($this->orderService($this->messagingThatSends())->pay('invalid-meta-hash'));

        $meta = $this->paymentMeta('invalid-meta-hash');
        $this->assertSame('{not-json', $meta['_unparsed_meta']);
        $this->assertSame('sent', $meta['push_notification']['state']);
    }

    public function test_parallel_payment_callbacks_send_push_once(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork недоступен, параллельный callback не проверяется.');
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->fail('Параллельный callback проверяется только на MySQL/InnoDB.');
        }

        $user = $this->makeUser();
        $this->setFcmToken($user, 'device-parallel');
        $seed = $this->seedOnlinePayment($user, 'parallel-hash', meta: json_encode(['provider' => ['existing' => 'data']], JSON_UNESCAPED_UNICODE));
        $counter = tempnam(sys_get_temp_dir(), 'pay-push-');
        file_put_contents($counter, '');

        DB::disconnect();
        $files = [];
        $pids = [];
        foreach ([1, 2] as $ignored) {
            $file = tempnam(sys_get_temp_dir(), 'pay-result-');
            $files[] = $file;
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Не удалось запустить процесс callback.');
            }
            if ($pid === 0) {
                try {
                    DB::purge();
                    DB::reconnect();
                    $messaging = Mockery::mock(Messaging::class);
                    $messaging->shouldReceive('send')->zeroOrMoreTimes()->andReturnUsing(function () use ($counter) {
                        file_put_contents($counter, "1\n", FILE_APPEND | LOCK_EX);
                        return ['name' => 'projects/test/messages/1'];
                    });
                    $paid = $this->orderService($messaging)->pay('parallel-hash');
                    file_put_contents($file, json_encode(['paid' => $paid]));
                    DB::disconnect();
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($file, json_encode(['error' => $e->getMessage()]));
                    exit(1);
                }
            }
            $pids[] = $pid;
        }

        $failed = [];
        foreach ($pids as $index => $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) !== 0) {
                $failed[] = (string) @file_get_contents($files[$index]);
            }
        }
        DB::purge();
        DB::reconnect();
        $this->assertSame([], $failed, 'Процесс callback завершился с ошибкой: ' . implode(' | ', $failed));

        $this->assertSame(1, substr_count((string) file_get_contents($counter), '1'));
        $this->assertSame(1, (int) DB::table('evo_commerce_order_payments')->where('hash', 'parallel-hash')->value('paid'));
        $meta = $this->paymentMeta('parallel-hash');
        $this->assertSame('sent', $meta['push_notification']['state']);
        $this->assertSame(['existing' => 'data'], $meta['provider']);
        $fields = json_decode((string) DB::table('evo_commerce_orders')->where('id', $seed['order_id'])->value('fields'), true);
        $this->assertTrue($fields['is_paid']);
        $this->assertSame(12.5, $fields['online_payment_price']);
        $this->assertSame(2, $this->pivotQuantity($seed['cart_id'], 10));
        @unlink($counter);
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    public function test_http_cart_add_updates_and_removes_by_absolute_quantity(): void
    {
        $user = $this->makeUser();
        DB::table('evo_site_content')->insert(['id' => 10]);
        $this->setStock(10, 1, 8);

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', [
            'product_id' => 10,
            'quantity' => 2,
            'pharmacy_id' => 1,
        ])->assertOk()->assertExactJson(['success' => true]);

        $cartId = (int) Cart::query()->where('user_id', $user->id)->value('id');
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, $this->pivotQuantity($cartId, 10));

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', [
            'product_id' => 10,
            'quantity' => 4,
            'pharmacy_id' => 1,
        ])->assertOk();
        $this->assertSame(4, $this->pivotQuantity($cartId, 10));

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', [
            'product_id' => 10,
            'quantity' => 0,
            'pharmacy_id' => 1,
        ])->assertOk();
        $this->assertNull($this->pivotQuantity($cartId, 10));

        $this->actingAs($user, 'sanctum')->deleteJson('/api/cart')->assertOk();
        $this->assertSame(0, $this->mergeOperationCount($user->id));
    }

    public function test_check_cart_creates_one_cart_and_returns_existing_lowest_id(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $created = $this->cartService()->checkCart();
        $again = $this->cartService()->checkCart();

        $this->assertSame($created->id, $again->id);
        $this->assertSame('1', (string) $created->promo);
        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->count());

        $second = $this->makeCart($user);
        $user->unsetRelation('cart');
        $selected = $this->cartService()->checkCart();

        $this->assertSame($created->id, $selected->id);
        $this->assertNotSame($second, $selected->id);
        $this->assertSame(2, Cart::query()->where('user_id', $user->id)->count());
        $this->assertSame($created->id, $user->fresh()->cart->id);
    }

    public function test_parent_duplicate_diagnosis_distinguishes_empty_stocked_and_settings(): void
    {
        $user = $this->makeUser();
        $emptyLow = $this->makeCart($user, ['promo' => '1']);
        $stocked = $this->makeCart($user, [
            'promo' => 'SALE',
            'pharmacy_id' => 9,
            'promocodes' => 'SPRING',
            'delivery_zone' => 'green',
            'geo_lat' => '53.9',
            'geo_long' => '27.5',
        ]);
        $this->addPivot($stocked, 10, 2);

        $report = $this->duplicates()->diagnoseParents();

        $this->assertCount(1, $report);
        $this->assertSame($user->id, $report[0]['user_id']);
        $this->assertSame([$emptyLow], $report[0]['empty_cart_ids']);
        $this->assertSame([$stocked], $report[0]['carts_with_products']);
        $this->assertTrue($report[0]['settings_differ']);
        $this->assertSame(0, $report[0]['carts'][0]['product_rows']);
        $this->assertSame(1, $report[0]['carts'][1]['product_rows']);

        $this->artisan('cart:diagnose-parents')
            ->expectsOutputToContain('user_id=' . $user->id)
            ->assertSuccessful();
    }

    public function test_empty_parent_cleanup_is_preview_then_deletes_only_empty_duplicates(): void
    {
        $allEmpty = $this->makeUser();
        $keep = $this->makeCart($allEmpty);
        $drop = $this->makeCart($allEmpty);

        $mixed = $this->makeUser();
        $emptyCanonical = $this->makeCart($mixed);
        $withProducts = $this->makeCart($mixed, ['pharmacy_id' => 4, 'promocodes' => 'A']);
        $this->addPivot($withProducts, 10, 3);

        $bothStocked = $this->makeUser();
        $stockedA = $this->makeCart($bothStocked);
        $stockedB = $this->makeCart($bothStocked);
        $this->addPivot($stockedA, 10, 1);
        $this->addPivot($stockedB, 11, 1);

        $single = $this->makeUser();
        $only = $this->makeCart($single);

        $preview = $this->duplicates()->cleanupEmptyParents(false);
        $this->assertTrue(Cart::query()->whereKey($drop)->exists());
        $this->assertSame([$drop], $this->planFor($preview, $allEmpty->id));

        $this->artisan('cart:cleanup-empty-parents')->assertSuccessful();
        $this->assertTrue(Cart::query()->whereKey($drop)->exists());

        $applied = $this->duplicates()->cleanupEmptyParents(true);
        $byUser = collect($applied)->keyBy('user_id');

        $this->assertSame([$drop], $byUser[$allEmpty->id]['deleted']);
        $this->assertSame([$keep], $byUser[$allEmpty->id]['kept']);
        $this->assertFalse($byUser[$allEmpty->id]['canonical_changed']);

        $this->assertSame([$emptyCanonical], $byUser[$mixed->id]['deleted']);
        $this->assertSame([$withProducts], $byUser[$mixed->id]['kept']);
        $this->assertTrue($byUser[$mixed->id]['canonical_changed']);
        $this->assertSame(3, $this->pivotQuantity($withProducts, 10));

        $this->assertSame([], $byUser[$bothStocked->id]['deleted']);
        $this->assertEqualsCanonicalizing([$stockedA, $stockedB], $byUser[$bothStocked->id]['kept']);
        $this->assertFalse(collect($applied)->contains(fn (array $row) => $row['user_id'] === $single->id));
        $this->assertTrue(Cart::query()->whereKey($only)->exists());
    }

    public function test_pivot_cleanup_keeps_highest_id_without_summing_quantities(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $low = $this->addPivot($cartId, 10, 9);
        $high = $this->addPivot($cartId, 10, 1);
        $single = $this->addPivot($cartId, 11, 4);

        $groups = $this->duplicates()->diagnosePivots();
        $this->assertCount(1, $groups);
        $this->assertSame($cartId, $groups[0]['cart_id']);
        $this->assertSame(10, $groups[0]['evo_site_content_id']);
        $this->assertSame(2, $groups[0]['row_count']);
        $this->assertSame($high, $groups[0]['keep_id']);
        $this->assertSame([$low], $groups[0]['delete_ids']);
        $this->assertSame(
            [['id' => $low, 'quantity' => 9], ['id' => $high, 'quantity' => 1]],
            $groups[0]['lines']
        );

        $preview = $this->duplicates()->cleanupPivotDuplicates(false);
        $this->assertFalse($preview['applied']);
        $this->assertSame(2, $this->pivotCount($cartId, 10));

        $this->artisan('cart:cleanup-pivots')
            ->expectsOutputToContain('оставить id=' . $high)
            ->assertSuccessful();
        $this->assertSame(2, $this->pivotCount($cartId, 10));

        $applied = $this->duplicates()->cleanupPivotDuplicates(true);
        $this->assertTrue($applied['applied']);
        $this->assertSame($high, $applied['carts'][0]['kept'][0]['id']);
        $this->assertSame(1, $applied['carts'][0]['kept'][0]['quantity']);
        $this->assertSame($low, $applied['carts'][0]['deleted'][0]['id']);
        $this->assertSame(9, $applied['carts'][0]['deleted'][0]['quantity']);
        $this->assertSame(1, $this->pivotQuantity($cartId, 10));
        $this->assertSame(4, $this->pivotQuantity($cartId, 11));
        $this->assertSame($single, (int) DB::table('cart_evo_site_content')->where('cart_id', $cartId)->where('evo_site_content_id', 11)->value('id'));
        $this->assertSame([], $this->duplicates()->diagnosePivots());
    }

    public function test_parent_unique_migration_rejects_duplicates_then_adds_index(): void
    {
        $user = $this->makeUser();
        $first = $this->makeCart($user);
        $second = $this->makeCart($user);
        $migration = require base_path('database/migrations/2026_11_19_150000_add_unique_user_id_to_carts_table.php');

        try {
            $migration->up();
            $this->fail('Миграция должна остановиться, пока есть две корзины.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cart:diagnose-parents', $e->getMessage());
            $this->assertStringContainsString('cart:cleanup-empty-parents --apply', $e->getMessage());
        }

        $this->assertSame(2, Cart::query()->where('user_id', $user->id)->count());
        $this->assertFalse($this->indexExists('carts', 'carts_user_id_unique'));

        DB::table('carts')->where('id', $second)->delete();
        $migration->up();

        $this->assertTrue($this->indexExists('carts', 'carts_user_id_unique'));
        $this->assertTrue(Cart::query()->whereKey($first)->exists());

        $rejected = false;
        try {
            $this->makeCart($user);
        } catch (QueryException) {
            $rejected = true;
        }
        $this->assertTrue($rejected);
    }

    public function test_pivot_unique_migration_rejects_duplicates_then_adds_index(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $low = $this->addPivot($cartId, 10, 9);
        $high = $this->addPivot($cartId, 10, 1);
        $migration = require base_path('database/migrations/2026_11_19_150100_add_unique_cart_product_to_cart_evo_site_content_table.php');

        try {
            $migration->up();
            $this->fail('Миграция должна остановиться, пока есть дубли строк.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cart:diagnose-pivots', $e->getMessage());
            $this->assertStringContainsString('cart:cleanup-pivots --apply', $e->getMessage());
        }

        $this->assertSame(2, $this->pivotCount($cartId, 10));
        $this->assertFalse($this->indexExists('cart_evo_site_content', 'cart_evo_site_content_cart_product_unique'));

        DB::table('cart_evo_site_content')->where('id', $low)->delete();
        $migration->up();

        $this->assertTrue($this->indexExists('cart_evo_site_content', 'cart_evo_site_content_cart_product_unique'));
        $this->assertSame(1, $this->pivotQuantity($cartId, 10));
        $this->assertSame($high, (int) DB::table('cart_evo_site_content')->where('cart_id', $cartId)->value('id'));
    }

    public function test_check_cart_and_add_to_cart_emit_row_locks(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        $this->setStock(10, 1, 8);
        $this->actingAs($user);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $this->cartService()->checkCart();
        $this->cartService()->addToCart($this->cartDto($user, $cartId, 10, 1, 1));

        $this->assertTrue($this->sqlContainsLock($sql, 'users'), 'checkCart должен блокировать строку users. SQL: ' . implode(' | ', $sql));
        $this->assertTrue($this->sqlContainsLock($sql, 'carts'), 'addToCart должен блокировать строку carts. SQL: ' . implode(' | ', $sql));
    }

    public function test_user_row_lock_blocks_a_second_connection(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'SQLite не удерживает lockForUpdate между соединениями. '
                . 'Этот тест проверяет реальное ожидание блокировки InnoDB и не заменяется последовательным сценарием.'
            );
        }

        $user = $this->makeUser();
        config([
            'database.connections.cart_lock_probe' => config('database.connections.mysql'),
        ]);
        DB::purge('cart_lock_probe');
        $probe = DB::connection('cart_lock_probe');

        DB::beginTransaction();
        try {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $blocked = false;
            try {
                $probe->statement('SET innodb_lock_wait_timeout = 1');
                $probe->beginTransaction();
                $probe->select('select * from users where id = ? for update', [$user->id]);
            } catch (QueryException $e) {
                $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
            } finally {
                $probe->rollBack();
            }

            $this->assertTrue($blocked, 'Второе соединение должно ждать блокировку строки users.');
        } finally {
            DB::rollBack();
        }
    }

    public function test_reads_follow_the_lowest_cart_id(): void
    {
        $user = $this->makeUser();
        $low = $this->makeCart($user, ['promocodes' => 'LOW']);
        $high = $this->makeCart($user, ['promocodes' => 'HIGH', 'pharmacy_id' => 4]);
        $this->addPivot($low, 10, 1);
        $this->addPivot($high, 11, 9);
        $this->actingAs($user);
        DB::table('evo_pharmacies_view')->insert([
            'pharmacy_id' => 4,
            'pagetitle' => 'Аптека',
            'address' => 'адрес',
        ]);

        $cart = $this->cartService()->getCart();
        $this->assertInstanceOf(CartsView::class, $cart);
        $this->assertSame($low, (int) $cart->cart['cart_id']);
        $this->assertSame(10, (int) $cart->product_info[0]['product_id']);

        $detailed = $this->cartService()->getCartDetailed(new CartDetailedDTO(
            pharmacyId: 4,
            deliveryZone: 'green',
            promocodes: 'FROM-LOW',
        ));
        $this->assertSame($low, (int) $detailed['cart']['cart_id']);
        $this->assertSame('FROM-LOW', DB::table('carts')->where('id', $low)->value('promocodes'));
        $this->assertSame('HIGH', DB::table('carts')->where('id', $high)->value('promocodes'));
        $this->assertSame(4, (int) DB::table('carts')->where('id', $low)->value('pharmacy_id'));
        $this->assertSame(4, (int) DB::table('carts')->where('id', $high)->value('pharmacy_id'));

        DB::table('carts')->where('id', $low)->update(['promocodes' => 'KEEP-LOW']);
        $delivery = $this->cartService()->getCartDetailedWithoutChoosenPharm(new CartDetailedDTO(
            pharmacyId: null,
            deliveryZone: 'yellow',
            promocodes: 'DELIVERY-LOW',
        ));
        $this->assertSame($low, (int) $delivery['cart']['cart_id']);
        $this->assertSame('DELIVERY-LOW', DB::table('carts')->where('id', $low)->value('promocodes'));
        $this->assertSame('HIGH', DB::table('carts')->where('id', $high)->value('promocodes'));
        $this->assertSame('yellow', DB::table('carts')->where('id', $low)->value('delivery_zone'));
        $this->assertNull(DB::table('carts')->where('id', $high)->value('delivery_zone'));

        DB::table('evo_products_view')->insert([
            'product_id' => 10,
            'pagetitle' => 'Товар',
            'is_recipe' => 0,
            'is_alcohol' => 'no',
        ]);
        $pharmacies = $this->cartService()->getProductByPharmacies(new CartPharmaciesDTO(
            geoLat: 53.9,
            geoLong: 27.56,
            products: [new CartProductItemDTO(productId: 10, quantity: 1)],
        ));
        $this->assertSame('full', $pharmacies[0]['products'][0]['availability']);
        $this->assertSame(5.0, (float) $pharmacies[0]['products'][0]['stock_count']);

        DB::table('evo_product_pharmacy_view')->insert([
            'product_id' => 11,
            'pharmacy_id' => 1,
            'pharmacy_name' => 'Аптека',
            'coordinates' => '53.9,27.5',
            'stock_count' => 4,
            'price' => 2,
            'price_old' => 2,
        ]);
        $priority = $this->cartService()->getPriorityPharmacy(new CartPharmaciesDTO(
            geoLat: 53.9,
            geoLong: 27.56,
            products: [new CartProductItemDTO(productId: 11, quantity: 1)],
        ));
        $this->assertSame([], $priority);
    }

    public function test_two_processes_calling_check_cart_create_one_cart(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork недоступен, одновременный checkCart не проверяется.');
        }

        $user = $this->makeUser();
        $userId = $user->id;
        DB::disconnect();

        $results = [
            tempnam(sys_get_temp_dir(), 'cart-a-'),
            tempnam(sys_get_temp_dir(), 'cart-b-'),
        ];
        $pids = [];

        foreach ($results as $resultFile) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Не удалось запустить процесс для checkCart.');
            }
            if ($pid === 0) {
                try {
                    usleep(200000);
                    DB::purge();
                    DB::reconnect();
                    Auth::login(User::query()->findOrFail($userId));
                    file_put_contents($resultFile, 'entering');
                    app(CartService::class)->checkCart();
                    file_put_contents($resultFile, 'ok');
                } catch (\Throwable $e) {
                    file_put_contents($resultFile, $e->getMessage());
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
        $whileLocked = null;
        try {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $deadline = microtime(true) + 4;
            do {
                $states = array_map(static fn ($file) => (string) @file_get_contents($file), $results);
                $entered = count(array_filter($states, static fn ($state) => in_array($state, ['entering', 'ok'], true)));
                if ($entered === 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);

            usleep(700000);
            $states = array_map(static fn ($file) => (string) @file_get_contents($file), $results);
            $whileLocked = (int) DB::table('carts')->where('user_id', $userId)->count();
            $finishedWhileLocked = array_filter($states, static fn ($state) => $state === 'ok');
        } finally {
            DB::rollBack();
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();
        $messages = array_map(static fn ($file) => (string) @file_get_contents($file), $results);
        $this->assertSame([], array_values($finishedWhileLocked), 'checkCart завершился, пока строка пользователя была заблокирована: ' . implode(' | ', $messages));
        $this->assertSame(0, $whileLocked, 'Корзина появилась до снятия блокировки пользователя.');
        $this->assertSame(1, (int) DB::table('carts')->where('user_id', $userId)->count());
        $this->assertSame(['ok', 'ok'], $messages);

        foreach ($results as $resultFile) {
            @unlink($resultFile);
        }
    }

    public function test_old_cart_add_keeps_absolute_response_and_stock_error(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        DB::table('evo_site_content')->insert(['id' => 10]);
        $this->addPivot($cartId, 10, 1);
        $this->setStock(10, 1, 4);

        $this->postMerge($user, [
            'product_id' => 10,
            'quantity' => 3,
            'pharmacy_id' => 1,
        ])->assertOk()->assertExactJson(['success' => true]);
        $this->assertSame(3, $this->pivotQuantity($cartId, 10));

        $this->postMerge($user, [
            'product_id' => 10,
            'quantity' => 9,
            'pharmacy_id' => 1,
        ])->assertStatus(422)->assertExactJson([
            'success' => false,
            'data' => ['error' => 'Больше нет в наличии'],
        ]);
        $this->assertSame(3, $this->pivotQuantity($cartId, 10));
        $this->assertSame(0, $this->mergeOperationCount($user->id));

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', [
            'product_id' => 999,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'])
            ->assertJsonMissingPath('success');
    }

    public function test_merge_adds_delta_to_the_canonical_cart(): void
    {
        [$user, $low, $high] = $this->twoCartsWithProduct(3, 9, 10);

        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson([
            'success' => true,
            'data' => [
                'product_id' => 10,
                'quantity' => 5,
                'limited' => false,
                'already_applied' => false,
            ],
        ]);

        $this->assertSame(5, $this->pivotQuantity($low, 10));
        $this->assertSame(9, $this->pivotQuantity($high, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id));
    }

    public function test_merge_limits_quantity_to_available_stock(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 4);

        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson($this->mergeResponse(4, true));
        $this->assertSame(4, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id));
    }

    public function test_merge_decreases_quantity_down_to_positive_stock(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 2);

        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson($this->mergeResponse(2, true));
        $this->assertSame(2, $this->pivotQuantity($cartId, 10));
    }

    public function test_merge_caps_quantity_at_255(): void
    {
        [$user, $cartId] = $this->mergeCart(254, 300);

        $this->postMerge($user, $this->mergeBody(5))->assertOk()->assertExactJson($this->mergeResponse(255, true));
        $this->assertSame(255, $this->pivotQuantity($cartId, 10));
    }

    public function test_merge_rejects_zero_stock_without_changing_cart_or_recording_operation(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 0);

        $this->postMerge($user, $this->mergeBody(2))->assertStatus(422)->assertExactJson([
            'success' => false,
            'data' => ['error' => 'Товар недоступен'],
        ]);
        $this->assertSame(3, $this->pivotQuantity($cartId, 10));
        $this->assertSame(0, $this->mergeOperationCount($user->id));
    }

    public function test_merge_replay_does_not_add_delta_again(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 10);

        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson($this->mergeResponse(5, false));
        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson($this->mergeResponse(5, false, true));

        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id));
    }

    public function test_merge_replay_with_another_delta_returns_the_stored_result(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 20);

        $this->postMerge($user, $this->mergeBody(2))->assertOk()->assertExactJson($this->mergeResponse(5, false));
        $this->postMerge($user, $this->mergeBody(9))->assertOk()->assertExactJson($this->mergeResponse(5, false, true));

        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id));
    }

    public function test_merge_replay_after_product_deletion_returns_the_stored_result(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 10);

        $this->postMerge($user, $this->mergeBody(2))->assertOk();
        DB::table('evo_site_content')->where('id', 10)->delete();

        $this->postMerge($user, $this->mergeBody(4))->assertOk()->assertExactJson($this->mergeResponse(5, false, true));
        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id));
    }

    public function test_merge_of_missing_product_returns_unavailable_without_operation(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);

        $this->postMerge($user, $this->mergeBody(2, 404))->assertStatus(422)->assertExactJson([
            'success' => false,
            'data' => ['error' => 'Товар недоступен'],
        ]);
        $this->assertNull($this->pivotQuantity($cartId, 404));
        $this->assertSame(0, $this->mergeOperationCount($user->id));
    }

    public function test_merge_accepts_existing_product_regardless_of_published_and_deleted_flags(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        DB::table('evo_site_content')->insert([
            ['id' => 20, 'published' => 0, 'deleted' => 0],
            ['id' => 21, 'published' => 1, 'deleted' => 1],
        ]);
        $this->setStock(20, 1, 10);
        $this->setStock(21, 1, 10);

        $this->postMerge($user, $this->mergeBody(2, 20, 'unpublished'))
            ->assertOk()
            ->assertExactJson($this->mergeResponse(2, false, false, 20));
        $this->postMerge($user, $this->mergeBody(2, 21, 'deleted'))
            ->assertOk()
            ->assertExactJson($this->mergeResponse(2, false, false, 21));

        $this->assertSame(2, $this->pivotQuantity($cartId, 20));
        $this->assertSame(2, $this->pivotQuantity($cartId, 21));
    }

    public function test_merge_ignores_delivery_pharmacy_stock_and_requested_pharmacy(): void
    {
        [$user, $cartId] = $this->mergeCart(0, 0);
        $this->setStock(10, PharmaciesView::PHARMACY_ID_FOR_DELIVERY, 50);

        $this->postMerge($user, $this->mergeBody(2) + ['pharmacy_id' => 1])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'data' => ['error' => 'Товар недоступен'],
            ]);
        $this->assertNull($this->pivotQuantity($cartId, 10));

        $this->setStock(10, 1, 1);
        $this->setStock(10, 2, 10);
        $this->postMerge($user, $this->mergeBody(2) + ['pharmacy_id' => 1])
            ->assertOk()
            ->assertExactJson($this->mergeResponse(2, false));
        $this->assertSame(2, $this->pivotQuantity($cartId, 10));
    }

    public function test_incomplete_merge_parameters_do_not_change_the_cart(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 10);
        $before = $this->pivotQuantity($cartId, 10);

        $this->postMerge($user, [
            'product_id' => 10,
            'merge_token' => 'only-token',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['delta'])
            ->assertJsonMissingValidationErrors(['quantity'])
            ->assertJsonMissingPath('success');

        $this->postMerge($user, [
            'product_id' => 10,
            'delta' => 2,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['merge_token'])
            ->assertJsonMissingPath('success');

        $this->postMerge($user, $this->mergeBody(0))->assertStatus(422)
            ->assertJsonValidationErrors(['delta'])
            ->assertJsonMissingPath('success');

        $this->postMerge($user, $this->mergeBody(-1))->assertStatus(422)
            ->assertJsonValidationErrors(['delta'])
            ->assertJsonMissingPath('success');

        $this->postMerge($user, $this->mergeBody(2, 10, str_repeat('a', 65)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['merge_token'])
            ->assertJsonMissingPath('success');

        $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', [
            'product_id' => 10,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity'])
            ->assertJsonMissingValidationErrors(['delta', 'merge_token']);

        $this->assertSame($before, $this->pivotQuantity($cartId, 10));
        $this->assertSame(0, $this->mergeOperationCount($user->id));
    }

    public function test_same_merge_token_applies_to_different_products_separately(): void
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        DB::table('evo_site_content')->insert([
            ['id' => 10],
            ['id' => 11],
        ]);
        $this->addPivot($cartId, 10, 3);
        $this->setStock(10, 1, 10);
        $this->setStock(11, 1, 10);

        $this->postMerge($user, $this->mergeBody(2, 10, 'shared'))->assertOk()->assertExactJson($this->mergeResponse(5, false));
        $this->postMerge($user, $this->mergeBody(1, 11, 'shared'))->assertOk()->assertExactJson($this->mergeResponse(1, false, false, 11));

        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->pivotQuantity($cartId, 11));
        $this->assertSame(2, $this->mergeOperationCount($user->id, 'shared'));
    }

    public function test_merge_operation_migration_creates_unique_key_without_foreign_keys(): void
    {
        Schema::drop('cart_merge_operations');
        $migration = require base_path('database/migrations/2026_11_20_141000_create_cart_merge_operations_table.php');
        $migration->up();

        $this->assertTrue($this->indexExists('cart_merge_operations', 'cart_merge_operations_user_token_product_unique'));
        $columns = DB::select(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            ['cart_merge_operations']
        );
        $types = [];
        foreach ($columns as $column) {
            $types[$column->COLUMN_NAME] = $column->COLUMN_TYPE;
        }
        $this->assertSame('bigint unsigned', $types['id']);
        $this->assertSame('bigint unsigned', $types['user_id']);
        $this->assertSame('varchar(64)', $types['merge_token']);
        $this->assertSame('int unsigned', $types['product_id']);
        $this->assertSame('tinyint unsigned', $types['quantity']);
        $this->assertSame('tinyint(1)', $types['limited']);
        $this->assertSame('timestamp', $types['created_at']);
        $this->assertArrayNotHasKey('updated_at', $types);

        $foreignKeys = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?',
            ['cart_merge_operations', 'FOREIGN KEY']
        );
        $this->assertSame([], $foreignKeys);

        $migration->down();
        $this->assertFalse(Schema::hasTable('cart_merge_operations'));
    }

    public function test_two_processes_with_the_same_merge_key_apply_delta_once(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 10);

        $payloads = $this->mergeInParallel($user->id, 10, [
            ['token' => 'same-token', 'delta' => 2],
            ['token' => 'same-token', 'delta' => 2],
        ]);

        $applied = array_map(static fn (array $payload) => $payload['already_applied'], $payloads);
        sort($applied);
        $this->assertSame([false, true], $applied);
        foreach ($payloads as $payload) {
            $this->assertSame(5, $payload['quantity']);
            $this->assertFalse($payload['limited']);
        }
        $this->assertSame(5, $this->pivotQuantity($cartId, 10));
        $this->assertSame(1, $this->mergeOperationCount($user->id, 'same-token', 10));
    }

    public function test_two_processes_with_different_merge_tokens_add_deltas_in_sequence(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 100);

        $payloads = $this->mergeInParallel($user->id, 10, [
            ['token' => 'token-a', 'delta' => 2],
            ['token' => 'token-b', 'delta' => 1],
        ]);

        foreach ($payloads as $payload) {
            $this->assertFalse($payload['already_applied']);
            $this->assertFalse($payload['limited']);
        }
        $this->assertSame(6, $this->pivotQuantity($cartId, 10));
        $this->assertSame(2, $this->mergeOperationCount($user->id));
        $stored = DB::table('cart_merge_operations')->orderBy('quantity')->pluck('quantity')->map(fn ($quantity) => (int) $quantity)->all();
        $this->assertContains(6, $stored);
        $this->assertTrue(in_array(4, $stored, true) || in_array(5, $stored, true));
    }

    public function test_failed_merge_transaction_does_not_leave_pivot_without_operation(): void
    {
        [$user, $cartId] = $this->mergeCart(3, 10);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cart_merge_operations_fail_insert
            BEFORE INSERT ON cart_merge_operations
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced merge failure'
        SQL);

        try {
            $failed = false;
            try {
                $this->cartService()->mergeGuestItem($user, 10, 'force-fail', 2);
            } catch (QueryException $e) {
                $failed = str_contains($e->getMessage(), 'forced merge failure');
            }

            $this->assertTrue($failed, 'Сбой записи операции должен откатить транзакцию.');
            $this->assertSame(3, $this->pivotQuantity($cartId, 10));
            $this->assertSame(0, $this->mergeOperationCount($user->id));
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS cart_merge_operations_fail_insert');
        }
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
            $table->string('fcm_token')->nullable();
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
            $table->increments('id');
            $table->unsignedTinyInteger('published')->default(1);
            $table->unsignedTinyInteger('deleted')->default(0);
        });

        $mergeOperations = require base_path('database/migrations/2026_11_20_141000_create_cart_merge_operations_table.php');
        $mergeOperations->up();

        Schema::create('offer_cache', function ($table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->integer('stock_count')->default(0);
            $table->primary(['product_id', 'pharmacy_id']);
        });

        Schema::create('evo_commerce_orders', function ($table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->text('fields')->nullable();
            $table->timestamps();
        });

        Schema::create('evo_commerce_order_payments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('hash');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('paid')->default(0);
            $table->text('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('evo_commerce_order_products', function ($table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('product_id');
        });

        Schema::create('evo_products_view', function ($table) {
            $table->unsignedInteger('product_id')->primary();
            $table->string('pagetitle')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_recipe')->nullable();
            $table->string('is_alcohol')->nullable();
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
                    'products', JSON_ARRAY(JSON_OBJECT('product_id', 10, 'quantity', 1))
                ) AS cart
            FROM carts c
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW evo_cart_product_pharmacy_view AS
            SELECT
                c.id AS cart_id,
                c.user_id AS user_id,
                JSON_ARRAY(JSON_OBJECT(
                    'product_id', 10,
                    'pharmacies', JSON_ARRAY(JSON_OBJECT(
                        'pharmacy_id', 1,
                        'pharmacy_name', 'Аптека',
                        'address', 'адрес',
                        'coordinates', '53.9,27.5',
                        'schedule', '',
                        'stock_count', IF(c.id = (SELECT MIN(id) FROM carts WHERE user_id = c.user_id), 5, 0),
                        'price', 1,
                        'price_old', 1
                    ))
                )) AS cart
            FROM carts c
        SQL);
    }

    private function makeUser(): User
    {
        $this->phoneSequence++;

        return User::query()->create([
            'name' => 'Test',
            'phone' => '+37525-000-00-' . str_pad((string) $this->phoneSequence, 2, '0', STR_PAD_LEFT),
            'password' => 'secret',
        ]);
    }

    private function makeCart(User $user, array $extra = []): int
    {
        return (int) DB::table('carts')->insertGetId(array_merge([
            'user_id' => $user->id,
            'promo' => '1',
            'pharmacy_id' => null,
            'promocodes' => null,
            'delivery_zone' => null,
            'geo_lat' => null,
            'geo_long' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function addPivot(int $cartId, int $productId, int $quantity): int
    {
        return (int) DB::table('cart_evo_site_content')->insertGetId([
            'cart_id' => $cartId,
            'evo_site_content_id' => $productId,
            'quantity' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setStock(int $productId, int $pharmacyId, int $stock): void
    {
        DB::table('offer_cache')->updateOrInsert(
            ['product_id' => $productId, 'pharmacy_id' => $pharmacyId],
            ['stock_count' => $stock]
        );
    }

    private function postMerge(User $user, array $payload)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/cart/add', $payload);
    }

    /**
     * @return array{0: User, 1: int}
     */
    private function mergeCart(int $current, int $stock, int $productId = 10): array
    {
        $user = $this->makeUser();
        $cartId = $this->makeCart($user);
        DB::table('evo_site_content')->insert([
            'id' => $productId,
            'published' => 1,
            'deleted' => 0,
        ]);
        if ($current > 0) {
            $this->addPivot($cartId, $productId, $current);
        }
        if ($stock > 0) {
            $this->setStock($productId, 1, $stock);
        }

        return [$user, $cartId];
    }

    /**
     * @return array{0: User, 1: int, 2: int}
     */
    private function twoCartsWithProduct(int $lowQuantity, int $highQuantity, int $stock): array
    {
        $user = $this->makeUser();
        $low = $this->makeCart($user);
        $high = $this->makeCart($user);
        DB::table('evo_site_content')->insert(['id' => 10, 'published' => 1, 'deleted' => 0]);
        $this->addPivot($low, 10, $lowQuantity);
        $this->addPivot($high, 10, $highQuantity);
        $this->setStock(10, 1, $stock);

        return [$user, $low, $high];
    }

    private function mergeBody(int $delta, int $productId = 10, string $token = '6f1c0c3e-3d2a-4c2e-9c2a-0b6e2f0a9c11'): array
    {
        return [
            'product_id' => $productId,
            'merge_token' => $token,
            'delta' => $delta,
        ];
    }

    private function mergeResponse(int $quantity, bool $limited, bool $alreadyApplied = false, int $productId = 10): array
    {
        return [
            'success' => true,
            'data' => [
                'product_id' => $productId,
                'quantity' => $quantity,
                'limited' => $limited,
                'already_applied' => $alreadyApplied,
            ],
        ];
    }

    private function mergeOperationCount(int $userId, ?string $token = null, ?int $productId = null): int
    {
        $query = DB::table('cart_merge_operations')->where('user_id', $userId);
        if ($token !== null) {
            $query->where('merge_token', $token);
        }
        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        return (int) $query->count();
    }

    /**
     * @param list<array{token: string, delta: int}> $calls
     * @return list<array<string, mixed>>
     */
    private function mergeInParallel(int $userId, int $productId, array $calls): array
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork недоступен, одновременное слияние не проверяется.');
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->fail('Конкурентное слияние проверяется только на MySQL/InnoDB.');
        }

        DB::disconnect();
        $files = [];
        $pids = [];
        foreach ($calls as $call) {
            $file = tempnam(sys_get_temp_dir(), 'merge-');
            $files[] = $file;
            $token = $call['token'];
            $delta = $call['delta'];
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->fail('Не удалось запустить процесс слияния.');
            }
            if ($pid === 0) {
                try {
                    DB::purge();
                    DB::reconnect();
                    $user = User::query()->findOrFail($userId);
                    Auth::login($user);
                    $payload = app(CartService::class)->mergeGuestItem($user, $productId, $token, $delta);
                    file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE));
                    DB::disconnect();
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($file, json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
                    exit(1);
                }
            }
            $pids[] = $pid;
        }

        $failed = [];
        foreach ($pids as $index => $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) !== 0) {
                $failed[] = (string) @file_get_contents($files[$index]);
            }
        }

        DB::purge();
        DB::reconnect();
        $payloads = [];
        foreach ($files as $file) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            $payloads[] = is_array($decoded) ? $decoded : ['error' => (string) @file_get_contents($file)];
            @unlink($file);
        }
        $this->assertSame([], $failed, 'Процесс слияния завершился с ошибкой: ' . implode(' | ', $failed));

        return $payloads;
    }

    /**
     * @return array{order_id: int, cart_id: int}
     */
    private function seedOnlinePayment(User $user, string $hash, ?string $meta = null, string $method = 'bepaid'): array
    {
        $cartId = $this->makeCart($user);
        $this->addPivot($cartId, 10, 2);
        $orderId = (int) DB::table('evo_commerce_orders')->insertGetId([
            'customer_id' => $user->id,
            'fields' => json_encode(['payment_method' => $method], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('evo_commerce_order_products')->insert([
            'order_id' => $orderId,
            'product_id' => 10,
        ]);
        $payment = [
            'order_id' => $orderId,
            'hash' => $hash,
            'amount' => 12.50,
            'paid' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($meta !== null) {
            $payment['meta'] = $meta;
        }
        DB::table('evo_commerce_order_payments')->insert($payment);

        return ['order_id' => $orderId, 'cart_id' => $cartId];
    }

    private function setFcmToken(User $user, string $token): void
    {
        DB::table('users')->where('id', $user->id)->update(['fcm_token' => $token]);
    }

    private function bindMessaging(Messaging $messaging): void
    {
        $this->app->instance(Messaging::class, $messaging);
    }

    private function messagingThatSends(): Messaging
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->with(Mockery::on(function ($message) {
            $payload = json_decode(json_encode($message), true);

            return ($payload['notification']['body'] ?? null) === 'Ваш заказ оплачен.'
                && ($payload['notification']['title'] ?? null) === 'Inlek. Информация о заказе.';
        }))->andReturn(['name' => 'projects/test/messages/1']);

        return $messaging;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMeta(string $hash): array
    {
        $decoded = json_decode((string) DB::table('evo_commerce_order_payments')->where('hash', $hash)->value('meta'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function pivotQuantity(int $cartId, int $productId): ?int
    {
        $quantity = DB::table('cart_evo_site_content')
            ->where('cart_id', $cartId)
            ->where('evo_site_content_id', $productId)
            ->value('quantity');

        return $quantity === null ? null : (int) $quantity;
    }

    private function pivotCount(int $cartId, int $productId): int
    {
        return (int) DB::table('cart_evo_site_content')
            ->where('cart_id', $cartId)
            ->where('evo_site_content_id', $productId)
            ->count();
    }

    private function cartDto(User $user, int $cartId, int $productId, int $quantity, ?int $pharmacyId): CartDTO
    {
        return new CartDTO(
            user: $user,
            cart: Cart::query()->findOrFail($cartId),
            product_id: $productId,
            quantity: $quantity,
            pharmacy_id: $pharmacyId,
        );
    }

    private function cartService(): CartService
    {
        return $this->app->make(CartService::class);
    }

    private function duplicates(): CartDuplicateService
    {
        return $this->app->make(CartDuplicateService::class);
    }

    private function orderService(?Messaging $messaging = null): OrderService
    {
        return new OrderService(
            new User(),
            $this->cartService(),
            new OrderViewJson(),
            new EvoCommerceOrderStatuses(),
            new EvoCommerceOrders(),
            new EvoCommerceOrderProducts(),
            new EvoCommerceOrderHistory(),
            new EvoCommerceOrderPayments(),
            new PharmacyService(new PharmaciesView()),
            new ProductInfoViewJson(),
            new FirebaseService($messaging ?? Mockery::mock(Messaging::class)),
        );
    }

    private function invoke(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function planFor(array $rows, int $userId): array
    {
        foreach ($rows as $row) {
            if ($row['user_id'] === $userId) {
                return $row['delete_ids'];
            }
        }

        return [];
    }

    private function indexExists(string $table, string $name): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select("PRAGMA index_list('" . $table . "')") as $index) {
                if ($index->name === $name) {
                    return true;
                }
            }

            return false;
        }

        $indexes = DB::select('SHOW INDEX FROM `' . str_replace('`', '', $table) . '`');
        foreach ($indexes as $index) {
            if (($index->Key_name ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $sql
     */
    private function sqlContainsLock(array $sql, string $table): bool
    {
        foreach ($sql as $statement) {
            if (!str_contains($statement, 'for update')) {
                continue;
            }
            if (str_contains($statement, $table)) {
                return true;
            }
        }

        return false;
    }
}
