<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesQuoteConvertRequest;
use App\Http\Requests\SalesQuoteDecisionRequest;
use App\Http\Requests\SalesQuoteUpdateRequest;
use App\Models\SalesQuote;
use App\Services\SalesQuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class SalesQuoteController extends Controller
{
    private const PUBLIC_QUOTE_ORDER_TYPES = ['corporate', 'bulk'];

    public function __construct(private readonly SalesQuoteService $quotes)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalesQuote::class);

        $query = $this->quotes->visibleQuery()
            ->whereIn('order_type', self::PUBLIC_QUOTE_ORDER_TYPES)
            ->with(['inquiry', 'order']);
        $status = (string) $request->query('status', '');
        $orderType = (string) $request->query('order_type', '');
        $search = trim((string) $request->query('q', ''));

        if (in_array($status, ['submitted', 'approved', 'rejected', 'cancelled', 'converted'], true)) {
            $query->where('status', $status);
        }
        if (in_array($orderType, self::PUBLIC_QUOTE_ORDER_TYPES, true)) {
            $query->where('order_type', $orderType);
        }
        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(function ($searchQuery) use ($needle): void {
                $searchQuery
                    ->where('uuid', 'like', $needle)
                    ->orWhereHas('inquiry', function ($inquiryQuery) use ($needle): void {
                        $inquiryQuery
                            ->where('name', 'like', $needle)
                            ->orWhere('email', 'like', $needle)
                            ->orWhere('company', 'like', $needle);
                    });
            });
        }

        $perPage = in_array((int) $request->query('per_page', 25), [10, 25, 50], true)
            ? (int) $request->query('per_page', 25)
            : 25;
        $quotes = $query->latest('id')->paginate($perPage)->withQueryString();
        $statusCounts = $this->quotes->visibleQuery()
            ->whereIn('order_type', self::PUBLIC_QUOTE_ORDER_TYPES)
            ->get(['status'])
            ->groupBy('status')
            ->map->count();

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'status' => $status,
            'orderType' => $orderType,
            'search' => $search,
            'statusCounts' => $statusCounts,
            'statuses' => ['submitted', 'approved', 'rejected', 'cancelled', 'converted'],
            'orderTypes' => self::PUBLIC_QUOTE_ORDER_TYPES,
        ]);
    }

    public function show(SalesQuote $quote): View
    {
        Gate::authorize('view', $quote);
        $quote->load([
            'inquiry',
            'franchiseApplication',
            'conversation',
            'order.items',
            'customer',
            'creator',
            'approver',
        ]);

        return view('admin.quotes.show', compact('quote'));
    }

    public function update(SalesQuoteUpdateRequest $request, SalesQuote $quote): RedirectResponse
    {
        Gate::authorize('update', $quote);
        $this->quotes->updatePricing($quote, $request->validated());

        return back()->with('success', 'Quote pricing and terms saved.');
    }

    public function approve(SalesQuoteDecisionRequest $request, SalesQuote $quote): RedirectResponse
    {
        Gate::authorize('approve', $quote);
        $this->quotes->transition($quote, 'approved', $request->validated());

        return back()->with('success', 'Quote approved and ready for conversion.');
    }

    public function reject(SalesQuoteDecisionRequest $request, SalesQuote $quote): RedirectResponse
    {
        Gate::authorize('reject', $quote);
        $this->quotes->transition($quote, 'rejected', $request->validated());

        return back()->with('success', 'Quote rejected and retained in the audit trail.');
    }

    public function cancel(SalesQuoteDecisionRequest $request, SalesQuote $quote): RedirectResponse
    {
        Gate::authorize('cancel', $quote);
        $this->quotes->transition($quote, 'cancelled', $request->validated());

        return back()->with('success', 'Quote cancelled.');
    }

    public function convert(SalesQuoteConvertRequest $request, SalesQuote $quote): RedirectResponse
    {
        Gate::authorize('convert', $quote);
        $order = $this->quotes->convert(
            $quote,
            (string) $request->validated('idempotency_key'),
            $request->validated('expected_version'),
        );

        return redirect()
            ->route('admin.order-master.show', [$order->order_type, $order])
            ->with('success', 'Quote converted to a shared order.');
    }
}
