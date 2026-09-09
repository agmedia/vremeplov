<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Recaptcha;
use App\Http\Controllers\Controller;
use App\Mail\BookPurchaseMessage;
use App\Models\BookPurchaseRequest;
use App\Services\BookPurchaseContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookPurchaseController extends Controller
{
    private const MAX_TOTAL_UPLOAD_BYTES = 40 * 1024 * 1024;

    public function create(BookPurchaseContentService $contentService)
    {
        $defaults = [
            'full_name' => '',
            'postal_code' => '',
            'email' => '',
            'phone' => '',
        ];

        if (auth()->check()) {
            $user = auth()->user();
            $defaults['full_name'] = (string) ($user->name ?? '');
            $defaults['email'] = (string) ($user->email ?? '');
        }

        return view('front.book-purchase', [
            'content' => $contentService->get(),
            'defaults' => $defaults,
        ]);
    }

    public function store(Request $request, BookPurchaseContentService $contentService)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'postal_code' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:50'],
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:4096'],
            'privacy' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ], [
            'photos.required' => 'Dodajte barem jednu fotografiju knjiga.',
            'photos.min' => 'Dodajte barem jednu fotografiju knjiga.',
            'photos.max' => 'Možete poslati najviše 20 fotografija.',
            'photos.*.mimes' => 'Fotografije moraju biti u JPG, PNG, WEBP, HEIC ili HEIF formatu.',
            'photos.*.max' => 'Pojedina fotografija ne smije biti veća od 4 MB.',
            'privacy.accepted' => 'Za slanje prijave potrebno je prihvatiti obradu podataka.',
            'website.max' => 'Obrazac nije moguće poslati.',
        ]);

        $photos = $request->file('photos', []);
        $totalUploadSize = collect($photos)->sum(function ($photo) {
            return (int) $photo->getSize();
        });

        if ($totalUploadSize > self::MAX_TOTAL_UPLOAD_BYTES) {
            return back()
                ->withErrors(['photos' => 'Ukupna veličina fotografija ne smije biti veća od 40 MB.'])
                ->withInput($request->except(['photos', '_token', 'recaptcha', 'website']));
        }

        $recaptcha = (new Recaptcha())->check($request->toArray(), 'book_purchase');
        if (! $recaptcha->ok()) {
            return back()
                ->withErrors(['recaptcha' => 'Sigurnosna provjera nije uspjela. Pokušajte ponovno.'])
                ->withInput($request->except(['photos', '_token', 'recaptcha', 'website']));
        }

        $submittedAt = now();

        do {
            $reference = 'OK-' . $submittedAt->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (BookPurchaseRequest::query()->where('reference', $reference)->exists());

        $directory = 'book-purchases/' . $reference;
        $storedPhotos = [];

        try {
            foreach ($photos as $index => $photo) {
                $mimeType = strtolower((string) $photo->getMimeType());
                $extension = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/heic' => 'heic',
                    'image/heif' => 'heif',
                ][$mimeType] ?? 'jpg';
                $filename = sprintf('%02d-%s.%s', $index + 1, Str::lower(Str::random(16)), $extension);
                $path = $photo->storeAs($directory, $filename, 'local');

                if (! $path) {
                    throw new \RuntimeException('Photograph could not be stored.');
                }

                $storedPhotos[] = [
                    'path' => $path,
                    'name' => Str::limit(basename((string) $photo->getClientOriginalName()), 190, ''),
                    'mime_type' => $mimeType ?: 'application/octet-stream',
                    'size' => (int) $photo->getSize(),
                ];
            }

            $purchase = BookPurchaseRequest::query()->create([
                'reference' => $reference,
                'full_name' => trim((string) $validated['full_name']),
                'postal_code' => trim((string) $validated['postal_code']),
                'email' => Str::lower(trim((string) $validated['email'])),
                'phone' => trim((string) $validated['phone']),
                'photos' => $storedPhotos,
                'status' => BookPurchaseRequest::STATUS_RECEIVED,
                'submitted_at' => $submittedAt,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);
            Log::error('Book purchase request could not be stored.', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return back()
                ->withErrors(['photos' => 'Prijavu trenutačno nije moguće poslati. Pokušajte ponovno.'])
                ->withInput($request->except(['photos', '_token', 'recaptcha', 'website']));
        }

        try {
            Mail::to(config('mail.admin'))->send(new BookPurchaseMessage($purchase));
        } catch (\Throwable $exception) {
            Log::error('Book purchase notification could not be sent.', [
                'purchase_id' => $purchase->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('book-purchase.create')
            ->with('success', $contentService->get()['success_message']);
    }
}
