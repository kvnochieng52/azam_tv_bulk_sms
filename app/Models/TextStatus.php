<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextStatus extends Model
{
    use HasFactory;
    const PROCESSING = 1;
    const SENDING = 2;
    const SENT = 3;
    const FAILED = 4;
    const CANCELLED = 5;
    const SCHEDULED = 6;
    const ERROR = 7;
    const PARTIAL = 8;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'text_status_name',
        'is_active',
        'order',
        'color_code',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get all texts with this status.
     */
    public function texts()
    {
        return $this->hasMany(Text::class, 'status', 'id');
    }

    /**
     * Get all queues with this status.
     */
    public function queues()
    {
        return $this->hasMany(Queue::class, 'status', 'id');
    }
}
