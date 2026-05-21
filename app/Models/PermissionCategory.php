<?php
// app/Models/PermissionCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class PermissionCategory extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'permission_categories';
    
    protected $fillable = [
        'name',
        'description',
    ];

    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all permissions that belong to this category.
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'category_id');
    }
}