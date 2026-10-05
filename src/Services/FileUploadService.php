<?php

namespace ME\MerchandisingSfl\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stores tech packs, images and attachments on the package's upload disk. */
class FileUploadService
{
    /** Validation for every document upload (tech pack, BOM file, attachments). */
    public const RULES = ['file', 'mimes:pdf,xls,xlsx,doc,docx,zip,jpg,jpeg,png', 'max:20480'];

    public function store(?UploadedFile $file, string $folder, ?string $replacing = null): ?string
    {
        if (! $file) {
            return $replacing;
        }

        $this->delete($replacing);

        return $file->store('merchandising-sfl/' . $folder, config('merchandising-sfl.upload_disk'));
    }

    public function copy(?string $path, string $folder): ?string
    {
        $disk = Storage::disk(config('merchandising-sfl.upload_disk'));
        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $copy = 'merchandising-sfl/' . $folder . '/' . Str::random(40) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $copy);

        return $copy;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(config('merchandising-sfl.upload_disk'))->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path ? Storage::disk(config('merchandising-sfl.upload_disk'))->url($path) : null;
    }
}
