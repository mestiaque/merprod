<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\OrderRequest;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Models\Order;
use ME\MerchandisingSfl\Models\Size;
use ME\MerchandisingSfl\Models\TnaPlan;
use ME\MerchandisingSfl\Services\DocumentNumberService;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\Lookups;

/** Order + BOM — buyer order header; PO lines live in OrderPoController. */
class OrderController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_order.list');

        $orders = Order::query()
            ->with(['buyer', 'season', 'merchandiser', 'currency'])
            ->withCount('pos')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('order_no', 'like', '%' . $request->search . '%')
                ->orWhere('buyer_order_ref', 'like', '%' . $request->search . '%')
                ->orWhereHas('pos', fn ($q) => $q->where('po_no', 'like', '%' . $request->search . '%'))))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $buyers = Lookups::buyers();

        return view('merchandising-sfl::admin.orders.index', compact('orders', 'buyers'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_order.add');

        $order = new Order(['order_date' => now(), 'merchandiser_id' => auth()->id()]);
        if ($inquiry = Inquiry::find($request->integer('inquiry_id'))) {
            $order->fill($inquiry->only(['buyer_id', 'season_id', 'merchandiser_id', 'factory_id']) + ['inquiry_id' => $inquiry->id]);
        }

        return view('merchandising-sfl::admin.orders.create', $this->formData() + compact('order'));
    }

    public function store(OrderRequest $request, DocumentNumberService $numbers): RedirectResponse
    {
        $data = Arr::except($request->validated(), 'attachment');
        $data['attachment'] = $this->files->store($request->file('attachment'), 'orders');

        $order = DB::transaction(fn () => Order::create($data + [
            'order_no' => $numbers->next('order', Order::class, 'order_no'),
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('msfl.orders.show', $order)->with('success', 'Order ' . $order->order_no . ' created — now add its PO lines.');
    }

    public function show(Order $order): View
    {
        $this->authorize('msfl_order.view');

        $order->load(['buyer', 'season', 'merchandiser', 'factory', 'inquiry', 'currency', 'confirmer', 'boms.style']);
        $tnaPlans = TnaPlan::where('order_id', $order->id)->where('status', '!=', 'cancelled')->get()->keyBy('style_id');

        return view('merchandising-sfl::admin.orders.show', $this->poLinesData($order) + compact('order', 'tnaPlans'));
    }

    /** What the PO lines table and its add / edit modals need (orders/partials/po-lines). */
    private function poLinesData(Order $order): array
    {
        $order->load(['pos' => fn ($q) => $q->orderBy('po_no')->orderBy('style_id'), 'pos.style', 'pos.color', 'pos.shipMode', 'pos.sizes']);

        // Size columns: every size used by a PO line, plus all active sizes for the PO form.
        $usedSizeIds = $order->pos->flatMap(fn ($po) => $po->sizes->pluck('size_id'))->unique();
        $allSizes = Size::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $usedSizeIds))
            ->orderBy('sort_order')->orderBy('name')->get();

        return [
            'sizeColumns' => $allSizes->whereIn('id', $usedSizeIds)->values(),
            'formSizes' => $allSizes->where('is_active', true)->values(),
            'styles' => Lookups::styles()->where('buyer_id', $order->buyer_id)->values(),
            'colors' => Lookups::colors(),
            'shipModes' => Lookups::shipModes(),
        ];
    }

    public function edit(Order $order): View|RedirectResponse
    {
        $this->authorize('msfl_order.edit');

        if (! $order->isEditable()) {
            return redirect()->route('msfl.orders.show', $order)->with('error', 'A ' . $order->statusLabel() . ' order cannot be edited');
        }

        // PO lines are edited on this page too (same table + modals as the order page).
        return view('merchandising-sfl::admin.orders.edit', $this->formData() + $this->poLinesData($order) + compact('order'));
    }

    public function update(OrderRequest $request, Order $order): RedirectResponse
    {
        abort_unless($order->isEditable(), 403);

        $data = Arr::except($request->validated(), 'attachment');

        if ((int) $data['buyer_id'] !== $order->buyer_id && $order->pos()->exists()) {
            return back()->withInput()->with('error', 'The buyer cannot be changed after PO lines are added');
        }

        $data['attachment'] = $this->files->store($request->file('attachment'), 'orders', $order->attachment);

        $order->update($data);

        return redirect()->route('msfl.orders.show', $order)->with('success', 'Order updated successfully.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('msfl_order.delete');

        if ($order->status !== 'draft') {
            return back()->with('error', 'Only a draft order can be deleted — cancel it instead');
        }

        $order->delete();

        return redirect()->route('msfl.orders.index')->with('success', 'Order deleted successfully.');
    }

    /** Confirm / Close / Cancel. */
    public function changeStatus(Request $request, Order $order): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(Order::STATUSES))]])['status'];
        $this->authorize($status === 'confirmed' ? 'msfl_order.approve' : 'msfl_order.edit');

        if (! $order->canMoveTo($status)) {
            return back()->with('error', 'A ' . $order->statusLabel() . ' order cannot be changed to ' . Order::STATUSES[$status][0]);
        }

        if ($status === 'confirmed' && ! $order->pos()->exists()) {
            return back()->with('error', 'Add at least one PO line before confirming the order');
        }

        $order->forceFill(['status' => $status] + ($status === 'confirmed' ? ['confirmed_by' => auth()->id(), 'confirmed_at' => now()] : []))->save();

        return back()->with('success', 'Order ' . strtolower(Order::STATUSES[$status][0]) . '.');
    }

    private function formData(): array
    {
        return [
            'buyers' => Lookups::buyers(),
            'seasons' => Lookups::seasons(),
            'merchandisers' => Lookups::merchandisers(),
            'factories' => Lookups::factories(),
            'inquiries' => Lookups::inquiries(),
            'currencies' => Lookups::currencies(),
        ];
    }
}
