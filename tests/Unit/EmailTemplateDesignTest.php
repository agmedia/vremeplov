<?php

namespace Tests\Unit;

use App\Mail\AbandonedCartReminderMail;
use App\Mail\ContactFormMessage;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Mail\OrderStatusChanged;
use App\Mail\ProductReviewRequestMail;
use App\Mail\StatusCanceled;
use App\Mail\StatusPaid;
use App\Mail\StatusReady;
use App\Mail\StatusSend;
use App\Mail\WishlistArrived;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Back\Orders\OrderTotal;
use App\Models\ProductReviewInvitation;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Tests\TestCase;

class EmailTemplateDesignTest extends TestCase
{
    public function test_all_order_email_designs_render_with_the_vremeplov_brand(): void
    {
        $order = $this->sampleOrder();
        $mailables = [
            new OrderSent($order),
            new OrderReceived($order),
            new OrderStatusChanged($order, (object) ['title' => 'U obradi'], 'Testna napomena'),
            new StatusPaid($order),
            new StatusReady($order),
            new StatusSend($order),
            new StatusCanceled($order),
            new AbandonedCartReminderMail($order, 'https://example.test/siguran-nastavak', 1),
        ];

        foreach ($mailables as $mailable) {
            $html = $mailable->render();

            $this->assertStringContainsString('Antikvarijat Vremeplov', $html);
            $this->assertStringContainsString('vremeplov-logo.png', $html);
            $this->assertStringContainsString('Knjiga iz snimke narudžbe', $html);
            $this->assertStringNotContainsString('font-size:12px">MOLIMO', $html);
        }
    }

    public function test_non_order_email_designs_render_and_contact_message_is_escaped(): void
    {
        $wishlist = (new WishlistArrived([
            'name' => 'Rijetka knjiga',
            'url' => '/knjige/rijetka-knjiga',
            'image' => 'media/img/products/test.webp',
        ]))->render();

        $contact = (new ContactFormMessage([
            'name' => 'Ivan Primjer',
            'email' => 'ivan@example.test',
            'phone' => '091 000 0000',
            'message' => '<script>alert("x")</script>',
        ]))->render();

        $password = view('emails.forget-password', [
            'token' => 'test-token',
            'resetUrl' => 'https://example.test/reset-password/test-token',
            'user' => (object) ['name' => 'Ana'],
        ])->render();

        $report = view('emails.akmk-send-report')->render();

        $this->assertStringContainsString('Rijetka knjiga', $wishlist);
        $this->assertStringContainsString('www.antikvarijat-vremeplov.hr/media/img/products/test.webp', $wishlist);
        $this->assertStringNotContainsString('<script>alert', $contact);
        $this->assertStringContainsString('&lt;script&gt;', $contact);
        $this->assertStringContainsString('Postavi novu lozinku', $password);
        $this->assertStringContainsString('Dnevni izvještaj je spreman', $report);
    }

    public function test_password_reset_notification_uses_the_branded_template_and_secure_email_parameter(): void
    {
        $user = new User([
            'name' => 'Ana',
            'email' => 'ana@example.test',
        ]);
        $message = (new ResetPasswordNotification('secure-test-token'))->toMail($user);
        $html = view($message->view, $message->viewData)->render();

        $this->assertSame('emails.forget-password', $message->view);
        $this->assertSame('Resetiranje lozinke — Antikvarijat Vremeplov', $message->subject);
        $this->assertStringContainsString('secure-test-token', $html);
        $this->assertStringContainsString('email=ana%40example.test', $html);
        $this->assertStringContainsString('Postavi novu lozinku', $html);
    }

    public function test_product_review_request_uses_the_vremeplov_design_and_private_signed_link(): void
    {
        $order = $this->sampleOrder();
        $invitation = new ProductReviewInvitation([
            'order_id' => $order->id,
            'recipient_name' => 'Ana Anić',
            'recipient_email' => 'ana@example.test',
            'recipient_email_normalized' => 'ana@example.test',
            'eligible_at' => now(),
        ]);
        $invitation->setRelation('order', $order);

        $html = (new ProductReviewRequestMail(
            $invitation,
            'https://example.test/zahtjev-za-recenziju/token?expires=1&signature=siguran-potpis'
        ))->render();

        $this->assertStringContainsString('Antikvarijat Vremeplov', $html);
        $this->assertStringContainsString('Knjiga iz snimke narudžbe', $html);
        $this->assertStringContainsString('Podijelite dojam', $html);
        $this->assertStringContainsString('siguran-potpis', $html);
        $this->assertStringNotContainsString('ana@example.test', $html);
    }

    private function sampleOrder(): Order
    {
        $order = new Order([
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'payment_address' => 'Ilica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_state' => 'Croatia',
            'payment_email' => 'ana@example.test',
            'payment_phone' => '0911111111',
            'payment_code' => 'wspay',
            'shipping_method' => 'GLS',
            'shipping_state' => 'Croatia',
            'tracking_code' => 'GLS-TRACK-1',
            'shipping_tracking_url' => 'https://example.test/track/GLS-TRACK-1',
            'total' => 25.50,
        ]);
        $order->id = 4321;
        $order->created_at = now();

        $product = new OrderProduct([
            'product_id' => 99,
            'name' => 'Knjiga iz snimke narudžbe',
            'quantity' => 1,
            'price' => 25.50,
            'total' => 25.50,
        ]);
        $product->setRelation('product', null);
        $order->setRelation('products', collect([$product]));
        $order->setRelation('totals', collect([
            new OrderTotal(['code' => 'total', 'title' => 'Sveukupno', 'value' => 25.50]),
        ]));

        return $order;
    }
}
