<?php

namespace Tests\Unit\Entitlements;

use PHPUnit\Framework\TestCase;

class ResolveCompanyFromHostSchemaGuardContractTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(
            dirname(__DIR__, 3)
            .'/app/Http/Middleware/ResolveCompanyFromHost.php'
        );
    }

    public function test_custom_domain_query_is_schema_guarded(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "use Illuminate\\Support\\Facades\\Schema;",
            $source
        );

        $this->assertStringContainsString(
            "Schema::hasColumn('companies', 'domain')",
            $source
        );

        $guard = strpos(
            $source,
            "Schema::hasColumn('companies', 'domain')"
        );

        $query = strpos(
            $source,
            "whereRaw('LOWER(domain) = ?', [\$host])"
        );

        $this->assertNotFalse($guard);
        $this->assertNotFalse($query);
        $this->assertLessThan(
            $query,
            $guard,
            'La consulta domain debe ocurrir después del schema guard.'
        );
    }

    public function test_subdomain_fallback_remains_available(): void
    {
        $source = $this->source();

        foreach ([
            "->where('ws_subdomain', \$sub)",
            "->orWhere('slug', \$sub)",
            "config('app.base_domain', 'laudaapi.com')",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }

    public function test_company_context_attributes_are_preserved(): void
    {
        $source = $this->source();

        foreach ([
            "\$request->attributes->set('company', \$company);",
            "\$request->attributes->set('resolved_company_id', \$company->id);",
            "\$request->attributes->set('resolved_subscriber_id', \$company->subscriber_id);",
            "app()->instance('currentCompany', \$company);",
        ] as $token) {
            $this->assertStringContainsString(
                $token,
                $source
            );
        }
    }
}
