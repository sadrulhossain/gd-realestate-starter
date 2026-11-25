<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_2nd', 150)->nullable();
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->text('description_2nd')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')->nullable()->constrained('developers')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('name_2nd', 200)->nullable();
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->text('description_2nd')->nullable();
            $table->string('address_line')->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->string('status', 20)->default('upcoming'); // upcoming | under_construction | completed
            $table->date('handover_date')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'status']);
            $table->index('developer_id');
            $table->index('location_id');
        });

        Schema::create('project_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_type_id')->constrained('property_types')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 150)->nullable();
            $table->string('name_2nd', 150)->nullable();
            $table->unsignedSmallInteger('beds')->nullable();
            $table->unsignedSmallInteger('baths')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('area_unit', 10)->default('sqft');
            $table->smallInteger('floor')->nullable();
            $table->unsignedBigInteger('price')->default(0); // minor units
            $table->string('status', 20)->default('available'); // available | reserved | sold
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status']);
        });

        // R2 left these as plain columns. A unit can back at most one property.
        Schema::table('properties', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('project_units')->restrictOnDelete();
            $table->unique('unit_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['unit_id']);
            $table->dropUnique(['unit_id']);
            $table->dropIndex(['project_id']);
        });
        Schema::dropIfExists('project_units');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('developers');
    }
};
