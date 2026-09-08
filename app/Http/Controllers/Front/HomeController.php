<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Helper;
use App\Helpers\Njuskalo;
use App\Helpers\Recaptcha;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Mail\ContactFormMessage;
use App\Models\Back\Marketing\Review;
use App\Models\Back\Marketing\Wishlist;
use App\Models\Back\Orders\Order;
use App\Models\ContractTermination;
use App\Models\Front\Blog;
use App\Models\Front\Faq;
use App\Models\Front\Page;
use App\Models\Sitemap;
use App\Services\ContractTerminationNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        return view('front.blog', compact('blog'));
    }


    /**
     * @param Faq $faq
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function faq()
    {
        $faq = Faq::where('status', 1)->get();
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

    public function contractTermination()
    {
        return view('front.contract-termination');
    }

    public function sendContractTermination(
        Request $request,
        ContractTerminationNotificationService $notifications
    )
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:190'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:80'],
            'order_number' => ['required', 'string', 'max:80'],
            'order_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'items' => ['required', 'string', 'max:3000'],
            'iban' => ['nullable', 'string', 'max:50'],
            'statement' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ], [
            'statement.accepted' => 'Za slanje je potrebno potvrditi izjavu o raskidu ugovora.',
            'website.max' => 'Obrazac nije moguće poslati.',
        ]);

        $siteKey = config('services.recaptcha.sitekey');
        $secretKey = config('services.recaptcha.secret');
        if ($siteKey && $secretKey) {
            $recaptcha = (new Recaptcha())->check($request->toArray());
            if (! $recaptcha || ! $recaptcha->ok()) {
                return back()->withErrors(['recaptcha' => 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.'])->withInput();
            }
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
        $siteKey = config('services.recaptcha.sitekey');
        $secretKey = config('services.recaptcha.secret');

        if ($siteKey && $secretKey) {
            $recaptcha = (new Recaptcha())->check($request->toArray());

            if (! $recaptcha || ! $recaptcha->ok()) {
                return back()->withErrors(['error' => 'ReCaptcha Error! Kontaktirajte administratora!'])
                    ->withInput();
            }
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

        $siteKey = config('services.recaptcha.sitekey');
        $secretKey = config('services.recaptcha.secret');

        if ($siteKey && $secretKey) {
            $recaptcha = (new Recaptcha())->check($request->toArray());

            if (! $recaptcha || ! $recaptcha->ok()) {
                return back()->withErrors(['error' => 'ReCaptcha Error! Kontaktirajte administratora!'])
                    ->withInput();
            }
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
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'message' => 'required',
        ]);

        // Recaptcha
        $recaptcha = (new Recaptcha())->check($request->toArray());

        if ( ! $recaptcha->ok()) {
            return back()->withErrors(['error' => 'ReCaptcha Error! Kontaktirajte administratora!']);
        }

        $message = $request->toArray();

        dispatch(function () use ($message) {
            Mail::to(config('mail.admin'))->send(new ContactFormMessage($message));
        })->afterResponse();

        return back()->with(['success' => 'Vaša poruka je uspješno poslana.! Odgovoriti ćemo vam uskoro.']);
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function imageCache(Request $request)
    {
        $src = $request->input('src');

        $cacheimage = Image::cache(function($image) use ($src) {
            $image->make($src);
        }, config('imagecache.lifetime'));

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
            return asset('media/img/knjiga-detalj.jpg');
        }

        $cacheimage = Image::cache(function($image) use ($request) {
            $width = 400;
            $height = 400;

            if ($request->has('size')) {
                if (strpos($request->input('size'), 'x') !== false) {
                    $size = explode('x', $request->input('size'));
                    $width = $size[0];
                    $height = $size[1];
                }
            } else {
                $width = $request->input('size');
            }

            $image->make($request->input('src'))->resize($width, $height);

        }, config('imagecache.lifetime'));

        return Image::make($cacheimage)->response();
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
            ])->header('Content-Type', 'text/xml');
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
        ])->header('Content-Type', 'text/xml');
    }


    /**
     * @return \Illuminate\Http\Response
     */
    public function sitemapImageXML()
    {
        $sm = new Sitemap('images');

        return response()->view('front.layouts.partials.sitemap-image', [
            'items' => $sm->getResponse()
        ])->header('Content-Type', 'text/xml');
    }


    public function njuskaloXML(Request $request)
    {
        $njuskalo = new Njuskalo();

        return response()->view('front.layouts.partials.njuskalo', [
            'items' => $njuskalo->getItems()
        ])->header('Content-Type', 'text/xml');
    }

}
