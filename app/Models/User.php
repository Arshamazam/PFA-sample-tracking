<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'cnic',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    // Relationships -------------------------------------------------------

    /** @return HasMany<SamplingEvent, $this> */
    public function samplingEvents(): HasMany
    {
        return $this->hasMany(SamplingEvent::class, 'fso_id');
    }

    /** @return HasMany<RapidTest, $this> */
    public function rapidTests(): HasMany
    {
        return $this->hasMany(RapidTest::class, 'fso_id');
    }

    /** @return HasMany<CustodyEvent, $this> */
    public function custodyEvents(): HasMany
    {
        return $this->hasMany(CustodyEvent::class, 'actor_id');
    }

    /** @return HasMany<LabResult, $this> */
    public function analyzedResults(): HasMany
    {
        return $this->hasMany(LabResult::class, 'analyst_id');
    }

    /** @return HasMany<LabResult, $this> */
    public function verifiedResults(): HasMany
    {
        return $this->hasMany(LabResult::class, 'verified_by_id');
    }
}
