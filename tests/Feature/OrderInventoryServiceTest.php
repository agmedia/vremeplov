<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Back\Orders\Order;
use App\Services\Inventory\OrderInventoryService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderInventoryServiceTest extends TestCase
{
    /** @var string */
    private $originalConnection;

    /** @var OrderInventoryService */
    private $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = (string) config('database.default');
        config([
            'database.default' => 'inventory_testing',
            'database.connections.inventory_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.order.status.unfinished' => 8,
            'settings.order.status.canceled' => 5,
            'settings.order.status.declined' => 7,
            'settings.order.status.paid' => 3,
        ]);

        DB::purge('inventory_testing');
        Carbon::setTestNow(Carbon::parse('2026-09-03 12:00:00'));
        $this->createSchema();
        $this->inventory = app(OrderInventoryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('inventory_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_only_one_competing_order_can_reserve_the_last_copy(): void
    {
        $productId = $this->createProduct(1, 'Zadnji primjerak');
        $winner = $this->createOrderWithItem($productId, 1);
        $loser = $this->createOrderWithItem($productId, 1);

        $this->inventory->reserve($winner, now()->addMinutes(30));

        try {
            $this->inventory->reserve($loser, now()->addMinutes(30));
            $this->fail('Druga narudžba ne smije rezervirati već rezervirani zadnji primjerak.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame($productId, $exception->productId());
            $this->assertSame(0, $exception->available());
            $this->assertSame(1, $exception->requested());
        }

        $this->assertSame(0, $this->stock($productId));
        $this->assertNotNull($winner->fresh()->inventory_reserved_at);
        $this->assertNull($loser->fresh()->inventory_reserved_at);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
    }

    public function test_deleted_product_line_cannot_be_silently_confirmed(): void
    {
        $productId = $this->createProduct(1, 'Obrisana knjiga');
        $order = $this->createOrderWithItem($productId, 1);
        DB::table('products')->where('id', $productId)->delete();

        $this->expectException(InsufficientStockException::class);

        try {
            $this->inventory->reserve($order, now()->addMinutes(30));
        } finally {
            $this->assertNull($order->fresh()->inventory_reserved_at);
            $this->assertSame(0, DB::table('inventory_movements')->count());
        }
    }

    public function test_reserve_is_idempotent_and_only_renews_the_expiry(): void
    {
        $productId = $this->createProduct(3);
        $order = $this->createOrderWithItem($productId, 2);
        $firstExpiry = now()->addMinutes(10);
        $renewedExpiry = now()->addMinutes(30);

        $firstReservation = $this->inventory->reserve($order, $firstExpiry);
        $secondReservation = $this->inventory->reserve($firstReservation, $renewedExpiry);

        $this->assertSame(1, $this->stock($productId));
        $this->assertSame(1, (int) $secondReservation->inventory_reservation_version);
        $this->assertTrue($secondReservation->inventory_reservation_expires_at->equalTo($renewedExpiry));
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    public function test_commit_is_idempotent(): void
    {
        $productId = $this->createProduct(2);
        $order = $this->createOrderWithItem($productId, 1);

        $reservation = $this->inventory->reserve($order, now()->addMinutes(30));
        $firstCommit = $this->inventory->commit($reservation);
        $secondCommit = $this->inventory->commit($firstCommit);

        $this->assertSame(1, $this->stock($productId));
        $this->assertNotNull($secondCommit->inventory_committed_at);
        $this->assertNull($secondCommit->inventory_reservation_expires_at);
        $this->assertSame(1, (int) $secondCommit->inventory_reservation_version);
        $this->assertSame(1, DB::table('inventory_movements')->count());
    }

    public function test_cart_only_adds_back_its_own_uncommitted_reservation(): void
    {
        $productId = $this->createProduct(1);
        $order = $this->createOrderWithItem($productId, 1);

        $reserved = $this->inventory->reserve($order, now()->addMinutes(30));

        $this->assertSame(
            1,
            (int) $this->inventory->availableQuantities([$productId], $reserved->id)->get($productId)
        );

        $committed = $this->inventory->commit($reserved);

        $this->assertSame(
            0,
            (int) $this->inventory->availableQuantities([$productId], $committed->id)->get($productId)
        );
    }

    public function test_release_restores_stock_only_once(): void
    {
        $productId = $this->createProduct(2);
        $order = $this->createOrderWithItem($productId, 1);
        $committed = $this->inventory->commit($order);

        $firstRelease = $this->inventory->release($committed);
        $secondRelease = $this->inventory->release($firstRelease);

        $this->assertSame(2, $this->stock($productId));
        $this->assertNotNull($secondRelease->inventory_released_at);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    public function test_canceling_a_legacy_holding_order_without_inventory_markers_does_not_increase_stock(): void
    {
        $productId = $this->createProduct(0);
        $order = $this->createOrderWithItem($productId, 1, 3);

        $result = $this->inventory->applyStatusTransition($order, 3, 5);

        $this->assertSame(0, $this->stock($productId));
        $this->assertNull($result->inventory_reserved_at);
        $this->assertNull($result->inventory_released_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    public function test_transition_from_canceled_to_active_reserves_and_commits_stock(): void
    {
        $productId = $this->createProduct(1);
        $order = $this->createOrderWithItem($productId, 1, 3);

        $result = $this->inventory->applyStatusTransition($order, 5, 3, 'admin_status:5_to_3');

        $this->assertSame(0, $this->stock($productId));
        $this->assertNotNull($result->inventory_reserved_at);
        $this->assertNotNull($result->inventory_committed_at);
        $this->assertNull($result->inventory_released_at);
        $this->assertSame(1, (int) $result->inventory_reservation_version);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
    }

    public function test_transition_from_canceled_to_call_when_found_does_not_allocate_stock(): void
    {
        $productId = $this->createProduct(1);
        $order = $this->createOrderWithItem($productId, 1, 5);

        $result = $this->inventory->applyStatusTransition($order, 5, 13);

        $this->assertSame(1, $this->stock($productId));
        $this->assertNull($result->inventory_reserved_at);
        $this->assertNull($result->inventory_committed_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    public function test_call_when_found_to_paid_allocates_replenished_stock(): void
    {
        $productId = $this->createProduct(1);
        $order = $this->createOrderWithItem($productId, 1, 13);

        $result = $this->inventory->applyStatusTransition($order, 13, 3, 'admin_status:13_to_3');

        $this->assertSame(0, $this->stock($productId));
        $this->assertNotNull($result->inventory_reserved_at);
        $this->assertNotNull($result->inventory_committed_at);
        $this->assertTrue($this->inventory->reservationMatchesOrder($result));
    }

    /**
     * @dataProvider statusTargetMatrixProvider
     */
    public function test_every_status_target_follows_the_inventory_matrix(
        int $targetStatus,
        string $expectedBehavior
    ): void {
        $productId = $this->createProduct(2, 'Statusna matrica');
        $order = $this->createOrderWithItem($productId, 1, 8);
        $reserved = $this->inventory->reserve($order, now()->addMinutes(30), 'status_matrix_checkout');

        $reserved->update(['order_status_id' => $targetStatus]);
        $result = $this->inventory->applyStatusTransition(
            $reserved->fresh(),
            8,
            $targetStatus,
            'status_matrix_transition'
        );
        $repeated = $this->inventory->applyStatusTransition(
            $result->fresh(),
            $targetStatus,
            $targetStatus,
            'status_matrix_repeated'
        );

        $this->assertSame(1, (int) $repeated->inventory_reservation_version);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());

        if ($expectedBehavior === 'commit') {
            $this->assertSame(1, $this->stock($productId));
            $this->assertTrue($this->inventory->isActive($repeated));
            $this->assertNotNull($repeated->inventory_committed_at);
            $this->assertNull($repeated->inventory_reservation_expires_at);
            $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());

            return;
        }

        if ($expectedBehavior === 'release') {
            $this->assertSame(2, $this->stock($productId));
            $this->assertFalse($this->inventory->isActive($repeated));
            $this->assertNull($repeated->inventory_committed_at);
            $this->assertNotNull($repeated->inventory_released_at);
            $this->assertSame(1, DB::table('inventory_movements')->where('action', 'release')->count());

            return;
        }

        $this->assertSame(1, $this->stock($productId));
        $this->assertTrue($this->inventory->isActive($repeated));
        $this->assertNull($repeated->inventory_committed_at);
        $this->assertNotNull($repeated->inventory_reservation_expires_at);
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    /**
     * @dataProvider passiveStatusProvider
     */
    public function test_passive_statuses_release_an_uncommitted_reservation_only_once(int $targetStatus): void
    {
        $productId = $this->createProduct(2, 'Privremeno rezervirana knjiga');
        $order = $this->createOrderWithItem($productId, 1, 8);
        $reserved = $this->inventory->reserve($order, now()->addMinutes(30), 'checkout_reservation');

        $reserved->update(['order_status_id' => $targetStatus]);
        $first = $this->inventory->applyStatusTransition(
            $reserved->fresh(),
            8,
            $targetStatus,
            'passive_status'
        );
        $second = $this->inventory->applyStatusTransition(
            $first->fresh(),
            $targetStatus,
            $targetStatus,
            'passive_status_repeated'
        );

        $this->assertSame(2, $this->stock($productId));
        $this->assertFalse($this->inventory->isActive($second));
        $this->assertNull($second->inventory_committed_at);
        $this->assertNotNull($second->inventory_released_at);
        $this->assertNull($second->inventory_reservation_expires_at);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    /**
     * @dataProvider passiveStatusProvider
     */
    public function test_passive_statuses_preserve_an_existing_committed_allocation(int $targetStatus): void
    {
        $productId = $this->createProduct(2, 'Već alocirana knjiga');
        $order = $this->createOrderWithItem($productId, 1, 3);
        $committed = $this->inventory->commit($order, 'existing_committed_order');

        $committed->update(['order_status_id' => $targetStatus]);
        $first = $this->inventory->applyStatusTransition(
            $committed->fresh(),
            3,
            $targetStatus,
            'passive_status'
        );
        $second = $this->inventory->applyStatusTransition(
            $first->fresh(),
            $targetStatus,
            $targetStatus,
            'passive_status_repeated'
        );

        $this->assertSame(1, $this->stock($productId));
        $this->assertTrue($this->inventory->isActive($second));
        $this->assertNotNull($second->inventory_committed_at);
        $this->assertNull($second->inventory_released_at);
        $this->assertSame(1, DB::table('inventory_movements')->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    /**
     * @dataProvider passiveStatusProvider
     */
    public function test_passive_statuses_do_not_allocate_or_release_a_legacy_order(int $targetStatus): void
    {
        $productId = $this->createProduct(2, 'Legacy knjiga');
        $order = $this->createOrderWithItem($productId, 1, 3);

        $order->update(['order_status_id' => $targetStatus]);
        $first = $this->inventory->applyStatusTransition($order->fresh(), 3, $targetStatus, 'legacy_passive');
        $second = $this->inventory->applyStatusTransition(
            $first->fresh(),
            $targetStatus,
            $targetStatus,
            'legacy_passive_repeated'
        );

        $this->assertSame(2, $this->stock($productId));
        $this->assertFalse($this->inventory->isActive($second));
        $this->assertNull($second->inventory_reserved_at);
        $this->assertNull($second->inventory_released_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    /**
     * @dataProvider legacyHoldingTransitionProvider
     */
    public function test_legacy_holding_to_holding_transition_never_invents_a_ledger_entry(
        int $previousStatus,
        int $targetStatus
    ): void {
        $productId = $this->createProduct(2, 'Legacy aktivna knjiga');
        $order = $this->createOrderWithItem($productId, 1, $previousStatus);

        $order->update(['order_status_id' => $targetStatus]);
        $first = $this->inventory->applyStatusTransition(
            $order->fresh(),
            $previousStatus,
            $targetStatus,
            sprintf('legacy_status:%d_to_%d', $previousStatus, $targetStatus)
        );
        $second = $this->inventory->applyStatusTransition(
            $first->fresh(),
            $targetStatus,
            $targetStatus,
            'legacy_status_repeated'
        );

        $this->assertSame(2, $this->stock($productId));
        $this->assertNull($second->inventory_reserved_at);
        $this->assertNull($second->inventory_committed_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    /**
     * @dataProvider reactivationSourceProvider
     */
    public function test_non_admin_reactivation_does_not_allocate_stock(
        int $previousStatus,
        string $reason
    ): void {
        $productId = $this->createProduct(1, 'Neautorizirana reaktivacija');
        $order = $this->createOrderWithItem($productId, 1, $previousStatus);

        $order->update(['order_status_id' => 3]);
        $result = $this->inventory->applyStatusTransition(
            $order->fresh(),
            $previousStatus,
            3,
            $reason
        );

        $this->assertSame(1, $this->stock($productId));
        $this->assertFalse($this->inventory->isActive($result));
        $this->assertNull($result->inventory_committed_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    public function test_provider_return_cannot_reactivate_reservation_released_by_call_when_found(): void
    {
        $productId = $this->createProduct(2, 'Rezervacija prenesena u status 13');
        $order = $this->createOrderWithItem($productId, 1, 8);
        $reserved = $this->inventory->reserve($order, now()->addMinutes(30), 'checkout_reservation');

        $reserved->update(['order_status_id' => 13]);
        $callWhenFound = $this->inventory->applyStatusTransition(
            $reserved->fresh(),
            8,
            13,
            'admin_status:8_to_13'
        );

        $callWhenFound->update(['order_status_id' => 3]);
        $result = $this->inventory->applyStatusTransition(
            $callWhenFound->fresh(),
            13,
            3,
            'payment_return:wspay'
        );

        $this->assertSame(2, $this->stock($productId));
        $this->assertFalse($this->inventory->isActive($result));
        $this->assertNull($result->inventory_committed_at);
        $this->assertNotNull($result->inventory_released_at);
        $this->assertNull($result->inventory_reservation_expires_at);
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'reserve')->count());
        $this->assertSame(1, DB::table('inventory_movements')->where('action', 'release')->count());
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'commit')->count());
    }

    /**
     * @dataProvider explicitAdminReactivationProvider
     */
    public function test_explicit_admin_reactivation_allocates_available_stock_once(
        int $previousStatus,
        int $targetStatus
    ): void {
        $productId = $this->createProduct(1, 'Ručno reaktivirana knjiga');
        $order = $this->createOrderWithItem($productId, 1, $previousStatus);

        $order->update(['order_status_id' => $targetStatus]);
        $first = $this->inventory->applyStatusTransition(
            $order->fresh(),
            $previousStatus,
            $targetStatus,
            sprintf('admin_status:%d_to_%d', $previousStatus, $targetStatus)
        );
        $second = $this->inventory->applyStatusTransition(
            $first->fresh(),
            $targetStatus,
            $targetStatus,
            'admin_status:repeat'
        );

        $this->assertSame(0, $this->stock($productId));
        $this->assertTrue($this->inventory->isActive($second));
        $this->assertNotNull($second->inventory_committed_at);
        $this->assertSame(1, (int) $second->inventory_reservation_version);
        $this->assertSame(1, DB::table('inventory_movements')->count());
    }

    public function test_explicit_admin_reactivation_rolls_back_when_stock_is_unavailable(): void
    {
        $productId = $this->createProduct(0, 'Nedostupna knjiga');
        $order = $this->createOrderWithItem($productId, 1, 5);

        try {
            DB::transaction(function () use ($order) {
                $order->update(['order_status_id' => 3]);
                $this->inventory->applyStatusTransition(
                    $order->fresh(),
                    5,
                    3,
                    'admin_status:5_to_3'
                );
            });
            $this->fail('Admin reaktivacija mora pasti kada zaliha nije dostupna.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame($productId, $exception->productId());
        }

        $fresh = $order->fresh();
        $this->assertSame(5, (int) $fresh->order_status_id);
        $this->assertSame(0, $this->stock($productId));
        $this->assertNull($fresh->inventory_reserved_at);
        $this->assertSame(0, DB::table('inventory_movements')->count());
    }

    public function test_item_changes_adjust_stock_by_the_net_delta(): void
    {
        $productId = $this->createProduct(5);
        $order = $this->createOrderWithItem($productId, 2);
        $reservation = $this->inventory->reserve($order, now()->addMinutes(30));

        $this->setItemQuantity($order, $productId, 3);
        $increased = $this->inventory->reserve($reservation, now()->addMinutes(30));

        $this->assertSame(2, $this->stock($productId));
        $this->assertSame(2, (int) $increased->inventory_reservation_version);

        $this->setItemQuantity($order, $productId, 1);
        $decreased = $this->inventory->reserve($increased, now()->addMinutes(30));

        $this->assertSame(4, $this->stock($productId));
        $this->assertSame(3, (int) $decreased->inventory_reservation_version);
        $this->assertSame(1, (int) DB::table('inventory_movements')
            ->where('order_id', $order->id)
            ->where('reservation_version', 3)
            ->where('action', 'reserve')
            ->value('quantity'));
    }

    public function test_failed_item_change_rolls_back_every_stock_and_reservation_change(): void
    {
        $firstProductId = $this->createProduct(5, 'Prva knjiga');
        $unavailableProductId = $this->createProduct(1, 'Druga knjiga');
        $order = $this->createOrderWithItem($firstProductId, 3);
        $reservation = $this->inventory->reserve($order, now()->addMinutes(30));

        $this->setItemQuantity($order, $firstProductId, 4);
        $this->addOrderItem($order, $unavailableProductId, 2);

        try {
            $this->inventory->reserve($reservation, now()->addMinutes(30));
            $this->fail('Cijela izmjena rezervacije mora pasti ako jedan proizvod nema dovoljno zalihe.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame($unavailableProductId, $exception->productId());
        }

        $freshOrder = $order->fresh();
        $this->assertSame(2, $this->stock($firstProductId));
        $this->assertSame(1, $this->stock($unavailableProductId));
        $this->assertSame(1, (int) $freshOrder->inventory_reservation_version);
        $this->assertNull($freshOrder->inventory_released_at);
        $this->assertSame(1, DB::table('inventory_movements')->count());
        $this->assertFalse($this->inventory->reservationMatchesOrder($freshOrder));
    }

    public function test_expiry_rechecks_candidates_and_does_not_release_committed_or_renewed_reservations(): void
    {
        $committedProductId = $this->createProduct(1, 'Upravo plaćena knjiga');
        $renewedProductId = $this->createProduct(1, 'Obnovljena rezervacija');
        $committedOrder = $this->createOrderWithItem($committedProductId, 1, 8);
        $renewedOrder = $this->createOrderWithItem($renewedProductId, 1, 8);

        $this->inventory->reserve($committedOrder, now()->subMinute());
        $this->inventory->reserve($renewedOrder, now()->subMinute());

        $changedAfterCandidateQuery = false;
        DB::listen(function (QueryExecuted $query) use (
            &$changedAfterCandidateQuery,
            $committedOrder,
            $renewedOrder
        ): void {
            if ($changedAfterCandidateQuery
                || stripos($query->sql, 'select') !== 0
                || stripos($query->sql, 'from "orders"') === false
                || stripos($query->sql, '"inventory_reservation_expires_at"') === false) {
                return;
            }

            $changedAfterCandidateQuery = true;
            DB::table('orders')->where('id', $committedOrder->id)->update([
                'inventory_committed_at' => now(),
                'inventory_reservation_expires_at' => null,
            ]);
            DB::table('orders')->where('id', $renewedOrder->id)->update([
                'inventory_reservation_expires_at' => now()->addMinutes(30),
            ]);
        });

        $released = $this->inventory->expireReservations();

        $this->assertTrue($changedAfterCandidateQuery, 'Test mora izmijeniti rezervacije nakon odabira kandidata.');
        $this->assertSame(0, $released);
        $this->assertSame(0, $this->stock($committedProductId));
        $this->assertSame(0, $this->stock($renewedProductId));
        $this->assertNull($committedOrder->fresh()->inventory_released_at);
        $this->assertNull($renewedOrder->fresh()->inventory_released_at);
        $this->assertSame(0, DB::table('inventory_movements')->where('action', 'release')->count());
    }

    public function statusTargetMatrixProvider(): array
    {
        return [
            '1 Novo' => [1, 'commit'],
            '2 Čeka uplatu' => [2, 'commit'],
            '3 Plaćeno' => [3, 'commit'],
            '4 Poslano' => [4, 'commit'],
            '5 Otkazano' => [5, 'release'],
            '6 Vraćeno' => [6, 'release'],
            '7 Odbijeno' => [7, 'release'],
            '8 Nedovršeno' => [8, 'preserve'],
            '9 Završeno' => [9, 'commit'],
            '10 Spremno za preuzimanje' => [10, 'commit'],
            '11 Nije poslano' => [11, 'commit'],
            '12 Izvršiti povrat' => [12, 'release'],
            '13 Zvati kad nađemo' => [13, 'release'],
            '14 Crna lista' => [14, 'release'],
        ];
    }

    public function passiveStatusProvider(): array
    {
        return [
            '6 Vraćeno' => [6],
            '12 Izvršiti povrat' => [12],
            '13 Zvati kad nađemo' => [13],
            '14 Crna lista' => [14],
        ];
    }

    public function legacyHoldingTransitionProvider(): array
    {
        $statuses = [1, 2, 3, 4, 9, 10, 11];
        $cases = [];

        foreach ($statuses as $previousStatus) {
            foreach ($statuses as $targetStatus) {
                $cases[$previousStatus . ' -> ' . $targetStatus] = [$previousStatus, $targetStatus];
            }
        }

        return $cases;
    }

    public function reactivationSourceProvider(): array
    {
        return [
            'payment return canceled -> paid' => [5, 'payment_return:wspay'],
            'provider callback declined -> paid' => [7, 'wspay_callback'],
            'provider callback manual resolution -> paid' => [13, 'paypal_ipn_completed'],
        ];
    }

    public function explicitAdminReactivationProvider(): array
    {
        $holdingStatuses = [1, 2, 3, 4, 9, 10, 11];
        $cases = [];

        foreach ([5, 7, 13] as $previousStatus) {
            foreach ($holdingStatuses as $targetStatus) {
                $cases[$previousStatus . ' -> ' . $targetStatus] = [$previousStatus, $targetStatus];
            }
        }

        return $cases;
    }

    private function createSchema(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedInteger('quantity')->default(0);
            $table->boolean('decrease')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('order_status_id');
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        require_once database_path('migrations/2026_09_03_120000_add_inventory_tracking_to_orders.php');
        (new \AddInventoryTrackingToOrders())->up();
    }

    private function createProduct(int $quantity, string $name = 'Testna knjiga'): int
    {
        return (int) DB::table('products')->insertGetId([
            'name' => $name,
            'quantity' => $quantity,
            'decrease' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrderWithItem(int $productId, int $quantity, int $status = 8): Order
    {
        $orderId = (int) DB::table('orders')->insertGetId([
            'order_status_id' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $order = Order::query()->findOrFail($orderId);
        $this->addOrderItem($order, $productId, $quantity);

        return $order;
    }

    private function addOrderItem(Order $order, int $productId, int $quantity): void
    {
        DB::table('order_products')->insert([
            'order_id' => $order->id,
            'product_id' => $productId,
            'name' => (string) DB::table('products')->where('id', $productId)->value('name'),
            'quantity' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setItemQuantity(Order $order, int $productId, int $quantity): void
    {
        DB::table('order_products')
            ->where('order_id', $order->id)
            ->where('product_id', $productId)
            ->update([
                'quantity' => $quantity,
                'updated_at' => now(),
            ]);
    }

    private function stock(int $productId): int
    {
        return (int) DB::table('products')->where('id', $productId)->value('quantity');
    }
}
