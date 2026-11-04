<?php

declare(strict_types=1);

namespace Tests\Unit\AppHub;

use App\Models\Company;
use App\Models\CompanyTaxProfile;
use App\Models\FiscalDocument;
use App\Models\Service;
use App\Models\Subscriber;
use App\Services\Billing\StandaloneServiceCheckoutService;
use App\Services\Dgii\DgiiDocumentSequenceService;
use App\Services\Dgii\HttpDgiiXmlSender;
use App\Services\Fiscal\FiscalDocumentIssuerService;
use App\Services\Fiscal\FiscalDocumentTotals;
use Closure;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/** Existing gates only. This does not certify the pre-existing issuer/model integration gaps. */
final class OptionalCompanyLegalNameFiscalContractTest extends TestCase
{
    private array $previous;
    private FiscalBoundaryReadMemory $connection;
    protected function setUp(): void
    {
        $this->previous=['container'=>Container::getInstance(),'facade'=>Facade::getFacadeApplication(),
            'roots'=>(new ReflectionProperty(Facade::class,'resolvedInstance'))->getValue(),'resolver'=>Model::getConnectionResolver()];
        foreach(['booted','bootedCallbacks','globalScopes'] as $key) $this->previous[$key]=(new ReflectionProperty(Model::class,$key))->getValue();
        $app=new Container; Container::setInstance($app); Facade::setFacadeApplication($app); Facade::clearResolvedInstances();
        $app->instance('config',new Repository);
        $app->instance('validator',new Factory(new Translator(new ArrayLoader,'es'),$app));
        $this->connection=new FiscalBoundaryReadMemory; DB::swap($this->connection);
        $resolver=new ConnectionResolver(['fiscal_memory'=>$this->connection]); $resolver->setDefaultConnection('fiscal_memory'); Model::setConnectionResolver($resolver);
    }
    protected function tearDown(): void
    {
        try { self::assertFalse($this->connection->pdoAttempted); }
        finally {
            Container::setInstance($this->previous['container']); Facade::setFacadeApplication($this->previous['facade']);
            (new ReflectionProperty(Facade::class,'resolvedInstance'))->setValue(null,$this->previous['roots']);
            foreach(['booted','bootedCallbacks','globalScopes'] as $key) (new ReflectionProperty(Model::class,$key))->setValue(null,$this->previous[$key]);
            $this->previous['resolver'] ? Model::setConnectionResolver($this->previous['resolver']) : Model::unsetConnectionResolver();
        }
    }
    private function company(): Company
    {
        $c=(new Company)->setRawAttributes(['id'=>7,'subscriber_id'=>17,'active'=>1],true);
        $c->setRelation('taxProfile',(new CompanyTaxProfile)->setRawAttributes(['company_id'=>7,'legal_name'=>null],true));
        return $c;
    }
    public function test_nullable_profile_does_not_bypass_checkout_tenant_identity(): void
    {
        $subscriber=(new Subscriber)->setRawAttributes(['id'=>99,'active'=>1],true);
        $this->expectException(ValidationException::class);
        (new StandaloneServiceCheckoutService)->previewQuote($subscriber,$this->company(),new Service,'monthly');
    }
    public function test_nullable_profile_does_not_bypass_inactive_subscriber(): void
    {
        $subscriber=(new Subscriber)->setRawAttributes(['id'=>17,'active'=>0],true);
        $this->expectException(ValidationException::class);
        (new StandaloneServiceCheckoutService)->previewQuote($subscriber,$this->company(),new Service,'monthly');
    }
    public function test_nullable_profile_does_not_make_a_service_billable(): void
    {
        $subscriber=(new Subscriber)->setRawAttributes(['id'=>17,'active'=>1],true);
        $service=(new Service)->setRawAttributes(['active'=>1,'billable'=>0],true);
        $this->expectException(ValidationException::class);
        (new StandaloneServiceCheckoutService)->previewQuote($subscriber,$this->company(),$service,'monthly');
    }
    public function test_fiscal_issue_keeps_the_existing_draft_state_gate_before_any_write(): void
    {
        $doc=(new FiscalDocument)->setRawAttributes(['id'=>30,'company_id'=>7,'status'=>'voided'],true);
        try { (new FiscalDocumentIssuerService(new FiscalDocumentTotals,new DgiiDocumentSequenceService))->issue($doc,$this->company()); self::fail('Invalid state accepted.'); }
        catch(RuntimeException $e) { self::assertStringContainsString('Solo se puede emitir desde draft',$e->getMessage()); }
        self::assertSame(1,$this->connection->reads);
    }
    public function test_xml_sending_keeps_missing_signature_rejection_without_network(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falta Signature');
        $this->validateXml('<ECF><TipoeCF>31</TipoeCF><FechaHoraFirma>02-11-2026 10:20:30</FechaHoraFirma></ECF>');
    }
    public function test_xml_sending_keeps_missing_signing_date_rejection_without_network(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Falta FechaHoraFirma');
        $this->validateXml('<ECF><TipoeCF>31</TipoeCF><Signature/></ECF>');
    }
    private function validateXml(string $xml): void
    {
        $previous=libxml_use_internal_errors(true);
        try {
            $sender=(new ReflectionClass(HttpDgiiXmlSender::class))->newInstanceWithoutConstructor();
            (new ReflectionMethod(HttpDgiiXmlSender::class,'validateXmlBeforeSend'))->invoke($sender,$xml,'qa.xml');
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
    public function test_existing_buyer_tax_id_and_line_checks_remain_in_the_issuer_source(): void
    {
        $source=file_get_contents(dirname(__DIR__,3).'/app/Services/Fiscal/FiscalDocumentIssuerService.php');
        foreach(['if ($type->requires_buyer_tax_id)', 'if (!$buyer || !$taxId)', 'if ($lineCount <= 0)', '$this->seqService->reserveNext('] as $token) self::assertStringContainsString($token,$source);
        // Source preservation only: the report identifies the separate, pre-existing relation/type gaps.
    }
    public function test_nullable_reader_contracts_do_not_require_fabricating_a_name(): void
    {
        $root=dirname(__DIR__,3);
        $form=file_get_contents($root.'/resources/js/components/company/CompanyProfileForm.vue');
        self::assertStringContainsString("legal_name: props.initial.legal_name ?? ''",$form);
        $admin=file_get_contents($root.'/resources/js/pages/Admin/Company/Index.vue');
        self::assertStringContainsString('legal_name: string | null',$admin);
        $invoice=file_get_contents($root.'/resources/js/pages/Subscriber/Invoices/Show.vue');
        self::assertStringContainsString('legal_name?: string | null',$invoice);
        self::assertStringContainsString('props.company.legal_name',$invoice);
        self::assertStringContainsString('|| props.company.name',$invoice);
    }
}

final class FiscalBoundaryReadMemory extends MySqlConnection
{
    public bool $pdoAttempted=false;
    public int $reads=0;
    public function __construct()
    {
        parent::__construct(function(){ $this->pdoAttempted=true; throw new LogicException('NATIVE_PDO_FORBIDDEN'); },'fiscal_memory','',['driver'=>'mysql']);
    }
    public function transaction(Closure $callback,$attempts=1) { return $callback($this); }
    public function select($query,$bindings=[],$useReadPdo=true)
    {
        if($query!=='select * from `fiscal_documents` where `id` = ? and `company_id` = ? limit 1 for update' || $bindings!==[30,7]) throw new LogicException('UNEXPECTED_FISCAL_READ');
        ++$this->reads; return [(object)['id'=>30,'company_id'=>7,'status'=>'voided']];
    }
}
