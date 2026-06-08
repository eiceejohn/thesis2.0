<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_grade_audits', function (Blueprint $table) {
            $table->unsignedInteger('male_learners')->default(0)->after('grade_level');
            $table->unsignedInteger('female_learners')->default(0)->after('male_learners');
        });
    }

    public function down(): void
    {
        Schema::table('school_grade_audits', function (Blueprint $table) {
            $table->dropColumn(['male_learners', 'female_learners']);
        });
    }
};
