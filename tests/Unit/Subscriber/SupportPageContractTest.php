<?php

namespace Tests\Unit\Subscriber;

use App\Models\SupportFaqCategory;
use App\Models\SupportFaqItem;
use PHPUnit\Framework\TestCase;

/**
 * Soporte del cliente: la página recibe las preguntas frecuentes que envía
 * Laravel y su categoría se puede cargar (antes faltaba el modelo y la
 * página fallaba en cuanto existía una pregunta publicada).
 */
class SupportPageContractTest extends TestCase
{
    private function read(string $relative): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$relative);
    }

    public function test_faq_category_relation_points_to_an_existing_model(): void
    {
        $this->assertTrue(class_exists(SupportFaqCategory::class));
        $this->assertSame('support_faq_categories', (new SupportFaqCategory)->getTable());

        $relation = new \ReflectionMethod(SupportFaqItem::class, 'category');
        $this->assertStringContainsString(
            'SupportFaqCategory::class',
            implode('', array_slice(file(dirname(__DIR__, 3).'/app/Models/SupportFaqItem.php'), $relation->getStartLine() - 1, $relation->getEndLine() - $relation->getStartLine() + 1)),
        );
    }

    public function test_page_reads_the_prop_the_controller_sends(): void
    {
        $controller = $this->read('app/Http/Controllers/Subscriber/SubscriberSupportController.php');
        $page = $this->read('resources/js/pages/Subscriber/Support/Index.vue');

        $this->assertStringContainsString("'faq' => \$faq", $controller);
        $this->assertStringContainsString('faq: FaqItem[];', $page);
        $this->assertStringNotContainsString('props.faqs', $page);
    }
}
