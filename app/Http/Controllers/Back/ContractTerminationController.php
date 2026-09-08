<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\ContractTermination;
use App\Services\ContractTerminationNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractTerminationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(ContractTermination::statuses()))],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        $terminations = ContractTermination::query()
            ->with(['order:id', 'handler:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('reference', 'like', '%' . $search . '%')
                        ->orWhere('order_number', 'like', '%' . $search . '%')
                        ->orWhere('full_name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest('submitted_at')
            ->paginate(25)
            ->appends($request->query());

        return view('back.contract-terminations.index', [
            'terminations' => $terminations,
            'statuses' => ContractTermination::statuses(),
            'statusColors' => ContractTermination::statusColors(),
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }

    public function show(ContractTermination $termination)
    {
        $termination->load(['order', 'user:id,name,email', 'handler:id,name']);

        return view('back.contract-terminations.show', [
            'termination' => $termination,
            'statuses' => ContractTermination::statuses(),
            'statusColors' => ContractTermination::statusColors(),
        ]);
    }

    public function update(Request $request, ContractTermination $termination)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContractTermination::statuses()))],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $closed = in_array($validated['status'], [
            ContractTermination::STATUS_COMPLETED,
            ContractTermination::STATUS_DECLINED,
        ], true);

        $termination->forceFill([
            'status' => $validated['status'],
            'internal_note' => trim((string) ($validated['internal_note'] ?? '')) ?: null,
            'handled_by' => optional($request->user())->id,
            'handled_at' => now(),
            'completed_at' => $closed ? ($termination->completed_at ?: now()) : null,
        ])->save();

        return redirect()->route('contract-terminations.show', $termination)
            ->with('success', 'Status raskida ugovora je spremljen.');
    }

    public function resend(
        ContractTermination $termination,
        ContractTerminationNotificationService $notifications
    ) {
        $termination->forceFill(['notification_error' => null])->save();
        $notifications->send($termination);
        $termination->refresh();

        return redirect()->route('contract-terminations.show', $termination)->with(
            $termination->notification_error ? 'warning' : 'success',
            $termination->notification_error
                ? 'Slanje nije u cijelosti uspjelo. Provjerite prikazanu pogrešku.'
                : 'Potvrda korisniku i obavijest administratoru ponovno su poslane.'
        );
    }
}
