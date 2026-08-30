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
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('full_name');
            $table->string('business_name')->nullable()->after('last_name');
            $table->string('phone_country_code', 8)->nullable()->after('email');
            $table->string('phone_number', 20)->nullable()->after('phone_country_code');
            $table->string('gender', 32)->nullable()->after('phone_number');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->unsignedSmallInteger('fiscal_year')->nullable()->after('date_of_birth');
            $table->unsignedInteger('full_time_employees')->nullable()->after('fiscal_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'last_name',
                'business_name',
                'phone_country_code',
                'phone_number',
                'gender',
                'date_of_birth',
                'fiscal_year',
                'full_time_employees',
            ]);
        });
    }
};
