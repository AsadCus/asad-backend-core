<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Consolidated migration for maids table with all fields
     */
    public function up(): void
    {
        Schema::create('maids', function (Blueprint $table) {
            $table->id();
            $table->string('maid_number')->unique()->nullable();
            $table->string('bio_code')->nullable()->unique();
            $table->string('passport_number')->nullable();

            // ========================================
            // SECTION A1: PROFILE / PERSONAL INFORMATION
            // ========================================
            $table->string('name', 100);
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->decimal('height', 5, 2)->nullable()->comment('In cm');
            $table->decimal('weight', 5, 2)->nullable()->comment('In kg');
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->text('address')->nullable();
            $table->string('repatriation_port_airport', 100)->nullable();
            $table->string('contact_number_home_country', 30)->nullable();
            $table->foreignId('religion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_level_id')->constrained()->cascadeOnDelete();
            $table->string('marital_status', 50)->nullable();
            $table->unsignedTinyInteger('number_of_siblings')->nullable();
            $table->unsignedTinyInteger('number_of_children')->nullable();
            $table->string('children_ages', 50)->nullable()->comment('Comma-separated ages of children');
            $table->string('photo_url')->nullable()->comment('Photo uploaded from document or manual upload');

            // ========================================
            // SECTION A2: MEDICAL HISTORY
            // ========================================
            $table->boolean('mental_illness')->default(false);
            $table->boolean('tuberculosis')->default(false);
            $table->boolean('epilepsy')->default(false);
            $table->boolean('malaria')->default(false);
            $table->boolean('asthma')->default(false);
            $table->boolean('operations')->default(false);
            $table->boolean('diabetes')->default(false);
            $table->boolean('hypertension')->default(false);
            $table->boolean('heart_disease')->default(false);
            $table->text('other_illnesses')->nullable();

            // ========================================
            // SECTION A3: OTHERS
            // ========================================
            $table->unsignedTinyInteger('rest_days_per_month')->default(4);
            $table->text('other_remarks')->nullable();

            // ========================================
            // SECTION B: EVALUATION METHODS
            // ========================================
            $table->boolean('eval_declaration_no_eval')->default(false)->comment('Declaration: No evaluation conducted');

            // Singapore-based assessments
            $table->boolean('eval_sg_interview')->default(false)->comment('Singapore: Interview');
            $table->boolean('eval_sg_phone')->default(false)->comment('Singapore: Telephone interview');
            $table->boolean('eval_sg_video')->default(false)->comment('Singapore: Video call interview');
            $table->boolean('eval_sg_in_person')->default(false)->comment('Singapore: In-person interview');
            $table->boolean('eval_sg_in_person_observed')->default(false)->comment('Singapore: In-person observed');

            // Overseas-based assessments
            $table->boolean('eval_overseas_interview')->default(false)->comment('Overseas: Interview');
            $table->string('eval_overseas_name')->nullable()->comment('Overseas assessor name');
            $table->string('eval_overseas_cert')->nullable()->comment('Overseas assessor certification');
            $table->boolean('eval_overseas_phone')->default(false)->comment('Overseas: Telephone interview');
            $table->boolean('eval_overseas_video')->default(false)->comment('Overseas: Video call interview');
            $table->boolean('eval_overseas_in_person')->default(false)->comment('Overseas: In-person interview');
            $table->boolean('eval_overseas_in_person_observed')->default(false)->comment('Overseas: In-person observed');

            // ========================================
            // SECTION C: EMPLOYMENT HISTORY
            // ========================================
            $table->json('employment_history')->nullable()->comment('Employment history data from Section C (JSON array)');
            $table->boolean('singapore_experience')->default(false)->comment('Has work experience in Singapore');
            $table->unsignedTinyInteger('experience_years')->nullable()->comment('Total years of work experience');
            $table->text('employment_feedback')->nullable()->comment('Employer feedback from Section C3');
            $table->json('employer_feedback')->nullable();

            // ========================================
            // SECTION D: SKILLS ASSESSMENT
            // ========================================
            $table->json('skills_assessment_singapore')->nullable()->comment('Skills assessment from Singapore evaluation');
            $table->json('skills_assessment_overseas')->nullable()->comment('Skills assessment from overseas evaluation');

            // ========================================
            // SECTION E: AVAILABILITY
            // ========================================
            $table->text('availability_remarks')->nullable()->comment('Availability remarks from Section E');
            $table->boolean('interview_not_available')->default(false)->comment('FDW is not available for interview');
            $table->boolean('interview_by_phone')->default(false)->comment('FDW can be interviewed by phone');
            $table->boolean('interview_by_video')->default(false)->comment('FDW can be interviewed by video-conference');
            $table->boolean('interview_in_person')->default(false)->comment('FDW can be interviewed in person');

            // ========================================
            // SYSTEM FIELDS
            // ========================================
            $table->enum('status', ['available', 'interviewing', 'pending', 'assigned'])->default('available');
            $table->timestamp('interview_date')->nullable();
            $table->dateTime('interview_end_date')->nullable();
            $table->string('status_job_id')->nullable()->comment('Stores the job ID to prevent duplicate jobs and track active status jobs');
            $table->timestamp('pending_until')->nullable()->comment('Deadline for document finalization. Auto-assign to ASSIGNED after this date.');
            $table->text('pending_reason')->nullable()->comment('Reason or notes for pending status');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->decimal('remaining_loan', 10, 2)->nullable()->comment('Remaining loan amount');
            $table->decimal('monthly_salary', 10, 2)->nullable()->comment('Monthly salary of maid');
            $table->decimal('cost_of_maid', 10, 2)->nullable()->comment('Total cost of maid (auto-calculated: remaining_loan * monthly_salary)');

            // ========================================
            // INDEXES FOR PERFORMANCE
            // ========================================
            $table->index('date_of_birth');
            $table->index('country_id');
            $table->index('religion_id');
            $table->index('education_level_id');
            $table->index('marital_status');
            $table->index('status');
            $table->index('singapore_experience');
            $table->index('supplier_id');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maids');
    }
};
