<?php

declare(strict_types=1);

namespace SOFe\Capital\Config;

use Closure;
use Generator;
use Logger;
use RuntimeException;
use SOFe\AwaitGenerator\Await;
use SOFe\Capital as C;
use SOFe\Capital\Di\Context;
use SOFe\Capital\Di\FromContext;
use SOFe\Capital\Di\Singleton;
use SOFe\Capital\Di\SingletonArgs;
use SOFe\Capital\Di\SingletonTrait;
use SOFe\Capital\Plugin\MainClass;
use function count;
use function file_exists;
use function file_put_contents;
use function gettype;
use function yaml_emit;
use function yaml_parse_file;

/**
 * Stores the raw config data. Should not be used directly except for config parsing.
 */
final class Raw implements Singleton, FromContext {
    use SingletonArgs, SingletonTrait;

    private const ALL_CONFIGS = [
        C\Database\Config::class => true,
        C\Schema\Config::class => true,
        C\Analytics\Config::class => true,
        C\Transfer\Config::class => true,
    ];

    /** @var list<Closure() : void> resolve functions called when all configs are loaded */
    private array $onConfigLoaded = [];

    /** @var array<class-string<ConfigInterface>, object> Loaded config files are stored here. */
    private array $loadedConfigs = [];

    public Parser $parser;

    /**
     * @param null|array<string, mixed> $mainConfig data from config.yml
     * @param array<string, mixed> $dbConfig data from db.yml
     */
    public function __construct(
        private Logger $logger,
        private Context $di,
        private string $dataFolder,
        ?array $mainConfig,
        public array $dbConfig,
    ) {
        if($mainConfig === null) {
            // need to generate new config
            $this->parser = self::createFailSafeParser([]);
        } else {
            $this->parser = new Parser(new ArrayRef($mainConfig), [], false);
        }
    }

    /**
     * @template T of ConfigInterface
     * @param class-string<T> $class
     * @return Generator<mixed, mixed, mixed, T>
     */
    public function loadConfig(string $class) : Generator {
        if(!isset(self::ALL_CONFIGS[$class])) {
            throw new RuntimeException("Config $class not in " . self::class . "::ALL_CONFIGS");
        }

        if(count($this->onConfigLoaded) === 0) {
            yield from $this->loadAll();
        } else {
            $this->onConfigLoaded[] = yield Await::RESOLVE;
            yield Await::ONCE;
        }

        $config = $this->loadedConfigs[$class];
        if(!($config instanceof $class)) {
            throw new RuntimeException("$class::parse() returned " . gettype($config));
        }

        return $config;
    }

    /**
     * @return VoidPromise
     */
    private function loadAll() : Generator {
        $this->logger->debug("Start loading configs");

        $promises = [];
        /** @var class-string<ConfigInterface> $class */
        foreach(self::ALL_CONFIGS as $class => $_) {
            $promises[$class] = (function() use($class){
                $this->loadedConfigs[$class] = yield from $class::parse($this->parser, $this->di);
            })();
        }

        try {
            yield from Await::all($promises);
        } catch(ConfigException $e) {
            $this->logger->error("Error loading config.yml: " . $e->getMessage());

            $backupPath = $this->dataFolder . "config.yml.old";
            $i = 1;
            while(file_exists($backupPath)) {
                $i += 1;
                $backupPath = $this->dataFolder . "config.yml.old.$i";
            }

            $this->logger->notice("Regenerating new config file. The old file is saved to $backupPath.");

            $this->parser = self::createFailSafeParser($this->parser->getFullConfig());

            $promises = [];
            /** @var class-string<ConfigInterface> $class */
            foreach(self::ALL_CONFIGS as $class => $_) {
                $promises[$class] = (function() use($class){
                    $this->loadedConfigs[$class] = yield from $class::parse($this->parser, $this->di);
                })();
            }

            yield from Await::all($promises);
        }

        if($this->parser->isFailSafe()) {
            file_put_contents($this->dataFolder . "config.yml", yaml_emit($this->parser->getFullConfig()));
        }
    }

    public static function fromSingletonArgs(MainClass $main, Context $di, Logger $logger) : self {
        if(file_exists($main->getDataFolder() . "config.yml")) {
            $mainConfig = yaml_parse_file($main->getDataFolder() . "config.yml");
        } else {
            $mainConfig = null;
        }

        $main->saveResource("db.yml");
        $dbConfig = yaml_parse_file($main->getDataFolder() . "db.yml");

        return new self($logger, $di, $main->getDataFolder(), $mainConfig, $dbConfig);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function createFailSafeParser(array $data) : Parser {
        $array = new ArrayRef($data);
        $array->set(["#"], <<<'EOT'
            This is the main config file of Capital.
            You can change the values in this file to configure Capital.
            If you change some main settings that change the structure (e.g. schema), Capital will try its best
            to migrate your previous settings to the new structure and overwrite this file.
            The previous file will be stored in config.yml.old.
            EOT);

        return new Parser($array, [], true);
    }
}
