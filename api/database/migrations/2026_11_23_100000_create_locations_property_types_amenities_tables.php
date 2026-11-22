<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('name');
            $table->string('name_2nd')->nullable();
            $table->string('slug', 120);
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
            $table->index(['is_active', 'sort_order']);
        });
        // NULL parents are distinct in a unique index, so root slugs need their own partial index.
        DB::statement('CREATE UNIQUE INDEX locations_root_slug_unique ON locations (slug) WHERE parent_id IS NULL');

        Schema::create('property_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('name_2nd')->nullable();
            $table->string('group', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_2nd')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('group', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('area_unit', 10)->default('sqft')->after('currency_symbol');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('area_unit');
        });
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('property_types');
        Schema::dropIfExists('locations');
    }
};
