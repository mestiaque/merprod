<?php

namespace ME\MerchandisingSfl\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SampleComment extends Model
{
    protected $table = 'msfl_sample_comments';

    protected $fillable = ['sample_id', 'comment_date', 'comment', 'is_buyer_comment', 'attachment', 'commented_by'];

    protected $casts = [
        'comment_date' => 'date',
        'is_buyer_comment' => 'boolean',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commented_by');
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment ? Storage::disk(config('merchandising-sfl.upload_disk'))->url($this->attachment) : null;
    }
}
