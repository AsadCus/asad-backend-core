<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_trips', function (Blueprint $table) {
            $table->id();
            $table->string('btr_no')->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // General requirement
            $table->string('work_type');
            $table->string('so_reference')->nullable();
            $table->string('project_name');
            $table->string('division')->nullable();
            $table->string('province');
            $table->string('city');
            $table->text('destination_address');
            $table->dateTime('depart_at');
            $table->dateTime('return_at');

            // Platform
            $table->string('hotel_ref')->nullable();
            $table->string('origin_terminal')->nullable();
            $table->string('dest_terminal')->nullable();
            $table->text('notes')->nullable();

            // Disbursement account
            $table->string('bank');
            $table->string('account_no');
            $table->string('account_holder');

            // Cost breakdown, captured as submitted — sections of {title, items:[{description,cost,qty,unit}]}.
            $table->json('cost_breakdown');
            $table->unsignedBigInteger('grand_total');

            // Travelling companions — [{name, jabatan}], free-form (no FK; jabatan is a label, not a role).
            $table->json('members')->nullable();

            $table->string('status')->default('pending_leader');
            $table->foreignId('leader_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('leader_decided_at')->nullable();
            $table->text('leader_note')->nullable();
            $table->foreignId('hc_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('hc_decided_at')->nullable();
            $table->text('hc_note')->nullable();
            $table->foreignId('finance_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finance_decided_at')->nullable();
            $table->text('finance_note')->nullable();

            // Disbursement — set once Finance pays out the approved grand total.
            $table->string('payment_status')->default('unpaid');
            $table->dateTime('paid_at')->nullable();

            // Post-trip report ("Laporan"): a multi-ledger reconciliation (income / expense /
            // settlement / ticket line items, see business_trip_report_items) submitted after the
            // trip, going through its own Leader → Finance approval — separate from the
            // disbursement approval above. These columns are a denormalized summary of the ledger
            // for fast list rendering; the line items are the source of truth.
            $table->unsignedBigInteger('total_income')->default(0);
            $table->unsignedBigInteger('actual_cost')->default(0); // expense + ticket
            $table->unsignedBigInteger('total_settlement')->default(0);
            $table->integer('variance')->default(0); // income - actual_cost - settlement
            $table->unsignedTinyInteger('report_percentage')->default(0);
            $table->dateTime('report_submitted_at')->nullable();

            $table->string('report_status')->nullable();
            $table->foreignId('report_leader_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->dateTime('report_leader_decided_at')->nullable();
            $table->text('report_leader_note')->nullable();
            $table->foreignId('report_finance_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('report_finance_decided_at')->nullable();
            $table->text('report_finance_note')->nullable();

            // Leftover (variance) handed back/reimbursed and confirmed received — only actionable
            // once the report itself is Approved.
            $table->boolean('balance_settled')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('status');
            $table->index('depart_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_trips');
    }
};
