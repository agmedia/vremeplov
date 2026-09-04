<?php

namespace App\Console\Commands;

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
use App\Models\ProductReviewInvitation;
use App\Models\Front\Catalog\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class GenerateEmailPreviews extends Command
{
    protected $signature = 'mail:preview-design';

    protected $description = 'Generira lokalnu galeriju Vremeplov email predložaka bez slanja poruka';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Pregledi se smiju generirati samo u lokalnom ili testnom okruženju.');

            return 1;
        }

        $sourceOrder = Order::query()
            ->whereHas('products.product', function ($query) {
                $query->whereNotNull('image')->where('image', '<>', '');
            })
            ->whereHas('totals')
            ->with(['products.product', 'totals'])
            ->latest('id')
            ->first();

        if (! $sourceOrder) {
            $this->error('Nije pronađena narudžba s artiklima i iznosima za vizualni pregled.');

            return 1;
        }

        $order = $sourceOrder->replicate();
        $order->id = 4321;
        $order->created_at = now();
        $order->payment_fname = 'Ana';
        $order->payment_lname = 'Horvat';
        $order->payment_email = 'ana@example.test';
        $order->payment_phone = '091 234 5678';
        $order->payment_address = 'Ilica 25';
        $order->payment_zip = '10000';
        $order->payment_city = 'Zagreb';
        $order->payment_state = 'Croatia';
        $order->company = null;
        $order->oib = null;
        $order->comment = 'Molim pažljivo zapakirati knjige.';
        $order->payment_code = 'wspay';
        $order->payment_method = 'Kartično plaćanje';
        $order->shipping_method = 'GLS dostava na adresu';
        $order->shipping_carrier = 'gls';
        $order->tracking_code = 'GLS-123456789';
        $order->shipping_tracking_url = 'https://gls-group.com/track/GLS-123456789';
        $order->setRelation('products', $sourceOrder->products);
        $order->setRelation('totals', $sourceOrder->totals);

        $bankOrder = clone $order;
        $bankOrder->payment_code = 'bank';
        $bankOrder->payment_method = 'Virman / internet bankarstvo';

        $product = Product::query()
            ->whereNotNull('image')
            ->where('image', '<>', '')
            ->latest('id')
            ->first();

        if (! $product) {
            $product = [
                'name' => 'Odabrana knjiga iz Vremeplova',
                'url' => '/',
                'image' => null,
            ];
        }

        $user = new User([
            'name' => 'Ana Horvat',
            'email' => 'ana@example.test',
        ]);

        $reviewInvitation = new ProductReviewInvitation([
            'order_id' => $order->id,
            'recipient_name' => 'Ana Horvat',
            'recipient_email' => 'ana@example.test',
            'recipient_email_normalized' => 'ana@example.test',
            'eligible_at' => now(),
        ]);
        $reviewInvitation->setRelation('order', $order);

        $previews = [
            '01-potvrda-narudzbe' => (new OrderSent($bankOrder))->render(),
            '02-nova-narudzba-admin' => (new OrderReceived($order))->render(),
            '03-promjena-statusa' => (new OrderStatusChanged($order, (object) ['title' => 'U obradi'], 'Narudžbu pripremamo za slanje.'))->render(),
            '04-uplata-potvrdena' => (new StatusPaid($order))->render(),
            '05-spremno-za-preuzimanje' => (new StatusReady($order))->render(),
            '06-posiljka-je-na-putu' => (new StatusSend($order))->render(),
            '07-narudzba-otkazana' => (new StatusCanceled($order))->render(),
            '08-nedovrsena-kupnja' => (new AbandonedCartReminderMail($order, URL::temporarySignedRoute('abandoned-cart.restore', now()->addDays(7), ['order' => $order->id]), 1))->render(),
            '09-lista-zelja' => (new WishlistArrived($product))->render(),
            '10-reset-lozinke' => view('emails.forget-password', [
                'token' => Str::random(64),
                'resetUrl' => route('reset.password.get', ['token' => Str::random(64), 'email' => $user->email]),
                'user' => $user,
            ])->render(),
            '11-kontakt-forma-admin' => (new ContactFormMessage([
                'name' => 'Ivan Primjer',
                'email' => 'ivan@example.test',
                'phone' => '098 765 4321',
                'message' => "Dobar dan,\nzanima me imate li još jedan primjerak ovog naslova?\nHvala!",
            ]))->render(),
            '12-dnevni-izvjestaj' => view('emails.akmk-send-report')->render(),
            '13-poziv-za-recenziju' => (new ProductReviewRequestMail(
                $reviewInvitation,
                URL::temporarySignedRoute('product-review-invitations.show', now()->addDays(180), ['token' => Str::random(64)])
            ))->render(),
        ];

        $directory = storage_path('app/email-previews');
        File::ensureDirectoryExists($directory);

        foreach ($previews as $name => $html) {
            File::put($directory . '/' . $name . '.html', $html);
        }

        File::put($directory . '/index.html', $this->galleryHtml(array_keys($previews)));

        $this->info('Generirano ' . count($previews) . ' sigurnih lokalnih pregleda.');
        $this->line($directory . '/index.html');

        return 0;
    }

    private function galleryHtml(array $names): string
    {
        $labels = [
            '01-potvrda-narudzbe' => 'Potvrda narudžbe',
            '02-nova-narudzba-admin' => 'Nova narudžba · administracija',
            '03-promjena-statusa' => 'Promjena statusa',
            '04-uplata-potvrdena' => 'Uplata potvrđena',
            '05-spremno-za-preuzimanje' => 'Spremno za preuzimanje',
            '06-posiljka-je-na-putu' => 'Pošiljka je na putu',
            '07-narudzba-otkazana' => 'Narudžba otkazana',
            '08-nedovrsena-kupnja' => 'Nedovršena kupnja',
            '09-lista-zelja' => 'Lista želja',
            '10-reset-lozinke' => 'Reset lozinke',
            '11-kontakt-forma-admin' => 'Kontakt forma · administracija',
            '12-dnevni-izvjestaj' => 'Dnevni izvještaj',
            '13-poziv-za-recenziju' => 'Poziv za recenziju',
        ];
        $cards = '';
        foreach ($names as $name) {
            $title = $labels[$name] ?? Str::of($name)->after('-')->replace('-', ' ')->title();

            $cards .= '<section><h2>' . e($title) . '</h2><iframe loading="lazy" src="' . e($name) . '.html"></iframe></section>';
        }

        return '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vremeplov — pregled emailova</title><style>body{margin:0;padding:34px;background:#e9e3d8;color:#2d2224;font-family:Arial,sans-serif}header{max-width:1420px;margin:0 auto 28px}h1{margin:0 0 8px;font:normal 34px/1.2 Georgia,serif}p{margin:0;color:#6f6258}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:28px;max-width:1420px;margin:auto}section{overflow:hidden;border:1px solid #d5c9b8;border-radius:12px;background:#f7f3eb;box-shadow:0 5px 20px rgba(45,34,36,.08)}h2{margin:0;padding:15px 18px;background:#2d2224;color:#e3cf9e;font:normal 17px/1.3 Georgia,serif}iframe{display:block;width:100%;height:940px;border:0;background:#f2eee5}@media(max-width:900px){body{padding:18px}.grid{grid-template-columns:1fr}}</style></head><body><header><h1>Vremeplov email sustav</h1><p>' . count($names) . ' lokalno renderiranih predložaka · testni podaci · ništa nije poslano</p></header><main class="grid">' . $cards . '</main></body></html>';
    }
}
