<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('id_number', 60)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('rent_amount'); // minor units, per period
            $table->string('rent_period', 10)->default('month'); // month | year
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->string('status', 20)->default('draft'); // draft | active | ended | terminated | cancelled
            $table->date('ended_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'status']);
            $table->index('tenant_id');
            $table->index(['status', 'end_date']);
        });

        // One running lease per property, whatever the application does.
        DB::statement("create unique index leases_one_active_per_property on leases (property_id) where status = 'active'");

        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('status', 10)->default('pending'); // pending | partial | paid | void
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['lease_id', 'period_start']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('rent_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rent_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('method', 10)->default('cash'); // cash | bank | cheque | online | other
            $table->string('reference', 100)->nullable();
            $table->timestamps();
            $table->index('rent_charge_id');
            $table->index('paid_on');
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal'); // low | normal | high | urgent
            $table->string('status', 20)->default('open'); // open | in_progress | resolved | cancelled
            $table->unsignedBigInteger('cost')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
            $table->index('property_id');
            $table->index('lease_id');
        });
    }

    public function down(): void
    {
        foreach (['maintenance_requests', 'rent_payments', 'rent_charges', 'leases', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
