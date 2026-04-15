<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'recorded_by')) {
                $table->unsignedBigInteger('recorded_by')->nullable()->after('user_id');
                $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('paid_at');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['vehicle_id', 'status', 'end_date'], 'idx_sub_vehicle_status_end');
            $table->index(['user_id', 'status', 'end_date'], 'idx_sub_user_status_end');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_sub_vehicle_status_end');
            $table->dropIndex('idx_sub_user_status_end');
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'recorded_by')) {
                $table->dropForeign(['recorded_by']);
                $table->dropColumn('recorded_by');
            }

            if (Schema::hasColumn('payments', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};