<?php

declare(strict_types=1);

namespace Tests\Unit\AppHub;

use App\Http\Controllers\Subscriber\SubscriberCompanyController;
use App\Models\Company;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Companies\CentralCompanyProfileService;
use Closure;
use Illuminate\Auth\RequestGuard;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\MySqlConnection;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

final class OptionalCompanyLegalNameTest extends TestCase
{
    private array $previous;
    private ProfileNameMemoryConnection $connection;
    private Container $app;

    protected function setUp(): void
    {
        $this->previous=['container'=>Container::getInstance(),'facade'=>Facade::getFacadeApplication(),
            'roots'=>(new ReflectionProperty(Facade::class,'resolvedInstance'))->getValue(),
            'resolver'=>Eloquent::getConnectionResolver(),'events'=>Eloquent::getEventDispatcher()];
        foreach(['booted','bootedCallbacks','globalScopes'] as $key) $this->previous[$key]=(new ReflectionProperty(Eloquent::class,$key))->getValue();
        $this->app=new class extends Container { public function runningInConsole(): bool { return true; } };
        Container::setInstance($this->app);
        $this->app->instance('config',new Repository(['cache'=>['default'=>'array','stores'=>['array'=>['driver'=>'array']]]]));
        $this->app->instance('validator',new Factory(new Translator(new ArrayLoader,'es'),$this->app));
        $this->app->instance('cache',new CacheManager($this->app));
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->app);
        Auth::swap(new RequestGuard(fn()=>null,Request::create('/')));
        $this->connection=new ProfileNameMemoryConnection;
        $capsule=new Capsule($this->app);
        $capsule->addConnection(['driver'=>'mysql'],'profile_memory');
        $manager=$capsule->getDatabaseManager(); $manager->extend('mysql',fn()=> $this->connection);
        $manager->setDefaultConnection('profile_memory'); $this->app->instance('db',$manager);
        $capsule->setEventDispatcher(new Dispatcher($this->app)); Eloquent::clearBootedModels(); $capsule->bootEloquent();
    }

    protected function tearDown(): void
    {
        try {
            self::assertFalse($this->connection->pdoAttempted);
            self::assertSame(0,$this->connection->transactionLevel());
            foreach($this->connection->mutations as $mutation) self::assertContains($mutation['table'],['companies','subscribers','company_tax_profiles','audit_logs']);
        } finally {
            Container::setInstance($this->previous['container']); Facade::setFacadeApplication($this->previous['facade']);
            (new ReflectionProperty(Facade::class,'resolvedInstance'))->setValue(null,$this->previous['roots']);
            foreach(['booted','bootedCallbacks','globalScopes'] as $key) (new ReflectionProperty(Eloquent::class,$key))->setValue(null,$this->previous[$key]);
            $this->previous['resolver'] ? Eloquent::setConnectionResolver($this->previous['resolver']) : Eloquent::unsetConnectionResolver();
            $this->previous['events'] ? Eloquent::setEventDispatcher($this->previous['events']) : Eloquent::unsetEventDispatcher();
        }
    }

    private function context(): array
    {
        $company=(new Company)->setRawAttributes(['id'=>7,'name'=>'QA company','slug'=>'qa-company','ws_subdomain'=>'qa-company-7','currency'=>'DOP','timezone'=>'America/Santo_Domingo','subscriber_id'=>17,'active'=>1],true); $company->exists=true;
        $subscriber=(new Subscriber)->setRawAttributes(['id'=>17,'name'=>'QA company','country_code'=>'DO','currency'=>'DOP','timezone'=>'America/Santo_Domingo','active'=>1,'meta'=>json_encode(['keep'=>'historical'])],true); $subscriber->exists=true;
        $user=(new User)->setRawAttributes(['id'=>20,'role'=>'subscriber','name'=>'QA owner'],true); $user->exists=true;
        return [$company,$subscriber,$user];
    }

    private function input(array $extra=[]): array
    {
        return array_merge(['company_name'=>'QA company','country_code'=>'DO','currency'=>'DOP','timezone'=>'America/Santo_Domingo','company_size'=>'1-10','billing_email'=>'profile'.'@'.'example.test'],$extra);
    }

    public static function absentNames(): array { return [['omitted'],[''],['   ']]; }

    #[DataProvider('absentNames')]
    public function test_new_onboarding_profile_can_leave_legal_name_undeclared(string $value): void
    {
        [$company,$subscriber,$user]=$this->context();
        $data=$this->input($value==='omitted' ? [] : ['legal_name'=>$value]);
        $service=new CentralCompanyProfileService;
        $validator=$this->app['validator']->make($data,$service->rules()); self::assertTrue($validator->passes());
        $profile=$service->save($company,$subscriber,$validator->validated(),$user);
        self::assertNull($profile->legal_name); self::assertNull($this->connection->profile['legal_name']);
        self::assertSame('QA company',$profile->trade_name);
        self::assertNull($subscriber->meta['app_hub_onboarding']['legal_name']);
        self::assertSame(1,$this->connection->profileReads);
        self::assertSame('insert',end($this->connection->mutations)['op']);
    }

    public function test_declared_legal_name_is_stored_without_a_commercial_name_fallback(): void
    {
        [$company,$subscriber,$user]=$this->context();
        $profile=(new CentralCompanyProfileService)->save($company,$subscriber,$this->input(['legal_name'=>'  QA declared identity  ']),$user);
        self::assertSame('QA declared identity',$profile->legal_name);
        self::assertSame('QA declared identity',$subscriber->meta['app_hub_onboarding']['legal_name']);
        self::assertNotSame($company->name,$profile->legal_name);
    }

    #[DataProvider('absentNames')]
    public function test_absent_or_empty_input_cannot_erase_a_historical_declared_name(string $value): void
    {
        $this->connection->profile=['id'=>12,'company_id'=>7,'legal_name'=>'  Historical declared identity  ','tax_regime'=>'rst','fiscal_year_end_id'=>99];
        [$company,$subscriber,$user]=$this->context();
        $data=$this->input(['company_name'=>'QA changed trade name']+($value==='omitted' ? [] : ['legal_name'=>$value]));
        $profile=(new CentralCompanyProfileService)->save($company,$subscriber,$data,$user);
        self::assertSame('  Historical declared identity  ',$profile->legal_name);
        self::assertSame('  Historical declared identity  ',$this->connection->profile['legal_name']);
        self::assertSame('  Historical declared identity  ',$subscriber->meta['app_hub_onboarding']['legal_name']);
        self::assertSame('rst',$this->connection->profile['tax_regime']); self::assertSame(99,$this->connection->profile['fiscal_year_end_id']);
        foreach($this->connection->mutations as $m) if($m['table']==='company_tax_profiles') self::assertArrayNotHasKey('legal_name',$m['data']);
        self::assertSame('historical',$subscriber->meta['keep']);
    }

    public function test_explicit_profile_edit_keeps_the_existing_nonempty_edit_contract(): void
    {
        $this->connection->profile=['id'=>12,'company_id'=>7,'legal_name'=>'Earlier declared identity'];
        [$company,$subscriber,$user]=$this->context();
        $profile=(new CentralCompanyProfileService)->save($company,$subscriber,$this->input(['legal_name'=>'Explicitly declared identity']),$user);
        self::assertSame('Explicitly declared identity',$profile->legal_name);
        self::assertSame('Explicitly declared identity',$this->connection->profile['legal_name']);
        // Financial/document tables are rejected by the transport; existing invoice snapshots cannot be rewritten.
    }

    public function test_existing_null_name_remains_undeclared_and_payload_is_display_safe(): void
    {
        $this->connection->profile=['id'=>12,'company_id'=>7,'legal_name'=>null];
        [$company,$subscriber,$user]=$this->context(); $service=new CentralCompanyProfileService;
        $profile=$service->save($company,$subscriber,$this->input(),$user);
        self::assertNull($profile->legal_name); $before=count($this->connection->mutations);
        self::assertSame('',$service->payload($company,$subscriber)['legal_name']);
        self::assertSame($before,count($this->connection->mutations));
    }

    public function test_legacy_profile_initializer_does_not_invent_a_legal_name(): void
    {
        [$company,,$user]=$this->context();
        $profile=(new ReflectionMethod(SubscriberCompanyController::class,'ensureTaxProfile'))->invoke(new SubscriberCompanyController,$company,$user);
        self::assertNull($profile->legal_name); self::assertNull($this->connection->profile['legal_name']);
        self::assertSame(1,count(array_filter($this->connection->mutations,fn($m)=>$m['table']==='audit_logs')));
    }

    public function test_legacy_initializer_preserves_an_existing_name_without_writing_it(): void
    {
        $this->connection->profile=['id'=>12,'company_id'=>7,'legal_name'=>'Historical identity'];
        [$company,,$user]=$this->context();
        $profile=(new ReflectionMethod(SubscriberCompanyController::class,'ensureTaxProfile'))->invoke(new SubscriberCompanyController,$company,$user);
        self::assertSame('Historical identity',$profile->legal_name); self::assertSame([],$this->connection->mutations);
    }

    public function test_memory_transport_never_accepts_fiscal_or_unknown_operations(): void
    {
        foreach(['invoices','invoice_items','payments','subscriptions','subscription_items'] as $table) {
            try { $this->connection->insert('insert into `'.$table.'` (`id`) values (?)',[1]); self::fail('Unexpected operation accepted.'); } catch(LogicException) {}
        }
    }
}

/** Explicit, memory-only shapes. No fallback to PDO, SQL execution, or unknown-table success. */
final class ProfileNameMemoryConnection extends MySqlConnection
{
    public ?array $profile=null;
    public array $mutations=[];
    public int $profileReads=0;
    public bool $pdoAttempted=false;
    private const PROFILE_COLUMNS=['company_id','legal_name','trade_name','country_code','tax_id','tax_id_type','address_line1','address_line2','city','state','postal_code','billing_email','billing_phone','billing_contact_name','taxpayer_type','economic_activity_primary_code','economic_activity_primary_name','tax_exempt','default_itbis_rate','tax_regime','economic_activities_secondary','meta','created_at','updated_at'];

    public function __construct()
    {
        parent::__construct(function(){ $this->pdoAttempted=true; throw new LogicException('NATIVE_PDO_FORBIDDEN'); },'profile_memory','',['driver'=>'mysql','name'=>'profile_memory']);
    }
    public function select($query,$bindings=[],$useReadPdo=true)
    {
        if(!in_array($query,['select * from `company_tax_profiles` where (`company_id` = ?) limit 1','select * from `company_tax_profiles` where `company_id` = ? limit 1','select * from `company_tax_profiles` where `company_id` = ? limit 1 for update'],true) || $bindings!==[7]) throw new LogicException('UNEXPECTED_READ');
        ++$this->profileReads; return $this->profile ? [(object)$this->profile] : [];
    }
    public function insert($query,$bindings=[],$sequence=null)
    {
        if(!preg_match('/\Ainsert into `(company_tax_profiles|audit_logs)` \((`[a-z0-9_]+`(?:, `[a-z0-9_]+`)*)\) values \((\?(?:, \?)*)\)\z/',$query,$m)) throw new LogicException('UNEXPECTED_INSERT');
        preg_match_all('/`([a-z0-9_]+)`/',$m[2],$columns);
        if(count($columns[1])!==count($bindings)) throw new LogicException('INSERT_BINDINGS');
        $data=array_combine($columns[1],$this->prepareBindings($bindings));
        if($m[1]==='company_tax_profiles') {
            if($this->profile!==null || ($data['company_id']??null)!==7 || array_diff(array_keys($data),self::PROFILE_COLUMNS)!==[]) throw new LogicException('PROFILE_INSERT_SCOPE');
            $this->lastInsertId=12; $this->profile=$data+['id'=>12];
        } else {
            if(($data['event']??null)!=='subscriber_company_tax_profile_auto_created' || ($data['model_id']??null)!==12 || ($data['user_id']??null)!==20) throw new LogicException('AUDIT_INSERT_SCOPE');
            $this->lastInsertId=13;
        }
        $this->mutations[]=['op'=>'insert','table'=>$m[1],'data'=>$data]; return true;
    }
    public function affectingStatement($query,$bindings=[])
    {
        if(!preg_match('/\Aupdate `(companies|subscribers|company_tax_profiles)` set /',$query,$target)) throw new LogicException('UNEXPECTED_UPDATE');
        $sql=str_replace('`'.$target[1].'`.','',$query);
        if(!preg_match('/\Aupdate `([a-z0-9_]+)` set (`[a-z0-9_]+` = \?(?:, `[a-z0-9_]+` = \?)*) where `id` = \?\z/',$sql,$m)) throw new LogicException('UPDATE_SHAPE');
        preg_match_all('/`([a-z0-9_]+)` = \?/',$m[2],$columns);
        if(count($columns[1])+1!==count($bindings) || (int)end($bindings)!==['companies'=>7,'subscribers'=>17,'company_tax_profiles'=>12][$m[1]]) throw new LogicException('UPDATE_BINDINGS');
        $data=array_combine($columns[1],array_slice($this->prepareBindings($bindings),0,-1));
        $allowed=['companies'=>['name','currency','timezone','updated_at'],'subscribers'=>['name','country_code','currency','timezone','meta','active','updated_at'],'company_tax_profiles'=>array_diff(self::PROFILE_COLUMNS,['tax_regime','default_itbis_rate','tax_exempt','economic_activities_secondary','meta'])];
        if(array_diff(array_keys($data),$allowed[$m[1]])!==[]) throw new LogicException('UPDATE_COLUMNS');
        if($m[1]==='company_tax_profiles') $this->profile=array_replace($this->profile ?? [],$data);
        $this->mutations[]=['op'=>'update','table'=>$m[1],'data'=>$data]; return 1;
    }
    public function transaction(Closure $callback,$attempts=1)
    {
        $before=$this->profile; ++$this->transactions;
        try { return $callback($this); } catch(Throwable $e) { $this->profile=$before; throw $e; } finally { --$this->transactions; }
    }
}
