<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use App\Services\Inventory\OrderInventoryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryAuditCommandTest extends TestCase
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
            'database.default' => 'inventory_audit_testing',
            'database.connections.inventory_audit_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'settings.order.status.unfinished' => 8,
            'settings.order.turnover_statuses' => [1, 2, 3, 4, 9, 10, 11],
        ]);

        DB::purge('inventory_audit_testing');
        Carbon::setTestNow(Carbon::parse('2026-09-03 14:00:00'));
        $this->createSchema();
        $this->inventory = app(OrderInventoryService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('inventory_audit_testing');
        config(['database.default' => $this->originalConnection]);

        parent::tearDown();
    }

    public function test_legacy_holding_order_is_only_a_warning_and_audit_does_not_change_inventory(): void
    {
        $productId = $this->createProduct(3);
        $managedOrder = $this->createOrderWithItem($productId, 1, 8);
        $this->inventory->reserve($managedOrder, now()->addMinutes(30));
        $legacyOrder = $this->createOrderWithItem($productId, 1, 3);

        $before = $this->inventorySnapshot();

        $exitCode = Artisan::call('inventory:audit');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            sprintf('[UPOZORENJE] Narudžba #%d:', $legacyOrder->id),
            $output
        );
        $this->assertStringContainsString('Managed greške: 0 zapisa na 0 narudžbi.', $output);
        $this->assertStringContainsString('Legacy upozorenja: 1.', $output);
        $this->assertStringContainsString('Zaliha nije mijenjana.', $output);
        $this->assertSame($before, $this->inventorySnapshot());
    }

    public function test_managed_errors_return_failure_and_report_each_required_category(): void
    {
        $productId = $this->createProduct(10);

        $allocationError = $this->createOrderWithItem($productId, 1, 8);
        DB::table('orders')->where('id', $allocationError->id)->update([
            'inventory_allocation_error' => 'Plaćeno, ali nema zalihe.',
        ]);

        $mismatch = $this->createOrderWithItem($productId, 1, 8);
        $this->inventory->reserve($mismatch, now()->addMinutes(30));
        DB::table('order_products')->where('order_id', $mismatch->id)->update([
            'quantity' => 2,
            'updated_at' => now(),
        ]);

        $missingMovement = $this->createOrderWithItem($productId, 1, 8);
        DB::table('orders')->where('id', $missingMovement->id)->update([
            'inventory_reserved_at' => now(),
            'inventory_committed_at' => now(),
            'inventory_reservation_version' => 7,
        ]);

        $before = $this->inventorySnapshot();

        $exitCode = Artisan::call('inventory:audit');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            sprintf('[GREŠKA] Narudžba #%d: inventory_allocation_error:', $allocationError->id),
            $output
        );
        $this->assertStringContainsString(
            sprintf(
                '[GREŠKA] Narudžba #%d: Aktivna rezervacija ne odgovara trenutnim stavkama narudžbe.',
                $mismatch->id
            ),
            $output
        );
        $this->assertStringContainsString(
            sprintf(
                '[GREŠKA] Narudžba #%d: Aktivni ili commitani inventory zapis nema reserve movement za verziju 7.',
                $missingMovement->id
            ),
            $output
        );
        $this->assertStringContainsString('Rezultat: pronađene su greške novog inventory sustava.', $output);
        $this->assertSame($before, $this->inventorySnapshot());
    }

    public function test_order_filter_and_limit_restrict_the_report(): void
    {
        $productId = $this->createProduct(5);
        $olderOrder = $this->createOrderWithItem($productId, 1, 8);
        $newerOrder = $this->createOrderWithItem($productId, 1, 8);

        DB::table('orders')->where('id', $olderOrder->id)->update([
            'inventory_allocation_error' => 'Starija greška',
        ]);
        DB::table('orders')->where('id', $newerOrder->id)->update([
            'inventory_allocation_error' => 'Novija greška',
        ]);

        $this->assertSame(1, Artisan::call('inventory:audit', ['--limit' => 1]));
        $limitedOutput = Artisan::output();
        $this->assertStringContainsString(sprintf('Narudžba #%d:', $newerOrder->id), $limitedOutput);
        $this->assertStringNotContainsString(sprintf('Narudžba #%d:', $olderOrder->id), $limitedOutput);

        $this->assertSame(1, Artisan::call('inventory:audit', ['--order' => $olderOrder->id]));
        $filteredOutput = Artisan::output();
        $this->assertStringContainsString(sprintf('Narudžba #%d:', $olderOrder->id), $filteredOutput);
        $this->assertStringNotContainsString(sprintf('Narudžba #%d:', $newerOrder->id), $filteredOutput);
    }

    public function test_invalid_options_return_usage_error(): void
    {
        $this->assertSame(2, Artisan::call('inventory:audit', ['--order' => 'abc']));
        $this->assertStringContainsString('Opcija --order mora biti pozitivan cijeli broj.', Artisan::output());

        $this->assertSame(2, Artisan::call('inventory:audit', ['--limit' => 0]));
        $this->assertStringContainsString('Opcija --limit mora biti pozitivan cijeli broj.', Artisan::output());
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
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_committed_at')->nullable();
            $table->timestamp('inventory_released_at')->nullable();
            $table->timestamp('inventory_reservation_expires_at')->nullable();
            $table->unsignedInteger('inventory_reservation_version')->default(0);
            $table->string('inventory_allocation_error', 500)->nullable();
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

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('reservation_version');
            $table->string('action', 16);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('stock_before')->nullable();
            $table->unsignedInteger('stock_after')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    private function createProduct(int $quantity): int
    {
        return (int) DB::table('products')->insertGetId([
            'name' => 'Testna knjiga',
            'quantity' => $quantity,
            'decrease' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrderWithItem(int $productId, int $quantity, int $status): Order
    {
        $orderId = (int) DB::table('orders')->insertGetId([
            'order_status_id' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => 'Testna knjiga',
            'quantity' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::query()->findOrFail($orderId);
    }

    private function inventorySnapshot(): array
    {
        return [
            'products' => DB::table('products')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
            'orders' => DB::table('orders')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
            'movements' => DB::table('inventory_movements')->orderBy('id')->get()->map(function ($row) {
                return (array) $row;
            })->all(),
        ];
    }
}
