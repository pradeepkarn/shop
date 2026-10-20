<?php

namespace App\Models;

use Database\Factories\SellerDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $seller_profile_id
 * @property string $type
 * @property string $path
 * @property string $original_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SellerProfile $sellerProfile
 */
class SellerDocument extends Model
{
    /** @use HasFactory<SellerDocumentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'seller_profile_id',
        'type',
        'path',
        'original_name',
    ];

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * Delete the associated physical file from storage.
     */
    public function deleteFile(): void
    {
        if ($this->path && Storage::disk('local')->exists($this->path)) {
            Storage::disk('local')->delete($this->path);
        }
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (SellerDocument $document): void {
            $document->deleteFile();
        });
    }
}
