<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\NewsletterSubscriber;
use App\Services\Mailchimp\MailchimpConnectionSettings;
use App\Services\Mailchimp\NewsletterSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NewsletterSubscriberController extends Controller
{
    public function index(
        Request $request,
        NewsletterSyncService $mailchimp,
        MailchimpConnectionSettings $connectionSettings
    ) {
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

        $mailchimpSettings = $connectionSettings->publicSettings();
        $mailchimpConnection = $mailchimp->connectionStatus();
        $mailchimpWebhookUrl = $connectionSettings->webhookUrl();
        $mailchimpStates = $this->mailchimpStates();
        $mailchimpStatistics = ['synced' => 0, 'pending' => 0, 'error' => 0];
        $activeWithConsent = NewsletterSubscriber::query()->where('status', true)->where('gdpr', true);
        if ($mailchimpConnection['available']) {
            $counts = $activeWithConsent->selectRaw('mailchimp_sync_status, COUNT(*) as total')
                ->groupBy('mailchimp_sync_status')->pluck('total', 'mailchimp_sync_status');
            foreach ($counts as $state => $count) {
                $group = isset($mailchimpStates[$state]) ? $mailchimpStates[$state]['group'] : 'error';
                if ($group !== null) {
                    $mailchimpStatistics[$group] += (int) $count;
                }
            }
        } else {
            $mailchimpStatistics['pending'] = $activeWithConsent->count();
        }

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
            'subscribers',
            'mailchimpSettings',
            'mailchimpConnection',
            'mailchimpWebhookUrl',
            'mailchimpStates',
            'mailchimpStatistics'
        ));
    }

    public function sync(Request $request, NewsletterSyncService $mailchimp)
    {
        $redirect = redirect()->route('newsletter-subscribers.index', $this->filterParameters($request));
        $connection = $mailchimp->connectionStatus();
        if (! $connection['ready']) {
            $message = ! $connection['enabled']
                ? 'Povezivanje s Mailchimpom je isključeno. Uključite ga u postavkama.'
                : (! $connection['configured']
                    ? 'Dovršite Mailchimp postavke prije usklađivanja.'
                    : 'Povezivanje još nije spremno. Potrebno je dovršiti nadogradnju web stranice.');

            return $redirect->with('warning', $message);
        }

        $ids = $this->filteredQuery($this->filters($request))
            ->where('status', true)->where('gdpr', true)
            ->whereNotIn('mailchimp_sync_status', ['synced', 'pending_confirmation', 'preserved', 'skipped'])
            ->orderBy('mailchimp_last_attempt_at')->orderBy('id')
            ->limit(25)->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();

        if ($ids === []) {
            return $redirect->with('warning', 'U odabranom pregledu nema aktivnih prijava s privolom koje čekaju usklađivanje.');
        }

        $result = $mailchimp->syncIds($ids, 25);
        $processed = (int) ($result['processed'] ?? 0);
        $synced = (int) ($result['synced'] ?? 0);
        $confirmation = (int) ($result['pending_confirmation'] ?? 0);
        $preserved = (int) ($result['preserved'] ?? 0) + (int) ($result['skipped'] ?? 0);
        $message = "Obrađeno prijava: {$processed}. Usklađeno: {$synced}. Čeka potvrdu: {$confirmation}.";
        if ($preserved > 0) {
            $message .= " Sačuvan je postojeći status za {$preserved} prijava.";
        }

        $warning = false;
        foreach (['retry', 'error', 'disabled', 'unconfigured', 'busy', 'unavailable', 'limit_reached'] as $status) {
            if (($result[$status] ?? 0) > 0) {
                $warning = true;
                $message .= ' Dio prijava čeka provjeru ili novi pokušaj.';
                break;
            }
        }

        return $redirect->with($warning ? 'warning' : 'success', $message);
    }

    public function saveMailchimpSettings(Request $request, MailchimpConnectionSettings $connectionSettings)
    {
        $settings = $connectionSettings->publicSettings();
        $input = $request->only([
            'enabled', 'api_key', 'server_prefix', 'audience_id', 'webhook_signing_secret',
        ]);
        $input['enabled'] = $request->input('enabled', false);
        foreach (['server_prefix', 'audience_id'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim($input[$field]);
            }
        }

        $validator = Validator::make($input, [
            'enabled' => ['required', 'boolean'],
            'api_key' => [
                $request->boolean('enabled') && ! $settings['key_configured'] ? 'required' : 'nullable',
                'string', 'max:255', 'regex:/^[^\r\n]+$/',
            ],
            'server_prefix' => ['nullable', 'required_if:enabled,1', 'string', 'regex:/^us[1-9][0-9]{0,2}$/', 'max:20'],
            'audience_id' => ['nullable', 'required_if:enabled,1', 'string', 'regex:/^[a-zA-Z0-9_-]+$/', 'max:80'],
            'webhook_signing_secret' => ['nullable', 'string', 'max:255'],
        ], [], [
            'api_key' => 'Mailchimp API ključ',
            'server_prefix' => 'poslužitelj',
            'audience_id' => 'Audience ID',
            'webhook_signing_secret' => 'ključ za provjeru odjava',
        ]);

        // Credentials must never be flashed into the session or rendered back.
        if ($validator->fails()) {
            return redirect()->route('newsletter-subscribers.index', $this->filterParameters($request))
                ->withErrors($validator);
        }

        try {
            $saved = $connectionSettings->save($validator->validated());
        } catch (Throwable $exception) {
            $saved = false;
        }

        if (! $saved) {
            return redirect()->route('newsletter-subscribers.index', $this->filterParameters($request))
                ->with('error', 'Mailchimp postavke nije moguće spremiti. Pokušajte ponovno.');
        }

        return redirect()->route('newsletter-subscribers.index', $this->filterParameters($request))
            ->with('success', 'Mailchimp postavke su spremljene.');
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

    private function filterParameters(Request $request): array
    {
        return array_filter($this->filters($request), function ($value) {
            return $value !== '' && $value !== 'all';
        });
    }

    private function mailchimpStates(): array
    {
        return [
            'pending' => ['label' => 'Čeka usklađivanje', 'class' => 'badge-secondary', 'group' => 'pending'],
            'synced' => ['label' => 'Usklađeno', 'class' => 'badge-success', 'group' => 'synced'],
            'pending_confirmation' => ['label' => 'Čeka potvrdu', 'class' => 'badge-info', 'group' => 'pending'],
            'preserved' => ['label' => 'Sačuvan postojeći status', 'class' => 'badge-secondary', 'group' => null],
            'skipped' => ['label' => 'Ne prenosi se', 'class' => 'badge-secondary', 'group' => null],
            'limit_reached' => ['label' => 'Čeka novi pokušaj', 'class' => 'badge-secondary', 'group' => 'pending'],
            'retry' => ['label' => 'Čeka novi pokušaj', 'class' => 'badge-secondary', 'group' => 'pending'],
            'error' => ['label' => 'Potrebna provjera', 'class' => 'badge-warning', 'group' => 'error'],
            'disabled' => ['label' => 'Povezivanje isključeno', 'class' => 'badge-secondary', 'group' => 'pending'],
            'unconfigured' => ['label' => 'Čeka postavke povezivanja', 'class' => 'badge-secondary', 'group' => 'pending'],
            'busy' => ['label' => 'Čeka usklađivanje', 'class' => 'badge-secondary', 'group' => 'pending'],
            'unavailable' => ['label' => 'Potrebna provjera', 'class' => 'badge-warning', 'group' => 'error'],
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
