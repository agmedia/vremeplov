<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Wishlist;
use App\Services\WishlistNotificationService;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = in_array($request->input('tab'), ['wishlists', 'top-products', 'statistics'], true)
            ? $request->input('tab')
            : 'wishlists';
        $stock = in_array($request->input('stock'), ['all', 'ready', 'waiting', 'sent'], true)
            ? $request->input('stock')
            : 'all';

        $query = Wishlist::query()
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url', 'quantity', 'status');
            }]);

        if ($search = $request->input('search')) {
            $query->where(function ($match) use ($search) {
                $match->where('email', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($product) use ($search) {
                        $product->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        if ($stock === 'ready') {
            $query->readyToSend();
        } elseif ($stock === 'waiting') {
            $query->waitingForStock();
        } elseif ($stock === 'sent') {
            $query->sent();
        }

        $topProducts = Wishlist::query()
            ->select('product_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->with(['product' => function ($q) {
                $q->select('id', 'name', 'sku', 'image', 'url', 'quantity', 'status');
            }])
            ->paginate(20, ['*'], 'top_page');

        $statistics = [
            'total' => Wishlist::query()->count(),
            'ready' => Wishlist::query()->readyToSend()->count(),
            'waiting' => Wishlist::query()->waitingForStock()->count(),
            'sent' => Wishlist::query()->sent()->count(),
            'customers' => Wishlist::query()->distinct()->count('email'),
        ];

        $wishlists = $query->orderBy('created_at', 'desc')->paginate(20, ['*'], 'wishlists_page');

        return view('back.marketing.wishlist.index', compact(
            'activeTab',
            'stock',
            'wishlists',
            'topProducts',
            'statistics'
        ));
    }

    public function send(Wishlist $wishlist, WishlistNotificationService $service)
    {
        $result = $service->send($wishlist);

        if ($result['status'] === WishlistNotificationService::STATUS_SENT) {
            return redirect()->route('wishlists', ['tab' => 'wishlists', 'stock' => 'ready'])
                ->with('success', 'Wishlist obavijest je poslana.');
        }

        $flash = $result['status'] === WishlistNotificationService::STATUS_FAILED ? 'error' : 'warning';

        return redirect()->route('wishlists', ['tab' => 'wishlists', 'stock' => 'ready'])
            ->with($flash, $result['message']);
    }

    public function sendSelected(Request $request, WishlistNotificationService $service)
    {
        $validated = $request->validate([
            'wishlist_ids' => ['required', 'array', 'min:1', 'max:100'],
            'wishlist_ids.*' => ['required', 'integer', 'distinct', 'exists:wishlist,id'],
        ], [
            'wishlist_ids.required' => 'Odaberite barem jednu wishlist prijavu.',
            'wishlist_ids.max' => 'Odjednom je moguće poslati najviše 100 obavijesti.',
        ]);

        $entries = Wishlist::query()
            ->whereIn('id', $validated['wishlist_ids'])
            ->orderBy('id')
            ->get();
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $result = $service->send($entry);

            if ($result['status'] === WishlistNotificationService::STATUS_SENT) {
                $sent++;
            } elseif ($result['status'] === WishlistNotificationService::STATUS_FAILED) {
                $failed++;
            } else {
                $skipped++;
            }
        }

        $message = "Poslano: {$sent}. Preskočeno: {$skipped}. Neuspjelo: {$failed}.";
        $flash = $failed > 0 ? 'error' : ($sent > 0 ? 'success' : 'warning');

        return redirect()->route('wishlists', ['tab' => 'wishlists', 'stock' => 'ready'])
            ->with($flash, $message);
    }
}
