<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Models\FranchiseMilestone;
use App\Models\FranchiseStore;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FranchiseManagementController extends Controller
{
    public const SECTIONS = ['franchise-dashboard','franchise-applications','franchise-territories','franchise-agreements','franchisees','franchise-retail-stores','store-setup','training-documents','marketing-assets','performance-targets','renewals','franchise-reports','data-management'];

    public function show(Request $request, string $section): View
    {
        if ($section === 'franchise-dashboard') {
            return $this->dashboard($request);
        }

        if ($section === 'store-setup') {
            return $this->storeSetup($request);
        }

        $config = $this->blueprint($section); abort_unless($config, 404);
        $query = $this->query($section, $config['source']);
        $this->filters($query, $request, $config);
        $records = $query->paginate(10)->withQueryString();
        $records->setCollection($records->getCollection()->map(fn($row) => $this->row($row, $config)));
        $metrics = $this->metrics($section);
        $admins = User::query()->where('is_admin', true)->orderBy('name')->get(['id','name']);
        $search = trim((string)$request->query('q','')); $status=(string)$request->query('status',''); $tab=(string)$request->query('tab','all');
        return view('admin.franchise.index', compact('section','config','records','metrics','admins','search','status','tab'));
    }

    public function dashboard(Request $request): View
    {
        $applications = FranchiseApplication::query();
        $stores = FranchiseStore::query();
        $storeSetup = FranchiseMilestone::query()->where('type', 'store_setup');
        $territories = AdminRecord::query()->where('module', 'franchise-territories');
        $agreements = AdminRecord::query()->where('module', 'franchise-agreements');
        $training = AdminRecord::query()->where('module', 'training-documents');
        $renewals = AdminRecord::query()->where('module', 'renewals');
        $openFollowUps = Conversation::query()->whereNotNull('follow_up_at')->where('status', '!=', 'closed');
        $franchiseOrders = Order::query()->whereIn('order_type', ['franchise', 'franchise_retail'])->whereNotIn('status', ['cancelled', 'refunded']);

        $metrics = [
            [
                'label' => 'Applications',
                'value' => $applications->count(),
                'sub' => 'All franchise leads',
                'icon' => 'users',
                'tone' => 'green',
                'href' => route('admin.franchise.page', ['section' => 'franchise-applications']),
            ],
            [
                'label' => 'Under Review',
                'value' => (clone $applications)->whereIn('status', ['pending', 'under-review', 'under_review', 'review'])->count(),
                'sub' => 'Due diligence queue',
                'icon' => 'clock',
                'tone' => 'orange',
                'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'tab' => 'under-review']),
            ],
            [
                'label' => 'Store Setup',
                'value' => (clone $storeSetup)->whereNotIn('status', ['complete', 'completed'])->count(),
                'sub' => 'Open setup milestones',
                'icon' => 'settings',
                'tone' => 'purple',
                'href' => route('admin.franchise.store-setup'),
            ],
            [
                'label' => 'Active Stores',
                'value' => (clone $stores)->whereIn('status', ['active', 'open'])->count(),
                'sub' => 'Operating locations',
                'icon' => 'shopping-bag',
                'tone' => 'blue',
                'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'tab' => 'active']),
            ],
            [
                'label' => 'Due Soon',
                'value' => (clone $storeSetup)->whereNotIn('status', ['complete', 'completed'])->whereNotNull('due_on')->whereDate('due_on', '<=', today()->addDays(14))->count(),
                'sub' => 'Next 14 days',
                'icon' => 'alert',
                'tone' => 'red',
                'href' => route('admin.franchise.store-setup', ['status' => 'pending']),
            ],
        ];

        $operationalMetrics = [
            [
                'label' => 'Available Territories',
                'value' => (clone $territories)->whereIn('status', ['unassigned', 'available', 'reserved'])->count(),
                'sub' => 'Open for assignment',
                'icon' => 'globe',
                'tone' => 'green',
                'href' => route('admin.franchise.territories', ['status' => 'unassigned']),
            ],
            [
                'label' => 'Agreements Expiring',
                'value' => (clone $agreements)->whereIn('status', ['expiring-soon', 'expired'])->count(),
                'sub' => 'Needs renewal action',
                'icon' => 'briefcase',
                'tone' => 'orange',
                'href' => route('admin.franchise.page', ['section' => 'franchise-agreements', 'status' => 'expiring-soon']),
            ],
            [
                'label' => 'Training Drafts',
                'value' => (clone $training)->where('status', 'draft')->count(),
                'sub' => 'Needs publication',
                'icon' => 'file-text',
                'tone' => 'purple',
                'href' => route('admin.franchise.page', ['section' => 'training-documents', 'status' => 'draft']),
            ],
            [
                'label' => 'Renewals Due',
                'value' => (clone $renewals)->whereIn('status', ['due-soon', 'overdue'])->count(),
                'sub' => 'Due soon or overdue',
                'icon' => 'refresh',
                'tone' => 'red',
                'href' => route('admin.franchise.page', ['section' => 'renewals', 'status' => 'due-soon']),
            ],
            [
                'label' => 'Open Follow-ups',
                'value' => $openFollowUps->count(),
                'sub' => 'Communication commitments',
                'icon' => 'clock',
                'tone' => 'blue',
                'href' => route('admin.communication-center.page.action-follow-ups'),
            ],
            [
                'label' => 'Franchise Orders',
                'value' => $franchiseOrders->count(),
                'sub' => 'Retail and franchise order flow',
                'icon' => 'shopping-bag',
                'tone' => 'teal',
                'href' => route('admin.order-master', 'franchise'),
            ],
        ];

        $pipelineStatuses = [
            'new' => 'New Leads',
            'under-review' => 'Under Review',
            'approved' => 'Approved',
            'onboarding' => 'Onboarding',
            'converted' => 'Active Partners',
        ];
        $pipeline = collect($pipelineStatuses)->map(function (string $label, string $status) use ($applications): array {
            $statuses = match ($status) {
                'under-review' => ['under-review', 'under_review', 'pending', 'review'],
                'converted' => ['converted'],
                default => [$status],
            };

            return [
                'status' => $status,
                'label' => $label,
                'count' => (clone $applications)->whereIn('status', $statuses)->count(),
                'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'tab' => $status]),
            ];
        })->values();

        $setupItems = (clone $storeSetup)
            ->with('application')
            ->orderBy('due_on')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (FranchiseMilestone $milestone): array => $this->storeSetupRow($milestone))
            ->values();

        $activeStores = (clone $stores)
            ->whereIn('status', ['active', 'open'])
            ->orderByDesc('opened_at')
            ->limit(6)
            ->get()
            ->map(fn (FranchiseStore $store): array => [
                'uuid' => $store->uuid,
                'code' => $store->code,
                'name' => $store->name,
                'territory' => $store->territory,
                'opened_on' => $store->opened_at?->format('d M Y') ?: 'Not opened',
                'monthly_sales' => is_numeric(data_get($store->address, 'monthly_sales'))
                    ? '€'.number_format((float) data_get($store->address, 'monthly_sales'), 2)
                    : '€0.00',
                'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'q' => $store->code]),
            ])->values();

        $recentApplications = (clone $applications)
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (FranchiseApplication $application): array => [
                'uuid' => $application->uuid,
                'name' => $application->applicant_name,
                'territory' => $application->territory,
                'status' => $this->normaliseApplicationStatus($application->status),
                'status_label' => $this->applicationStatuses()[$this->normaliseApplicationStatus($application->status)] ?? Str::headline((string) $application->status),
                'created_at' => $application->created_at?->format('d M Y'),
                'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'q' => $application->applicant_name]),
            ])->values();

        return view('admin.franchise.dashboard', compact('metrics', 'operationalMetrics', 'pipeline', 'setupItems', 'activeStores', 'recentApplications'));
    }

    public function storeSetup(Request $request, ?FranchiseMilestone $editing = null): View
    {
        $statuses = $this->storeSetupStatuses();
        $trashed = $request->query('view') === 'trash';
        $query = FranchiseMilestone::query()->with('application')->where('type', 'store_setup');

        if ($trashed) {
            $query->onlyTrashed();
        }

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->whereHas('application', fn (Builder $application) => $application
                    ->where('applicant_name', 'like', '%'.$search.'%')
                    ->orWhere('territory', 'like', '%'.$search.'%'))
                    ->orWhere('data->store_name', 'like', '%'.$search.'%')
                    ->orWhere('data->territory', 'like', '%'.$search.'%');
            });
        }
        if ($status !== '' && isset($statuses[$status])) {
            $query->whereIn('status', $status === 'complete' ? ['complete', 'completed'] : [$status]);
        }

        $records = $query->orderBy('due_on')->orderByDesc('id')->paginate(10)->withQueryString();
        $records->setCollection($records->getCollection()->map(fn (FranchiseMilestone $milestone): array => $this->storeSetupRow($milestone)));
        $applications = FranchiseApplication::query()->orderBy('applicant_name')->get(['uuid', 'applicant_name', 'territory']);
        $editing ??= $request->filled('edit')
            ? FranchiseMilestone::query()->where('type', 'store_setup')->where('uuid', (string) $request->query('edit'))->first()
            : null;

        $metrics = [
            ['label' => 'Open Setups', 'value' => FranchiseMilestone::query()->where('type', 'store_setup')->whereNotIn('status', ['complete', 'completed'])->count(), 'sub' => 'Needs progress', 'tone' => 'purple', 'icon' => 'settings'],
            ['label' => 'In Progress', 'value' => FranchiseMilestone::query()->where('type', 'store_setup')->where('status', 'in-progress')->count(), 'sub' => 'Currently assigned', 'tone' => 'blue', 'icon' => 'refresh'],
            ['label' => 'Blocked', 'value' => FranchiseMilestone::query()->where('type', 'store_setup')->where('status', 'blocked')->count(), 'sub' => 'Needs decision', 'tone' => 'red', 'icon' => 'alert'],
            ['label' => 'Complete', 'value' => FranchiseMilestone::query()->where('type', 'store_setup')->whereIn('status', ['complete', 'completed'])->count(), 'sub' => 'Ready for go-live', 'tone' => 'green', 'icon' => 'check'],
            ['label' => 'Trash', 'value' => FranchiseMilestone::onlyTrashed()->where('type', 'store_setup')->count(), 'sub' => 'Recoverable records', 'tone' => 'orange', 'icon' => 'trash'],
        ];

        return view('admin.franchise.store-setup', compact('records', 'applications', 'editing', 'metrics', 'statuses', 'search', 'status', 'trashed'));
    }

    public function storeSetupEdit(FranchiseMilestone $milestone, Request $request): View
    {
        abort_unless($milestone->type === 'store_setup', 404);

        return $this->storeSetup($request, $milestone);
    }

    public function storeSetupStore(Request $request)
    {
        $data = $this->storeSetupData($request);
        $this->validateStoreSetupCompletion($data);
        $application = FranchiseApplication::query()->where('uuid', $data['application_uuid'])->firstOrFail();

        $milestone = DB::transaction(function () use ($data, $application): FranchiseMilestone {
            $milestone = FranchiseMilestone::create([
                'franchise_application_id' => $application->id,
                'type' => 'store_setup',
                'status' => $data['status'],
                'due_on' => $data['due_on'] ?? null,
                'completed_on' => $data['status'] === 'complete' ? ($data['completed_on'] ?? today()) : null,
                'data' => $this->storeSetupMeta($data, $application),
            ]);
            AuditTrail::record('franchise.store_setup.created', $milestone, null, $milestone->toArray());

            return $milestone;
        });

        return redirect()->route('admin.franchise.store-setup.edit', ['milestone' => $milestone->uuid])
            ->with('success', 'Store Setup milestone created.');
    }

    public function storeSetupUpdate(Request $request, FranchiseMilestone $milestone)
    {
        abort_unless($milestone->type === 'store_setup', 404);
        $data = $this->storeSetupData($request);
        $this->validateStoreSetupCompletion($data);
        $application = FranchiseApplication::query()->where('uuid', $data['application_uuid'])->firstOrFail();
        $before = $milestone->toArray();

        DB::transaction(function () use ($data, $application, $milestone, $before): void {
            $milestone->update([
                'franchise_application_id' => $application->id,
                'status' => $data['status'],
                'due_on' => $data['due_on'] ?? null,
                'completed_on' => $data['status'] === 'complete' ? ($data['completed_on'] ?? today()) : null,
                'data' => $this->storeSetupMeta($data, $application, $milestone->data ?? []),
            ]);
            AuditTrail::record('franchise.store_setup.updated', $milestone, $before, $milestone->fresh()->toArray());
        });

        return redirect()->route('admin.franchise.store-setup.edit', ['milestone' => $milestone->uuid])
            ->with('success', 'Store Setup milestone updated.');
    }

    public function storeSetupAction(FranchiseMilestone $milestone, string $action)
    {
        abort_unless($milestone->type === 'store_setup', 404);
        abort_unless(in_array($action, ['start', 'complete', 'activate', 'reopen', 'block'], true), 404);
        $currentStatus = $this->normaliseStoreSetupStatus($milestone->status);
        $allowedFrom = [
            'start' => ['pending'],
            'complete' => ['in-progress'],
            'activate' => ['complete'],
            'reopen' => ['blocked', 'complete'],
            'block' => ['pending', 'in-progress'],
        ];
        abort_unless(in_array($currentStatus, $allowedFrom[$action], true), 422, 'This Store Setup milestone cannot take that action from its current state.');
        $before = $milestone->toArray();
        $checklist = (array) data_get($milestone->data, 'checklist', []);

        if (in_array($action, ['complete', 'activate'], true) && collect($this->storeSetupChecklist())->keys()->contains(fn (string $key): bool => ! (bool) ($checklist[$key] ?? false))) {
            return redirect()
                ->route('admin.franchise.store-setup.edit', ['milestone' => $milestone->uuid])
                ->withErrors(['checklist' => 'Complete every Store Setup checklist item before marking this milestone complete.']);
        }
        if ($action === 'activate') {
            try {
                $this->validateStoreSetupActivation($milestone);
            } catch (ValidationException $exception) {
                return redirect()
                    ->route('admin.franchise.store-setup.edit', ['milestone' => $milestone->uuid])
                    ->withErrors($exception->errors());
            }
        }
        if ($action === 'reopen' && filled(data_get($milestone->data, 'store_id'))) {
            abort(422, 'An activated store cannot reopen setup until a store deactivation workflow is available.');
        }

        $nextStatus = match ($action) {
            'start' => 'in-progress',
            'complete' => 'complete',
            'activate' => 'complete',
            'reopen' => 'pending',
            'block' => 'blocked',
        };

        DB::transaction(function () use ($milestone, $nextStatus, $action, $before): void {
            if ($action === 'activate') {
                $milestone->loadMissing('application');
                $data = $milestone->data ?? [];
                $storeId = data_get($data, 'store_id');
                $store = $storeId
                    ? FranchiseStore::query()
                        ->whereKey($storeId)
                        ->where('franchise_application_id', $milestone->franchise_application_id)
                        ->firstOrFail()
                    : new FranchiseStore(['franchise_application_id' => $milestone->franchise_application_id]);
                $storeBefore = $store->exists ? $store->toArray() : null;
                $store->fill([
                    'code' => data_get($data, 'store_code'),
                    'name' => data_get($data, 'store_name'),
                    'territory' => data_get($data, 'territory', $milestone->application?->territory),
                    'status' => 'active',
                    'opened_at' => $store->opened_at ?: today(),
                    'address' => array_merge($store->address ?? [], [
                        'franchisee_name' => data_get($data, 'owner_name'),
                        'manager_name' => data_get($data, 'manager_name'),
                        'setup_milestone_uuid' => $milestone->uuid,
                    ]),
                ]);
                $store->save();
                $data['store_id'] = $store->id;
                $data['activated_at'] = now()->toIso8601String();
                $milestone->update(['data' => $data, 'completed_on' => $milestone->completed_on ?: today()]);
                AuditTrail::record('franchise.store.activated', $store, $storeBefore, $store->fresh()->toArray());
                AuditTrail::record('franchise.store_setup.activate', $milestone, $before, $milestone->fresh()->toArray());

                return;
            }

            $milestone->update([
                'status' => $nextStatus,
                'completed_on' => $nextStatus === 'complete' ? today() : null,
            ]);
            AuditTrail::record('franchise.store_setup.'.$action, $milestone, $before, $milestone->fresh()->toArray());
        });

        if ($action === 'activate') {
            return back()->with('success', 'Store Setup milestone activated as a retail store.');
        }

        return back()->with('success', 'Store Setup milestone marked '.$this->storeSetupStatuses()[$nextStatus].'.');
    }

    public function storeSetupTrash(FranchiseMilestone $milestone)
    {
        abort_unless($milestone->type === 'store_setup', 404);
        $before = $milestone->toArray();
        $milestone->delete();
        AuditTrail::record('franchise.store_setup.trashed', $milestone, $before, null);

        return back()->with('success', 'Store Setup milestone moved to trash.');
    }

    public function storeSetupRestore(string $milestone)
    {
        $record = FranchiseMilestone::withTrashed()->where('uuid', $milestone)->where('type', 'store_setup')->firstOrFail();
        $record->restore();
        AuditTrail::record('franchise.store_setup.restored', $record, null, $record->fresh()->toArray());

        return back()->with('success', 'Store Setup milestone restored.');
    }

    public function applicationAction(Request $request, FranchiseApplication $application, string $action)
    {
        $transitions = [
            'start-review' => ['from' => ['new'], 'to' => 'under-review'],
            'approve' => ['from' => ['under-review'], 'to' => 'approved'],
            'reject' => ['from' => ['new', 'under-review'], 'to' => 'rejected'],
            'start-onboarding' => ['from' => ['approved'], 'to' => 'onboarding'],
            'convert' => ['from' => ['onboarding'], 'to' => 'converted'],
        ];
        abort_unless(isset($transitions[$action]), 404);
        $current = $this->normaliseApplicationStatus($application->status);
        abort_unless(in_array($current, $transitions[$action]['from'], true), 422, 'This application cannot take that action from its current state.');
        $before = $application->toArray();
        $application->update(['status' => $transitions[$action]['to']]);
        AuditTrail::record('franchise.application.'.$action, $application, $before, $application->fresh()->toArray());

        return back()->with('success', 'Application moved to '.$this->applicationStatuses()[$transitions[$action]['to']].'.');
    }

    public function store(Request $request, string $section)
    {
        $config=$this->blueprint($section); abort_unless($config,404);
        abort_unless($config['source'] !== 'milestone', 404);
        if($config['source']==='application') return $this->storeApplication($request,$config);
        if($config['source']==='store') return $this->storeStore($request,$config);
        $data=$this->genericData($request,$config);
        $record=AdminRecord::create(['module'=>$section,'title'=>$data['title'],'reference'=>$data['reference']??null,'status'=>$data['status'],'amount'=>$data['amount']??null,'record_date'=>$data['record_date']??null,'user_id'=>auth()->id(),'data'=>$this->meta($data)]);
        AuditTrail::record($section.'.created',$record,null,$record->toArray());
        return back()->with('success',$config['singular'].' created.');
    }

    public function update(Request $request,string $section,int $id)
    {
        $config=$this->blueprint($section); abort_unless($config,404);
        abort_unless($config['source'] !== 'milestone', 404);
        if($config['source']==='application') return $this->updateApplication($request,$config,$id);
        if($config['source']==='store') return $this->updateStore($request,$config,$id);
        $record=AdminRecord::query()->where('module',$section)->findOrFail($id); $before=$record->toArray(); $data=$this->genericData($request,$config);
        $record->update(['title'=>$data['title'],'reference'=>$data['reference']??null,'status'=>$data['status'],'amount'=>$data['amount']??null,'record_date'=>$data['record_date']??null,'data'=>$this->meta($data,$record->data??[])]);
        AuditTrail::record($section.'.updated',$record,$before,$record->fresh()->toArray());
        return back()->with('success',$config['singular'].' updated.');
    }

    public function destroy(string $section,int $id)
    {
        $config=$this->blueprint($section); abort_unless($config,404);
        abort_unless($config['source'] !== 'milestone', 404);
        $record=$config['source']==='application'?FranchiseApplication::findOrFail($id):($config['source']==='store'?FranchiseStore::findOrFail($id):AdminRecord::query()->where('module',$section)->findOrFail($id));
        $before=$record->toArray(); AuditTrail::record($section.'.deleted',$record,$before,null); $record->delete();
        return back()->with('success',$config['singular'].' deleted.');
    }

    public function export(Request $request,string $section): StreamedResponse
    {
        $config=$this->blueprint($section); abort_unless($config,404); $query=$this->query($section,$config['source']); $this->filters($query,$request,$config);
        $rows=$query->limit(5000)->get()->map(fn($row)=>$this->row($row,$config)); $columns=$config['columns'];
        return response()->streamDownload(function()use($rows,$columns){$h=fopen('php://output','w');fputcsv($h,array_column($columns,'label'));foreach($rows as $row)fputcsv($h,array_map(fn($c)=>strip_tags((string)($row[$c['key']]??'')),$columns));fclose($h);},$section.'-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv']);
    }

    private function query(string $section,string $source): Builder
    {
        return match($source){
            'application' => FranchiseApplication::query()->latest('id'),
            'store' => FranchiseStore::query()->latest('id'),
            'milestone' => FranchiseMilestone::query()->where('type', 'store_setup')->latest('id'),
            default => AdminRecord::query()->where('module',$section)->latest('id'),
        };
    }

    private function filters(Builder $query,Request $request,array $config): void
    {
        $q=trim((string)$request->query('q','')); $status=(string)$request->query('status',''); $tab=(string)$request->query('tab','all'); if($status===''&&isset($config['statuses'][$tab]))$status=$tab;
        if($q!==''){
            if($config['source']==='application')$query->where(fn(Builder $b)=>$b->where('applicant_name','like','%'.$q.'%')->orWhere('email','like','%'.$q.'%')->orWhere('territory','like','%'.$q.'%'));
            elseif($config['source']==='store')$query->where(fn(Builder $b)=>$b->where('name','like','%'.$q.'%')->orWhere('code','like','%'.$q.'%')->orWhere('territory','like','%'.$q.'%'));
            elseif($config['source']==='milestone')$query->where(function(Builder $b)use($q){$b->whereHas('application',fn(Builder $a)=>$a->where('applicant_name','like','%'.$q.'%')->orWhere('territory','like','%'.$q.'%'));if(DB::getDriverName()==='pgsql')$b->orWhereRaw("data::text ILIKE ?",['%'.$q.'%']);else$b->orWhere('data','like','%'.$q.'%');});
            else $query->where(fn(Builder $b)=>$b->where('title','like','%'.$q.'%')->orWhere('reference','like','%'.$q.'%'));
        }
        if($status!==''&&isset($config['statuses'][$status])){
            if($config['source']==='application')$query->whereIn('status',$status==='under-review'?['under-review','under_review','pending','review']:[$status]);
            elseif($config['source']==='store')$query->whereIn('status',match($status){'active'=>['active','open'],'pending'=>['pending','onboarding'],'inactive'=>['inactive','suspended'],default=>[$status]});
            else $query->where('status',$status);
        }
    }

    private function row(mixed $record,array $config): array
    {
        if($config['source']==='application'){
            $status=match((string)$record->status){'pending','under_review','review'=>'under-review','on_boarding'=>'onboarding',default=>(string)$record->status}; if(!isset($config['statuses'][$status]))$status='new';
            $edit=['applicant_name'=>$record->applicant_name,'email'=>$record->email,'phone'=>$record->phone,'territory'=>$record->territory,'preferred_location'=>$record->preferred_location,'investment_range'=>$record->investment_range,'status'=>$status,'assigned_to'=>$record->assigned_to,'source'=>data_get($record->data,'source','Website')];
            return ['id'=>$record->id,'uuid'=>$record->uuid,'reference'=>'LEAD-'.str_pad((string)$record->id,8,'0',STR_PAD_LEFT),'primary'=>$record->applicant_name,'secondary'=>$record->email,'territory'=>$record->territory,'record_type'=>$record->investment_range?:'Franchise Lead','status'=>$status,'status_label'=>$config['statuses'][$status],'source'=>data_get($record->data,'source','Website'),'date'=>optional($record->created_at)->format('d M Y h:i A')?:'—','end_date'=>'—','assigned'=>$record->assigned_to?(User::query()->find($record->assigned_to)?->name?:'Unassigned'):'Unassigned','value'=>$record->investment_range?:'—','amount'=>'—','growth'=>'—','notes'=>$record->preferred_location?:'','edit'=>$edit,'actions'=>$this->applicationActions($status)];
        }
        if($config['source']==='store'){
            $meta=$record->address??[]; $status=match((string)$record->status){'open'=>'active','onboarding'=>'pending','suspended'=>'inactive',default=>(string)$record->status}; if(!isset($config['statuses'][$status]))$status='pending'; $sales=data_get($meta,'monthly_sales');
            $edit=['code'=>$record->code,'name'=>$record->name,'franchisee_name'=>data_get($meta,'franchisee_name'),'territory'=>$record->territory,'status'=>$status,'manager_name'=>data_get($meta,'manager_name'),'manager_email'=>data_get($meta,'manager_email'),'opened_at'=>optional($record->opened_at)->format('Y-m-d'),'monthly_sales'=>$sales];
            return ['id'=>$record->id,'reference'=>$record->code,'primary'=>$record->name,'secondary'=>data_get($meta,'franchisee_name','—')?:'—','territory'=>$record->territory,'record_type'=>'Retail Store','status'=>$status,'status_label'=>$config['statuses'][$status],'source'=>'Franchise Network','date'=>optional($record->opened_at)->format('d M Y')?:'—','end_date'=>'—','assigned'=>data_get($meta,'manager_name','Unassigned')?:'Unassigned','value'=>is_numeric($sales)?'€'.number_format((float)$sales,2):'€0.00','amount'=>is_numeric($sales)?'€'.number_format((float)$sales,2):'€0.00','growth'=>'—','notes'=>data_get($meta,'manager_email',''),'edit'=>$edit];
        }
        if($config['source']==='milestone'){
            $row=$this->storeSetupRow($record);
            return ['id'=>$record->id,'reference'=>'SETUP-'.strtoupper(substr((string)$record->uuid,0,8)),'primary'=>$row['store_name'],'secondary'=>$row['application'],'territory'=>$row['territory'],'record_type'=>'Store Setup','status'=>$row['status'],'status_label'=>$row['status_label'],'source'=>'Franchise Management','date'=>$row['due_on'],'end_date'=>'—','assigned'=>$row['owner_name'],'value'=>$row['progress_label'],'amount'=>'—','growth'=>'—','notes'=>$row['notes'],'edit'=>$row['edit'],'uuid'=>$record->uuid];
        }
        $m=$record->data??[]; $status=(string)$record->status; $edit=['title'=>$record->title,'reference'=>$record->reference,'status'=>$status,'amount'=>$record->amount,'record_date'=>optional($record->record_date)->format('Y-m-d'),'secondary'=>data_get($m,'secondary'),'territory'=>data_get($m,'territory'),'record_type'=>data_get($m,'record_type'),'source'=>data_get($m,'source'),'assigned_to_name'=>data_get($m,'assigned_to_name'),'end_date'=>data_get($m,'end_date'),'value'=>data_get($m,'value'),'growth'=>data_get($m,'growth'),'notes'=>data_get($m,'notes')];
        return ['id'=>$record->id,'reference'=>$record->reference?:'REC-'.str_pad((string)$record->id,6,'0',STR_PAD_LEFT),'primary'=>$record->title,'secondary'=>data_get($m,'secondary','—')?:'—','territory'=>data_get($m,'territory','—')?:'—','record_type'=>data_get($m,'record_type','—')?:'—','status'=>$status,'status_label'=>$config['statuses'][$status]??Str::headline($status),'source'=>data_get($m,'source','—')?:'—','date'=>optional($record->record_date)->format('d M Y')?:optional($record->updated_at)->format('d M Y h:i A')?:'—','end_date'=>filled(data_get($m,'end_date'))?Carbon::parse(data_get($m,'end_date'))->format('d M Y'):'—','assigned'=>data_get($m,'assigned_to_name','—')?:'—','value'=>data_get($m,'value','—')?:'—','amount'=>$record->amount!==null?'€'.number_format((float)$record->amount,2):'—','growth'=>data_get($m,'growth','—')?:'—','notes'=>data_get($m,'notes',''),'edit'=>$edit];
    }

    private function applicationStatuses(): array
    {
        return [
            'new' => 'New',
            'under-review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'onboarding' => 'Onboarding',
            'converted' => 'Active Partner',
        ];
    }

    private function normaliseApplicationStatus(?string $status): string
    {
        return match ((string) $status) {
            'pending', 'under_review', 'review' => 'under-review',
            'on_boarding' => 'onboarding',
            default => (string) $status,
        };
    }

    private function applicationActions(string $status): array
    {
        return match ($status) {
            'new' => ['start-review' => 'Start review', 'reject' => 'Reject'],
            'under-review' => ['approve' => 'Approve', 'reject' => 'Reject'],
            'approved' => ['start-onboarding' => 'Start onboarding'],
            'onboarding' => ['convert' => 'Mark active'],
            default => [],
        };
    }

    private function storeSetupStatuses(): array
    {
        return [
            'pending' => 'Pending',
            'in-progress' => 'In Progress',
            'blocked' => 'Blocked',
            'complete' => 'Complete',
        ];
    }

    private function normaliseStoreSetupStatus(?string $status): string
    {
        return (string) $status === 'completed' ? 'complete' : (string) $status;
    }

    private function storeSetupActions(string $status): array
    {
        return match ($this->normaliseStoreSetupStatus($status)) {
            'pending' => ['start' => 'Start setup', 'block' => 'Block setup'],
            'in-progress' => ['complete' => 'Mark complete', 'block' => 'Block setup'],
            'blocked' => ['reopen' => 'Reopen setup'],
            default => [],
        };
    }

    private function storeSetupActionsForRecord(string $status, array $data): array
    {
        if ($this->normaliseStoreSetupStatus($status) === 'complete' && ! filled(data_get($data, 'store_id'))) {
            return ['activate' => 'Activate store', 'reopen' => 'Reopen setup'];
        }

        return $this->storeSetupActions($status);
    }

    private function validateStoreSetupActivation(FranchiseMilestone $milestone): void
    {
        $code = trim((string) data_get($milestone->data, 'store_code', ''));
        if ($code === '') {
            throw ValidationException::withMessages([
                'store_code' => 'Add a unique Store Code before activating this milestone.',
            ]);
        }

        $storeQuery = FranchiseStore::query()->where('code', $code);
        if (filled($storeId = data_get($milestone->data, 'store_id'))) {
            $storeQuery->where($storeQuery->getModel()->getKeyName(), '!=', $storeId);
        }

        if ($storeQuery->exists()) {
            throw ValidationException::withMessages([
                'store_code' => 'That Store Code is already assigned to another retail store.',
            ]);
        }
    }

    private function validateStoreSetupCompletion(array $data): void
    {
        if ($this->normaliseStoreSetupStatus($data['status'] ?? '') !== 'complete') {
            return;
        }

        foreach (array_keys($this->storeSetupChecklist()) as $key) {
            $checked = data_get($data, 'checklist.'.$key, false);
            if (! (bool) $checked) {
                throw ValidationException::withMessages([
                    'checklist' => 'Complete every Store Setup checklist item before saving a Complete milestone.',
                ]);
            }
        }
    }

    private function storeSetupChecklist(): array
    {
        return [
            'territory_approved' => 'Territory approved',
            'agreement_signed' => 'Agreement signed',
            'training_complete' => 'Training complete',
            'premises_ready' => 'Premises ready',
            'opening_order_ready' => 'Opening order ready',
            'launch_approved' => 'Launch approved',
        ];
    }

    private function storeSetupData(Request $request): array
    {
        return $request->validate([
            'application_uuid' => ['required', 'uuid', Rule::exists('franchise_applications', 'uuid')],
            'store_name' => ['required', 'string', 'max:180'],
            'store_code' => ['nullable', 'string', 'max:100'],
            'territory' => ['required', 'string', 'max:180'],
            'owner_name' => ['nullable', 'string', 'max:180'],
            'manager_name' => ['nullable', 'string', 'max:180'],
            'due_on' => ['nullable', 'date'],
            'completed_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_keys($this->storeSetupStatuses()))],
            'evidence_reference' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['boolean'],
        ]);
    }

    private function storeSetupMeta(array $data, FranchiseApplication $application, array $old = []): array
    {
        $checklist = [];
        foreach (array_keys($this->storeSetupChecklist()) as $key) {
            $checklist[$key] = (bool) data_get($data, 'checklist.'.$key, false);
        }

        return array_merge($old, [
            'store_name' => $data['store_name'],
            'store_code' => array_key_exists('store_code', $data) ? $data['store_code'] : data_get($old, 'store_code'),
            'territory' => $data['territory'],
            'owner_name' => $data['owner_name'] ?? null,
            'manager_name' => $data['manager_name'] ?? null,
            'evidence_reference' => $data['evidence_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'application_uuid' => $application->uuid,
            'checklist' => $checklist,
        ]);
    }

    private function storeSetupRow(FranchiseMilestone $milestone): array
    {
        $data = $milestone->data ?? [];
        $checklist = (array) data_get($data, 'checklist', []);
        $total = count($this->storeSetupChecklist());
        $completed = collect(array_keys($this->storeSetupChecklist()))
            ->filter(fn (string $key): bool => (bool) ($checklist[$key] ?? false))
            ->count();
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $status = $this->normaliseStoreSetupStatus($milestone->status);

        return [
            'uuid' => $milestone->uuid,
            'application' => $milestone->application?->applicant_name ?: 'Unlinked application',
            'application_uuid' => $milestone->application?->uuid,
            'store_name' => (string) data_get($data, 'store_name', 'Unnamed store'),
            'territory' => (string) data_get($data, 'territory', $milestone->application?->territory ?: 'Unassigned'),
            'owner_name' => (string) data_get($data, 'owner_name', 'Unassigned') ?: 'Unassigned',
            'manager_name' => (string) data_get($data, 'manager_name', 'Unassigned') ?: 'Unassigned',
            'status' => $status,
            'status_label' => $this->storeSetupStatuses()[$status] ?? Str::headline($status),
            'progress' => $progress,
            'progress_label' => $progress.'% ('.$completed.'/'.$total.')',
            'due_on' => $milestone->due_on?->format('d M Y') ?: 'No due date',
            'due_date_iso' => $milestone->due_on?->format('Y-m-d'),
            'overdue' => $milestone->due_on?->isPast() && ! in_array($status, ['complete', 'completed'], true),
            'notes' => (string) data_get($data, 'notes', ''),
            'evidence_reference' => (string) data_get($data, 'evidence_reference', ''),
            'checklist' => $checklist,
            'actions' => $this->storeSetupActionsForRecord($status, $data),
            'store_code' => (string) (data_get($data, 'store_code') ?: 'Not assigned'),
            'activated' => filled(data_get($data, 'store_id')),
            'store_id' => data_get($data, 'store_id'),
            'edit' => [
                'application_uuid' => $milestone->application?->uuid,
                'store_name' => data_get($data, 'store_name'),
                'store_code' => data_get($data, 'store_code'),
                'territory' => data_get($data, 'territory', $milestone->application?->territory),
                'owner_name' => data_get($data, 'owner_name'),
                'manager_name' => data_get($data, 'manager_name'),
                'due_on' => $milestone->due_on?->format('Y-m-d'),
                'completed_on' => $milestone->completed_on?->format('Y-m-d'),
                'status' => $status,
                'evidence_reference' => data_get($data, 'evidence_reference'),
                'notes' => data_get($data, 'notes'),
                'checklist' => $checklist,
            ],
        ];
    }

    private function storeApplication(Request $r,array $config){$d=$this->applicationData($r,$config);$record=FranchiseApplication::create(['applicant_name'=>$d['applicant_name'],'email'=>$d['email'],'phone'=>$d['phone']??null,'territory'=>$d['territory'],'preferred_location'=>$d['preferred_location']??null,'investment_range'=>$d['investment_range']??null,'status'=>$d['status'],'assigned_to'=>$d['assigned_to']??null,'data'=>['source'=>$d['source']??'Website']]);AuditTrail::record('franchise.application.created',$record,null,$record->toArray());return back()->with('success','Franchise application created.');}
    private function updateApplication(Request $r,array $config,int $id){$record=FranchiseApplication::findOrFail($id);$before=$record->toArray();$d=$this->applicationData($r,$config);$record->update(['applicant_name'=>$d['applicant_name'],'email'=>$d['email'],'phone'=>$d['phone']??null,'territory'=>$d['territory'],'preferred_location'=>$d['preferred_location']??null,'investment_range'=>$d['investment_range']??null,'status'=>$d['status'],'assigned_to'=>$d['assigned_to']??null,'data'=>array_merge($record->data??[],['source'=>$d['source']??'Website'])]);AuditTrail::record('franchise.application.updated',$record,$before,$record->fresh()->toArray());return back()->with('success','Franchise application updated.');}
    private function applicationData(Request $r,array $c):array{return $r->validate(['applicant_name'=>['required','string','max:180'],'email'=>['required','email','max:180'],'phone'=>['nullable','string','max:60'],'territory'=>['required','string','max:180'],'preferred_location'=>['nullable','string','max:180'],'investment_range'=>['nullable','string','max:180'],'status'=>['required',Rule::in(array_keys($c['statuses']))],'assigned_to'=>['nullable','integer',Rule::exists('users','id')->where('is_admin',true)],'source'=>['nullable','string','max:100']]);}
    private function storeStore(Request $r,array $config){$d=$this->storeData($r,$config);$record=FranchiseStore::create(['code'=>$d['code'],'name'=>$d['name'],'territory'=>$d['territory'],'status'=>$d['status'],'opened_at'=>$d['opened_at']??null,'address'=>$this->storeMeta($d)]);AuditTrail::record('franchise.store.created',$record,null,$record->toArray());return back()->with('success','Franchise retail store created.');}
    private function updateStore(Request $r,array $config,int $id){$record=FranchiseStore::findOrFail($id);$before=$record->toArray();$d=$this->storeData($r,$config,$id);$record->update(['code'=>$d['code'],'name'=>$d['name'],'territory'=>$d['territory'],'status'=>$d['status'],'opened_at'=>$d['opened_at']??null,'address'=>array_merge($record->address??[],$this->storeMeta($d))]);AuditTrail::record('franchise.store.updated',$record,$before,$record->fresh()->toArray());return back()->with('success','Franchise retail store updated.');}
    private function storeData(Request $r,array $c,?int $id=null):array{$unique=Rule::unique('franchise_stores','code');if($id)$unique->ignore($id);return $r->validate(['code'=>['required','string','max:100',$unique],'name'=>['required','string','max:180'],'franchisee_name'=>['nullable','string','max:180'],'territory'=>['required','string','max:180'],'status'=>['required',Rule::in(array_keys($c['statuses']))],'manager_name'=>['nullable','string','max:180'],'manager_email'=>['nullable','email','max:180'],'opened_at'=>['nullable','date'],'monthly_sales'=>['nullable','numeric','min:0','max:999999999.99']]);}
    private function storeMeta(array $d):array{return ['franchisee_name'=>$d['franchisee_name']??null,'manager_name'=>$d['manager_name']??null,'manager_email'=>$d['manager_email']??null,'monthly_sales'=>isset($d['monthly_sales'])?(float)$d['monthly_sales']:null];}
    private function genericData(Request $r,array $c):array{return $r->validate(['title'=>['required','string','max:180'],'reference'=>['nullable','string','max:100'],'status'=>['required',Rule::in(array_keys($c['statuses']))],'amount'=>['nullable','numeric','min:0','max:999999999.99'],'record_date'=>['nullable','date'],'secondary'=>['nullable','string','max:180'],'territory'=>['nullable','string','max:180'],'record_type'=>['nullable','string','max:120'],'source'=>['nullable','string','max:120'],'assigned_to_name'=>['nullable','string','max:180'],'end_date'=>['nullable','date'],'value'=>['nullable','string','max:120'],'growth'=>['nullable','string','max:120'],'notes'=>['nullable','string','max:3000']]);}
    private function meta(array $d,array $old=[]):array{return array_merge($old,array_intersect_key($d,array_flip(['secondary','territory','record_type','source','assigned_to_name','end_date','value','growth','notes'])));}

    private function metrics(string $section): array
    {
        $apps=FranchiseApplication::query();$stores=FranchiseStore::query();$m=AdminRecord::query()->where('module',$section);$metric=fn($label,$value,$icon,$tone,$sub)=>compact('label','value','icon','tone','sub');
        return match($section){
            'franchise-dashboard'=>[$metric('Total Applications',$apps->count(),'users','green','All franchise leads'),$metric('Active Franchisees',$this->statusCount('franchisees',['active']),'check','green','Operating partners'),$metric('Retail Stores',$stores->count(),'shopping-bag','blue','Network stores'),$metric('Agreements',$this->moduleCount('franchise-agreements'),'file-text','orange','Tracked agreements'),$metric('Renewals Due',$this->statusCount('renewals',['due-soon','overdue']),'refresh','purple','Needs attention')],
            'franchise-applications'=>[$metric('Total Leads',$apps->count(),'users','green','All applications'),$metric('New This Week',(clone $apps)->where('created_at','>=',now()->subDays(7))->count(),'file-text','green','Last 7 days'),$metric('Under Review',(clone $apps)->whereIn('status',['pending','under-review','under_review','review'])->count(),'clock','orange','In evaluation'),$metric('Approved',(clone $apps)->where('status','approved')->count(),'check','green','Approved leads'),$metric('Conversion Rate',$this->conversion().'%', 'star','purple','Converted / total')],
            'franchise-retail-stores'=>[$metric('Total Stores',$stores->count(),'shopping-bag','green','All stores'),$metric('Active Stores',(clone $stores)->whereIn('status',['active','open'])->count(),'check','green','Open and operating'),$metric('Pending Approval',(clone $stores)->whereIn('status',['pending','onboarding'])->count(),'clock','orange','Awaiting approval'),$metric('Inactive Stores',(clone $stores)->whereIn('status',['inactive','suspended','terminated'])->count(),'alert','red','Needs action'),$metric('This Month Sales','€'.number_format($this->storeSales(),2),'chart','purple','Recorded store sales')],
            'franchise-reports'=>[$metric('Total Applications',$apps->count(),'users','green','Pipeline'),$metric('Active Franchisees',$this->statusCount('franchisees',['active']),'shopping-bag','purple','Partners'),$metric('Retail Stores',$stores->count(),'shopping-bag','blue','Stores'),$metric('Agreements Signed',$this->statusCount('franchise-agreements',['active','completed']),'file-text','orange','Agreements'),$metric('Target Achievement',$this->avgPercent('performance-targets','value').'%','star','orange','Average target')],
            default=>[$metric('Total '.$this->blueprint($section)['singular'].'s',$m->count(),'users','green','All tracked records'),$metric('Active',(clone $m)->whereIn('status',['active','published','top-performer','renewed'])->count(),'check','green','Active records'),$metric('Pending',(clone $m)->whereIn('status',['pending','under-review','due-soon','needs-attention'])->count(),'clock','orange','Needs review'),$metric('At Risk',(clone $m)->whereIn('status',['at-risk','overdue','expired','suspended'])->count(),'alert','red','Needs action'),$metric('Tracked Value','€'.number_format((float)$m->sum('amount'),2),'chart','purple','Total recorded value')]
        };
    }
    private function moduleCount(string $m):int{return AdminRecord::query()->where('module',$m)->count();}
    private function statusCount(string $m,array $s):int{return AdminRecord::query()->where('module',$m)->whereIn('status',$s)->count();}
    private function conversion():string{$t=FranchiseApplication::query()->count();return $t?number_format(FranchiseApplication::query()->whereIn('status',['converted','onboarding','approved'])->count()/$t*100,1):'0.0';}
    private function storeSales():float{return (float)FranchiseStore::query()->get()->sum(fn($s)=>(float)data_get($s->address,'monthly_sales',0));}
    private function avgPercent(string $m,string $key):string{$v=AdminRecord::query()->where('module',$m)->get()->map(function($r)use($key){preg_match('/-?\d+(?:\.\d+)?/',(string)data_get($r->data,$key,''),$x);return isset($x[0])?(float)$x[0]:null;})->filter(fn($v)=>$v!==null);return $v->isEmpty()?'0.0':number_format((float)$v->average(),1);}

    private function blueprint(string $section): ?array
    {
        $appCols=[['key'=>'reference','label'=>'Lead ID'],['key'=>'primary','label'=>'Applicant / Company'],['key'=>'territory','label'=>'Territory'],['key'=>'status','label'=>'Status'],['key'=>'source','label'=>'Source'],['key'=>'date','label'=>'Date'],['key'=>'assigned','label'=>'Assigned To']];
        $common=['integrations'=>['Communication Center','Reports & Analytics','Users & Roles','Unified Stock Master','Unified Finance'],'benefits'=>[['Unified UUID / Traceability','Every record is fully traceable.'],['Unified Stock Master','Operational stock stays connected.'],['Unified Finance','Financial activity remains linked.'],['One Project 1 cPanel','One system. One source of truth.']]];
        $pages=[
            'franchise-dashboard'=>['title'=>'Franchise Dashboard','subtitle'=>'Central overview of franchise pipeline, stores, agreements and performance.','singular'=>'Franchise record','source'=>'application','editable'=>false,'purpose'=>'See the complete franchise network from first lead through onboarding, retail operations and renewal.','features'=>['Live application pipeline','Territory and agreement visibility','Retail store status','Performance and target monitoring','Renewal and follow-up visibility'],'statuses'=>['new'=>'New','under-review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','onboarding'=>'Onboarding','converted'=>'Converted'],'tabs'=>['all'=>'Overview','new'=>'New Leads','under-review'=>'Under Review','approved'=>'Approved','onboarding'=>'Onboarding'],'columns'=>$appCols],
            'franchise-applications'=>['title'=>'Applications & Leads','subtitle'=>'Manage all franchise applications from initial inquiry to onboarding.','singular'=>'Application','source'=>'application','editable'=>true,'purpose'=>'Central hub to capture, track and manage franchise leads and applications from inquiry to onboarding and conversion.','features'=>['Real-time lead and application metrics','Status pipeline with visual indicators','Advanced search and filtering','Assign applications to team members','Quick view and edit actions','Create new application','Source tracking'],'statuses'=>['new'=>'New','under-review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','onboarding'=>'Onboarding','converted'=>'Converted'],'tabs'=>['all'=>'All Applications','new'=>'New','under-review'=>'Under Review','approved'=>'Approved','rejected'=>'Rejected','onboarding'=>'Onboarding','converted'=>'Converted'],'columns'=>$appCols],
            'franchise-territories'=>['title'=>'Territories','subtitle'=>'Manage franchise territories, availability, ownership and network coverage.','singular'=>'Territory','source'=>'record','editable'=>true,'purpose'=>'Control territory availability, ownership, coverage and performance across the franchise network.','features'=>['Territory availability tracking','Franchisee assignment','Store and coverage visibility','Territory performance status','Search and filters','Full audit trail'],'statuses'=>['active'=>'Active','available'=>'Available','reserved'=>'Reserved','at-risk'=>'At Risk','inactive'=>'Inactive'],'tabs'=>['all'=>'All Territories','active'=>'Active','available'=>'Available','reserved'=>'Reserved','at-risk'=>'At Risk'],'columns'=>[['key'=>'reference','label'=>'Territory ID'],['key'=>'primary','label'=>'Territory'],['key'=>'secondary','label'=>'Franchisee'],['key'=>'status','label'=>'Status'],['key'=>'value','label'=>'Stores'],['key'=>'amount','label'=>'Revenue'],['key'=>'date','label'=>'Last Updated']]],
            'franchise-agreements'=>['title'=>'Agreements','subtitle'=>'Create, manage and track all franchise agreements.','singular'=>'Agreement','source'=>'record','editable'=>true,'purpose'=>'Create, manage and monitor all franchise agreements, terms and obligations.','features'=>['Create and customize agreements','Digital signature & version control','Track validity and renewals','Link agreements to territories and stores','Set terms, fees and obligations','Automated renewal reminders','Export agreements and reports'],'statuses'=>['active'=>'Active','under-review'=>'Under Review','expiring-soon'=>'Expiring Soon','expired'=>'Expired','draft'=>'Draft','terminated'=>'Terminated','archived'=>'Archived','completed'=>'Fully Executed'],'tabs'=>['all'=>'All Agreements','active'=>'Active','expiring-soon'=>'Expiring Soon','expired'=>'Expired','draft'=>'Draft','under-review'=>'Under Review','terminated'=>'Terminated','archived'=>'Archived'],'columns'=>[['key'=>'reference','label'=>'Agreement ID'],['key'=>'primary','label'=>'Franchisee'],['key'=>'territory','label'=>'Territory'],['key'=>'record_type','label'=>'Agreement Type'],['key'=>'status','label'=>'Status'],['key'=>'date','label'=>'Start Date'],['key'=>'end_date','label'=>'End Date'],['key'=>'assigned','label'=>'Signed By']]],
            'franchisees'=>['title'=>'Franchisees','subtitle'=>'Manage all franchisees and their business relationship.','singular'=>'Franchisee','source'=>'record','editable'=>true,'purpose'=>'Manage franchisee profiles, status, agreements, territories and overall business relationship.','features'=>['Centralized franchisee management','Status and lifecycle tracking','Territory and agreement visibility','Store count and performance link','Quick view and edit actions','Access to documents & history','Integrated communication'],'statuses'=>['active'=>'Active','pending'=>'Pending','suspended'=>'Suspended','terminated'=>'Terminated'],'tabs'=>['all'=>'All Franchisees','active'=>'Active','pending'=>'Pending Approval','suspended'=>'Suspended','terminated'=>'Terminated','by-territory'=>'By Territory','by-agreement'=>'By Agreement'],'columns'=>[['key'=>'reference','label'=>'Franchisee ID'],['key'=>'primary','label'=>'Franchisee Name'],['key'=>'secondary','label'=>'Primary Contact'],['key'=>'territory','label'=>'Territory'],['key'=>'record_type','label'=>'Agreement'],['key'=>'status','label'=>'Status'],['key'=>'date','label'=>'Joined Date'],['key'=>'value','label'=>'Stores']]],
            'franchise-retail-stores'=>['title'=>'Franchise Retail Stores','subtitle'=>'Manage all franchise retail stores and their performance.','singular'=>'Store','source'=>'store','editable'=>true,'purpose'=>'Oversee all franchise retail stores, track performance, status and key operational details.','features'=>['Centralized store management','Real-time performance tracking','Store status & lifecycle management','Territory and franchisee visibility','Manager & contact information','Sales overview and analytics','Export store data and reports'],'statuses'=>['active'=>'Active','pending'=>'Pending','inactive'=>'Inactive','terminated'=>'Terminated'],'tabs'=>['all'=>'All Stores','active'=>'Active','pending'=>'Pending Approval','inactive'=>'Inactive','by-territory'=>'By Territory','by-franchisee'=>'By Franchisee','top-performers'=>'Top Performers'],'columns'=>[['key'=>'reference','label'=>'Store ID'],['key'=>'primary','label'=>'Store Name'],['key'=>'secondary','label'=>'Franchisee'],['key'=>'territory','label'=>'Territory'],['key'=>'status','label'=>'Status'],['key'=>'assigned','label'=>'Store Manager'],['key'=>'date','label'=>'Opened Date'],['key'=>'value','label'=>'Monthly Sales']]],
            'training-documents'=>['title'=>'Training & Documents','subtitle'=>'Manage training programs, learning progress and important documents.','singular'=>'Training item','source'=>'record','editable'=>true,'purpose'=>'Manage training programs, learning progress and important documents across the franchise network.','features'=>['Create and manage training modules','Upload and organize documents','Assign content by role and territory','Mandatory training enforcement','Track progress and completion','Issue and manage certificates','Version control and history'],'statuses'=>['published'=>'Published','active'=>'Active','draft'=>'Draft','completed'=>'Completed','archived'=>'Archived'],'tabs'=>['all'=>'All Content','modules'=>'Training Modules','documents'=>'Documents','mandatory'=>'Mandatory','by-role'=>'By Role','by-territory'=>'By Territory'],'columns'=>[['key'=>'record_type','label'=>'Type'],['key'=>'primary','label'=>'Title'],['key'=>'secondary','label'=>'Category / Topic'],['key'=>'territory','label'=>'Audience'],['key'=>'source','label'=>'Mandatory'],['key'=>'status','label'=>'Status'],['key'=>'growth','label'=>'Completion Rate'],['key'=>'date','label'=>'Last Updated']]],
            'marketing-assets'=>['title'=>'Marketing Assets','subtitle'=>'Manage brand assets, marketing materials and campaign resources.','singular'=>'Marketing asset','source'=>'record','editable'=>true,'purpose'=>'Organize and distribute brand assets and marketing materials across the franchise network.','features'=>['Centralized marketing asset library','Asset version control & approvals','Campaign and purpose tagging','Territory and franchisee targeting','Asset usage tracking & downloads','Expiration alerts & renewals','Quick search, filters & categories'],'statuses'=>['active'=>'Active','scheduled'=>'Scheduled','expiring-soon'=>'Expiring Soon','expired'=>'Expired','draft'=>'Draft','archived'=>'Archived'],'tabs'=>['all'=>'All Assets','images'=>'Images','videos'=>'Videos','documents'=>'Documents','templates'=>'Templates','campaigns'=>'Campaigns','guidelines'=>'Brand Guidelines'],'columns'=>[['key'=>'primary','label'=>'Asset Name'],['key'=>'record_type','label'=>'Type'],['key'=>'secondary','label'=>'Category'],['key'=>'source','label'=>'Campaign / Purpose'],['key'=>'territory','label'=>'Territories'],['key'=>'status','label'=>'Status'],['key'=>'date','label'=>'Last Updated'],['key'=>'amount','label'=>'Downloads']]],
            'performance-targets'=>['title'=>'Performance & Targets','subtitle'=>'Track performance, measure results and manage targets across the franchise network.','singular'=>'Performance record','source'=>'record','editable'=>true,'purpose'=>'Monitor performance, track target achievement and identify top performers and areas that need improvement.','features'=>['Real-time performance tracking','Target setting & management','KPI scorecards & leaderboards','Territory & franchisee comparisons','Store level performance visibility','At risk identification & alerts','Growth trends & analytics'],'statuses'=>['top-performer'=>'Top Performer','above-target'=>'Above Target','on-target'=>'On Target','needs-attention'=>'Needs Attention','at-risk'=>'At Risk'],'tabs'=>['all'=>'Overview','targets'=>'Targets','kpis'=>'KPIs','scorecard'=>'Scorecard','leaderboards'=>'Leaderboards','by-territory'=>'By Territory','by-franchisee'=>'By Franchisee','by-store'=>'By Store'],'columns'=>[['key'=>'reference','label'=>'Rank'],['key'=>'primary','label'=>'Franchisee / Store'],['key'=>'territory','label'=>'Territory'],['key'=>'amount','label'=>'Revenue'],['key'=>'secondary','label'=>'Orders'],['key'=>'value','label'=>'Target Achievement'],['key'=>'growth','label'=>'YTD Growth'],['key'=>'status','label'=>'Status']]],
            'renewals'=>['title'=>'Renewals','subtitle'=>'Manage upcoming renewals, expirations and lifecycle of franchise agreements and related assets.','singular'=>'Renewal','source'=>'record','editable'=>true,'purpose'=>'Oversee and manage upcoming renewals for agreements, franchises and stores to ensure business continuity.','features'=>['Upcoming and overdue renewals','Automated reminders & alerts','Renewal value tracking','Agreement & store renewals','Renewal history & audit trail','Approve, renew or extend','Calendar & timeline view'],'statuses'=>['upcoming'=>'Upcoming','due-soon'=>'Due Soon','overdue'=>'Overdue','renewed'=>'Renewed','cancelled'=>'Cancelled'],'tabs'=>['all'=>'All Renewals','upcoming'=>'Upcoming','overdue'=>'Overdue','renewed'=>'Renewed','by-agreement'=>'By Agreement','by-franchisee'=>'By Franchisee','by-territory'=>'By Territory','by-store'=>'By Store'],'columns'=>[['key'=>'reference','label'=>'Item ID'],['key'=>'primary','label'=>'Franchisee / Store'],['key'=>'secondary','label'=>'Agreement'],['key'=>'record_type','label'=>'Type'],['key'=>'end_date','label'=>'Expiry Date'],['key'=>'value','label'=>'Due In'],['key'=>'status','label'=>'Status'],['key'=>'amount','label'=>'Renewal Value'],['key'=>'date','label'=>'Last Action']]],
            'franchise-reports'=>['title'=>'Franchise Reports','subtitle'=>'Comprehensive performance, pipeline and operational overview of all franchise activities.','singular'=>'Report','source'=>'record','editable'=>true,'purpose'=>'Bring application, franchisee, store, agreement, sales and performance reporting into one operational view.','features'=>['Pipeline reporting','Store and territory performance','Agreement and renewal reporting','Orders & sales analysis','Comparative analysis','Exportable reports'],'statuses'=>['active'=>'Active','draft'=>'Draft','completed'=>'Completed','archived'=>'Archived'],'tabs'=>['all'=>'Overview','applications'=>'Applications','franchisees'=>'Franchisees','stores'=>'Franchise Retail Stores','agreements'=>'Agreements','sales'=>'Orders & Sales','performance'=>'Performance & Targets','renewals'=>'Renewals'],'columns'=>[['key'=>'reference','label'=>'Report ID'],['key'=>'primary','label'=>'Report'],['key'=>'record_type','label'=>'Type'],['key'=>'status','label'=>'Status'],['key'=>'date','label'=>'Generated'],['key'=>'assigned','label'=>'Owner']]],
            'data-management'=>['title'=>'Data Management','subtitle'=>'Manage data quality, backups, imports, exports and retention across the franchise network.','singular'=>'Data activity','source'=>'record','editable'=>true,'purpose'=>'Maintain data accuracy, security and availability through proper lifecycle management.','features'=>['Data quality monitoring','Automated & manual backups','Import & export management','Data retention & archival','Cleanup & storage optimization','UUID based data traceability'],'statuses'=>['active'=>'Healthy','import'=>'Import','export'=>'Export','backup'=>'Backup','cleanup'=>'Cleanup','archived'=>'Archived'],'tabs'=>['all'=>'Overview','quality'=>'Data Quality','backups'=>'Backups','imports'=>'Imports','exports'=>'Exports','retention'=>'Data Retention','cleanup'=>'Cleanup & Archive','uuid'=>'UUID Traceability'],'columns'=>[['key'=>'record_type','label'=>'Type'],['key'=>'primary','label'=>'Description'],['key'=>'assigned','label'=>'Performed By'],['key'=>'date','label'=>'Date & Time'],['key'=>'status','label'=>'Status']]],
        ];
        $pages['store-setup']=['title'=>'Store Setup','subtitle'=>'Own the operational checklist from approved franchise application to store go-live.','singular'=>'Store Setup milestone','source'=>'milestone','editable'=>true,'purpose'=>'Coordinate territory, agreement, training, premises, opening order and launch approval in one cPanel workflow.','features'=>['Checklist progress and owners','Due dates and exception state','Application and territory link','Evidence reference and notes','Start, block, complete, activate and reopen actions','Recoverable trash and restore'],'statuses'=>$this->storeSetupStatuses(),'tabs'=>['all'=>'All Setups','pending'=>'Pending','in-progress'=>'In Progress','blocked'=>'Blocked','complete'=>'Complete'],'columns'=>[['key'=>'reference','label'=>'Setup ID'],['key'=>'primary','label'=>'Store'],['key'=>'secondary','label'=>'Application'],['key'=>'territory','label'=>'Territory'],['key'=>'status','label'=>'Status'],['key'=>'value','label'=>'Progress'],['key'=>'date','label'=>'Due Date'],['key'=>'assigned','label'=>'Owner']]];
        return isset($pages[$section])?array_merge($common,$pages[$section]):null;
    }
}
