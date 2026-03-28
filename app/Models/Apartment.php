<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Apartment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "landlord_id",
        "title",
        "description",
        "wilaya",
        "municipality",
        "price",
        "price_unit",
        "rooms",
        "bathrooms",
        "area",
        "is_active",
        "is_featured",
        "status",
        "views_count",
        "phone_clicks",
        "amenities",
        "images",
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        "is_active" => "boolean",
        "is_featured" => "boolean",
        "amenities" => "array", // Cast JSON column to array
        "images" => "array",    // Cast JSON column to array
        "price" => "decimal:2",
        "area" => "decimal:2",
    ];

    // Define relationships
    public function landlord()
    {
        return $this->belongsTo(User::class, "landlord_id");
    }

    public function favoritedBy()
    {
        return $this->hasMany(Favorite::class);
    }
}