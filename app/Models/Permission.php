<?php
// app/Models/Permission.php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    
    protected $fillable = [
        'name',
        'category_id'
    ];

   

    /**
     * Get the category that owns the permission.
     */
    public function category()
    {
        return $this->belongsTo(PermissionCategory::class, 'category_id');
    }

    /**
     * Scope a query to only include permissions by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId) {
            return $query->where('category_id', $categoryId);
        }

        return $query;
    }

    /**
     * Scope a query to only include permissions without category.
     */
    public function scopeWithoutCategory($query)
    {
        return $query->whereNull('category_id');
    }
}