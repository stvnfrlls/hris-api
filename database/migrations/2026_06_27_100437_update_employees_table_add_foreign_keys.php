<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['department', 'position', 'employment_type']);

            $table->foreignId('department_id')->after('employee_code')->nullable()->constrained();
            $table->foreignId('position_id')->after('department_id')->nullable()->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['position_id']);
            $table->dropColumn(['department_id', 'position_id']);

            $table->string('department');
            $table->string('position');
            $table->enum('employment_type', ['full_time', 'part_time', 'contractual']);
        });
    }
};
