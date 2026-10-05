<?php

namespace ME\MerchandisingSfl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use ME\MerchandisingSfl\Http\Requests\StyleRequest;
use ME\MerchandisingSfl\Models\Inquiry;
use ME\MerchandisingSfl\Models\Style;
use ME\MerchandisingSfl\Models\StyleImage;
use ME\MerchandisingSfl\Services\FileUploadService;
use ME\MerchandisingSfl\Support\Lookups;

/** Dev (R&D) — Tech Pack / Style. */
class StyleController extends Controller
{
    private const FILES = ['tech_pack_file', 'artwork_file', 'size_chart_file'];

    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('msfl_style.list');

        $styles = Style::query()
            ->with(['buyer', 'season', 'productType', 'merchandiser'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('style_no', 'like', '%' . $request->search . '%')
                ->orWhere('name', 'like', '%' . $request->search . '%')))
            ->when($request->filled('buyer_id'), fn ($q) => $q->where('buyer_id', $request->buyer_id))
            ->when($request->filled('status'), fn ($q) => $q->where('development_status', $request->status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $buyers = Lookups::buyers();

        return view('merchandising-sfl::admin.styles.index', compact('styles', 'buyers'));
    }

    public function create(Request $request): View
    {
        $this->authorize('msfl_style.add');

        // "Create Tech Pack" from an inquiry pre-fills what the inquiry already knows.
        $style = null;
        if ($inquiry = Inquiry::find($request->integer('inquiry_id'))) {
            $style = new Style([
                'inquiry_id' => $inquiry->id,
                'buyer_id' => $inquiry->buyer_id,
                'season_id' => $inquiry->season_id,
                'merchandiser_id' => $inquiry->merchandiser_id,
                'product_type_id' => $inquiry->product_type_id,
                'style_no' => $inquiry->style_ref,
                'name' => $inquiry->style_ref,
                'description' => $inquiry->description,
            ]);
        }

        return view('merchandising-sfl::admin.styles.create', $this->formData() + compact('style'));
    }

    public function store(StyleRequest $request): RedirectResponse
    {
        $data = Arr::except($request->validated(), self::FILES);
        foreach (self::FILES as $file) {
            $data[$file] = $this->files->store($request->file($file), 'styles');
        }
        $data['is_active'] = $data['is_active'] ?? true;
        $data['created_by'] = auth()->id();

        $style = Style::create($data);

        return redirect()->route('msfl.styles.show', $style)->with('success', 'Style ' . $style->style_no . ' created successfully.');
    }

    public function show(Style $style): View
    {
        $this->authorize('msfl_style.view');

        $style->load([
            'buyer', 'inquiry', 'season', 'merchandiser', 'productType', 'washType', 'images',
            'costSheets', 'boms.order', 'samples.sampleType', 'orderPos.order', 'orderPos.color',
        ]);

        return view('merchandising-sfl::admin.styles.show', compact('style'));
    }

    public function edit(Style $style): View
    {
        $this->authorize('msfl_style.edit');

        return view('merchandising-sfl::admin.styles.edit', $this->formData() + compact('style'));
    }

    public function update(StyleRequest $request, Style $style): RedirectResponse
    {
        $data = Arr::except($request->validated(), self::FILES);
        foreach (self::FILES as $file) {
            $data[$file] = $this->files->store($request->file($file), 'styles', $style->{$file});
        }
        $data['is_active'] = $data['is_active'] ?? true;

        $style->update($data);

        return redirect()->route('msfl.styles.show', $style)->with('success', 'Style updated successfully.');
    }

    public function destroy(Style $style): RedirectResponse
    {
        $this->authorize('msfl_style.delete');

        if ($style->orderPos()->exists() || $style->boms()->exists() || $style->samples()->exists()) {
            return back()->with('error', 'This style is used in an order, BOM or sample — it cannot be deleted');
        }

        $style->delete();

        return redirect()->route('msfl.styles.index')->with('success', 'Style deleted successfully.');
    }

    public function storeImage(Request $request, Style $style): RedirectResponse
    {
        $this->authorize('msfl_style.edit');

        $data = $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'max:5120'],
            'type' => ['required', 'in:' . implode(',', array_keys(StyleImage::TYPES))],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($request->file('images') as $image) {
            $style->images()->create([
                'path' => $this->files->store($image, 'styles/images'),
                'type' => $data['type'],
                'caption' => $data['caption'] ?? null,
                'uploaded_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Image(s) uploaded successfully.');
    }

    public function destroyImage(Style $style, StyleImage $image): RedirectResponse
    {
        $this->authorize('msfl_style.edit');
        abort_unless($image->style_id === $style->id, 404);

        $this->files->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image deleted successfully.');
    }

    private function formData(): array
    {
        return [
            'buyers' => Lookups::buyers(),
            'inquiries' => Lookups::inquiries(),
            'seasons' => Lookups::seasons(),
            'merchandisers' => Lookups::merchandisers(),
            'productTypes' => Lookups::productTypes(),
            'washTypes' => Lookups::washTypes(),
        ];
    }
}
