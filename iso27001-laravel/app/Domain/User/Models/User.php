<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User aggregate root.
 *
 * A.10: password is stored as bcrypt hash (never plaintext).
 * A.12: SoftDeletes preserves audit trail — records are never hard-deleted.
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'users';

    /** A.14: Explicit fillable — never use guarded: [] */
    protected $fillable = [
        'email',
        'password',
        'role',
    ];

    /** A.10: Never expose password or remember_token in serialization */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',  // Laravel 10+ auto-hashing
        'deleted_at'        => 'datetime',
    ];

    public const ROLES = ['admin', 'manager', 'analyst', 'viewer'];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /** A.12: SHA-256 email hash for events and logs — never raw PII */
    public function emailHash(): string
    {
        return hash('sha256', $this->email);
    }
}
