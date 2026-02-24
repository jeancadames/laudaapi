<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_tax_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // -------------------------
            // Identidad / facturación base
            // -------------------------
            $table->string('legal_name');
            $table->string('trade_name')->nullable();

            $table->string('country_code', 2)->default('DO');

            $table->string('tax_id')->nullable();             // RNC / Cédula
            $table->string('tax_id_type')->default('RNC');    // RNC/CEDULA/etc

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();

            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_contact_name')->nullable();

            $table->boolean('tax_exempt')->default(false);
            $table->decimal('default_itbis_rate', 6, 3)->default(18.000);

            $table->json('meta')->nullable();

            // -------------------------
            // DGII / Identidad tributaria
            // -------------------------
            $table->enum('taxpayer_type', ['persona_fisica', 'persona_juridica'])
                ->nullable()
                ->index()
                ->comment('Tipo contribuyente DGII');

            $table->enum('tax_regime', ['general', 'rst', 'special'])
                ->default('general')
                ->index()
                ->comment('Regimen DGII');

            // ✅ Ahora sí: este campo queda físicamente DESPUÉS de tax_regime (por orden de declaración)
            $table->foreignId('fiscal_year_end_id')
                ->nullable()
                ->constrained('fiscal_year_end_catalog')
                ->nullOnDelete();

            $table->enum('rst_modality', ['ingresos', 'compras'])
                ->nullable()
                ->comment('Solo si tax_regime=rst');

            $table->string('rst_category', 10)
                ->nullable()
                ->comment('Clasificacion RST (RS1/RS2/...)');

            // -------------------------
            // Actividad económica (DGII)
            // -------------------------
            $table->string('economic_activity_primary_code', 20)
                ->nullable()
                ->index()
                ->comment('Codigo actividad principal DGII');

            $table->string('economic_activity_primary_name', 255)
                ->nullable()
                ->comment('Nombre actividad principal DGII');

            $table->json('economic_activities_secondary')
                ->nullable()
                ->comment('Actividades secundarias DGII (json)');

            // -------------------------
            // Facturación / comprobantes
            // -------------------------
            $table->enum('invoicing_mode', ['ncf', 'ecf', 'both'])
                ->nullable()
                ->comment('Modo facturacion: NCF/ECF/both');

            $table->string('dgii_status', 50)
                ->nullable()
                ->index()
                ->comment('Estado DGII (opcional / sincronizacion futura)');

            $table->date('dgii_registered_on')
                ->nullable()
                ->comment('Fecha registro DGII (opcional)');

            $table->timestamps();

            $table->unique('company_id');

            $table->index(
                ['country_code', 'tax_id', 'company_id', 'fiscal_year_end_id'],
                'idx_company_fye'
            );
        });
    }

    public function down(): void
    {
        // En down() NO puedes dropear la tabla y luego intentar alterarla.
        Schema::dropIfExists('company_tax_profiles');
    }
};