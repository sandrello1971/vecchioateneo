<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'email', 'active', 'token', 'subscribed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sub) {
            if (empty($sub->token)) {
                $sub->token = Str::uuid()->toString();
            }
        });
    }
}
