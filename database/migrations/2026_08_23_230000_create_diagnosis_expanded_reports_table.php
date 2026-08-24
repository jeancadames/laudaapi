<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'diagnosis_expanded_reports',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('diagnosis_assessment_id')
                    ->constrained('diagnosis_assessments')
                    ->cascadeOnDelete();

                /*
                 * Versionado histórico.
                 * Una nueva publicación futura puede producir una nueva
                 * versión sin reescribir el contenido publicado anterior.
                 */
                $table->unsignedInteger('version')
                    ->default(1);

                $table->string('status', 30)
                    ->default('draft')
                    ->index();

                $table->foreignId('generated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('reviewed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                 * Snapshot comercial del entregable.
                 *
                 * NO existe invoice_id aquí en R1-C1A.
                 * El puente de facturación se resolverá en R1-C2 sin forzar
                 * la creación de Company/Subscriber durante el diagnóstico.
                 */
                $table->string('currency', 3)
                    ->default('DOP');

                $table->decimal('subtotal', 12, 2)
                    ->default(0);

                $table->decimal('tax_rate', 6, 3)
                    ->default(0);

                $table->decimal('tax_amount', 12, 2)
                    ->default(0);

                $table->decimal('total', 12, 2)
                    ->default(0);

                $table->string('methodology_version', 20)
                    ->nullable();

                /*
                 * source_snapshot congela exactamente el contexto con el que
                 * se produjo esta versión.
                 *
                 * sections contiene el borrador estructurado editable.
                 */
                $table->json('source_snapshot');
                $table->json('sections');

                $table->text('review_notes')
                    ->nullable();

                $table->timestamp('reviewed_at')
                    ->nullable();

                $table->timestamp('published_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'diagnosis_assessment_id',
                        'version',
                    ],
                    'diag_exp_reports_assessment_version_uq'
                );

                $table->index(
                    [
                        'diagnosis_assessment_id',
                        'status',
                    ],
                    'diag_exp_reports_assessment_status_idx'
                );

                $table->index(
                    [
                        'status',
                        'published_at',
                    ],
                    'diag_exp_reports_status_published_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'diagnosis_expanded_reports'
        );
    }
};
