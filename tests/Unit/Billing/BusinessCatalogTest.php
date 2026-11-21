<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\BusinessCatalogRules;
use App\Services\Billing\BusinessQuoteCalculator;
use Database\Seeders\BusinessCatalogSeeder;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BusinessCatalogTest extends TestCase
{
    private function selection(array $overrides = []): array
    {
        return array_replace(['offer' => 'business', 'level' => 'initial', 'cycle' => 'monthly', 'implementation' => 'guided', 'addons' => [], 'discount' => null], $overrides);
    }

    private function quote(array $selection = [], ?array $catalog = null, ?int $company = null): array
    {
        return (new BusinessQuoteCalculator)->calculate($catalog ?? BusinessCatalogSeeder::initialPayload(), $this->selection($selection), new DateTimeImmutable('2026-11-06T12:00:00Z'), $company);
    }

    public function test_capacity_policy_is_explicit_and_editable_without_repricing(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $original = $this->quote([], $catalog);
        $this->assertSame(BusinessCatalogRules::INITIAL_CAPACITY_POLICY, $original['capacity_policy']);
        $catalog['capacity_policy']['users'] = 'per_solution';
        $catalog['capacity_policy']['owner_counts'] = false;
        $changed = $this->quote([], $catalog);
        $this->assertSame($catalog['capacity_policy'], $changed['capacity_policy']);
        foreach (['capacity', 'recurring_cents', 'one_time_cents', 'taxes'] as $key) {
            $this->assertSame($original[$key], $changed[$key]);
        }
        $this->assertSame('shared_identity', $original['capacity_policy']['users']);
    }

    public function test_legacy_catalog_preview_has_no_invented_policy_but_cannot_be_published(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        unset($catalog['capacity_policy']);
        $catalog['tax_rate'] = 0;
        $catalog['quote_valid_days'] = 15;
        $catalog['terms'] = 'Synthetic terms';
        $this->assertNull($this->quote([], $catalog)['capacity_policy']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('capacity_policy:');
        (new BusinessCatalogRules)->validate($catalog, true);
    }

    public static function invalidCapacityPolicies(): array
    {
        return [
            'unsupported version' => ['schema_version', 2],
            'string version' => ['schema_version', '1'],
            'unknown user mode' => ['users', 'by_email'],
            'owner must be boolean' => ['owner_counts', 'false'],
            'physical registers unsupported' => ['registers', 'physical_registers'],
            'no per branch multiplication' => ['scope', 'branch'],
            'only active branches' => ['branches', 'all_branches'],
            'cannot include cantina' => ['excluded_channels', ['autoservicio']],
            'unknown field' => ['unlimited', true],
            'missing user mode' => ['users', null],
        ];
    }

    #[DataProvider('invalidCapacityPolicies')]
    public function test_invalid_policy_is_rejected_before_calculating(string $field, mixed $value): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['capacity_policy'][$field] = $value;
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('capacity_policy');
        $this->quote([], $catalog);
    }

    public function test_initial_catalog_is_a_valid_draft_but_cannot_be_published_without_review(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        (new BusinessCatalogRules)->validate($catalog);
        $this->assertCount(7, $catalog['offers']);
        $this->expectException(DomainException::class);
        (new BusinessCatalogRules)->validate($catalog, true);
    }

    public function test_initial_individuals_and_packages_have_the_agreed_monthly_prices(): void
    {
        foreach (['ecf' => 150000, 'pos' => 150000, 'cumplimiento' => 100000, 'bys' => 100000, 'fiscal' => 200000, 'comercial' => 250000, 'business' => 350000] as $offer => $price) {
            $quote = $this->quote(['offer' => $offer]);
            $this->assertSame($price, $quote['recurring_cents']);
            $this->assertSame(0, $quote['one_time_cents']);
            $this->assertSame(500, $quote['capacity']['documents']);
        }
    }

    public function test_capacity_is_not_multiplied_by_four_products_or_implementation_hours(): void
    {
        $quote = $this->quote(['implementation' => 'managed', 'addons' => ['hours' => 4]]);
        $this->assertSame(['documents' => 500, 'branches' => 1, 'registers' => 2, 'users' => 5], $quote['capacity']);
        $this->assertSame(2100000, $quote['one_time_cents']);
        $this->assertSame(350000, $quote['recurring_cents']);
    }

    public function test_branch_addon_includes_two_registers_without_a_second_charge(): void
    {
        $quote = $this->quote(['addons' => ['branch' => 1]]);
        $this->assertSame(400000, $quote['recurring_cents']);
        $this->assertSame(2, $quote['capacity']['branches']);
        $this->assertSame(4, $quote['capacity']['registers']);
        $this->assertArrayNotHasKey('register', $quote['addons']);
    }

    public function test_users_and_documents_are_blocks(): void
    {
        $quote = $this->quote(['addons' => ['documents' => 2, 'users' => 3, 'register' => 1]]);
        $this->assertSame(625000, $quote['recurring_cents']);
        $this->assertSame(1500, $quote['capacity']['documents']);
        $this->assertSame(20, $quote['capacity']['users']);
        $this->assertSame(3, $quote['capacity']['registers']);
    }

    public function test_annual_discount_only_affects_fixed_recurrence(): void
    {
        $quote = $this->quote(['cycle' => 'yearly', 'implementation' => 'assisted', 'addons' => ['documents' => 1, 'hours' => 1]]);
        $this->assertSame(4320000, $quote['recurring_cents']);
        $this->assertSame(480000, $quote['annual_discount_cents']);
        $this->assertSame(750000, $quote['one_time_cents']);
        $this->assertSame('monthly', $quote['capacity_cycle']);
        $this->assertSame(1000, $quote['capacity']['documents']);
    }

    public function test_explicit_annual_price_is_not_discounted_twice(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['business']['levels']['initial']['annual_cents'] = 3000000;
        $quote = $this->quote(['cycle' => 'yearly', 'addons' => ['documents' => 1]], $catalog);
        $this->assertSame(3540000, $quote['recurring_cents']);
        $this->assertSame(60000, $quote['annual_discount_cents']);
        $this->assertSame('explicit', $quote['annual_price_method']);
    }

    public function test_zero_is_a_price_and_null_is_pending(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['business']['levels']['initial']['monthly_cents'] = 0;
        $this->assertSame(0, $this->quote([], $catalog)['recurring_cents']);
        $catalog['offers']['business']['levels']['initial']['monthly_cents'] = null;
        $this->expectException(DomainException::class);
        $this->quote([], $catalog);
    }

    public function test_unconfigured_growth_prices_are_not_invented(): void
    {
        $this->expectException(DomainException::class);
        $this->quote(['offer' => 'ecf', 'level' => 'growth']);
    }

    public function test_pos_includes_ecommerce_and_no_separate_base_line(): void
    {
        $quote = $this->quote(['offer' => 'pos']);
        $this->assertTrue($quote['pos_includes_standard_ecommerce']);
        $this->assertSame(['pos'], array_keys($quote['products']));
        $this->assertSame(150000, $quote['recurring_cents']);
    }

    public function test_capacity_does_not_grant_pos_to_a_fiscal_customer(): void
    {
        $quote = $this->quote(['offer' => 'fiscal']);
        $this->assertSame(0, $quote['capacity']['registers']);
        $this->assertFalse($quote['pos_includes_standard_ecommerce']);
        $this->expectException(DomainException::class);
        $this->quote(['offer' => 'fiscal', 'addons' => ['branch' => 1]]);
    }

    public function test_growth_is_recommended_when_cheaper_than_document_blocks(): void
    {
        $quote = $this->quote(['addons' => ['documents' => 4]]);
        $this->assertSame(550000, $quote['recurring_cents']);
        $this->assertSame('growth', $quote['recommendation']['level']);
        $this->assertSame(50000, $quote['recommendation']['savings_cents']);
        $this->assertTrue($quote['recommendation']['requires_acceptance']);
        $this->assertSame('initial', $quote['selection']['level']);
    }

    public function test_recommendation_preserves_one_time_scope_and_checks_all_dimensions(): void
    {
        $quote = $this->quote(['addons' => ['documents' => 4, 'hours' => 2]]);
        $this->assertSame(['hours' => 2], $quote['recommendation']['selection']['addons']);
        $alternative = $this->quote(['addons' => ['documents' => 4, 'users' => 30]])['recommendation'];
        $this->assertNotNull($alternative);
        $recommended = $this->quote($alternative['selection']);
        $this->assertGreaterThanOrEqual(155, $recommended['capacity']['users']);
    }

    public function test_recommends_larger_level_with_remaining_addons(): void
    {
        $quote = $this->quote(['addons' => ['documents' => 29]]);
        $this->assertSame('scale', $quote['recommendation']['level']);
        $this->assertSame(1300000, $quote['recommendation']['recurring_cents']);
        $this->assertSame(10, $quote['recommendation']['selection']['addons']['documents']);
        $this->assertSame(500000, $quote['recommendation']['savings_cents']);
    }

    public function test_editing_block_size_changes_capacity_without_changing_price(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['addons']['documents']['block_size'] = 800;
        $quote = $this->quote(['addons' => ['documents' => 2]], $catalog);
        $this->assertSame(2100, $quote['capacity']['documents']);
        $this->assertSame(450000, $quote['recurring_cents']);
    }

    public function test_calculated_package_preserves_component_prices(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['fiscal']['pricing_method'] = 'sum';
        $catalog['offers']['fiscal']['package_discount_bps'] = 2000;
        $catalog['offers']['fiscal']['levels']['initial']['monthly_cents'] = null;
        $quote = $this->quote(['offer' => 'fiscal'], $catalog);
        $this->assertSame(200000, $quote['recurring_cents']);
        $this->assertSame([150000, 100000], array_column($quote['price_sources'], 'amount_cents'));
        $catalog['offers']['ecf']['levels']['initial']['monthly_cents'] = 200000;
        $this->assertSame(240000, $this->quote(['offer' => 'fiscal'], $catalog)['recurring_cents']);
        $this->assertSame(200000, $quote['recurring_cents']);
    }

    public function test_packages_cannot_contain_packages_or_cycles(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['business']['products'] = ['fiscal', 'business'];
        $this->expectException(DomainException::class);
        (new BusinessCatalogRules)->validate($catalog);
    }

    public function test_own_price_excludes_a_second_package_discount(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['business']['package_discount_bps'] = 1000;
        $this->expectException(DomainException::class);
        $this->quote([], $catalog);
    }

    private function catalogWithDiscount(): array
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['discounts']['agreement'] = ['name' => 'Acuerdo', 'percent_bps' => 1000, 'company_id' => 20,
            'reason' => 'Acuerdo aceptado', 'starts_at' => '2026-11-01T00:00:00Z', 'ends_at' => '2026-12-01T00:00:00Z',
            'offers' => ['business'], 'compatible_annual' => false, 'compatible_package' => true];

        return $catalog;
    }

    public function test_company_condition_only_applies_to_its_company_and_allowed_offer(): void
    {
        $catalog = $this->catalogWithDiscount();
        $this->assertSame(315000, $this->quote(['discount' => 'agreement'], $catalog, 20)['recurring_cents']);
        $this->expectException(DomainException::class);
        $this->quote(['discount' => 'agreement'], $catalog, 21);
    }

    public function test_discount_stacking_must_be_explicitly_compatible(): void
    {
        $this->expectException(DomainException::class);
        $this->quote(['discount' => 'agreement', 'cycle' => 'yearly'], $this->catalogWithDiscount(), 20);
    }

    public function test_expired_discount_cannot_be_applied(): void
    {
        $catalog = $this->catalogWithDiscount();
        $catalog['discounts']['agreement']['ends_at'] = '2026-11-06T12:00:00Z';
        $this->expectException(DomainException::class);
        $this->quote(['discount' => 'agreement'], $catalog, 20);
    }

    public function test_tax_is_pending_until_explicit_and_uses_existing_fiscal_calculator(): void
    {
        $this->assertNull($this->quote()['taxes']);
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['tax_rate'] = 0.18; // Fixture, not a default or a legal determination.
        $quote = $this->quote(['implementation' => 'assisted'], $catalog);
        $this->assertSame(630.0, $quote['taxes']['recurring']['tax_amount']);
        $this->assertSame(4130.0, $quote['taxes']['recurring']['line_total']);
        $this->assertSame(1080.0, $quote['taxes']['one_time']['tax_amount']);
    }

    public function test_retired_offer_is_not_quoted(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['offers']['business']['available'] = false;
        $this->expectException(DomainException::class);
        $this->quote([], $catalog);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->quote(['addons' => ['documents' => -1]]);
    }

    public function test_business_assisted_price_is_not_invented_for_an_individual_product(): void
    {
        $this->expectException(DomainException::class);
        $this->quote(['offer' => 'ecf', 'implementation' => 'assisted']);
    }

    public function test_non_recurrent_addon_cannot_grant_recurring_capacity(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['addons']['hours']['capacity'] = ['users' => 5];
        $this->expectException(DomainException::class);
        $this->quote([], $catalog);
    }

    public function test_future_products_cannot_be_made_purchasable(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['products']['treasury'] = $catalog['products']['ecf'];
        $this->expectException(DomainException::class);
        (new BusinessCatalogRules)->validate($catalog);
    }

    public function test_all_enabled_combinations_must_have_prices_before_publication(): void
    {
        $catalog = BusinessCatalogSeeder::initialPayload();
        $catalog['terms'] = 'Condiciones de prueba';
        $catalog['quote_valid_days'] = 15;
        $catalog['tax_rate'] = 0;
        foreach ($catalog['products'] as &$product) {
            $product['service_id'] = 1;
        }
        unset($product);
        (new BusinessCatalogRules)->validate($catalog, true);
        $this->assertTrue(true);
        $catalog['offers']['ecf']['levels']['growth']['enabled'] = true;
        $this->expectException(DomainException::class);
        (new BusinessCatalogRules)->validate($catalog, true);
    }
}
