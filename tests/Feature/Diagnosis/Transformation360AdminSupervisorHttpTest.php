<?php

namespace Tests\Feature\Diagnosis;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class Transformation360AdminSupervisorHttpTest
    extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_admin_can_open_transformation_supervisor(): void
    {
        $admin =
            User::factory()->create([
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

        $this
            ->actingAs($admin)
            ->get(
                '/admin/transformation-360'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/Index'
                        )
                        ->has('rows')
                        ->has('stats')
            );
    }

    public function test_admin_can_open_data_bi_supervisor(): void
    {
        $admin =
            User::factory()->create([
                'role' => 'admin',
                'email_verified_at' => now(),
            ]);

        $this
            ->actingAs($admin)
            ->get(
                '/admin/transformation-360/data-bi'
            )
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) =>
                    $page
                        ->component(
                            'Admin/Transformation360/DataBi'
                        )
                        ->where(
                            'capability.key',
                            'data_transformation_bi'
                        )
                        ->has('rows')
                        ->has('stats')
            );
    }
}
