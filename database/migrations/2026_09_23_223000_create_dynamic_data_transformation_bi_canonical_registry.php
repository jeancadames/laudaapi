<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Company-owned canonical semantic model.
         *
         * Versions are immutable after publication. A company can therefore
         * evolve its LAUDA canonical model without changing the meaning of
         * historical mappings.
         *
         * Nothing in this registry assumes the legacy seven-domain Standard
         * Intake contract.
         */
        Schema::create(
            'data_transformation_bi_canonical_registry_versions',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'company_id'
                );

                /*
                 * Traceability only: the request that originated this model
                 * revision. The canonical model itself belongs to Company.
                 */
                $table->unsignedBigInteger(
                    'source_transformation_implementation_request_id'
                )->nullable();

                $table->unsignedInteger(
                    'version'
                );

                $table->string(
                    'status',
                    32
                )->default('draft');

                $table->text(
                    'notes'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'published_by_user_id'
                )->nullable();

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'company_id',
                        'version',
                    ],
                    'dtbi_crv_company_version_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_crv_company_status_ix'
                );

                $table->foreign(
                    'company_id',
                    'dtbi_crv_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'source_transformation_implementation_request_id',
                    'dtbi_crv_request_fk'
                )
                    ->references('id')
                    ->on('transformation_implementation_requests')
                    ->nullOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_crv_created_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_crv_updated_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'published_by_user_id',
                    'dtbi_crv_published_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'data_transformation_bi_canonical_entities',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'canonical_registry_version_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->string(
                    'entity_key',
                    100
                );

                $table->string(
                    'label',
                    191
                );

                $table->text(
                    'description'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('active');

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'canonical_registry_version_id',
                        'entity_key',
                    ],
                    'dtbi_ce_registry_key_uq'
                );

                $table->index(
                    [
                        'company_id',
                        'status',
                    ],
                    'dtbi_ce_company_status_ix'
                );

                $table->foreign(
                    'canonical_registry_version_id',
                    'dtbi_ce_registry_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_registry_versions'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_ce_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_ce_created_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_ce_updated_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'data_transformation_bi_canonical_fields',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'canonical_entity_id'
                );

                $table->string(
                    'field_key',
                    100
                );

                $table->string(
                    'label',
                    191
                );

                $table->string(
                    'data_type',
                    32
                )->default('text');

                $table->boolean(
                    'required'
                )->default(false);

                $table->boolean(
                    'is_identity'
                )->default(false);

                $table->text(
                    'description'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('active');

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'canonical_entity_id',
                        'field_key',
                    ],
                    'dtbi_cf_entity_key_uq'
                );

                $table->foreign(
                    'canonical_entity_id',
                    'dtbi_cf_entity_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_entities'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_cf_created_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_cf_updated_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );

        Schema::create(
            'data_transformation_bi_canonical_relationships',
            function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger(
                    'canonical_registry_version_id'
                );

                $table->unsignedBigInteger(
                    'company_id'
                );

                $table->unsignedBigInteger(
                    'from_canonical_entity_id'
                );

                $table->unsignedBigInteger(
                    'from_canonical_field_id'
                );

                $table->unsignedBigInteger(
                    'to_canonical_entity_id'
                );

                $table->unsignedBigInteger(
                    'to_canonical_field_id'
                );

                $table->string(
                    'relationship_type',
                    32
                )->default('many_to_one');

                $table->string(
                    'label',
                    191
                )->nullable();

                $table->text(
                    'description'
                )->nullable();

                $table->string(
                    'status',
                    32
                )->default('active');

                $table->unsignedInteger(
                    'sort_order'
                )->default(0);

                $table->unsignedBigInteger(
                    'created_by_user_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by_user_id'
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'canonical_registry_version_id',
                        'from_canonical_field_id',
                        'to_canonical_field_id',
                        'relationship_type',
                    ],
                    'dtbi_cr_relationship_uq'
                );

                $table->foreign(
                    'canonical_registry_version_id',
                    'dtbi_cr_registry_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_registry_versions'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'company_id',
                    'dtbi_cr_company_fk'
                )
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();

                $table->foreign(
                    'from_canonical_entity_id',
                    'dtbi_cr_from_entity_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_entities'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'from_canonical_field_id',
                    'dtbi_cr_from_field_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_fields'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'to_canonical_entity_id',
                    'dtbi_cr_to_entity_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_entities'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'to_canonical_field_id',
                    'dtbi_cr_to_field_fk'
                )
                    ->references('id')
                    ->on(
                        'data_transformation_bi_canonical_fields'
                    )
                    ->cascadeOnDelete();

                $table->foreign(
                    'created_by_user_id',
                    'dtbi_cr_created_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign(
                    'updated_by_user_id',
                    'dtbi_cr_updated_user_fk'
                )
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'data_transformation_bi_canonical_relationships'
        );

        Schema::dropIfExists(
            'data_transformation_bi_canonical_fields'
        );

        Schema::dropIfExists(
            'data_transformation_bi_canonical_entities'
        );

        Schema::dropIfExists(
            'data_transformation_bi_canonical_registry_versions'
        );
    }
};
