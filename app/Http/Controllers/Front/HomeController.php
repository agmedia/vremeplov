<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Helper;
use App\Helpers\Njuskalo;
use App\Helpers\Recaptcha;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Mail\ContactFormMessage;
use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Marketing\Wishlist;
use App\Models\Back\Orders\Order;
use App\Models\ContractTermination;
use App\Models\Front\Blog;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Faq;
use App\Models\Front\Page;
use App\Models\Sitemap;
use App\Services\ContractTerminationNotificationService;
use App\Services\NewsletterSignupGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\Facades\Image;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $page = Cache::remember('page.homepage', config('cache.life'), function () {
            return Page::where('slug', 'homepage')->first();
        });

        $page->description = Helper::setDescription(isset($page->description) ? $page->description : '');

        return view('front.page', compact('page'));
    }


    /**
     * @param Page $page
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function page(Page $page)
    {
        return view('front.page', compact('page'));
    }


    /**
     * @param Blog $blog
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function blog(Blog $blog)
    {
        if (! $blog->exists) {
            $blogs = Blog::active()->get();

            return view('front.blog', compact('blogs'));
        }

        $relatedProductsWidget = $this->relatedBlogProducts($blog);

        return view('front.blog', compact('blog', 'relatedProductsWidget'));
    }


    private function relatedBlogProducts(Blog $blog): ?array
    {
        $settings = $blog->related_slider;
        $mode = $settings['mode'] ?? null;

        if (! in_array($mode, ['books', 'author'], true)) {
            return null;
        }

        $query = Product::query()
            ->active()
            ->available()
            ->where('group', 'knjige')
            ->cardData();

        $url = route('catalog.route', ['group' => '/knjige']);
        $subtitle = 'Pažljivo odabrani dostupni naslovi iz naše ponude.';

        if ($mode === 'author') {
            $author = Author::active()->find((int) ($settings['author_id'] ?? 0));
            if (! $author) {
                return null;
            }

            $items = $query
                ->where('author_id', $author->id)
                ->orderByDesc('viewed')
                ->orderByDesc('updated_at')
                ->limit(15)
                ->get();
            $title = trim((string) ($settings['title'] ?? '')) ?: 'Još naslova autora ' . $author->title;
            $subtitle = 'Dostupna izdanja autora ' . $author->title . '.';
            $url = route('catalog.route.author', ['author' => $author]);
        } else {
            $ids = collect($settings['product_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->take(15)
                ->values();

            if ($ids->isEmpty()) {
                return null;
            }

            $itemsById = $query->whereIn('id', $ids)->get()->keyBy('id');
            $items = $ids->map(fn ($id) => $itemsById->get($id))->filter()->values();
            $title = trim((string) ($settings['title'] ?? '')) ?: 'Knjige povezane s člankom';
        }

        if ($items->isEmpty()) {
            return null;
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'url' => $url,
            'items' => $items,
            'css' => 'blog-related-products',
            'container' => false,
        ];
    }


    /**
     * @param Faq $faq
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function faq()
    {
        $faq = Faq::where('status', 1)
            ->orderByRaw('CAST(sort_order AS UNSIGNED)')
            ->orderBy('id')
            ->get();

        return view('front.faq', compact('faq'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function contact(Request $request)
    {
        return view('front.contact');
    }

    /**
     * Store newsletter interest locally. Mailchimp synchronization will be
     * added separately once the audience configuration is available.
     */
    public function newsletter(Request $request, NewsletterSignupGuard $signupGuard)
    {
        if ($signupGuard->honeypotIsFilled($request->input('website'))) {
            return $this->newsletterSuccessResponse($request);
        }

        $timing = $signupGuard->timingResult($request->input('newsletter_started_at'));

        if ($timing === NewsletterSignupGuard::TOO_FAST) {
            return $this->newsletterValidationError(
                $request,
                'newsletter_started_at',
                'Pričekajte trenutak pa pokušajte ponovno.'
            );
        }

        if ($timing !== NewsletterSignupGuard::ALLOWED) {
            return $this->newsletterValidationError(
                $request,
                'newsletter_started_at',
                'Obrazac je istekao. Osvježite stranicu pa pokušajte ponovno.'
            );
        }

        $validator = Validator::make($request->only(['email', 'gdpr']), [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'gdpr' => ['required', 'accepted'],
        ], [
            'email.required' => 'Upišite svoju e-mail adresu.',
            'email.email' => 'Upišite ispravnu e-mail adresu.',
            'gdpr.accepted' => 'Za prijavu je potrebna privola za primanje newslettera.',
        ]);

        if ($validator->fails()) {
            return $this->newsletterValidationErrors($request, $validator->errors()->toArray());
        }

        $recaptcha = (new Recaptcha())->check($request->toArray(), 'newsletter');

        if (! $recaptcha->ok()) {
            return $this->newsletterValidationError(
                $request,
                'recaptcha',
                'Sigurnosna provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.'
            );
        }

        NewsletterSubscriber::subscribeFromHomepage(
            Str::lower(trim((string) $request->input('email'))),
            auth()->id()
        );

        return $this->newsletterSuccessResponse($request);
    }

    private function newsletterSuccessResponse(Request $request)
    {
        $message = 'Hvala na prijavi! Novosti će stizati na vašu e-mail adresu.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ]);
        }

        return back()->with('newsletter_success', $message);
    }

    private function newsletterValidationError(Request $request, string $field, string $message)
    {
        return $this->newsletterValidationErrors($request, [$field => [$message]]);
    }

    private function newsletterValidationErrors(Request $request, array $errors)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => collect($errors)->flatten()->first(),
                'errors' => $errors,
            ], 422);
        }

        return back()
            ->withErrors($errors)
            ->withInput($request->except(['_token', 'newsletter_started_at', 'website']));
    }

    public function contractTermination()
    {
        return view('front.contract-termination');
    }

    public function sendContractTermination(
        Request $request,
        ContractTerminationNotificationService $notifications
    )
    {
        $receivedDateRules = ['bail', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        if ($request->filled('order_date')) {
            $receivedDateRules[] = 'after_or_equal:order_date';
        }

        $validated = $request->validate([
            'full_name' => ['bail', 'required', 'string', 'max:150'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['bail', 'nullable', 'string', 'max:50', $this->phoneValidationRule()],
            'address' => ['bail', 'required', 'string', 'max:190'],
            'postal_code' => ['bail', 'required', 'string', 'max:20'],
            'city' => ['bail', 'required', 'string', 'max:100'],
            'country' => ['bail', 'required', 'string', 'max:80'],
            'order_number' => ['bail', 'required', 'string', 'max:80'],
            'order_date' => ['bail', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'received_date' => $receivedDateRules,
            'items' => ['bail', 'required', 'string', 'max:3000'],
            'iban' => ['bail', 'nullable', 'string', 'max:50', $this->ibanValidationRule()],
            'statement' => ['bail', 'accepted'],
            'website' => ['bail', 'nullable', 'string', 'max:0'],
            'recaptcha' => ['bail', 'nullable', 'string', 'max:4096'],
        ], [
            'full_name.required' => 'Upišite ime i prezime.',
            'full_name.string' => 'Ime i prezime moraju biti tekst.',
            'full_name.max' => 'Ime i prezime mogu sadržavati najviše 150 znakova.',
            'email.required' => 'Upišite e-mail adresu.',
            'email.string' => 'E-mail adresa mora biti tekst.',
            'email.email' => 'Upišite ispravnu e-mail adresu.',
            'email.max' => 'E-mail adresa može sadržavati najviše 190 znakova.',
            'phone.string' => 'Broj telefona mora biti tekst.',
            'phone.max' => 'Broj telefona može sadržavati najviše 50 znakova.',
            'address.required' => 'Upišite ulicu i kućni broj.',
            'address.string' => 'Ulica i kućni broj moraju biti tekst.',
            'address.max' => 'Ulica i kućni broj mogu sadržavati najviše 190 znakova.',
            'postal_code.required' => 'Upišite poštanski broj.',
            'postal_code.string' => 'Poštanski broj mora biti tekst.',
            'postal_code.max' => 'Poštanski broj može sadržavati najviše 20 znakova.',
            'city.required' => 'Upišite mjesto.',
            'city.string' => 'Mjesto mora biti tekst.',
            'city.max' => 'Mjesto može sadržavati najviše 100 znakova.',
            'country.required' => 'Upišite državu.',
            'country.string' => 'Država mora biti tekst.',
            'country.max' => 'Država može sadržavati najviše 80 znakova.',
            'order_number.required' => 'Upišite broj narudžbe ili računa.',
            'order_number.string' => 'Broj narudžbe ili računa mora biti tekst.',
            'order_number.max' => 'Broj narudžbe ili računa može sadržavati najviše 80 znakova.',
            'order_date.date_format' => 'Upišite ispravan datum narudžbe.',
            'order_date.before_or_equal' => 'Datum narudžbe ne može biti u budućnosti.',
            'received_date.date_format' => 'Upišite ispravan datum primitka robe.',
            'received_date.before_or_equal' => 'Datum primitka robe ne može biti u budućnosti.',
            'received_date.after_or_equal' => 'Datum primitka robe ne može biti prije datuma narudžbe.',
            'items.required' => 'Navedite artikle na koje se raskid odnosi.',
            'items.string' => 'Popis artikala mora biti tekst.',
            'items.max' => 'Popis artikala može sadržavati najviše 3000 znakova.',
            'iban.string' => 'IBAN mora biti tekst.',
            'iban.max' => 'IBAN može sadržavati najviše 50 znakova.',
            'statement.accepted' => 'Za slanje je potrebno potvrditi izjavu o raskidu ugovora.',
            'website.max' => 'Obrazac nije moguće poslati.',
            'recaptcha.string' => 'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
            'recaptcha.max' => 'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
        ]);

        $recaptcha = (new Recaptcha())->check([
            'recaptcha' => $validated['recaptcha'] ?? null,
        ], 'contract_termination');
        if (! $recaptcha->ok()) {
            return back()
                ->withErrors(['recaptcha' => 'Sigurnosna provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.'])
                ->withInput($request->except(['_token', 'recaptcha', 'website']));
        }

        $submittedAt = now();
        $orderNumber = ltrim(trim((string) $validated['order_number']), '#');
        $order = ctype_digit($orderNumber)
            ? Order::query()->whereKey((int) $orderNumber)
                ->where(function ($query) use ($validated) {
                    $query->where('payment_email', $validated['email'])
                        ->orWhere('shipping_email', $validated['email']);
                })->first()
            : null;

        do {
            $reference = 'JR-' . $submittedAt->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (ContractTermination::query()->where('reference', $reference)->exists());

        $termination = ContractTermination::query()->create([
            'reference' => $reference,
            'user_id' => optional($request->user())->id,
            'order_id' => optional($order)->id,
            'order_number' => trim((string) $validated['order_number']),
            'full_name' => trim((string) $validated['full_name']),
            'email' => strtolower(trim((string) $validated['email'])),
            'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
            'address' => trim((string) $validated['address']),
            'postal_code' => trim((string) $validated['postal_code']),
            'city' => trim((string) $validated['city']),
            'country' => strtoupper(trim((string) $validated['country'])),
            'order_date' => $validated['order_date'] ?? null,
            'received_date' => $validated['received_date'] ?? null,
            'items' => trim((string) $validated['items']),
            'iban' => trim((string) ($validated['iban'] ?? '')) ?: null,
            'statement' => true,
            'status' => ContractTermination::STATUS_RECEIVED,
            'submitted_at' => $submittedAt,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        $notifications->send($termination);
        $termination->refresh();

        $redirect = redirect()->route('contract-termination')->with(
            'success',
            'Izjava o jednostranom raskidu je zaprimljena pod oznakom ' . $reference . '.'
        );

        if (! $termination->consumer_notified_at) {
            $redirect->with('warning', 'Izjava je spremljena, ali potvrdu trenutačno nije bilo moguće poslati e-mailom.');
        }

        return $redirect;
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function sendProductComment(Request $request)
    {
        $recaptcha = (new Recaptcha())->check($request->toArray(), 'review');

        if (! $recaptcha->ok()) {
            return back()->withErrors(['error' => 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.'])
                ->withInput();
        }

        $review = new Review();

        $created_review = $review->validateRequest($request)->create();

        if ($created_review) {
            return back()->with(['success' => 'Komentar je uspješno poslan']);
        }

        return back()->with(['error' => 'Whoops..! Greška kod snimanja komentara']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function wishlist(Request $request)
    {
        $wish = new Wishlist();
        $wish->validateRequest($request);

        $recaptcha = (new Recaptcha())->check($request->toArray(), 'wishlist');

        if (! $recaptcha->ok()) {
            return back()->withErrors(['error' => 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.'])
                ->withInput();
        }

        if ($wish->create()) {
            return back()->with([
                'success' => 'Vaš Email je upisan u listu želja za ovaj artikl..!',
                'analytics_event' => 'add_to_wishlist',
            ]);
        }

        return back()->with(['error' => 'Već ste prijavljeni za obavijest za ovaj artikl ili je došlo do greške.']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function sendContactMessage(Request $request)
    {
        $validated = $request->validate([
            'name' => ['bail', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['bail', 'required', 'string', 'max:30', $this->phoneValidationRule()],
            'message' => ['bail', 'required', 'string', 'min:10', 'max:5000'],
            'recaptcha' => ['bail', 'nullable', 'string', 'max:4096'],
        ], [
            'name.required' => 'Upišite vaše ime.',
            'name.string' => 'Ime mora biti tekst.',
            'name.min' => 'Ime mora sadržavati najmanje 2 znaka.',
            'name.max' => 'Ime može sadržavati najviše 100 znakova.',
            'email.required' => 'Upišite e-mail adresu.',
            'email.string' => 'E-mail adresa mora biti tekst.',
            'email.email' => 'Upišite ispravnu e-mail adresu.',
            'email.max' => 'E-mail adresa može sadržavati najviše 190 znakova.',
            'phone.required' => 'Upišite broj telefona.',
            'phone.string' => 'Broj telefona mora biti tekst.',
            'phone.max' => 'Broj telefona može sadržavati najviše 30 znakova.',
            'message.required' => 'Upišite poruku.',
            'message.string' => 'Poruka mora biti tekst.',
            'message.min' => 'Poruka mora sadržavati najmanje 10 znakova.',
            'message.max' => 'Poruka može sadržavati najviše 5000 znakova.',
            'recaptcha.string' => 'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
            'recaptcha.max' => 'Sigurnosna provjera nije valjana. Osvježite stranicu i pokušajte ponovno.',
        ]);

        // Recaptcha
        $recaptcha = (new Recaptcha())->check([
            'recaptcha' => $validated['recaptcha'] ?? null,
        ], 'contact');

        if ( ! $recaptcha->ok()) {
            return back()
                ->withErrors(['recaptcha' => 'Sigurnosna provjera nije uspjela. Osvježite stranicu i pokušajte ponovno.'])
                ->withInput($request->except(['_token', 'recaptcha']));
        }

        $message = collect($validated)->only(['name', 'email', 'phone', 'message'])->all();

        dispatch(function () use ($message) {
            Mail::to(config('mail.admin'))->send(new ContactFormMessage($message));
        })->afterResponse();

        return back()->with(['success' => 'Vaša poruka uspješno je poslana. Odgovorit ćemo vam uskoro.']);
    }

    private function phoneValidationRule(): \Closure
    {
        return static function ($attribute, $value, $fail) {
            $phone = trim((string) $value);
            $digits = preg_replace('/\D+/', '', $phone) ?? '';

            if (! preg_match('/^\+?[0-9() .\/-]+$/u', $phone)
                || strlen($digits) < 6
                || strlen($digits) > 15) {
                $fail('Upišite ispravan broj telefona (6–15 znamenki).');
            }
        };
    }

    private function ibanValidationRule(): \Closure
    {
        return static function ($attribute, $value, $fail) {
            $iban = strtoupper((string) preg_replace('/\s+/', '', trim((string) $value)));

            if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)
                || ! self::ibanChecksumIsValid($iban)) {
                $fail('Upišite ispravan IBAN.');
            }
        };
    }

    private static function ibanChecksumIsValid(string $iban): bool
    {
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $numeric = ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;

            foreach (str_split($numeric) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function imageCache(Request $request)
    {
        $src = $request->input('src');

        if (! $src) {
            return $this->placeholderImageResponse();
        }

        try {
            $cacheimage = Image::cache(function($image) use ($src) {
                $image->make($src);
            }, config('imagecache.lifetime'));
        } catch (NotReadableException $exception) {
            return $this->placeholderImageResponse();
        }

        return Image::make($cacheimage)->response();
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function thumbCache(Request $request)
    {
        if ( ! $request->has('src')) {
            return $this->placeholderImageResponse();
        }

        try {
            $cacheimage = Image::cache(function ($image) use ($request) {
                $width = 400;
                $height = 400;

                if (preg_match('/^(\d{1,4})x(\d{1,4})$/', (string) $request->input('size'), $size)) {
                    $width = max(1, min(1600, (int) $size[1]));
                    $height = max(1, min(1600, (int) $size[2]));
                }

                $image->make($request->input('src'))->resize($width, $height);
            }, config('imagecache.lifetime'));
        } catch (NotReadableException $exception) {
            return $this->placeholderImageResponse();
        }

        return Image::make($cacheimage)->response();
    }


    /**
     * Return an actual image response so broken sources never produce a 500
     * or a URL string where image bytes are expected.
     */
    private function placeholderImageResponse()
    {
        return Image::make(public_path('media/img/thumb-product.jpg'))->response('jpg');
    }


    /**
     * @param Request $request
     * @param null    $sitemap
     *
     * @return \Illuminate\Http\Response
     */
    public function sitemapXML(Request $request, $sitemap = null)
    {
        if ( ! $sitemap) {
            $items = config('settings.sitemap');

            return response()->view('front.layouts.partials.sitemap-index', [
                'items' => $items
            ])->header('Content-Type', 'application/xml; charset=UTF-8')
                ->setPublic()
                ->setMaxAge(3600);
        }

        if (! in_array($sitemap, config('settings.sitemap'), true)
            && ! in_array($sitemap, array_map(function ($item) {
                return $item . '.xml';
            }, config('settings.sitemap')), true)) {
            abort(404);
        }

        $sm = new Sitemap($sitemap);

        return response()->view('front.layouts.partials.sitemap', [
            'items' => $sm->getSitemap()
        ])->header('Content-Type', 'application/xml; charset=UTF-8')
            ->setPublic()
            ->setMaxAge(3600);
    }


    /**
     * @return \Illuminate\Http\Response
     */
    public function sitemapImageXML()
    {
        $sm = new Sitemap('images');

        return response()->view('front.layouts.partials.sitemap-image', [
            'items' => $sm->getResponse()
        ])->header('Content-Type', 'application/xml; charset=UTF-8')
            ->setPublic()
            ->setMaxAge(3600);
    }


    public function njuskaloXML(Request $request)
    {
        $njuskalo = new Njuskalo();

        return response()->view('front.layouts.partials.njuskalo', [
            'items' => $njuskalo->getItems()
        ])->header('Content-Type', 'text/xml');
    }

}
