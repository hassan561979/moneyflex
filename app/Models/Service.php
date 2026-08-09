<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceStatus;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $name
 * @property string|null $description
 * @property string $price
 * @property ServiceStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property-read Customer $customer
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'description',
        'price',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * Mirrors the column defaults so a freshly created model already carries
     * them, instead of holding null until it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ServiceStatus::Active->value,
        'price' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ServiceStatus::class,
            // decimal:2 keeps the value a string, so it never passes through
            // a float and loses precision on the way to the response.
            'price' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
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
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStatus(Builder $query, ServiceStatus|string|null $status): Builder
    {
        if (blank($status)) {
            return $query;
        }

        return $query->where('status', $status instanceof ServiceStatus ? $status->value : $status);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCustomer(Builder $query, Customer|int|null $customer): Builder
    {
        if (blank($customer)) {
            return $query;
        }

        return $query->where('customer_id', $customer instanceof Customer ? $customer->id : $customer);
    }
}
