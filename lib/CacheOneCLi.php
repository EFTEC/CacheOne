<?php /** @noinspection PhpUnused */

namespace eftec;

use eftec\DocumentStoreOne\DocumentStoreOne;
use Exception;
use RuntimeException;

/**
 * Class CacheOneCli
 * It is the CLI interface for PdoOne.<br>
 * <b>How to execute it?</b><br>
 * In the command line, runs the next line:<br>
 * <pre>
 * vendor/bin/cacheonecli (Linux/macOS) vendor/bin/cacheonecli.bat (Windows)
 * </pre>
 *
 * @see           https://github.com/EFTEC/CacheOneCli
 * @package       eftec
 * @author        Jorge Castro Castillo
 * @copyright (c) Jorge Castro C. Dual Licence: MIT and Commercial License  https://github.com/EFTEC/PdoOne
 * @version       1.2
 */
class CacheOneCLi extends PdoOneCli
{
    public const VERSION = '1.2';

    public function __construct(bool $run = true)
    {
        parent::__construct(false);
        $this->cli->debug = true;
        $this->cli->addMenuItem('mainmenu', 'connect',
            '[{{connect}}] Configure the PDO connection', 'navigate:pdooneconnect');
        $this->cli->addMenuItem('mainmenu', 'cache',
            '[{{cacheonetypeok}}] Configure the cache', 'navigate:menucacheone');
        $this->cli->setVariable('cacheonetypepdo', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypedocument', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypeapcu', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetyperedis', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypememcache', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypeok', '<red>pending</red>', false);
        $this->cli->addVariableCallBack('cacheone', function() {
            $this->setCallBackVariables();
        });
        $this->menuCacheOne();
        $loadConfig=$this->cli->createOrReplaceParam('filecacheone', [], 'longflag')
            ->setRequired(false)
            ->setCurrentAsDefault()
            ->setDescription('select a configuration file to load', 'Select the configuration file to use', [
                    'Example: <dim>"--filecacheone myconfig"</dim>']
                , 'file')
            ->setDefault('')
            ->setInput(false, 'string', [])
            ->evalParam();

        if ($run) {
            if ($this->cli->getSTDIN() === null) {
                $this->showLogo();
            }
            $this->funLoadConfig($loadConfig);
            $this->cli->evalMenu('mainmenu', $this);
        } else {
            $this->funLoadConfig($loadConfig);
        }
    }

    public function setCallBackVariables(): void
    {
        $this->cli->setVariable('cacheonetypepdo', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypedocument', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypeapcu', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetyperedis', '<red>no</red>', false);
        $this->cli->setVariable('cacheonetypememcache', '<red>no</red>', false);
        if ($this->cli->getVariable('cacheonetype')) {
            $this->cli->createOrReplaceParam('type',[],'none')
                ->setValue($this->cli->getVariable('cacheonetype')) // this field is used as variable to load and save
                ->setInput(false)
                ->evalParam();
            $this->cli->setVariable('cacheonetypeok', '<green>ok</green>', false);
        } else {
            $this->cli->setVariable('cacheonetypeok', '<red>pending</red>', false);
        }
        switch ($this->cli->getVariable('cacheonetype')) {
            case 'pdoone':
                $this->cli->setVariable('cacheonetypepdo', '<green>ok</green>', false);
                break;
            case 'document':
                $this->cli->setVariable('cacheonetypedocument', '<green>ok</green>', false);
                break;
            case 'apcu':
                $this->cli->setVariable('cacheonetypeapcu', '<green>ok</green>', false);
                break;
            case 'redis':
                $this->cli->setVariable('cacheonetyperedis', '<green>ok</green>', false);
                break;
            case 'memcache':
                $this->cli->setVariable('cacheonetypememcache', '<green>ok</green>', false);
                break;
        }
    }

    public function menuCacheOne(): void
    {
        $this->cli->addMenu('menucacheone',
            function($cli) {
                $cli->upLevel('cache');
                $cli->setColor(['byellow'])->showBread();
            }
            , 'footer');
        $this->cli->addMenuItem('menucacheone', 'pdo',
            '[{{connect}}][{{cacheonetypepdo}}] Configure the PDO connection', 'cacheonepdo');
        $this->cli->addMenuItem('menucacheone', 'document',
            '[{{cacheonetypedocument}}] Configure the Document connection', 'cacheonedocument');
        $this->cli->addMenuItem('menucacheone', 'apcu',
            '[{{cacheonetypeapcu}}] Configure the APCU connection', 'cacheoneapcu');
        $this->cli->addMenuItem('menucacheone', 'memcache',
            '[{{cacheonetypememcache}}] Configure the Memcache connection', 'cacheonememcache');
        $this->cli->addMenuItem('menucacheone', 'redis',
            '[{{cacheonetyperedis}}] Configure the Redis connection', 'cacheoneredis');
        $this->cli->addMenuItem('menucacheone', 'load',
            'Load a previous configuration', 'cacheoneload');
        $this->cli->addMenuItem('menucacheone', 'save',
            '[{{cacheonetypeok}}] Save the configuration', 'cacheonesave');
        $this->cli->addMenuItem('menucacheone', 'test',
            '[{{cacheonetypeok}}] Integration test', 'cacheonetest');
    }

    public function menuCacheOneAPCU(): void
    {
        $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
            ->setDescription('', 'Do you want to use APCU for cache ?')
            ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
        if ($yesNoParam->value === 'no') {
            return;
        }
        $this->cli->setVariable('cacheonetype', 'apcu');
        try {
            if (function_exists('apcu_enabled')) {
                $result = apcu_enabled();
                if (!$result) {
                    throw new RuntimeException('APCU is not enabled');
                }
            } else {
                throw new RuntimeException('APCU not installed');
            }
        } catch (Exception $ex) {
            $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
        }
    }

    public function menuCacheOneDocument(): void
    {
        $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
            ->setDescription('', 'Do you want to use Document for cache ?')
            ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
        if ($yesNoParam->value === 'no') {
            return;
        }
        $this->cli->setVariable('cacheonetype', 'document');
        while (true) {
            try {
                if (!class_exists(DocumentStoreOne::class)) {
                    throw new RuntimeException('DocumentStoreOne not installed');
                }
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
            }
            $this->cli->showLine('Current path <bold>'.__DIR__ .'</bold>');
            $server = $this->cli->createOrReplaceParam('cacheserver', [], 'none')
                ->setDescription('', 'Select the relative or absolute folder',['example: folder1/folder2 (relative)',
                    '/folder1/folder2 (absolute)',
                    'c:\\folder1\\folder2 (absolute Windows)'])
                ->setDefault('base')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            $schema = $this->cli->createOrReplaceParam('schema', [], 'none')
                ->setDescription('', 'Select the schema subfolder folder')
                ->setDefault('schema')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            try {
                $server->value= DocumentStoreOne::isRelativePath($server->value)?getcwd().'/'.$server->value:$server->value;

                if (!is_dir($server->value)) {
                    /** @noinspection MkdirRaceConditionInspection */
                    if (!mkdir($server->value)) {
                        throw new RuntimeException('Directory ' . $server->value . ' was not created');
                    }
                    $this->cli->showCheck('OK', 'green', 'Database created '.$server->value);
                } else {
                    $this->cli->showCheck('OK', 'green', 'Database found');
                }
                $doc = new DocumentStoreOne($server->value, '');
                $doc->collection($schema->value, true);
                break;
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', 'Unable to connect to database ' . $ex->getMessage());
                $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
                    ->setDescription('', 'Do you want to retry?')
                    ->setDefault('yes')
                    ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
                if ($yesNoParam->value === 'no') {
                    break;
                }
            }
        } // while(true)
    }

    public function menuCacheOneRedis(): void
    {
        $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
            ->setDescription('', 'Do you want to use Redis for cache ?')
            ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
        if ($yesNoParam->value === 'no') {
            return;
        }
        $this->cli->setVariable('cacheonetype', 'redis');
        while (true) {
            try {
                if (!class_exists('Redis')) {
                    throw new RuntimeException('Redis not installed');
                }
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
            }
            $server = $this->cli->createOrReplaceParam('cacheserver', [], 'none')
                ->setDescription('', 'Select the server')
                ->setDefault('127.0.0.1')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            $schema = $this->cli->createOrReplaceParam('schema', [], 'none')
                ->setDescription('', 'Select the schema')
                ->setDefault('')
                ->setAllowEmpty()
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            $port = $this->cli->createOrReplaceParam('port', [], 'none')
                ->setDescription('', 'Select the port')
                ->setDefault('6379')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            try {
                new CacheOne("redis", $server->value, $schema->value, $port->value);
                $this->cli->showCheck('OK', 'green', 'Redis tested correctly');
                break;
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', 'Unable to connect to database ' . $ex->getMessage());
                $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
                    ->setDescription('', 'Do you want to retry?')
                    ->setDefault('yes')
                    ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
                if ($yesNoParam->value === 'no') {
                    break;
                }
            }
        } // while(true)
    }

    public function menuCacheOneMemcache(): void
    {
        $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
            ->setDescription('', 'Do you want to use Memcache for cache ?')
            ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
        if ($yesNoParam->value === 'no') {
            return;
        }
        $this->cli->setVariable('cacheonetype', 'memcache');
        while (true) {
            try {
                if (!class_exists('Memcache')) {
                    throw new RuntimeException('Memcache not installed');
                }
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
            }
            $server = $this->cli->createOrReplaceParam('cacheserver', [], 'none')
                ->setDescription('', 'Select the server')
                ->setDefault('127.0.0.1')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            $schema = $this->cli->createOrReplaceParam('schema', [], 'none')
                ->setDescription('', 'Select the schema')
                ->setDefault('')
                ->setAllowEmpty()
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            $port = $this->cli->createOrReplaceParam('port', [], 'none')
                ->setDescription('', 'Select the port')
                ->setDefault('11211')
                ->setCurrentAsDefault()
                ->setInput()
                ->evalParam(true);
            try {
                $cache = new CacheOne("memcache", $server->value, $schema->value, $port->value);
                if ($cache->enabled === false) {
                    throw new RuntimeException('not connected');
                }
                break;
            } catch (Exception $ex) {
                $this->cli->showCheck('ERROR', 'red', 'Error on memcache: ' . $ex->getMessage());
                $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
                    ->setDescription('', 'Do you want to retry?')
                    ->setDefault('yes')
                    ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
                if ($yesNoParam->value === 'no') {
                    break;
                }
            }
        } // while(true)
    }

    public function menuCacheOnePdo(): void
    {
        $yesNoParam = $this->cli->createOrReplaceParam('confirmation', [], 'none')
            ->setDescription('', 'Do you want to use PdoOne for cache ?')
            ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
        if ($yesNoParam->value === 'no') {
            return;
        }
        $this->cli->setVariable('cacheonetype', 'pdoone');
        try {
            $pdo = $this->createPdoInstance();
            if ($pdo === null) {
                throw new RuntimeException('Unable to connect to the database');
            }
            while (true) {
                // testing the table
                try {
                    $exist = $pdo->existKVTable();
                    if (!$exist) {
                        $this->cli->showCheck('info', 'yellow', 'Table does not exist, we will try to create');
                        $pdo->setKvDefaultTable($this->cli->getValue('tableKV'));
                        $creation = $pdo->createTableKV();
                        if (!$creation) {
                            throw new RuntimeException('Unable to create table');
                        }
                        $this->cli->showCheck('OK', 'green', 'Table created</bold>');
                        break;
                    }
                    $this->cli->showCheck('OK', 'green', 'Table exists</bold>');
                    break;
                } catch (Exception $ex) {
                    $this->cli->showCheck('ERROR', 'red', 'Unable to create the table ' . $ex->getMessage());
                    $rt = $this->cli->createParam('retry')
                        ->setDescription('', 'Do you want to retry?')
                        ->setInput(true, 'optionshort', ['yes', 'no'])->evalParam(true);
                    if ($rt->value === 'no') {
                        break;
                    }
                }
            }
        } catch (Exception $ex) {
            $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
        }
    }
    public function menuCacheOneTest():void
    {
        $this->cli->upLevel('test');
        $this->cli->setColor(['byellow'])->showBread();
        $r=$this->getConfig();
        // tableKV
        //'type', 'server', 'schema', 'port', 'user', 'password'
        if(($r['type'] === 'pdoone') && !isset($r['cacheserver']['databaseType'])) {
            //$r2=['databaseType','server','user','pwd','database','logFile','charset','nodeId','tableKV'];
            $r2 =$this->cli->getValueAsArray(['databaseType','server','user','pwd','database','logFile','charset','nodeId','tableKV']);
            $r['cacheserver'] = $r2;
        }
        $cache=new CacheOne($r['type'],$r['cacheserver'],$r['schema']??"",$r['port']??0,$r['user']??"",@$r['password']??"");

        try {
            if(!$cache->enabled) {
                throw new RuntimeException('not connected');
            }
            $r2 = $cache->set('g1', 'v1', 'hello');
            if ($r2 === false) {
                throw new RuntimeException('unable to set');
            }
            $get = $cache->get('g1', 'v1');
            if ($get !== 'hello') {
                throw new RuntimeException('unable to get');
            }
            $this->cli->showCheck('OK', 'green', $r['type'].' tested correctly');
        } catch(Exception $ex) {
            $this->cli->showCheck('ERROR', 'red', $ex->getMessage());
        }

        $this->cli->downLevel();
    }

    public function menuCacheOneSave(): void
    {
        $this->cli->upLevel('save');
        $this->cli->setColor(['byellow'])->showBread();
        $sg = $this->cli->createParam('yn', [], 'none')
            ->setDescription('', 'Do you want to save the configurations of connection?')
            ->setInput(true, 'optionshort', ['yes', 'no'])
            ->setDefault('yes')
            ->evalParam(true);
        if ($sg->value === 'yes') {
            $saveconfig = $this->cli->getParameter('filecacheone')->setInput()->evalParam(true);
            if ($saveconfig->value) {
                $r = $this->cli->saveDataPHPFormat($this->cli->getValue('filecacheone'), $this->getConfig());
                if ($r === '') {
                    $this->cli->showCheck('OK', 'green', 'file saved correctly');
                }
            }
        }
        $this->cli->downLevel();
    }

    public function menuCacheOneLoad(): void
    {
        $this->cli->upLevel('load');
        $this->cli->setColor(['byellow'])->showBread();
        $saveconfig = $this->cli->getParameter('filecacheone')
            ->setInput()
            ->evalParam(true);
        $this->funLoadConfig($saveconfig);
        $this->cli->downLevel();
    }

    public function getConfig(): array
    {
        $r= $this->cli->getValueAsArray(['type', 'cacheserver', 'schema', 'port', 'user', 'password']);
        if($r['type']==='pdoone') {
            $r['cacheserver']=$this->cli->getValueAsArray(['databaseType', 'server', 'user', 'password', 'user', 'password','database']);
        }
        return $r;
    }

    public function setConfig(array $array): void
    {
        $this->cli->setParamUsingArray($array, ['type', 'cacheserver', 'schema', 'port', 'user', 'password']);
        $this->cli->callVariablesCallBack();
    }

    /**
     * @param $loadConfig
     * @return void
     */
    protected function funLoadConfig($loadConfig): void
    {
        if ($loadConfig->value) {
            $r = $this->cli->readDataPHPFormat($this->cli->getValue('filecacheone'));
            if ($r !== null && $r[0] === true) {
                $this->setConfig($r[1]);
                $this->cli->setVariable('cacheonetype', $r[1]['type']);
                $this->cli->showCheck('OK', 'green', 'Configuration loaded');
            } else {
                $this->cli->showCheck('ERROR', 'red', $r === null ? 'unable to read file' : $r[1]);
            }
        }
    }

    protected function showLogo(): void
    {
        $v = CacheOne::VERSION;
        $vc = self::VERSION;
        $this->cli->show("
   _____              _             ____               
  / ____|            | |           / __ \              
 | |      __ _   ___ | |__    ___ | |  | | _ __    ___ 
 | |     / _` | / __|| '_ \  / _ \| |  | || '_ \  / _ \
 | |____| (_| || (__ | | | ||  __/| |__| || | | ||  __/
  \_____|\__,_| \___||_| |_| \___| \____/ |_| |_| \___|                                                       
CacheOne: $v  Cli: $vc  

<yellow>Syntax:php " . basename(__FILE__) . " <command> <flags></yellow>

");
        $this->cli->showParamSyntax2();
    }
}
