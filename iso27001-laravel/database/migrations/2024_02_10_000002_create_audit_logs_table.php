<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the audit_logs table.
 * A.12: Append-only — no UPDATE or DELETE in application layer.
 *       Indexes on action, performed_by, and resource for compliance queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 100);
            $table->string('performed_by', 255);
            $table->string('resource_type', 100);
            $table->string('resource_id', 255);
            $table->json('changes')->default('[]');
            $table->string('ip_address', 45)->nullable();
            $table->string('correlation_id', 36);
            $table->timestamp('created_at');

            // No updated_at — immutable records
            $table->index('action');
            $table->index('performed_by');
            $table->index(['resource_type', 'resource_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
