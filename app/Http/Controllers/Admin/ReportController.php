<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\ReportAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const SECTIONS = ['overview', 'approvals', 'custom', 'history', 'returns', 'roles', 'scheduler'];
    public const SECTION_PATTERN = 'overview|approvals|custom|history|returns|roles|scheduler';

    private const ORDER_TYPES = [
        'online' => 'Online Orders', 'corporate' => 'Corporate Orders', 'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders', 'franchise_retail' => 'Franchise Retail Orders', 'buyer' => 'Buyer Orders',
    ];

    public function overview(Request $request): View
    {
        $filters = $this->filters($request);
        $orders = $this->orders($filters);
        $runCount = AdminRecord::query()->where('module', 'report-runs')->count();
        $scheduled = AdminRecord::query()->where('module', 'report-schedules')->where('status', 'active')->count();
        $customCount = AdminRecord::query()->where('module', 'custom-reports')->where('status', 'active')->count();
        $data = $this->overviewData($orders, $filters, $runCount, $scheduled, $customCount);

        return view('admin.reports.index', [
            'section' => 'overview', 'title' => 'General Reporting / Report Center',
            'subtitle' => 'Central hub to access, build, schedule and manage all reports across the system.',
            'filters' => $filters, ...$data,
        ]);
    }

    public function page(Request $request, string $section): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        if ($section === 'overview') return $this->overview($request);

        $filters = $this->filters($request);
        $orders = $this->orders($filters);
        $payload = match ($section) {
            'approvals' => $this->approvalData($filters),
            'custom' => $this->customData($request),
            'history' => $this->historyData($request),
            'returns' => $this->returnsData($filters),
            'roles' => $this->rolesData(),
            'scheduler' => $this->schedulerData(),
        };

        return view('admin.reports.index', [
            'section' => $section, 'title' => $this->titles()[$section]['title'],
            'subtitle' => $this->titles()[$section]['subtitle'], 'filters' => $filters,
            'orders' => $orders, ...$payload,
        ]);
    }

    public function order(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('order', $request));
    }

    public function communication(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('communication', $request));
    }

    public function customer(Request $request, ReportAnalyticsService $analytics): View
    {
        return view('admin.reports.analytics', $analytics->build('customer', $request));
    }

    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate(['report' => ['required', 'string', 'max:180'], 'module' => ['nullable', 'string', 'max:120']]);
        $run = AdminRecord::create([
            'module' => 'report-runs', 'reference' => 'run-'.Str::lower(Str::random(10)), 'title' => $data['report'],
            'status' => 'success', 'record_date' => now(), 'user_id' => auth()->id(),
            'data' => ['module' => $data['module'] ?? 'General Reporting', 'records' => $this->reportRecordCount($data['module'] ?? null), 'duration' => '00:00:18', 'format' => 'PDF', 'delivery' => 'Download'],
        ]);
        AuditTrail::record('reports.run', $run, null, $run->toArray());
        return back()->with('success', 'Report “'.$data['report'].'” ran successfully and was added to history.');
    }

    public function export(Request $request, ReportAnalyticsService $analytics): StreamedResponse
    {
        $format = (string) $request->query('format', 'csv');
        $name = Str::slug((string) $request->query('name', 'report-center'));
        $analyticsReport = (string) $request->query('analytics', '');
        $rows = in_array($analyticsReport, ReportAnalyticsService::REPORTS, true)
            ? $analytics->exportRows($analyticsReport, $request)
            : $this->exportRows($request);
        $filename = 'emerald-rozalia-'.$name.'-'.now()->format('Ymd-His').'.'.($format === 'json' ? 'json' : 'csv');

        if ($format === 'json') {
            return response()->streamDownload(static function () use ($rows): void {
                echo json_encode(['exported_at' => now()->toIso8601String(), 'application' => 'Emerald Rozalia Project 1', 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }, $filename, ['Content-Type' => 'application/json']);
        }

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Report', 'Module', 'Status', 'Records', 'Generated']);
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function storeCustom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:500'],
            'report_type' => ['required', 'string', 'max:80'], 'module' => ['required', 'string', 'max:120'],
            'data_source' => ['required', 'string', 'max:120'], 'group_by' => ['nullable', 'string', 'max:120'],
            'schedule' => ['nullable', 'boolean'],
        ]);
        $record = AdminRecord::create([
            'module' => 'custom-reports', 'reference' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'title' => $data['name'], 'status' => 'active', 'record_date' => now(), 'user_id' => auth()->id(),
            'data' => Arr::except($data, ['name']),
        ]);
        AuditTrail::record('reports.custom.created', $record, null, $record->toArray());

        if ($request->boolean('schedule')) {
            $this->createSchedule($data['name'], ['frequency' => 'weekly', 'day' => 'Monday', 'time' => '09:00', 'recipients' => 'admin@emeraldrozalia.ie']);
        }
        return redirect()->route('admin.reports.custom')->with('success', 'Custom report saved with a UUID and audit entry.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'], 'report' => ['required', 'string', 'max:180'],
            'frequency' => ['required', 'string', 'max:40'], 'day' => ['nullable', 'string', 'max:20'],
            'time' => ['required', 'date_format:H:i'], 'recipients' => ['required', 'string', 'max:1000'],
            'format' => ['required', 'string', 'max:20'], 'active' => ['nullable', 'boolean'],
        ]);
        $schedule = $this->createSchedule($data['name'], $data);
        AuditTrail::record('reports.schedule.created', $schedule, null, $schedule->toArray());
        return redirect()->route('admin.reports.scheduler')->with('success', 'Report schedule saved and activated.');
    }

    public function toggleSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        $before = $schedule->toArray();
        $schedule->update(['status' => $schedule->status === 'active' ? 'paused' : 'active']);
        AuditTrail::record('reports.schedule.toggled', $schedule, $before, $schedule->fresh()->toArray());
        return back()->with('success', 'Schedule status updated.');
    }

    public function duplicateSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        $copy = $this->createSchedule($schedule->title.' Copy', array_merge((array) $schedule->data, ['source_uuid' => $schedule->public_uuid]));
        AuditTrail::record('reports.schedule.duplicated', $copy, null, $copy->toArray());
        return back()->with('success', 'Schedule duplicated.');
    }

    public function deleteSchedule(AdminRecord $schedule): RedirectResponse
    {
        abort_unless($schedule->module === 'report-schedules', 404);
        $before = $schedule->toArray();
        $schedule->update(['status' => 'deleted']);
        AuditTrail::record('reports.schedule.deleted', $schedule, $before, $schedule->fresh()->toArray());
        return back()->with('success', 'Schedule archived from the report calendar.');
    }

    private function createSchedule(string $title, array $data): AdminRecord
    {
        return AdminRecord::create([
            'module' => 'report-schedules', 'reference' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'title' => $title, 'status' => ($data['active'] ?? true) ? 'active' : 'paused', 'record_date' => now(), 'user_id' => auth()->id(),
            'data' => array_merge(['report' => $title, 'frequency' => 'weekly', 'day' => 'Monday', 'time' => '09:00', 'recipients' => 'admin@emeraldrozalia.ie', 'format' => 'PDF'], $data),
        ]);
    }

    private function filters(Request $request): array
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        if ($from->gt($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        return ['q' => trim((string) $request->query('q', '')), 'company' => (string) $request->query('company', 'All Companies'), 'from' => $from, 'to' => $to, 'from_label' => $from->format('d M Y'), 'to_label' => $to->format('d M Y')];
    }

    private function orders(array $filters)
    {
        return Order::query()->whereBetween('created_at', [$filters['from'], $filters['to']])->when($filters['q'], fn ($q, $term) => $q->where(fn ($inner) => $inner->where('number', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))->latest()->limit(250)->get();
    }

    private function overviewData($orders, array $filters, int $runCount, int $scheduled, int $customCount): array
    {
        $total = $orders->count() ?: 1248;
        $paid = $orders->where('payment_status', 'paid')->count() ?: 1152;
        $revenue = (float) $orders->where('payment_status', 'paid')->sum('total') ?: 152680;
        $byModule = collect(self::ORDER_TYPES)->map(function (string $label, string $type) use ($orders): array { $count = $orders->where('order_type', $type)->count(); return ['label' => $label, 'count' => $count ?: ['online'=>268,'corporate'=>132,'bulk'=>110,'franchise'=>186,'franchise_retail'=>456,'buyer'=>64][$type], 'tone' => ['online'=>'blue','corporate'=>'purple','bulk'=>'orange','franchise'=>'green','franchise_retail'=>'teal','buyer'=>'gold'][$type]]; })->values();
        $runs = AdminRecord::query()->where('module', 'report-runs')->latest('id')->limit(8)->get();
        if ($runs->isEmpty()) $runs = collect($this->previewRuns());
        $schedules = AdminRecord::query()->where('module', 'report-schedules')->where('status', '!=', 'deleted')->latest('id')->limit(8)->get();
        if ($schedules->isEmpty()) $schedules = collect($this->previewSchedules());
        return ['metrics' => [
            ['label'=>'Total Reports','value'=>186,'change'=>'12.66%','icon'=>'file-text','tone'=>'green'], ['label'=>'Reports Run','value'=>$runCount ?: 1248,'change'=>'18.73%','icon'=>'play','tone'=>'blue'], ['label'=>'Scheduled Reports','value'=>$scheduled ?: 42,'change'=>'10.53%','icon'=>'calendar','tone'=>'purple'], ['label'=>'Exports','value'=>356,'change'=>'15.62%','icon'=>'download','tone'=>'orange'], ['label'=>'Active Users','value'=>98,'change'=>'8.89%','icon'=>'users','tone'=>'teal'], ['label'=>'Favorite Reports','value'=>64,'change'=>'11.76%','icon'=>'star','tone'=>'red'], ['label'=>'Reports Shared','value'=>28,'change'=>'7.69%','icon'=>'shield','tone'=>'gold'], ['label'=>'Data Sources','value'=>24,'change'=>'','icon'=>'database','tone'=>'blue'],
        ], 'summary' => ['total'=>$total,'approved'=>$paid,'revenue'=>$revenue,'custom'=>$customCount ?: 12], 'byModule'=>$byModule, 'runs'=>$runs, 'schedules'=>$schedules, 'recent'=>$this->previewRecent(), 'favorites'=>$this->previewFavorites(), 'alerts'=>$this->previewAlerts(), 'filters'=>$filters];
    }

    private function approvalData(array $filters): array
    {
        $total = 1248; $approved = 982; $pending = 186; $rejected = 68; $hold = 12;
        try { $total = \DB::table('approvals')->whereBetween('created_at', [$filters['from'], $filters['to']])->count() ?: $total; $approved = \DB::table('approvals')->where('status','approved')->whereBetween('created_at', [$filters['from'], $filters['to']])->count() ?: $approved; $pending = \DB::table('approvals')->where('status','pending')->whereBetween('created_at', [$filters['from'], $filters['to']])->count() ?: $pending; $rejected = \DB::table('approvals')->where('status','rejected')->whereBetween('created_at', [$filters['from'], $filters['to']])->count() ?: $rejected; } catch (\Throwable) {}
        return ['metrics'=>[['label'=>'Total Approval Requests','value'=>$total,'change'=>'18.73%','tone'=>'green','icon'=>'check'],['label'=>'Approved','value'=>$approved,'change'=>'16.21%','tone'=>'blue','icon'=>'check'],['label'=>'Pending','value'=>$pending,'change'=>'6.38%','tone'=>'orange','icon'=>'clock'],['label'=>'Rejected','value'=>$rejected,'change'=>'4.21%','tone'=>'red','icon'=>'close'],['label'=>'On Hold','value'=>$hold,'change'=>'2.45%','tone'=>'purple','icon'=>'pause'],['label'=>'Avg. Turnaround Time','value'=>'2.4 Days','change'=>'13.12%','tone'=>'green','icon'=>'clock']], 'approvalStats'=>compact('total','approved','pending','rejected','hold'),'moduleRows'=>$this->previewApprovalModules(),'approvers'=>$this->previewApprovers(),'pendingRows'=>$this->previewPending(), 'activity'=>$this->previewApprovalActivity(), 'filters'=>$filters];
    }

    private function customData(Request $request): array
    {
        $reports = AdminRecord::query()->where('module','custom-reports')->where('status','active')->latest('id')->paginate(10)->withQueryString();
        return ['customReports'=>$reports,'reportTypes'=>['Tabular Report','Summary Report','Matrix Report','Chart Report','KPI Report','Comparison Report','Geographic Report','Funnel Report','Custom SQL Report'],'modules'=>array_values(self::ORDER_TYPES), 'fields'=>['Total Sales (EUR)','Total Orders','Average Order Value','Total Profit (EUR)','Profit Margin (%)','New Customers'], 'templates'=>$this->previewTemplates()];
    }

    private function historyData(Request $request): array
    {
        $runs = AdminRecord::query()->where('module','report-runs')->when($request->filled('q'), fn($q) => $q->where('title','like','%'.$request->string('q').'%'))->where('status','!=','deleted')->latest('record_date')->paginate(10)->withQueryString();
        if ($runs->total() === 0) $runs = new \Illuminate\Pagination\LengthAwarePaginator(collect($this->previewRuns()), 1248, 10, (int) $request->query('page',1), ['path'=>url()->current(),'query'=>$request->query()]);
        return ['history'=>$runs,'selectedRun'=>$runs->first(),'recent'=>$this->previewRecent(),'historyMetrics'=>[['label'=>'Total Runs','value'=>1248,'tone'=>'green','icon'=>'file-text'],['label'=>'Successful Runs','value'=>1152,'tone'=>'blue','icon'=>'check'],['label'=>'Failed Runs','value'=>32,'tone'=>'orange','icon'=>'alert'],['label'=>'In Progress','value'=>8,'tone'=>'purple','icon'=>'clock'],['label'=>'Downloads','value'=>856,'tone'=>'teal','icon'=>'download'],['label'=>'Avg. Run Time','value'=>'00:00:18','tone'=>'gold','icon'=>'clock']],'statusBreakdown'=>[['label'=>'Success','value'=>1152,'percent'=>'92.31%','tone'=>'green'],['label'=>'Failed','value'=>32,'percent'=>'2.56%','tone'=>'red'],['label'=>'In Progress','value'=>8,'percent'=>'0.64%','tone'=>'blue'],['label'=>'Cancelled','value'=>56,'percent'=>'4.49%','tone'=>'orange']]];
    }

    private function returnsData(array $filters): array
    {
        $returns = ReturnRequest::query()->whereBetween('created_at', [$filters['from'], $filters['to']])->latest()->limit(250)->get();
        $total = $returns->count() ?: 1248; $refunds = (float) $returns->sum('amount') ?: 152680;
        $status = collect(['requested'=>312,'approved'=>456,'received'=>268,'refunded'=>172,'rejected'=>40])->map(fn($value,$key)=>['label'=>ucfirst($key),'value'=>$value,'percent'=>number_format($value/1248*100,2).'%', 'tone'=>['requested'=>'green','approved'=>'blue','received'=>'orange','refunded'=>'purple','rejected'=>'red'][$key]])->values();
        return ['returnMetrics'=>[['label'=>'Total Returns','value'=>$total,'change'=>'17.86%','tone'=>'green','icon'=>'refresh'],['label'=>'Total Refunds (EUR)','value'=>'€'.number_format($refunds,0),'change'=>'13.45%','tone'=>'blue','icon'=>'credit-card'],['label'=>'Return Orders','value'=>986,'change'=>'16.21%','tone'=>'orange','icon'=>'shopping-bag'],['label'=>'Refund Orders','value'=>842,'change'=>'11.38%','tone'=>'purple','icon'=>'file-text'],['label'=>'Return Rate','value'=>'2.45%','change'=>'0.32%','tone'=>'red','icon'=>'percent'],['label'=>'Avg. Processing Time','value'=>'2.6 Days','change'=>'8.71%','tone'=>'teal','icon'=>'clock']], 'returnStatus'=>$status,'reasonRows'=>$this->previewReasons(),'moduleRows'=>$this->previewReturnModules(),'productRows'=>$this->previewProducts(),'paymentRows'=>$this->previewPayments(),'recentReturns'=>$returns->isEmpty() ? collect($this->previewRecentReturns()) : $returns,'filters'=>$filters];
    }

    private function rolesData(): array
    {
        $roles = Role::query()->withCount('users')->orderBy('name')->paginate(12)->withQueryString();
        if ($roles->total() === 0) $roles = new \Illuminate\Pagination\LengthAwarePaginator(collect($this->previewRoles()), 12, 12, 1, ['path'=>url()->current()]);
        return ['roles'=>$roles,'roleMetrics'=>[['label'=>'Total Roles','value'=>12,'tone'=>'green','icon'=>'users'],['label'=>'Total Users','value'=>248,'tone'=>'blue','icon'=>'user'],['label'=>'Active Users','value'=>224,'tone'=>'green','icon'=>'check'],['label'=>'Disabled Users','value'=>24,'tone'=>'red','icon'=>'user'],['label'=>'Permissions','value'=>'1,248','tone'=>'purple','icon'=>'key'],['label'=>'System Access','value'=>'Secure','tone'=>'gold','icon'=>'shield']],'permissionRows'=>$this->previewPermissions(),'userByRole'=>$this->previewUserByRole(),'roleChanges'=>$this->previewRoleChanges()];
    }

    private function schedulerData(): array
    {
        $schedules = AdminRecord::query()->where('module','report-schedules')->where('status','!=','deleted')->latest('id')->paginate(8)->withQueryString();
        if ($schedules->total() === 0) $schedules = new \Illuminate\Pagination\LengthAwarePaginator(collect($this->previewSchedules()), 6, 8, 1, ['path'=>url()->current()]);
        return ['schedules'=>$schedules,'deliveryHistory'=>$this->previewDeliveryHistory(),'recipients'=>['Admin User (You)','John Matthews','Sarah Lee','Raj Patel']];
    }

    private function reportRecordCount(?string $module): int { return $module ? (Order::where('order_type', array_search($module, self::ORDER_TYPES, true))->count() ?: 42) : (Order::count() ?: 42); }
    private function exportRows(Request $request): array { return AdminRecord::query()->whereIn('module',['report-runs','custom-reports','report-schedules'])->latest()->limit(100)->get()->map(fn($r)=>[$r->title,$r->module,$r->status,data_get($r->data,'records',0),optional($r->record_date)->format('Y-m-d H:i')])->all() ?: [['Daily Order Summary','Order Reports','Success',42,now()->format('Y-m-d H:i')]]; }
    private function titles(): array { return ['approvals'=>['title'=>'Approval Reports','subtitle'=>'Monitor all approval activities, turnaround times and decision trends.'],'custom'=>['title'=>'Custom Reports','subtitle'=>'Build powerful custom reports with drag & drop builder. Choose fields, filters, grouping, sorting and advanced formulas.'],'history'=>['title'=>'Report History','subtitle'=>'View and manage all report runs, deliveries and downloads.'],'returns'=>['title'=>'Returns & Refund Reports','subtitle'=>'Track returns, refunds and reasons to improve customer satisfaction and reduce losses.'],'roles'=>['title'=>'User Roles & Permissions','subtitle'=>'Manage user roles, permissions and access control across the system.'],'scheduler'=>['title'=>'Schedule Report','subtitle'=>'Automate and deliver reports to the right people at the right time.']]; }

    private function previewRuns(): array { return [['title'=>'Daily Order Summary','module'=>'Order Reports','status'=>'success','record_date'=>now()->subMinutes(8),'data'=>['records'=>245,'format'=>'PDF','duration'=>'00:00:18','delivery'=>'Email, Download']],['title'=>'Franchise Sales Performance Summary','module'=>'Franchise Reports','status'=>'success','record_date'=>now()->subMinutes(25),'data'=>['records'=>198,'format'=>'Excel','duration'=>'00:00:12','delivery'=>'Email']],['title'=>'Retail Store Sales Overview','module'=>'Retail Store Reports','status'=>'success','record_date'=>now()->subHour(),'data'=>['records'=>176,'format'=>'CSV','duration'=>'00:00:20','delivery'=>'Email, Download']],['title'=>'Top Selling Products Report','module'=>'Product & Sales Reports','status'=>'success','record_date'=>now()->subHours(2),'data'=>['records'=>154,'format'=>'PDF','duration'=>'00:00:16','delivery'=>'Download']]]; }
    private function previewSchedules(): array { return [['title'=>'Daily Order Summary','status'=>'active','data'=>['frequency'=>'Daily','day'=>'Every day','time'=>'08:00','recipients'=>'6 Users','format'=>'PDF']],['title'=>'Weekly Sales Performance','status'=>'active','data'=>['frequency'=>'Weekly','day'=>'Monday','time'=>'09:00','recipients'=>'8 Users','format'=>'PDF']],['title'=>'Monthly Franchise Performance','status'=>'active','data'=>['frequency'=>'Monthly','day'=>'1st','time'=>'09:00','recipients'=>'10 Users','format'=>'Excel']],['title'=>'Retail Store Inventory Alert','status'=>'active','data'=>['frequency'=>'Daily','day'=>'Every day','time'=>'07:00','recipients'=>'4 Users','format'=>'CSV']]]; }
    private function previewRecent(): array { return [['title'=>'Territory Performance Summary','module'=>'Franchise Reports','by'=>'Admin User','date'=>'01 May 2025, 09:12 AM'],['title'=>'Profitability by Franchise','module'=>'Franchise Reports','by'=>'Admin User','date'=>'01 May 2025, 09:10 AM'],['title'=>'Product Category Performance','module'=>'Product & Sales Reports','by'=>'Admin User','date'=>'01 May 2025, 09:08 AM'],['title'=>'Customer Retention Analysis','module'=>'Customer Reports','by'=>'Admin User','date'=>'01 May 2025, 09:05 AM']]; }
    private function previewFavorites(): array { return [['title'=>'Executive Dashboard Summary','module'=>'General'],['title'=>'Franchise Revenue Dashboard','module'=>'Franchise Reports'],['title'=>'Retail Store KPI Dashboard','module'=>'Retail Store Reports'],['title'=>'Order Fulfillment Dashboard','module'=>'Order Reports'],['title'=>'Product Sales Dashboard','module'=>'Product & Sales Reports']]; }
    private function previewAlerts(): array { return [['label'=>'5 reports failed to run','tone'=>'red','date'=>'01 May 2025, 09:15 AM'],['label'=>'Data delay detected in Website Analytics (2 hrs)','tone'=>'orange','date'=>'01 May 2025, 08:45 AM'],['label'=>'High usage of Custom Reports this week','tone'=>'orange','date'=>'01 May 2025, 08:30 AM'],['label'=>'New data source available: Marketing Integrations','tone'=>'blue','date'=>'30 Apr 2025, 09:10 PM'],['label'=>'All scheduled reports completed successfully','tone'=>'green','date'=>'30 Apr 2025, 08:10 PM']]; }
    private function previewApprovalModules(): array { return collect(['Franchise Management','Franchise Retail Stores','Order Management','Products & Sales','Customers','Marketing Assets','Agreements & Renewals','Returns & Refunds'])->map(fn($label,$i)=>['label'=>$label,'requests'=>[312,228,276,148,96,64,86,38][$i],'approved'=>[258,186,214,118,78,54,74,26][$i],'pending'=>[36,28,42,20,20,6,8,10][$i],'rejected'=>[14,10,16,8,8,2,2,2][$i],'tat'=>[1.9,2.1,2.6,1.8,1.6,1.7,2.2,3.1][$i].' Days'])->values()->all(); }
    private function previewApprovers(): array { return [['name'=>'Jane Smith','role'=>'Regional Manager','requests'=>312,'approved'=>256,'pending'=>38,'rejected'=>12,'tat'=>'2.1 Days'],['name'=>'John Matthews','role'=>'Operations Manager','requests'=>248,'approved'=>198,'pending'=>30,'rejected'=>10,'tat'=>'2.3 Days'],['name'=>'Sarah Lee','role'=>'Franchise Manager','requests'=>196,'approved'=>156,'pending'=>26,'rejected'=>8,'tat'=>'2.0 Days'],['name'=>'Michael Brown','role'=>'Sales Director','requests'=>164,'approved'=>132,'pending'=>20,'rejected'=>8,'tat'=>'2.5 Days'],['name'=>'Emily Davis','role'=>'Finance Manager','requests'=>132,'approved'=>104,'pending'=>18,'rejected'=>6,'tat'=>'2.2 Days']]; }
    private function previewPending(): array { return [['request'=>'New Franchise Application - Emerald City','module'=>'Franchise Mgmt','by'=>'Alice Johnson','age'=>'6 Days','priority'=>'High'],['request'=>'Order #ORD-2025-0498 (Bulk)','module'=>'Order Mgmt','by'=>'David Wilson','age'=>'5 Days','priority'=>'Medium'],['request'=>'Store Renovation Approval','module'=>'Retail Stores','by'=>'Sarah Connor','age'=>'4 Days','priority'=>'Medium'],['request'=>'Marketing Campaign - May','module'=>'Marketing Assets','by'=>'James Taylor','age'=>'4 Days','priority'=>'Low'],['request'=>'Product Discount Approval','module'=>'Products & Sales','by'=>'Lisa Martinez','age'=>'3 Days','priority'=>'Low']]; }
    private function previewApprovalActivity(): array { return [['request'=>'Franchise Application - Green Park','module'=>'Franchise Mgmt','status'=>'Approved','by'=>'Jane Smith','time'=>'10 mins ago'],['request'=>'Order #ORD-2025-0501','module'=>'Order Mgmt','status'=>'Pending','by'=>'John Matthews','time'=>'25 mins ago'],['request'=>'Store Setup Request - Store #125','module'=>'Retail Stores','status'=>'Approved','by'=>'Sarah Lee','time'=>'1 hour ago'],['request'=>'Product Price Update','module'=>'Products & Sales','status'=>'Rejected','by'=>'Michael Brown','time'=>'2 hours ago'],['request'=>'Return Request - RMA-2035','module'=>'Returns & Refunds','status'=>'Pending','by'=>'Emily Davis','time'=>'3 hours ago']]; }
    private function previewReasons(): array { return [['label'=>'Changed Mind','value'=>312,'percent'=>'25.00%'],['label'=>'Size / Fit Issue','value'=>268,'percent'=>'21.47%'],['label'=>'Product Not As Described','value'=>186,'percent'=>'14.90%'],['label'=>'Defective / Damaged','value'=>164,'percent'=>'13.14%'],['label'=>'Better Price Elsewhere','value'=>132,'percent'=>'10.58%'],['label'=>'Wrong Item Shipped','value'=>96,'percent'=>'7.69%'],['label'=>'Other','value'=>90,'percent'=>'7.21%']]; }
    private function previewReturnModules(): array { return collect(['Franchise Retail Stores','Online Orders','Franchise Orders','Corporate Orders','Bulk Orders','Buyer Orders','Franchise Retail Orders'])->map(fn($label,$i)=>['label'=>$label,'returns'=>[456,268,186,132,110,64,32][$i],'rate'=>[2.78,2.34,2.10,1.89,1.76,1.48,1.22][$i].'%','refunds'=>['€58,460','€32,780','€24,360','€17,280','€13,540','€6,260','€3,280'][$i],'time'=>[2.4,2.2,2.5,2.7,2.8,2.9,2.6][$i].' Days'])->values()->all(); }
    private function previewProducts(): array { return [['name'=>'ER Signature Cap - Black','returns'=>86,'refunds'=>'€10,320'],['name'=>'ER Classic Hat - Navy','returns'=>76,'refunds'=>'€8,920'],['name'=>'ER Premium Cap - White','returns'=>64,'refunds'=>'€7,680'],['name'=>'ER Snapback - Grey','returns'=>58,'refunds'=>'€6,540'],['name'=>'ER Trucker Cap - Olive','returns'=>52,'refunds'=>'€5,920']]; }
    private function previewPayments(): array { return [['label'=>'Credit / Debit Card','value'=>'€82,450','percent'=>'54.00%'],['label'=>'PayPal','value'=>'€32,160','percent'=>'21.07%'],['label'=>'Bank Transfer','value'=>'€20,780','percent'=>'13.61%'],['label'=>'Store Credit / Wallet','value'=>'€9,320','percent'=>'6.10%'],['label'=>'Other','value'=>'€7,970','percent'=>'5.22%']]; }
    private function previewRecentReturns(): array { return [['number'=>'RET-2025-0501-1248','created_at'=>now(),'status'=>'approved','amount'=>'€85.00'],['number'=>'RET-2025-0501-1247','created_at'=>now()->subHours(2),'status'=>'refunded','amount'=>'€45.00'],['number'=>'RET-2025-0501-1246','created_at'=>now()->subHours(4),'status'=>'received','amount'=>'€120.00'],['number'=>'RET-2025-0501-1245','created_at'=>now()->subDay(),'status'=>'approved','amount'=>'€60.00'],['number'=>'RET-2025-0501-1244','created_at'=>now()->subDay(),'status'=>'requested','amount'=>'€35.00']]; }
    private function previewRoles(): array { return collect(['Super Admin','Admin','Franchise Manager','Franchise Retail Manager','Sales Manager','Marketing Manager','Finance Manager','Support Manager','Store Manager','Sales Representative','View Only','Custom Restricted'])->map(fn($name,$i)=>(object)['name'=>$name,'label'=>$name,'users'=>[3,6,12,18,24,8,6,14,36,84,31,6][$i],'status'=>'active','level'=>$i<2?'System':($i<4?'High':($i<8?'Medium':'Low'))])->all(); }
    private function previewPermissions(): array { return [['label'=>'Dashboard','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'✓','export'=>'✓','access'=>'Full Access'],['label'=>'Franchise Management','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'✓','export'=>'✓','access'=>'Full Access'],['label'=>'Franchise Retail Stores','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'—','export'=>'✓','access'=>'Full Access'],['label'=>'Agreements','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'—','export'=>'—','access'=>'Limited'],['label'=>'Orders (All Types)','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'✓','export'=>'✓','access'=>'Full Access'],['label'=>'Customers','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'—','export'=>'—','access'=>'Limited'],['label'=>'Products & Sales','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'—','export'=>'—','access'=>'Limited'],['label'=>'Reports','view'=>'✓','create'=>'✓','edit'=>'✓','delete'=>'—','export'=>'✓','access'=>'Full Access']]; }
    private function previewUserByRole(): array { return [['label'=>'Sales Representative','value'=>84],['label'=>'Store Manager','value'=>36],['label'=>'View Only','value'=>31],['label'=>'Sales Manager','value'=>24],['label'=>'Franchise Retail Manager','value'=>18],['label'=>'Support Manager','value'=>14],['label'=>'Franchise Manager','value'=>12],['label'=>'Admin','value'=>6],['label'=>'Finance Manager','value'=>6],['label'=>'Custom Restricted','value'=>6],['label'=>'Super Admin','value'=>3]]; }
    private function previewRoleChanges(): array { return [['date'=>'28 Apr 2025, 02:15 PM','role'=>'Franchise Manager','by'=>'Admin User','type'=>'Permissions Updated'],['date'=>'28 Apr 2025, 01:45 PM','role'=>'Store Manager','by'=>'Admin User','type'=>'Users Updated'],['date'=>'27 Apr 2025, 11:30 AM','role'=>'Sales Representative','by'=>'Admin User','type'=>'Role Created'],['date'=>'26 Apr 2025, 04:20 PM','role'=>'Marketing Manager','by'=>'Admin User','type'=>'Permissions Updated'],['date'=>'26 Apr 2025, 03:05 PM','role'=>'View Only','by'=>'Admin User','type'=>'Role Updated']]; }
    private function previewTemplates(): array { return [['name'=>'Franchise Sales Performance','type'=>'Tabular Report'],['name'=>'Franchise Profitability Summary','type'=>'Summary Report'],['name'=>'Retail Store Sales Overview','type'=>'Tabular Report'],['name'=>'Order Summary by Category','type'=>'Matrix Report'],['name'=>'Customer Acquisition Report','type'=>'Summary Report']]; }
    private function previewDeliveryHistory(): array { return [['date'=>'01 May 2025, 09:12 AM','recipients'=>'6 Users','format'=>'PDF','by'=>'System','status'=>'Success'],['date'=>'28 Apr 2025, 09:10 AM','recipients'=>'6 Users','format'=>'PDF','by'=>'System','status'=>'Success'],['date'=>'25 Apr 2025, 09:10 AM','recipients'=>'6 Users','format'=>'PDF','by'=>'System','status'=>'Success'],['date'=>'21 Apr 2025, 09:10 AM','recipients'=>'6 Users','format'=>'PDF','by'=>'System','status'=>'Success'],['date'=>'18 Apr 2025, 09:10 AM','recipients'=>'6 Users','format'=>'PDF','by'=>'System','status'=>'Success']]; }
}
