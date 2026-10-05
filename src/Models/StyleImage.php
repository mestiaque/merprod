<?php

namespace ME\MerchandisingSfl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StyleImage extends Model
{
    public const TYPES = ['front' => 'Front', 'back' => 'Back', 'detail' => 'Detail', 'artwork' => 'Artwork', 'other' => 'Other'];

    protected $table = 'msfl_style_images';

    protected $fillable = ['style_id', 'path', 'type', 'caption', 'uploaded_by'];

    public function style(): BelongsTo
    {
        return $this->belongsTo(Style::class);
    }

    public function url(): string
    {
        return Storage::disk(config('merchandising-sfl.upload_disk'))->url($this->path);
    }
}
