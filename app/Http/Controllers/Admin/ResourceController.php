<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRecordRequest;
use App\Http\Requests\CommunicationConversationUpdateRequest;
use App\Http\Requests\CommunicationReplyRequest;
use App\Http\Requests\{ReviewBulkStatusRequest, ReviewImportRequest, ReviewStatusRequest};
use App\Models\AdminRecord;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Conversation;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\CommunicationCenter;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class ResourceController extends Controller {
    public array $modules=[
        'website-products','products','product-manager','add-product','online-sales','customers','cart-checkout','payments','reconciliation','payment-gateways',
        'franchise-management','communication-center','reports','users-roles','integrations','settings','audit-logs','automation',
        'backup-recovery','system-maintenance','returns-refunds','media-manager','images','videos','360-product-view','virtual-try-on',
        'categories','collections','variants','banners-sliders','seo-content','reviews-testimonials','reviews-ratings','shipping-delivery',
        'discounts-coupons','sales-reports','franchise-dashboard','franchise-applications','franchise-territories','franchise-agreements',
        'franchisees','franchise-retail-stores','training-documents','marketing-assets','performance-targets','renewals','inbox','chat-24-7',
        'whatsapp','email','email-templates','approval-center','action-follow-ups','alerts-notifications','communication-reports','communication-history',
        'customer-order-reports','users','roles','permissions','company-profile','language','currency','payment-settings','notifications','branding','security',
        'order-online','order-corporate','order-bulk','order-franchise','order-franchise-retail','order-buyer','page-manager',
    ];
    private const GENERIC_STATUSES=['active','draft','planned','in-progress','completed','on-hold','new','pending','approved','rejected','open','closed','archived'];
    private function valid(string $module):void { abort_unless(in_array($module,$this->modules,true),404); }
    private function data(AdminRecordRequest $r):array { return $r->validate(['title'=>['required','string','max:180'],'reference'=>['nullable','string','max:100'],'status'=>['required','string',Rule::in(self::GENERIC_STATUSES)],'amount'=>['nullable','numeric','min:0','max:999999999.99'],'record_date'=>['nullable','date'],'notes'=>['nullable','string','max:3000']]); }
    public function index(Request $request,string $module):View {
        $this->valid($module);
        if($module==='product-manager')return $this->productManager($request);
        if(in_array($module,['communication-center','inbox'],true))return $this->communicationCenter($request,$module);
        if($module==='reviews-ratings')return $this->reviewsRatings($request);
        $tab = in_array((string) $request->query('tab', 'active'), ['active', 'trash'], true)
            ? (string) $request->query('tab', 'active')
            : 'active';
        $query = $tab === 'trash' ? AdminRecord::onlyTrashed() : AdminRecord::query();
        $query->where('module',$module);
        $search=trim((string)$request->query('q',''));$status=(string)$request->query('status','');$dateFrom=(string)$request->query('date_from','');$dateTo=(string)$request->query('date_to','');
        if($search!=='')$query->where(fn($records)=>$records->where('title','like','%'.$search.'%')->orWhere('reference','like','%'.$search.'%'));
        if(in_array($status,self::GENERIC_STATUSES,true))$query->where('status',$status);
        if($dateFrom!=='')$query->whereDate('record_date','>=',$dateFrom);
        if($dateTo!=='')$query->whereDate('record_date','<=',$dateTo);
        $records=$query->latest()->paginate(25)->withQueryString();$statuses=self::GENERIC_STATUSES;$title=$this->titleFor($module);$description=$this->descriptionFor($module);
        return view('admin.resources.index',compact('module','records','statuses','title','description','search','status','dateFrom','dateTo','tab'));
    }

    public function show(string $module, int $record): View
    {
        $record = $this->findGenericRecord($module, $record, true);
        Gate::authorize('view', $record);

        return view('admin.resources.show', [
            'module' => $module,
            'record' => $record,
            'statuses' => self::GENERIC_STATUSES,
            'title' => $this->titleFor($module),
            'description' => $this->descriptionFor($module),
            'notes' => data_get($record->data, 'notes'),
            'actions' => app(\App\Services\AdminActionRegistry::class)->for($record),
        ]);
    }

    public function duplicate(string $module, int $record): RedirectResponse
    {
        $source = $this->findGenericRecord($module, $record);
        Gate::authorize('view', $source);
        Gate::authorize('create', AdminRecord::class);

        $copy = $source->replicate();
        $copy->public_uuid = (string) Str::uuid();
        $copy->title = Str::limit('Copy of '.$source->title, 180, '');
        $copy->reference = $source->reference
            ? Str::limit($source->reference.'-COPY', 100, '')
            : null;
        $copy->status = 'draft';
        $copy->user_id = auth()->id();
        $copy->deleted_at = null;
        $copy->save();

        AuditTrail::record($module.'.duplicated', $copy, $source->toArray(), $copy->toArray());

        return back()->with('success', 'Record duplicated as a draft.');
    }

    public function archive(string $module, int $record): RedirectResponse
    {
        $record = $this->findGenericRecord($module, $record);
        Gate::authorize('archive', $record);

        if ($record->status === 'archived') {
            return back()->with('success', 'Record is already archived.');
        }

        $before = $record->toArray();
        $record->update(['status' => 'archived']);
        AuditTrail::record($module.'.archived', $record, $before, $record->fresh()->toArray());

        return back()->with('success', 'Record archived.');
    }

    public function trash(string $module, int $record): RedirectResponse
    {
        $record = $this->findGenericRecord($module, $record);
        Gate::authorize('trash', $record);

        $before = $record->toArray();
        $record->delete();
        $after = $record->toArray();
        $after['deleted_at'] = $record->deleted_at?->toISOString();
        AuditTrail::record($module.'.trashed', $record, $before, $after);

        return back()->with('success', 'Record moved to trash.');
    }

    public function restore(string $module, int $record): RedirectResponse
    {
        $record = $this->findGenericRecord($module, $record, true);
        Gate::authorize('restore', $record);

        $before = $record->toArray();
        $record->restore();
        AuditTrail::record($module.'.restored', $record, $before, $record->fresh()->toArray());

        return back()->with('success', 'Record restored.');
    }

    public function permanentDestroy(string $module, int $record): RedirectResponse
    {
        $record = $this->findGenericRecord($module, $record, true);
        Gate::authorize('permanentlyDelete', $record);
        abort_unless($record->trashed(), 422, 'Only records already in trash can be permanently deleted.');

        $before = $record->toArray();
        $record->forceDelete();
        AuditTrail::record($module.'.permanently_deleted', $record, $before, null);

        return back()->with('success', 'Record permanently deleted.');
    }
    private function reviewsRatings(Request $request): View
    {
        Gate::authorize('viewAny', Review::class);
        $tab = (string) $request->query('tab', 'all');
        $tabs = ['all', 'pending', 'approved', 'rejected', 'flagged', 'import'];
        $tab = in_array($tab, $tabs, true) ? $tab : 'all';
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $status = in_array($status, ['pending', 'approved', 'rejected', 'flagged'], true) ? $status : '';
        if ($status === '' && in_array($tab, ['pending', 'approved', 'rejected', 'flagged'], true)) $status = $tab;
        $rating = (int) $request->query('rating', 0);
        $rating = $rating >= 1 && $rating <= 5 ? $rating : 0;
        $perPage = in_array((int) $request->query('per_page', 8), [8, 16, 32], true) ? (int) $request->query('per_page', 8) : 8;

        $query = Review::query()->with(['product', 'user'])->latest('created_at')->latest('id');
        if ($search !== '') {
            $query->where(function ($reviews) use ($search): void {
                $needle = '%'.$search.'%';
                $reviews->where('title', 'like', $needle)
                    ->orWhere('body', 'like', $needle)
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', $needle)->orWhere('sku', 'like', $needle))
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $needle)->orWhere('email', 'like', $needle));
            });
        }
        if ($status !== '') {
            $query->where($status === 'flagged' ? fn ($reviews) => $reviews->whereIn('status', ['flagged', 'reported']) : fn ($reviews) => $reviews->where('status', $status));
        }
        if ($rating > 0) $query->where('rating', $rating);

        $reviews = $query->paginate($perPage)->withQueryString();
        $reviews->setCollection($reviews->getCollection()->map(function (Review $review): object {
            $statusKey = strtolower((string) ($review->status ?: 'pending'));
            $statusKey = match ($statusKey) {
                'in_review' => 'pending',
                'reported' => 'flagged',
                default => $statusKey,
            };
            $image = $review->product?->image;
            if (filled($image) && ! Str::startsWith((string) $image, ['http://', 'https://', '/'])) {
                $image = Storage::disk('public')->url($image);
            }

            return (object) [
                'id' => $review->id,
                'uuid' => $review->public_uuid,
                'title' => $review->title ?: 'Untitled review',
                'comment' => $review->body ?: 'No written comment.',
                'product_name' => $review->product?->name ?: 'Deleted product',
                'sku' => $review->product?->sku ?: '—',
                'rating' => (float) $review->rating,
                'customer' => $review->user?->name ?: 'Customer',
                'verified' => false,
                'source' => 'Website',
                'source_icon' => 'globe',
                'status' => Str::headline($statusKey),
                'status_key' => $statusKey,
                'date' => $review->created_at?->timezone(config('app.timezone'))->format('d M Y') ?: '—',
                'time' => $review->created_at?->timezone(config('app.timezone'))->format('h:i A') ?: '—',
                'image' => $image,
            ];
        }));

        $totalReviews = (int) Review::query()->count();
        $stats = [
            'average_rating' => round((float) (Review::query()->avg('rating') ?? 0), 1),
            'total' => $totalReviews,
            'pending' => (int) Review::query()->whereIn('status', ['pending', 'in_review'])->count(),
            'approved' => (int) Review::query()->where('status', 'approved')->count(),
            'flagged' => (int) Review::query()->whereIn('status', ['flagged', 'reported'])->count(),
        ];

        $last30 = Review::query()->where('created_at', '>=', now()->subDays(30));
        $last30Reviews = (clone $last30)->get(['created_at', 'rating']);
        $dailyAnalytics = collect(range(29, 0))->map(function (int $daysAgo) use ($last30Reviews): array {
            $date = now(config('app.timezone'))->subDays($daysAgo)->startOfDay();
            $count = $last30Reviews->filter(fn (Review $review): bool => $review->created_at?->timezone(config('app.timezone'))->isSameDay($date))->count();

            return ['label' => $date->format('d M'), 'count' => $count];
        })->values()->all();
        $approvedLast30 = (clone $last30)->where('status', 'approved')->count();
        $analytics = [
            'new_reviews' => (int) $last30->count(),
            'average_rating' => round((float) (Review::query()->where('created_at', '>=', now()->subDays(30))->avg('rating') ?? 0), 1),
            'approved_rate' => $last30->count() > 0 ? round(($approvedLast30 / $last30->count()) * 100, 1) : 0,
            'moderation_queue' => $stats['pending'],
        ];

        $ratingBreakdown = [];
        for ($stars = 5; $stars >= 1; $stars--) {
            $count = (int) Review::query()->where('rating', $stars)->count();
            $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0;
            $ratingBreakdown[$stars] = [
                'count' => $count,
                'percentage' => $percentage,
            ];
        }

        $topProducts = Review::query()
            ->with('product')
            ->select('product_id')
            ->selectRaw('COUNT(*) AS reviews_count')
            ->selectRaw('AVG(rating) AS average_rating')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('reviews_count')
            ->limit(3)
            ->get();

        $lastReviewUuid = Review::query()->latest('created_at')->value('public_uuid');
        $settingsRecord = AdminRecord::query()->where('module', 'system-settings')->where('reference', 'application-settings')->latest('id')->first();
        $reviewSettings = array_merge([
            'auto_approve_reviews' => false,
            'require_review_approval' => true,
            'allow_review_photos' => true,
            'allow_review_videos' => true,
            'verified_purchases_only' => true,
            'minimum_review_rating' => 0,
        ], $settingsRecord?->data ?? []);

        return view('admin.reviews-ratings.index', compact(
            'reviews',
            'stats',
            'analytics',
            'ratingBreakdown',
            'topProducts',
            'lastReviewUuid',
            'search',
            'status',
            'rating',
            'perPage',
            'tab',
            'dailyAnalytics',
            'reviewSettings',
        ));
    }
    public function updateReviewStatus(ReviewStatusRequest $request, Review $review): RedirectResponse
    {
        Gate::authorize('update', $review);
        $data = $request->validated();
        $before = $review->toArray();
        $review->update(['status' => $data['status']]);
        AuditTrail::record('review.status.updated', $review, $before, $review->fresh()->toArray());

        return back()->with('success', 'Review status updated.');
    }

    public function bulkReviewStatus(ReviewBulkStatusRequest $request): RedirectResponse
    {
        Gate::authorize('viewAny', Review::class);
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $reviews = Review::query()->whereIn('id', $data['ids'])->lockForUpdate()->get();
            abort_unless($reviews->count() === count(array_unique($data['ids'])), 404);
            $reviews->each(function (Review $review) use ($data): void {
                Gate::authorize('update', $review);
                $before = $review->toArray();
                $review->update(['status' => $data['status']]);
                AuditTrail::record('review.status.bulk_updated', $review, $before, $review->fresh()->toArray());
            });
        });

        return back()->with('success', count($data['ids']).' review status update(s) saved.');
    }

    public function importReviews(ReviewImportRequest $request): RedirectResponse
    {
        Gate::authorize('create', Review::class);
        $data = $request->validated();
        $handle = fopen($data['file']->getRealPath(), 'rb');
        abort_unless(is_resource($handle), 422, 'The review import file could not be opened.');

        $headers = array_map(static fn ($header): string => strtolower(trim(ltrim((string) $header, "\xEF\xBB\xBF"))), fgetcsv($handle) ?: []);
        $required = ['email', 'product_sku', 'rating'];
        $missing = array_values(array_diff($required, $headers));
        if ($missing) {
            fclose($handle);
            return back()->withErrors(['file' => 'Review imports require these columns: '.implode(', ', $required).'.']);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        DB::transaction(function () use ($handle, $headers, &$created, &$updated, &$skipped, &$errors): void {
            $line = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $line++;
                if (count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0) continue;
                $values = array_pad($row, count($headers), null);
                $record = array_combine($headers, array_slice($values, 0, count($headers))) ?: [];
                $email = trim((string) ($record['email'] ?? ''));
                $sku = trim((string) ($record['product_sku'] ?? ''));
                $rating = filter_var($record['rating'] ?? null, FILTER_VALIDATE_INT);
                $user = $email !== '' ? User::query()->where('email', $email)->first() : null;
                $product = $sku !== '' ? Product::query()->where('sku', $sku)->first() : null;
                if (!$user || !$product || $rating === false || $rating < 1 || $rating > 5) {
                    $skipped++;
                    if (count($errors) < 10) $errors[] = 'Line '.$line.' must contain an existing user email, product SKU and a rating from 1 to 5.';
                    continue;
                }
                $review = Review::query()->where('user_id', $user->id)->where('product_id', $product->id)->first();
                $payload = ['rating' => $rating, 'title' => trim((string) ($record['title'] ?? '')) ?: null, 'body' => trim((string) ($record['body'] ?? '')) ?: null, 'status' => 'pending'];
                if ($review) {
                    Gate::authorize('update', $review);
                    $before = $review->toArray();
                    $review->update($payload);
                    AuditTrail::record('review.import.updated', $review, $before, $review->fresh()->toArray());
                    $updated++;
                } else {
                    $review = Review::create(['user_id' => $user->id, 'product_id' => $product->id, ...$payload]);
                    AuditTrail::record('review.import.created', $review, null, $review->toArray());
                    $created++;
                }
            }
        });
        fclose($handle);
        AuditTrail::record('reviews.import.completed', null, null, ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);

        $message = "Review import complete: {$created} created, {$updated} updated, {$skipped} skipped.";
        return back()->with($skipped ? 'warning' : 'success', $message.($errors ? ' Check the import rows and retry skipped records.' : ''));
    }

    private function communicationCenter(Request $request,string $module):View {$status=(string)$request->query('status','');$search=trim((string)$request->query('q',''));$kind=(string)$request->query('kind','');$query=Conversation::with(['messages'=>fn($messages)=>$messages->oldest(),'assignee'])->latest();if(in_array($status,['new','open','pending','closed'],true))$query->where('status',$status);if($kind==='questions')$query->where(fn($conversations)=>$conversations->where('subject','like','%?%')->orWhere('subject','like','%question%')->orWhereHas('messages',fn($messages)=>$messages->where('body','like','%?%')->orWhere('body','like','%question%')));if($search!=='')$query->where(fn($conversations)=>$conversations->where('contact','like','%'.$search.'%')->orWhere('subject','like','%'.$search.'%'));$conversations=$query->paginate(25)->withQueryString();$admins=User::query()->where('is_admin',true)->orderBy('name')->get(['id','name']);return view('admin.communication-center.index',compact('module','conversations','status','search','admins','kind'));}
    private function productManager(Request $request): View
    {
        return app(ProductManagerController::class)->index($request);
    }

    public function updateConversation(CommunicationConversationUpdateRequest $request, Conversation $conversation)
    {
        $data = $request->validated();

        app(CommunicationCenter::class)->updateConversation($conversation, $data);

        return back()->with('success', 'Conversation updated.');
    }

    public function storeMessage(CommunicationReplyRequest $request, Conversation $conversation)
    {
        $data = $request->validated();
        $idempotencyKey = $request->header('Idempotency-Key');

        $key = is_string($idempotencyKey) ? $idempotencyKey : null;
        if (($data['mode'] ?? 'reply') === 'internal_note') {
            app(CommunicationCenter::class)->addInternalNote($conversation, $data['body'], $key);

            return back()->with('success', 'Internal note saved in Communication Center.');
        }

        app(CommunicationCenter::class)->sendReply($conversation, $data['body'], $key);

        return back()->with('success', 'Reply queued in Communication Center.');
    }
    public function store(AdminRecordRequest $r,string $module){$this->valid($module);Gate::authorize('create',AdminRecord::class);$d=$this->data($r);$record=AdminRecord::create(['module'=>$module,'title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'user_id'=>auth()->id(),'data'=>['notes'=>$d['notes']??null]]);AuditTrail::record($module.'.created',$record,null,$record->toArray());return back()->with('success','Record created.');}
    public function update(AdminRecordRequest $r,string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);Gate::authorize('update',$record);$before=$record->toArray();$d=$this->data($r);$record->update(['title'=>$d['title'],'reference'=>$d['reference']??null,'status'=>$d['status'],'amount'=>$d['amount']??null,'record_date'=>$d['record_date']??null,'data'=>array_merge($record->data??[],['notes'=>$d['notes']??null])]);AuditTrail::record($module.'.updated',$record,$before,$record->fresh()->toArray());return back()->with('success','Record updated.');}
    public function destroy(string $module,AdminRecord $record){$this->valid($module);abort_unless($record->module===$module,404);Gate::authorize('trash',$record);$before=$record->toArray();$record->delete();$after=$record->toArray();$after['deleted_at']=$record->deleted_at?->toISOString();AuditTrail::record($module.'.trashed',$record,$before,$after);return back()->with('success','Record moved to trash.');}
    private function findGenericRecord(string $module, int $id, bool $withTrashed = false): AdminRecord
    {
        $this->valid($module);
        $query = $withTrashed ? AdminRecord::withTrashed() : AdminRecord::query();

        return $query->where('module', $module)->findOrFail($id);
    }
    private function titleFor(string $module):string{return match($module){ 'online-sales'=>'Online Sales','franchise-retail-stores'=>'Franchise Retail Stores','franchise-applications'=>'Franchise Applications & Leads','users-roles'=>'Users & Roles',default=>str($module)->headline(),};}
    private function descriptionFor(string $module):string{return match($module){ 'online-sales'=>'Track online sales readiness, campaigns, references and operational actions.','communication-center'=>'Manage customer enquiries, meeting requests, assignments, follow-ups and replies.','franchise-management'=>'Coordinate franchise applications, territories, agreements, training and renewals.','reports'=>'Keep report definitions and export requests visible to the Project 1 team.',default=>'Manage '.$this->titleFor($module).' records from the shared Project 1 cPanel.',};}
}
