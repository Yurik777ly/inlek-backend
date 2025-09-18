<?php

namespace App\Models;

use Carbon\Carbon;
use App\Http\Dto\Auth\AuthDTO;
use App\Http\Dto\Profile\ProfileDTO;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Cart;

class User extends Authenticatable
/**
 * Class User
 *
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $gender
 * @property string|null $birthday
 * @property bool $status_notifications
 * @property bool $accept_policy
 *
 * @package App\Models
 */
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;
	protected $table = 'users';

	protected $casts = [
		'email_verified_at' => 'datetime',
		'status_notifications' => 'bool',
		'accept_policy' => 'bool'
	];

	protected $fillable = [
		'name',
		'email',
		'email_verified_at',
		'password',
		'phone',
		'first_name',
		'last_name',
		'gender',
		'birthday',
		'status_notifications',
		'accept_policy'
	];

    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'email_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'user_id');
    }
}
