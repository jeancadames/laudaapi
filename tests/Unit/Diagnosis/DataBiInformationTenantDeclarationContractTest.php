<?php

namespace Tests\Unit\Diagnosis;

use App\Services\Diagnosis\DataBiInformationRequirementDraftGenerator;
use App\Services\Diagnosis\DataBiInformationRequirementReviewContract;
use App\Services\Diagnosis\DataBiInformationTenantDeclarationContract as Contract;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataBiInformationTenantDeclarationContractTest extends TestCase
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    private function sent(): array
    {
        $generated = DataBiInformationRequirementDraftGenerator::preview([
            'business_needs' => ['improvements' => ['gestion_clientes']],
        ]);
        $snapshot = DataBiInformationRequirementReviewContract::normalize($generated, [
            'requirements' => array_map(static fn (array $item): array => [
                'key' => $item['key'], 'included' => true,
                'label' => $item['label'], 'guidance' => $item['guidance'],
            ], $generated['requirements']),
        ]);

        return [
            'id' => 42, 'version' => 3, 'status' => 'sent',
            'review_snapshot' => $snapshot, 'content_sha256' => $snapshot['content_sha256'],
        ];
    }

    private function input(array $sent): array
    {
        return [
            'version' => $sent['version'],
            'declarations' => array_map(static fn (array $item): array => [
                'requirement_key' => $item['key'],
                'availability' => 'available',
                'sources' => [['type' => 'software', 'name' => 'ERP', 'structures' => ['Clientes']]],
            ], $sent['review_snapshot']['requirements']),
        ];
    }

    private function replace(array &$data, array $path, mixed $value): void
    {
        $target = &$data;
        foreach ($path as $key) {
            $target = &$target[$key];
        }
        $target = $value;
    }

    private function resign(array &$sent): void
    {
        $snapshot = $sent['review_snapshot'];
        unset($snapshot['content_sha256']);
        $hash = hash('sha256', json_encode($snapshot, self::JSON_FLAGS));
        $sent['content_sha256'] = $hash;
        $sent['review_snapshot']['content_sha256'] = $hash;
    }

    public function test_empty_draft_covers_the_snapshot_without_mutating_arguments(): void
    {
        $sent = $this->sent();
        $original = $sent;
        $input = ['version' => 3, 'declarations' => []];
        $result = Contract::normalize($sent, $input, 'draft');

        self::assertSame($original, $sent);
        self::assertSame(['version' => 3, 'declarations' => []], $input);
        self::assertSame(42, $result['information_request_id']);
        self::assertSame(3, $result['version']);
        self::assertSame($sent['content_sha256'], $result['questionnaire_sha256']);
        self::assertFalse($result['data_inspected']);
        self::assertFalse($result['files_received']);
        self::assertSame(array_column($sent['review_snapshot']['requirements'], 'key'),
            array_column($result['declarations'], 'requirement_key'));
        foreach ($result['declarations'] as $declaration) {
            self::assertSame(['requirement_key' => $declaration['requirement_key'],
                'availability' => 'pending', 'sources' => [], 'notes' => ''], $declaration);
        }
        $payload = $result;
        unset($payload['content_sha256']);
        self::assertSame(hash('sha256', json_encode($payload, self::JSON_FLAGS)), $result['content_sha256']);
        self::assertSame($result, Contract::normalize($sent, $input, 'draft'));
        self::assertSame(['information_request_id', 'version', 'questionnaire_sha256', 'declarations',
            'data_inspected', 'files_received', 'content_sha256'], array_keys($result));
    }

    public function test_draft_accepts_partial_work_and_fills_missing_requirements(): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        array_pop($input['declarations']);
        $input['declarations'][0]['sources'] = [['type' => 'database']];
        $result = Contract::normalize($sent, $input, 'draft');

        self::assertSame(['type' => 'database', 'name' => '', 'structures' => [], 'notes' => ''],
            $result['declarations'][0]['sources'][0]);
        self::assertSame('pending', $result['declarations'][1]['availability']);
    }

    #[DataProvider('completedAvailabilities')]
    public function test_submit_accepts_complete_declarations(string $availability): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $input['declarations'][0]['availability'] = $availability;
        $input['declarations'][0]['notes'] = "  Descripción disponible.\r\nSolo metadatos.  ";
        if (in_array($availability, ['unavailable', 'unknown'], true)) {
            $input['declarations'][0]['sources'] = [];
        }
        $result = Contract::normalize($sent, $input, 'submit');

        self::assertSame($availability, $result['declarations'][0]['availability']);
        self::assertSame("Descripción disponible.\nSolo metadatos.", $result['declarations'][0]['notes']);
        self::assertSame($result, Contract::normalize($sent, $input, 'draft'));
    }

    public static function completedAvailabilities(): iterable
    {
        foreach (['available', 'partial', 'unavailable', 'unknown'] as $value) {
            yield $value => [$value];
        }
    }

    public function test_all_source_types_and_inclusive_limits_are_supported(): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $structures = array_map(static fn (int $i): string => 'Estructura '.$i, range(1, 12));
        $structures[0] = str_repeat('á', 191);
        $input['declarations'][0]['sources'] = array_map(static fn (string $type): array => [
            'type' => $type, 'name' => ' '.str_repeat('ñ', 191).' ',
            'structures' => $structures, 'notes' => str_repeat('á', 800),
        ], ['software', 'database', 'spreadsheet', 'flat_file', 'api', 'other']);
        $input['declarations'][0]['notes'] = ' '.str_repeat('a', 800).' ';
        $result = Contract::normalize($sent, $input, 'submit');

        self::assertCount(6, $result['declarations'][0]['sources']);
        self::assertSame(800, mb_strlen($result['declarations'][0]['notes']));
        foreach ($result['declarations'][0]['sources'] as $source) {
            self::assertCount(12, $source['structures']);
            self::assertSame(191, mb_strlen($source['name']));
            self::assertSame(800, mb_strlen($source['notes']));
        }
    }

    public function test_source_structure_and_object_key_order_do_not_change_the_result(): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $input['declarations'][0]['sources'] = [
            ['type' => 'software', 'name' => ' Zeta ', 'structures' => ['Ventas', 'Clientes']],
            ['type' => 'software', 'name' => 'Alfa', 'structures' => ['[dbo].[Ventas]']],
            ['type' => 'api', 'name' => 'API', 'structures' => ['Pedidos']],
        ];
        $original = $input;
        $expected = Contract::normalize($sent, $input, 'submit');
        $input['declarations'][0]['sources'][0]['structures'] = [' Clientes ', 'Ventas'];
        $input['declarations'][0]['sources'] = array_reverse($input['declarations'][0]['sources']);
        $input['declarations'] = array_reverse($input['declarations']);
        $input = $this->reorderKeys($input);
        $sent = $this->reorderKeys($sent);

        self::assertSame($expected, Contract::normalize($sent, $input, 'submit'));
        self::assertSame(['api', 'software', 'software'], array_column($expected['declarations'][0]['sources'], 'type'));
        self::assertSame(['API', 'Alfa', 'Zeta'], array_column($expected['declarations'][0]['sources'], 'name'));
        self::assertSame(['Clientes', 'Ventas'], $expected['declarations'][0]['sources'][2]['structures']);
        self::assertSame(' Zeta ', $original['declarations'][0]['sources'][0]['name']);
    }

    private function reorderKeys(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->reorderKeys($value);
            }
        }
        unset($value);
        if (!array_is_list($data)) {
            krsort($data, SORT_STRING);
        }

        return $data;
    }

    public function test_hash_binds_answers_identity_version_and_questionnaire(): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $baseline = Contract::normalize($sent, $input, 'submit')['content_sha256'];
        $changed = $input;
        $changed['declarations'][0]['notes'] = 'Descripción adicional.';
        self::assertNotSame($baseline, Contract::normalize($sent, $changed, 'submit')['content_sha256']);
        $changed = $sent;
        $changed['id']++;
        self::assertNotSame($baseline, Contract::normalize($changed, $input, 'submit')['content_sha256']);
        $changed = $sent;
        $changed['version']++;
        $newInput = $input;
        $newInput['version']++;
        self::assertNotSame($baseline, Contract::normalize($changed, $newInput, 'submit')['content_sha256']);
        $changed = $sent;
        $changed['review_snapshot']['requirements'][0]['guidance'] = 'Otra instrucción revisada.';
        $this->resign($changed);
        self::assertNotSame($baseline, Contract::normalize($changed, $input, 'submit')['content_sha256']);
    }

    #[DataProvider('invalidPublishedVersions')]
    public function test_rejects_invalid_published_versions(array $path, mixed $value, bool $resign = false): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $this->replace($sent, $path, $value);
        if ($resign) {
            $this->resign($sent);
        }
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'draft');
    }

    public static function invalidPublishedVersions(): iterable
    {
        foreach (['id' => [null, 0, -1, '42'], 'version' => [null, 0, 10000, '3'],
            'status' => [null, 'draft', 'submitted'],
            'content_sha256' => [null, '', str_repeat('a', 63), str_repeat('A', 64), str_repeat('g', 64)],
            'review_snapshot' => [null, 'snapshot', []]] as $field => $values) {
            foreach ($values as $i => $value) {
                yield $field.' '.$i => [[$field], $value];
            }
        }
        foreach (['schema_version' => 2, 'purpose' => 'other', 'origin' => 'browser',
            'data_inspected' => true, 'tenant_response_received' => true,
            'content_sha256' => str_repeat('b', 64), 'requirements' => [],
            'selected_needs' => null, 'unmapped_need_keys' => 'invalid'] as $field => $value) {
            yield 'snapshot '.$field => [['review_snapshot', $field], $value];
        }
        yield 'matching hashes without matching content' => [['review_snapshot', 'requirements', 0, 'label'], 'Alterado'];
        yield 'unknown snapshot field' => [['review_snapshot', 'files'], [], true];
        yield 'unknown requirement field' => [['review_snapshot', 'requirements', 0, 'data'], [], true];
        yield 'duplicate requirement' => [['review_snapshot', 'requirements', 1, 'key'], 'clientes_usuarios', true];
        yield 'empty key' => [['review_snapshot', 'requirements', 0, 'key'], '', true];
        yield 'empty label' => [['review_snapshot', 'requirements', 0, 'label'], ' ', true];
        yield 'invalid guidance' => [['review_snapshot', 'requirements', 0, 'guidance'], [], true];
        yield 'empty objectives' => [['review_snapshot', 'requirements', 0, 'objective_keys'], [], true];
        yield 'non-list objectives' => [['review_snapshot', 'requirements', 0, 'objective_keys'], ['key' => 'gestion_clientes'], true];
        yield 'invalid objective' => [['review_snapshot', 'requirements', 0, 'objective_keys'], [null], true];
        yield 'duplicate objective' => [['review_snapshot', 'requirements', 0, 'objective_keys'], ['gestion_clientes', 'gestion_clientes'], true];
        yield 'invalid selected need' => [['review_snapshot', 'selected_needs'], [null], true];
        yield 'unknown selected need attribute' => [['review_snapshot', 'selected_needs', 0, 'other'], 'extra', true];
        yield 'invalid selected need label' => [['review_snapshot', 'selected_needs', 0, 'label'], [], true];
        yield 'duplicate unmapped key' => [['review_snapshot', 'unmapped_need_keys'], ['missing', 'missing'], true];
        yield 'non-list requirements' => [['review_snapshot', 'requirements'], ['key' => []], true];
        yield 'too many requirements' => [['review_snapshot', 'requirements'], array_fill(0, 101, []), true];
        yield 'non-array requirement' => [['review_snapshot', 'requirements'], [null], true];
    }

    public function test_rejects_missing_snapshot_attributes_even_if_remaining_hashes_agree(): void
    {
        $sent = $this->sent();
        unset($sent['review_snapshot']['selected_needs']);
        $this->resign($sent);
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, ['version' => 3, 'declarations' => []], 'draft');
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input_in_draft_too(array $path, mixed $value): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $this->replace($input, $path, $value);
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'draft');
    }

    public static function invalidInputs(): iterable
    {
        foreach ([null, 2, 4, '3', 3.0, true] as $i => $value) {
            yield 'version '.$i => [['version'], $value];
        }
        yield 'declarations type' => [['declarations'], 'invalid'];
        yield 'declarations null' => [['declarations'], null];
        yield 'declarations map' => [['declarations'], ['key' => []]];
        yield 'too many declarations' => [['declarations'], array_fill(0, 101, [])];
        yield 'declaration type' => [['declarations', 0], 'invalid'];
        yield 'missing key' => [['declarations', 0], []];
        yield 'unknown key' => [['declarations', 0, 'requirement_key'], 'not_published'];
        yield 'key type' => [['declarations', 0, 'requirement_key'], []];
        yield 'duplicate key' => [['declarations', 1, 'requirement_key'], 'clientes_usuarios'];
        yield 'unknown availability' => [['declarations', 0, 'availability'], 'yes'];
        yield 'availability type' => [['declarations', 0, 'availability'], []];
        yield 'availability null' => [['declarations', 0, 'availability'], null];
        yield 'notes type' => [['declarations', 0, 'notes'], []];
        yield 'notes null' => [['declarations', 0, 'notes'], null];
        yield 'notes too long' => [['declarations', 0, 'notes'], str_repeat('á', 801)];
        yield 'invalid utf8' => [['declarations', 0, 'notes'], "\xC3\x28"];
        yield 'control bytes' => [['declarations', 0, 'notes'], "Nombre\0Valor"];
        yield 'sources null' => [['declarations', 0, 'sources'], null];
        yield 'sources type' => [['declarations', 0, 'sources'], 'ERP'];
        yield 'sources map' => [['declarations', 0, 'sources'], ['source' => []]];
        yield 'too many sources' => [['declarations', 0, 'sources'], array_fill(0, 7, [])];
        yield 'source type' => [['declarations', 0, 'sources', 0], 'ERP'];
        yield 'source missing type' => [['declarations', 0, 'sources', 0], ['name' => 'ERP']];
        yield 'source unknown enum' => [['declarations', 0, 'sources', 0, 'type'], 'csv'];
        yield 'source enum null' => [['declarations', 0, 'sources', 0, 'type'], null];
        yield 'source name type' => [['declarations', 0, 'sources', 0, 'name'], []];
        yield 'source name too long' => [['declarations', 0, 'sources', 0, 'name'], str_repeat('a', 192)];
        yield 'source name multiline' => [['declarations', 0, 'sources', 0, 'name'], "Uno\nDos"];
        yield 'source notes too long' => [['declarations', 0, 'sources', 0, 'notes'], str_repeat('a', 801)];
        yield 'source notes null' => [['declarations', 0, 'sources', 0, 'notes'], null];
        yield 'structures null' => [['declarations', 0, 'sources', 0, 'structures'], null];
        yield 'structures type' => [['declarations', 0, 'sources', 0, 'structures'], 'Clientes'];
        yield 'structures map' => [['declarations', 0, 'sources', 0, 'structures'], ['name' => 'Clientes']];
        yield 'too many structures' => [['declarations', 0, 'sources', 0, 'structures'], range(1, 13)];
        yield 'structure record' => [['declarations', 0, 'sources', 0, 'structures'], [['cliente' => 'Real']]];
        yield 'structure numeric' => [['declarations', 0, 'sources', 0, 'structures'], [12]];
        yield 'structure empty' => [['declarations', 0, 'sources', 0, 'structures'], [' ']];
        yield 'structure too long' => [['declarations', 0, 'sources', 0, 'structures'], [str_repeat('a', 192)]];
        yield 'duplicate structure' => [['declarations', 0, 'sources', 0, 'structures'], ['Clientes', ' clientes ']];
        yield 'duplicate source' => [['declarations', 0, 'sources'], [
            ['type' => 'software', 'name' => 'ERP'], ['type' => 'software', 'name' => ' erp '],
        ]];
        foreach (['unavailable', 'unknown'] as $availability) {
            yield 'inconsistent '.$availability => [['declarations', 0, 'availability'], $availability];
        }
    }

    #[DataProvider('foreignAttributes')]
    public function test_rejects_foreign_attributes_at_every_input_level(array $path): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $this->replace($input, $path, 'forbidden');
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'draft');
    }

    public static function foreignAttributes(): iterable
    {
        foreach ([[], ['declarations', 0], ['declarations', 0, 'sources', 0]] as $level => $prefix) {
            foreach (['id', 'company_id', 'objective_keys', 'label', 'password', 'token',
                'credentials', 'file', 'files', 'attachment', 'rows', 'records', 'data',
                'data_inspected', 'files_received', 'content_sha256'] as $attribute) {
                yield $level.' '.$attribute => [[...$prefix, $attribute]];
            }
        }
    }

    #[DataProvider('unsafeTexts')]
    public function test_rejects_secrets_files_and_transaction_formats_in_all_text_fields(string $text, array $path): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $this->replace($input, $path, $text);
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'draft');
    }

    public static function unsafeTexts(): iterable
    {
        $texts = ['password=secret', 'contraseña: secreto', 'token: abc', 'API_KEY=abc',
            'Authorization: Bearer abc', 'Bearer secret', 'https://user:secret@example.test',
            'mysql:host=localhost;dbname=erp', 'username=admin;pwd=secret',
            '-----BEGIN PRIVATE KEY-----', 'ssh-ed25519 AAAAC3Nza', 'eyJhbGciOiJIUzI1NiJ9.e30.signature',
            'data:text/csv;base64,YWJj', 'base64: YWJj', '{"cliente":"Real","importe":100}',
            '[{"cliente":"Real"}]', '[[1,2]]', 'SELECT id, importe FROM ventas',
            'INSERT INTO ventas VALUES (1, 100)', 'UPDATE ventas SET importe=100',
            "cliente,importe\nReal,100", "cliente\timporte\nReal\t100"];
        $paths = [['declarations', 0, 'notes'], ['declarations', 0, 'sources', 0, 'name'],
            ['declarations', 0, 'sources', 0, 'notes'], ['declarations', 0, 'sources', 0, 'structures', 0]];
        foreach ($texts as $i => $text) {
            foreach ($paths as $j => $path) {
                yield $i.' field '.$j => [$text, $path];
            }
        }
    }

    #[DataProvider('incompleteSubmissions')]
    public function test_submit_rejects_incomplete_responses(array $declaration): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        $input['declarations'][0] = ['requirement_key' => 'clientes_usuarios', ...$declaration];
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'submit');
    }

    public static function incompleteSubmissions(): iterable
    {
        yield 'implicit pending' => [[]];
        yield 'explicit pending' => [['availability' => 'pending']];
        yield 'available without sources' => [['availability' => 'available']];
        yield 'partial without sources' => [['availability' => 'partial', 'notes' => 'Falta una parte.']];
        foreach (['partial', 'unavailable', 'unknown'] as $availability) {
            yield $availability.' without explanation' => [[
                'availability' => $availability, 'notes' => ' ',
                'sources' => $availability === 'partial'
                    ? [['type' => 'software', 'name' => 'ERP', 'structures' => ['Clientes']]] : [],
            ]];
        }
        yield 'unnamed source' => [['availability' => 'available',
            'sources' => [['type' => 'software', 'structures' => ['Clientes']]]]];
        yield 'missing structure' => [['availability' => 'available',
            'sources' => [['type' => 'software', 'name' => 'ERP']]]];
    }

    public function test_submit_rejects_an_omitted_requirement(): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        array_pop($input['declarations']);
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'submit');
    }

    #[DataProvider('invalidModes')]
    public function test_rejects_unknown_modes(string $mode): void
    {
        $sent = $this->sent();
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $this->input($sent), $mode);
    }

    public static function invalidModes(): iterable
    {
        foreach (['', 'save', 'Draft', 'submitted', ' submit '] as $mode) {
            yield [$mode];
        }
    }

    #[DataProvider('missingInputAttributes')]
    public function test_rejects_missing_required_input(string $attribute): void
    {
        $sent = $this->sent();
        $input = $this->input($sent);
        unset($input[$attribute]);
        $this->expectException(InvalidArgumentException::class);
        Contract::normalize($sent, $input, 'draft');
    }

    public static function missingInputAttributes(): iterable
    {
        yield ['version'];
        yield ['declarations'];
    }
}
