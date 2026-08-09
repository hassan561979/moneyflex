<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property CustomerStatus $status
 * @property-read Collection<int, Service> $services
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
        ];
    }

    /**
     * A soft delete is invisible to the database, so the foreign key cascade
     * never fires and the services would outlive the customer. Mirror the
     * deletion onto them, and undo it on restore.
     */
    protected static function booted(): void
    {
        static::deleting(function (Customer $customer): void {
            if (! $customer->isForceDeleting()) {
                $customer->services()->delete();
            }
        });

        static::restoring(function (Customer $customer): void {
            $customer->services()->onlyTrashed()->restore();
        });
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Case-insensitive match across the fields a user would search by.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStatus(Builder $query, CustomerStatus|string|null $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status instanceof CustomerStatus ? $status->value : $status);
    }
}
