<?php

namespace App\Http\Controllers;

use App\Services\Mailchimp\MailchimpWebhookService;
use Illuminate\Http\Request;

class MailchimpWebhookController extends Controller
{
    public function __invoke(Request $request, MailchimpWebhookService $webhooks)
    {
        abort_unless($webhooks->authorized($request), 403);

        // Mailchimp checks the callback with GET before saving it.
        if ($request->isMethod('get')) {
            return response('OK');
        }

        return $webhooks->handle($request->all())
            ? response('OK')
            : response('Event could not be processed.', 422);
    }
}
