<?php

namespace Tests\Feature\PublicExpense;

use Tests\TestCase;

class PublicExpenseWebRouteTest extends TestCase
{
    public function test_public_expense_without_manage_redirects_to_participant_path(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/public/expenses/{$hash}");

        $response->assertStatus(302);
        $response->assertRedirect("/p/{$hash}");
    }

    public function test_public_expense_with_manage_returns_ok(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/public/expenses/{$hash}?manage=".urlencode('test-manage-token'));

        $response->assertOk();
    }

    public function test_participant_path_returns_ok(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/p/{$hash}");

        $response->assertOk();
    }

    public function test_redirect_response_does_not_contain_expense_not_found_message(): void
    {
        $hash = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $response = $this->get("/public/expenses/{$hash}");

        $response->assertStatus(302);
        $this->assertStringNotContainsString('Despesa nao encontrada', $response->getContent());
    }
}
