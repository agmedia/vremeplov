<?php

namespace App\Console\Commands;

use App\Helpers\OrderHelper;
use App\Models\Back\Orders\Order;
use App\Services\Inventory\OrderInventoryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AuditInventory extends Command
{
    protected $signature = 'inventory:audit
                            {--order= : Provjeri samo jednu narudžbu po ID-u}
                            {--limit=200 : Najveći broj rezultata po kategoriji}';

    protected $description = 'Read-only provjera konzistencije rezervacija zalihe i inventory movement zapisa';

    public function handle(OrderInventoryService $inventory): int
    {
        $orderId = $this->validatedOrderId();

        if ($orderId === false) {
            return 2;
        }

        $limit = $this->validatedLimit();

        if ($limit === false) {
            return 2;
        }

        $managedIssues = [];
        $legacyWarnings = [];

        $allocationErrors = $this->orders($orderId, $limit)
            ->whereNotNull('inventory_allocation_error')
            ->where('inventory_allocation_error', '<>', '')
            ->get();

        foreach ($allocationErrors as $order) {
            $message = sprintf(
                'inventory_allocation_error: %s',
                (string) $order->inventory_allocation_error
            );
            $managedIssues[] = [(int) $order->id, $message];
        }

        // Mismatch se ne može pouzdano izraziti samo SQL upitom jer servis
        // uspoređuje agregirane stavke i movemente. Zato pregledavamo sve
        // aktivne kandidate, a --limit ograničava broj prijavljenih rezultata.
        $activeReservations = $this->orders($orderId)
            ->whereNotNull('inventory_reserved_at')
            ->whereNull('inventory_released_at')
            ->get();
        $mismatchCount = 0;

        foreach ($activeReservations as $order) {
            try {
                $matches = ! $inventory->isActive($order) || $inventory->reservationMatchesOrder($order);
                $message = 'Aktivna rezervacija ne odgovara trenutnim stavkama narudžbe.';
            } catch (\Throwable $exception) {
                $matches = false;
                $message = 'Aktivnu rezervaciju nije moguće provjeriti: ' .
                    mb_substr($exception->getMessage(), 0, 400);
            }

            if (! $matches) {
                $managedIssues[] = [
                    (int) $order->id,
                    $message,
                ];
                $mismatchCount++;

                if ($mismatchCount >= $limit) {
                    break;
                }
            }
        }

        $missingReserveMovements = $this->orders($orderId, $limit)
            ->where(function (Builder $query) {
                $query->where(function (Builder $active) {
                    $active->whereNotNull('inventory_reserved_at')
                        ->whereNull('inventory_released_at');
                })->orWhereNotNull('inventory_committed_at');
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_products as inventory_audit_items')
                    ->join(
                        'products as inventory_audit_products',
                        'inventory_audit_products.id',
                        '=',
                        'inventory_audit_items.product_id'
                    )
                    ->whereColumn('inventory_audit_items.order_id', 'orders.id')
                    ->where('inventory_audit_products.decrease', 1);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('inventory_movements as inventory_audit_movements')
                    ->whereColumn('inventory_audit_movements.order_id', 'orders.id')
                    ->whereColumn(
                        'inventory_audit_movements.reservation_version',
                        'orders.inventory_reservation_version'
                    )
                    ->where('inventory_audit_movements.action', 'reserve');
            })
            ->get();

        foreach ($missingReserveMovements as $order) {
            $managedIssues[] = [
                (int) $order->id,
                sprintf(
                    'Aktivni ili commitani inventory zapis nema reserve movement za verziju %d.',
                    (int) $order->inventory_reservation_version
                ),
            ];
        }

        $legacyOrders = $this->orders($orderId, $limit)
            ->whereIn('order_status_id', OrderHelper::turnoverStatuses())
            ->whereNull('inventory_reserved_at')
            ->whereNull('inventory_committed_at')
            ->whereNull('inventory_released_at')
            ->where(function (Builder $query) {
                $query->whereNull('inventory_reservation_version')
                    ->orWhere('inventory_reservation_version', 0);
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('inventory_movements as legacy_inventory_movements')
                    ->whereColumn('legacy_inventory_movements.order_id', 'orders.id');
            })
            ->get();

        foreach ($legacyOrders as $order) {
            $legacyWarnings[] = [
                (int) $order->id,
                sprintf(
                    'Holding status %d bez inventory markera; stari tijek nije moguće automatski dokazati.',
                    (int) $order->order_status_id
                ),
            ];
        }

        $this->renderIssues($managedIssues, $legacyWarnings);

        return $managedIssues ? 1 : 0;
    }

    /**
     * @param int|null $orderId
     */
    private function orders(?int $orderId, ?int $limit = null): Builder
    {
        return Order::query()
            ->when($orderId, function (Builder $query, int $id) {
                $query->where('orders.id', $id);
            })
            ->orderByDesc('orders.id')
            ->when($limit, function (Builder $query, int $maximum) {
                $query->limit($maximum);
            });
    }

    /**
     * @return int|null|false
     */
    private function validatedOrderId()
    {
        $value = $this->option('order');

        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            $this->error('Opcija --order mora biti pozitivan cijeli broj.');

            return false;
        }

        return (int) $value;
    }

    /**
     * @return int|false
     */
    private function validatedLimit()
    {
        $value = $this->option('limit');

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            $this->error('Opcija --limit mora biti pozitivan cijeli broj.');

            return false;
        }

        return (int) $value;
    }

    private function renderIssues(array $managedIssues, array $legacyWarnings): void
    {
        foreach ($managedIssues as [$orderId, $message]) {
            $this->error(sprintf('[GREŠKA] Narudžba #%d: %s', $orderId, $message));
        }

        foreach ($legacyWarnings as [$orderId, $message]) {
            $this->warn(sprintf('[UPOZORENJE] Narudžba #%d: %s', $orderId, $message));
        }

        $managedOrderCount = count(array_unique(array_column($managedIssues, 0)));

        $this->newLine();
        $this->line(sprintf(
            'Managed greške: %d zapisa na %d narudžbi.',
            count($managedIssues),
            $managedOrderCount
        ));
        $this->line(sprintf('Legacy upozorenja: %d.', count($legacyWarnings)));

        if ($managedIssues) {
            $this->error('Rezultat: pronađene su greške novog inventory sustava. Zaliha nije mijenjana.');

            return;
        }

        $this->info('Rezultat: nema grešaka novog inventory sustava. Zaliha nije mijenjana.');
    }
}
