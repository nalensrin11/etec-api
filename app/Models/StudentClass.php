<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name', 'code', 'join_code', 'status', 'created_by', 'instructor_id'];

    public function users()
    {
        return $this->hasMany(User::class, 'class_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'class_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
