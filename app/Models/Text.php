<?php

namespace App\Models;

use App\Models\User;
use App\Models\Queue;
use App\Models\TextStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Text extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'text_title',
        'contact_type',
        'recepient_contacts',
        'csv_file_path',
        'csv_file_name',
        'csv_file_columns',
        'contact_list',
        'message',
        'scheduled',
        'schedule_date',
        'status',
        'created_by',
        'updated_by',
        'contacts_count',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled' => 'boolean',
        'schedule_date' => 'datetime',
        'contacts_count' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get the text status associated with the text.
     */
    public function status()
    {
        return $this->belongsTo(TextStatus::class, 'status', 'id');
    }
    
    /**
     * Get the user who created the text.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    
    /**
     * Get the user who last updated the text.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
    
    /**
     * Get all queues associated with this text.
     */
    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
