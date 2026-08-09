<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 32)->nullable();
            $table->string('address')->nullable();
            // Stored as a string rather than a native enum column: adding a
            // case later is a code change instead of a table alteration.
            $table->string('status', 32)->default(CustomerStatus::Active->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            // Supports the default listing, which orders by newest first.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
