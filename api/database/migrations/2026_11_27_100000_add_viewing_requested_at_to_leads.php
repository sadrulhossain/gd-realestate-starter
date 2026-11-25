<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The slot a visitor asked for on the public viewing form. It is a request, not a booking: staff turn it into a
        // viewing (which checks the agent's calendar) from the lead.
        Schema::table('leads', function (Blueprint $table) {
            $table->timestampTz('viewing_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('viewing_requested_at');
        });
    }
};
