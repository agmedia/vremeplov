<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $subscribers = $this->filteredQuery($filters)
            ->with(['user:id,name,email'])
            ->orderByDesc('subscribed_at')
            ->orderByDesc('id')
            ->paginate(config('settings.pagination.back', 30))
            ->appends($request->query());

        $statistics = [
            'total' => NewsletterSubscriber::query()->count(),
            'active' => NewsletterSubscriber::query()->where('status', true)->count(),
            'inactive' => NewsletterSubscriber::query()->where('status', false)->count(),
        ];

        $sources = NewsletterSubscriber::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source');

        return view('back.marketing.newsletter.index', compact(
            'filters',
            'sources',
            'statistics',
            'subscribers'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $filename = 'newsletter-prijave-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($filters) {
            $output = fopen('php://output', 'w');

            // Excel reliably recognizes the exported file as UTF-8 with a BOM.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['E-mail', 'Status', 'Izvor', 'Privola', 'Datum prijave'], ';');

            $this->filteredQuery($filters)
                ->orderBy('id')
                ->chunkById(500, function ($subscribers) use ($output) {
                    foreach ($subscribers as $subscriber) {
                        fputcsv($output, [
                            $this->spreadsheetSafe((string) $subscriber->email),
                            $subscriber->status ? 'Aktivan' : 'Neaktivan',
                            $this->spreadsheetSafe($this->sourceLabel($subscriber->source)),
                            $subscriber->gdpr ? 'Da' : 'Ne',
                            optional($subscriber->subscribed_at)->format('d.m.Y. H:i') ?: '',
                        ], ';');
                    }
                });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        $statusValue = $request->query('status', 'all');
        $status = is_scalar($statusValue) ? (string) $statusValue : 'all';

        if (! in_array($status, ['all', 'active', 'inactive'], true)) {
            $status = 'all';
        }

        return [
            'search' => $this->filterText($request->query('search'), 191),
            'status' => $status,
            'source' => $this->filterText($request->query('source'), 50),
        ];
    }

    private function filterText($value, int $maxLength): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
    }

    private function filteredQuery(array $filters): Builder
    {
        return NewsletterSubscriber::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $match) use ($search) {
                    $match->where('email', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function (Builder $user) use ($search) {
                            $user->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($filters['status'] === 'active', function (Builder $query) {
                $query->where('status', true);
            })
            ->when($filters['status'] === 'inactive', function (Builder $query) {
                $query->where('status', false);
            })
            ->when($filters['source'] !== '', function (Builder $query) use ($filters) {
                $query->where('source', $filters['source']);
            });
    }

    private function sourceLabel(?string $source): string
    {
        return $source === 'homepage' ? 'Web stranica' : ((string) $source ?: '—');
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
    }
}
