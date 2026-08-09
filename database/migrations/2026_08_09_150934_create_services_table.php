<?php

declare(strict_types=1);

use App\Enums\ServiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained()
                // Removing a customer for good takes their services with it.
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // decimal, never float: money must not be stored approximately.
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status', 32)->default(ServiceStatus::Active->value);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Listing a customer's services filtered by status is the single
            // most frequent query in this API.
            $table->index(['customer_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
