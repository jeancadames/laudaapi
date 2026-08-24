<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->string('business_activity_type', 20)
                ->nullable()
                ->after('organization_name')
                ->index();

            $table->string('business_sector', 50)
                ->nullable()
                ->after('business_activity_type')
                ->index();

            $table->string('business_sector_other', 120)
                ->nullable()
                ->after('business_sector');

            $table->string('customer_market', 10)
                ->nullable()
                ->after('business_sector_other')
                ->index();

            $table->json('sales_channels')
                ->nullable()
                ->after('customer_market');

            $table->string('sales_channel_other', 120)
                ->nullable()
                ->after('sales_channels');

            $table->json('logistics_operation_types')
                ->nullable()
                ->after('sales_channel_other');

            $table->string('logistics_operation_other', 120)
                ->nullable()
                ->after('logistics_operation_types');

            $table->text('business_activity_description')
                ->nullable()
                ->after('logistics_operation_other');

            $table->timestamp('business_profile_completed_at')
                ->nullable()
                ->after('business_activity_description');
        });
    }

    public function down(): void
    {
        Schema::table('diagnosis_assessments', function (Blueprint $table): void {
            $table->dropIndex(['business_activity_type']);
            $table->dropIndex(['business_sector']);
            $table->dropIndex(['customer_market']);

            $table->dropColumn([
                'business_activity_type',
                'business_sector',
                'business_sector_other',
                'customer_market',
                'sales_channels',
                'sales_channel_other',
                'logistics_operation_types',
                'logistics_operation_other',
                'business_activity_description',
                'business_profile_completed_at',
            ]);
        });
    }
};
