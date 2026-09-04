<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\ProductReviewInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductReviewInvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = $this->resolveInvitation($token);
        $this->ensureOrderIsEligible($invitation->order);

        $invitation->load([
            'order.products.product',
            'reviews:id,invitation_id,order_product_id,status',
        ]);

        $reviewedOrderProductIds = $invitation->reviews
            ->pluck('order_product_id')
            ->map(fn ($id) => (int) $id);

        $items = $invitation->order->products
            ->filter(fn (OrderProduct $item) => $item->product_id > 0 && $item->product)
            ->unique('product_id')
            ->map(function (OrderProduct $item) use ($reviewedOrderProductIds) {
                $item->setAttribute(
                    'review_submitted',
                    $reviewedOrderProductIds->contains((int) $item->id)
                );

                return $item;
            })
            ->values();

        return view('front.reviews.invitation', [
            'invitation' => $invitation,
            'items' => $items,
            'formAction' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $invitation = $this->resolveInvitation($token);
        $this->ensureOrderIsEligible($invitation->order);

        $validated = $request->validate([
            'order_product_id' => [
                'required',
                'integer',
                Rule::exists('order_products', 'id')->where('order_id', $invitation->order_id),
            ],
            'stars' => ['required', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($invitation, $validated) {
            $item = OrderProduct::query()
                ->whereKey($validated['order_product_id'])
                ->where('order_id', $invitation->order_id)
                ->where('product_id', '>', 0)
                ->whereHas('product')
                ->lockForUpdate()
                ->firstOrFail();

            Review::query()->firstOrCreate(
                ['order_product_id' => $item->id],
                [
                    'product_id' => $item->product_id,
                    'order_id' => $invitation->order_id,
                    'invitation_id' => $invitation->id,
                    'user_id' => $invitation->order->user_id ?: 0,
                    'fname' => $invitation->recipient_name,
                    'lname' => '',
                    'email' => $invitation->recipient_email_normalized,
                    'avatar' => 'media/avatar.jpg',
                    'message' => trim($validated['message']),
                    'stars' => $validated['stars'],
                    'sort_order' => 0,
                    'featured' => false,
                    'status' => false,
                    'is_verified_purchase' => true,
                ]
            );

            $remaining = OrderProduct::query()
                ->where('order_id', $invitation->order_id)
                ->where('product_id', '>', 0)
                ->whereHas('product')
                ->whereNotExists(function ($reviews) {
                    $reviews->select(DB::raw(1))
                        ->from('reviews')
                        ->whereColumn('reviews.order_product_id', 'order_products.id');
                })
                ->exists();

            if (! $remaining && ! $invitation->completed_at) {
                $invitation->forceFill(['completed_at' => now()])->save();
            }
        });

        return redirect($request->fullUrl())
            ->with('success', 'Hvala! Recenzija je zaprimljena i bit će objavljena nakon provjere.');
    }

    private function resolveInvitation(string $token): ProductReviewInvitation
    {
        return ProductReviewInvitation::query()
            ->with('order')
            ->where('token_hash', ProductReviewInvitation::hashToken($token))
            ->firstOrFail();
    }

    private function ensureOrderIsEligible(?Order $order): void
    {
        abort_unless(
            $order && in_array((int) $order->order_status_id, Order::reviewEligibleStatusIds(), true),
            410
        );
    }
}
