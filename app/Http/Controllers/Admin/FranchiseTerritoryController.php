<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FranchiseTerritoryController extends Controller
{
    private const MODULE = 'franchise-territories';
    private const STATUSES = [
        'assigned' => 'Assigned',
        'unassigned' => 'Unassigned',
        'not-available' => 'Not Available',
    ];

    public function index(Request $request): View
    {
        $query = AdminRecord::query()->where('module', self::MODULE);
        $this->applyFilters($query, $request);

        $records = $query->latest('id')->paginate(8)->withQueryString();
        $records->setCollection($records->getCollection()->map(fn (AdminRecord $record) => $this->present($record)));

        $all = AdminRecord::query()->where('module', self::MODULE)->get();
        $presented = $all->map(fn (AdminRecord $record) => $this->present($record));
        $total = $presented->count();
        $assigned = $presented->where('status', 'assigned')->count();
        $unassigned = $presented->where('status', 'unassigned')->count();
        $notAvailable = $presented->where('status', 'not-available')->count();
        $active = $assigned + $unassigned;
        $coverageValues = $presented->pluck('coverage')->filter(fn ($value) => is_numeric($value));
        $coverage = $coverageValues->isNotEmpty() ? round((float) $coverageValues->avg(), 1) : ($total ? round(($assigned / $total) * 100, 1) : 0.0);

        $metrics = [
            ['label' => 'Total Territories', 'value' => number_format($total), 'icon' => 'globe', 'tone' => 'green', 'sub' => '↑ 12.8%   vs last 30 days'],
            ['label' => 'Active Territories', 'value' => number_format($active), 'icon' => 'tag', 'tone' => 'green', 'sub' => '↑ 11.3%   vs last 30 days'],
            ['label' => 'Assigned Territories', 'value' => number_format($assigned), 'icon' => 'users', 'tone' => 'green', 'sub' => '↑ 10.4%   vs last 30 days'],
            ['label' => 'Unassigned Territories', 'value' => number_format($unassigned), 'icon' => 'circle', 'tone' => 'green', 'sub' => $unassigned ? '↓ 4.3%   vs last 30 days' : 'Ready for assignment'],
            ['label' => 'Coverage Rate', 'value' => number_format($coverage, 1) . '%', 'icon' => 'chart', 'tone' => 'green', 'sub' => '↑ 6.7%   vs last 30 days'],
        ];

        $countrySummary = $presented->groupBy('country')->map(function ($rows, $country) {
            $values = $rows->pluck('coverage')->filter(fn ($value) => is_numeric($value));
            $coverage = $values->isNotEmpty() ? (float) $values->avg() : ($rows->count() ? ($rows->where('status', 'assigned')->count() / $rows->count()) * 100 : 0);
            return ['country' => $country ?: 'Unspecified', 'coverage' => round($coverage, 1)];
        })->sortByDesc('coverage')->values()->take(3);

        return view('admin.franchise.territories', [
            'records' => $records,
            'metrics' => $metrics,
            'statuses' => self::STATUSES,
            'search' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'tab' => (string) $request->query('tab', 'all'),
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'notAvailable' => $notAvailable,
            'coverage' => $coverage,
            'countrySummary' => $countrySummary,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $record = AdminRecord::create($this->attributes($data));
        AuditTrail::record('franchise.territory.created', $record, null, $record->toArray());

        return back()->with('success', 'Territory created.');
    }

    public function update(Request $request, AdminRecord $territory)
    {
        $this->guardTerritory($territory);
        $before = $territory->toArray();
        $data = $this->validated($request, $territory->id);
        $territory->update($this->attributes($data, $territory));
        AuditTrail::record('franchise.territory.updated', $territory, $before, $territory->fresh()->toArray());

        return back()->with('success', 'Territory updated.');
    }

    public function destroy(AdminRecord $territory)
    {
        $this->guardTerritory($territory);
        $before = $territory->toArray();
        AuditTrail::record('franchise.territory.deleted', $territory, $before, null);
        $territory->delete();

        return back()->with('success', 'Territory deleted.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AdminRecord::query()->where('module', self::MODULE);
        $this->applyFilters($query, $request);
        $rows = $query->latest('id')->limit(5000)->get()->map(fn (AdminRecord $record) => $this->present($record));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Territory ID', 'Territory Name', 'Country', 'Region', 'Status', 'Assigned To', 'Franchisee ID', 'Stores', 'Franchisees', 'Coverage']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row['reference'], $row['name'], $row['country'], $row['region'], $row['status_label'], $row['assigned_to'], $row['assigned_code'], $row['stores'], $row['franchisees'], $row['coverage']]);
            }
            fclose($handle);
        }, 'franchise-territories-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $search = Str::lower(trim((string) $request->query('q', '')));
        $status = (string) $request->query('status', '');
        $tab = (string) $request->query('tab', 'all');
        if ($status === '' && in_array($tab, array_keys(self::STATUSES), true)) {
            $status = $tab;
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(reference, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', [$like]);
            });
        }

        if (isset(self::STATUSES[$status])) {
            $aliases = match ($status) {
                'assigned' => ['assigned', 'active'],
                'unassigned' => ['unassigned', 'available', 'reserved'],
                'not-available' => ['not-available', 'inactive'],
            };
            $query->whereIn('status', $aliases);
        }
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $uniqueReference = Rule::unique('admin_records', 'reference')->where(fn ($query) => $query->where('module', self::MODULE));
        if ($id) {
            $uniqueReference->ignore($id);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'reference' => ['required', 'string', 'max:100', $uniqueReference],
            'country' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'assigned_to_name' => ['nullable', 'string', 'max:180'],
            'assigned_code' => ['nullable', 'string', 'max:100'],
            'stores_count' => ['required', 'integer', 'min:0', 'max:999999'],
            'franchisees_count' => ['required', 'integer', 'min:0', 'max:999999'],
            'coverage' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function attributes(array $data, ?AdminRecord $existing = null): array
    {
        return [
            'module' => self::MODULE,
            'title' => $data['title'],
            'reference' => $data['reference'],
            'status' => $data['status'],
            'amount' => null,
            'record_date' => $existing?->record_date ?? now()->toDateString(),
            'user_id' => auth()->id(),
            'data' => array_merge($existing?->data ?? [], [
                'country' => $data['country'],
                'region' => $data['region'] ?? null,
                'assigned_to_name' => $data['assigned_to_name'] ?? null,
                'assigned_code' => $data['assigned_code'] ?? null,
                'stores_count' => (int) $data['stores_count'],
                'franchisees_count' => (int) $data['franchisees_count'],
                'coverage' => (float) $data['coverage'],
                'notes' => $data['notes'] ?? null,
            ]),
        ];
    }

    private function present(AdminRecord $record): array
    {
        $data = $record->data ?? [];
        $status = match ((string) $record->status) {
            'active' => 'assigned',
            'available', 'reserved' => 'unassigned',
            'inactive' => 'not-available',
            default => (string) $record->status,
        };
        if (!isset(self::STATUSES[$status])) {
            $status = 'unassigned';
        }

        $country = (string) data_get($data, 'country', 'Ireland');
        $region = (string) data_get($data, 'region', '');
        $assignedTo = (string) data_get($data, 'assigned_to_name', data_get($data, 'secondary', ''));
        $assignedCode = (string) data_get($data, 'assigned_code', '');
        $stores = (int) data_get($data, 'stores_count', is_numeric(data_get($data, 'value')) ? data_get($data, 'value') : 0);
        $franchisees = (int) data_get($data, 'franchisees_count', is_numeric(data_get($data, 'growth')) ? data_get($data, 'growth') : 0);
        $coverage = (float) data_get($data, 'coverage', $status === 'assigned' ? 100 : 0);

        return [
            'id' => $record->id,
            'reference' => $record->reference ?: 'TER-' . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT),
            'name' => $record->title,
            'country' => $country,
            'region' => $region,
            'status' => $status,
            'status_label' => self::STATUSES[$status],
            'assigned_to' => $assignedTo !== '' ? $assignedTo : 'Unassigned',
            'assigned_code' => $assignedCode,
            'stores' => $stores,
            'franchisees' => $franchisees,
            'coverage' => $coverage,
            'notes' => (string) data_get($data, 'notes', ''),
            'edit' => [
                'title' => $record->title,
                'reference' => $record->reference,
                'country' => $country,
                'region' => $region,
                'status' => $status,
                'assigned_to_name' => $assignedTo,
                'assigned_code' => $assignedCode,
                'stores_count' => $stores,
                'franchisees_count' => $franchisees,
                'coverage' => $coverage,
                'notes' => (string) data_get($data, 'notes', ''),
            ],
        ];
    }

    private function guardTerritory(AdminRecord $territory): void
    {
        abort_unless($territory->module === self::MODULE, 404);
    }
}
