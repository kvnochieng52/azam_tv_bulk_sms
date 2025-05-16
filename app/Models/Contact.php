<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'is_active',
        'created_by',
        'updated_by',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get all contact lists associated with this contact group.
     */
    public function contactLists()
    {
        return $this->hasMany(ContactList::class, 'contact_id');
    }
    
    /**
     * Get the user who created the contact.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get the user who last updated the contact.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    
    /**
     * Get the count of individual contacts in this group
     */
    public function getContactsCountAttribute()
    {
        return $this->contactLists()->where('is_active', 1)->count();
    }
    
    /**
     * Get all phone numbers from this contact group
     */
    public function getAllPhonesAttribute()
    {
        return $this->contactLists()
            ->where('is_active', 1)
            ->pluck('telephone')
            ->toArray();
    }
}
