<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\BookPurchaseRequest;
use App\Services\BookPurchaseContentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class BookPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(BookPurchaseRequest::statuses()))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $purchases = BookPurchaseRequest::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('reference', 'like', '%' . $search . '%')
                        ->orWhere('full_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%')
                        ->orWhere('postal_code', 'like', '%' . $search . '%');
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom) {
                $query->whereDate('submitted_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo) {
                $query->whereDate('submitted_at', '<=', $dateTo);
            })
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(25)
            ->appends($request->query());

        return view('back.marketing.book-purchase.index', [
            'purchases' => $purchases,
            'statuses' => BookPurchaseRequest::statuses(),
            'statusColors' => BookPurchaseRequest::statusColors(),
            'search' => $search,
            'selectedStatus' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function show(BookPurchaseRequest $purchase)
    {
        $purchase->load('handler:id,name');

        return view('back.marketing.book-purchase.show', [
            'purchase' => $purchase,
            'statuses' => BookPurchaseRequest::statuses(),
            'statusColors' => BookPurchaseRequest::statusColors(),
        ]);
    }

    public function update(Request $request, BookPurchaseRequest $purchase)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(BookPurchaseRequest::statuses()))],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $purchase->forceFill([
            'status' => $validated['status'],
            'internal_note' => trim((string) ($validated['internal_note'] ?? '')) ?: null,
            'handled_by' => optional($request->user())->id,
            'handled_at' => now(),
        ])->save();

        return redirect()->route('book-purchases.show', $purchase)
            ->with('success', 'Obrada prijave je spremljena.');
    }

    public function photo(BookPurchaseRequest $purchase, int $photo)
    {
        $file = $purchase->photo($photo);
        abort_unless($file && isset($file['path']), 404);

        $path = (string) $file['path'];
        $expectedPrefix = 'book-purchases/' . $purchase->reference . '/';
        abort_unless(str_starts_with($path, $expectedPrefix) && Storage::disk('local')->exists($path), 404);

        $name = basename((string) ($file['name'] ?? $path));
        $fallbackName = 'fotografija-' . ($photo + 1) . '.' . pathinfo($path, PATHINFO_EXTENSION);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
            'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $name,
                $fallbackName
            ),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function editContent(BookPurchaseContentService $contentService)
    {
        return view('back.marketing.book-purchase.edit-content', [
            'content' => $contentService->get(),
        ]);
    }

    public function updateContent(Request $request, BookPurchaseContentService $contentService)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'meta_title' => ['required', 'string', 'max:160'],
            'meta_description' => ['required', 'string', 'max:255'],
            'intro_title' => ['required', 'string', 'max:191'],
            'intro_html' => ['required', 'string', 'max:20000'],
            'form_title' => ['required', 'string', 'max:191'],
            'full_name_label' => ['required', 'string', 'max:120'],
            'postal_code_label' => ['required', 'string', 'max:120'],
            'email_label' => ['required', 'string', 'max:120'],
            'phone_label' => ['required', 'string', 'max:120'],
            'photos_label' => ['required', 'string', 'max:120'],
            'photos_help' => ['required', 'string', 'max:1000'],
            'choose_photos_label' => ['required', 'string', 'max:120'],
            'no_photos_label' => ['required', 'string', 'max:120'],
            'selected_photos_label' => ['required', 'string', 'max:120'],
            'remove_photo_label' => ['required', 'string', 'max:120'],
            'consent_text' => ['required', 'string', 'max:1000'],
            'submit_label' => ['required', 'string', 'max:120'],
            'success_message' => ['required', 'string', 'max:1000'],
        ]);

        if ($contentService->save($validated)) {
            return redirect()->route('book-purchases.content.edit')
                ->with('success', 'Tekstovi stranice Otkup knjiga su spremljeni.');
        }

        return back()->withInput()->with('error', 'Tekstove nije moguće spremiti.');
    }

    public function destroy(BookPurchaseRequest $purchase)
    {
        Storage::disk('local')->deleteDirectory('book-purchases/' . $purchase->reference);
        $purchase->delete();

        return redirect()->route('book-purchases.index')
            ->with('success', 'Prijava i sve pripadajuće fotografije su obrisane.');
    }
}
