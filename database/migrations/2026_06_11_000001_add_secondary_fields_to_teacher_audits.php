<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_imports', function (Blueprint $table) {
            $table->string('education_level')->default('Elementary')->after('school_year');
        });

        Schema::table('school_grade_audits', function (Blueprint $table) {
            $table->string('education_level')->default('Elementary')->after('school_code');
            $table->unsignedInteger('actual_classrooms')->default(0)->after('female_learners');
            $table->text('remarks')->nullable()->after('shortage');
            $table->index(['education_level', 'school_code', 'grade_level'], 'school_grade_education_index');
        });
    }

    public function down(): void
    {
        Schema::table('school_grade_audits', function (Blueprint $table) {
            $table->dropIndex('school_grade_education_index');
            $table->dropColumn(['education_level', 'actual_classrooms', 'remarks']);
        });

        Schema::table('audit_imports', function (Blueprint $table) {
            $table->dropColumn('education_level');
        });
    }
};
