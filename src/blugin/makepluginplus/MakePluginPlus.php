<?php

namespace blugin\makepluginplus;

use pocketmine\command\PluginCommand;
use pocketmine\plugin\PluginBase;
use blugin\makepluginplus\lang\PluginLang;
use blugin\makepluginplus\util\Utils;

class MakePluginPlus extends PluginBase{

    /** @var MakePluginPlus */
    private static $instance = null;

    /** @var string */
    public static $prefix = '';

    /** @return MakePluginPlus */
    public static function getInstance() : MakePluginPlus{
        return self::$instance;
    }

    /** @var PluginCommand */
    private $command = null;

    /** @var PluginLang */
    private $language;

    public function onLoad() : void{
        self::$instance = $this;
    }

    public function onEnable() : void{
        $dataFolder = $this->getDataFolder();
        if (!file_exists($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->language = new PluginLang($this);

        if ($this->command !== null) {
            $this->getServer()->getCommandMap()->unregister($this->command);
        }
        $this->command = new PluginCommand($this->language->translate('commands.makepluginplus'), $this);
        $this->command->setPermission('makepluginplus.cmd');
        $this->command->setDescription($this->language->translate('commands.makepluginplus.description'));
        $this->command->setUsage($this->language->translate('commands.makepluginplus.usage'));
        if (is_array($aliases = $this->language->getArray('commands.makepluginplus.aliases'))) {
            $this->command->setAliases($aliases);
        }
        $this->getServer()->getCommandMap()->register('makepluginplus', $this->command);
    }

    /**
     * @param string $name = ''
     *
     * @return PluginCommand
     */
    public function getCommand(string $name = '') : PluginCommand{
        return $this->command;
    }

    /**
     * @return PluginLang
     */
    public function getLanguage() : PluginLang{
        return $this->language;
    }

    /**
     * @return string
     */
    public function getSourceFolder() : string{
        $pharPath = \Phar::running();
        if (empty($pharPath)) {
            return dirname(__FILE__, 4) . DIRECTORY_SEPARATOR;
        } else {
            return $pharPath . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @param PluginBase $plugin
     * @param string     $pharPath
     * @param string     $filePath
     */
    public function buildPhar(PluginBase $plugin, string $filePath, string $pharPath) : void{
        $setting = $this->getConfig()->getAll();
        $description = $plugin->getDescription();
        if (file_exists($pharPath)) {
            try{
                \Phar::unlinkArchive($pharPath);
            } catch (\Exception $e){
                unlink($pharPath);
            }
        }
        $phar = new \Phar($pharPath);
        $phar->setSignatureAlgorithm(\Phar::SHA1);
        if (!$setting['skip-metadata']) {
            $phar->setMetadata([
              'name'         => $description->getName(),
              'version'      => $description->getVersion(),
              'main'         => $description->getMain(),
              'api'          => $description->getCompatibleApis(),
              'depend'       => $description->getDepend(),
              'description'  => $description->getDescription(),
              'authors'      => $description->getAuthors(),
              'website'      => $description->getWebsite(),
              'creationDate' => time(),
            ]);
        }
        if ($setting['skip-stub']) {
            $phar->setStub('<?php echo "PocketMine-MP plugin ' . "{$description->getName()}_v{$description->getVersion()}\nThis file has been generated using MakePluginPlus at " . date("r") . '\n----------------\n";if(extension_loaded("phar")){$phar = new \Phar(__FILE__);foreach($phar->getMetadata() as $key => $value){echo ucfirst($key).": ".(is_array($value) ? implode(", ", $value):$value)."\n";}} __HALT_COMPILER();');
        } else {
            $phar->setStub('<?php __HALT_COMPILER();');
        }

        if (file_exists($buildFolder = "{$this->getDataFolder()}build/")) {
            Utils::removeDirectory($buildFolder);
        }
        mkdir($buildFolder);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $path => $fileInfo) {
            $fileName = $fileInfo->getFilename();
            if ($fileName !== "." && $fileName !== "..") {
                $inPath = substr($path, strlen($filePath));
                if (!$setting['include-minimal'] || $inPath === 'plugin.yml' || strpos($inPath, 'src\\') === 0 || strpos($inPath, 'resources\\') === 0) {
                    $newFilePath = "{$buildFolder}{$inPath}";
                    $newFileDir = dirname($newFilePath);
                    if (!file_exists($newFileDir)) {
                        mkdir($newFileDir, 0777, true);
                    }
                    if (substr($path, -4) == '.php') {
                        $contents = \file_get_contents($path);
                        if ($setting['code-optimize']) {
                            $contents = Utils::codeOptimize($contents);
                        }
                        if ($setting['rename-variable']) {
                            $contents = Utils::renameVariable($contents);
                        }
                        if ($setting['remove-comment']) {
                            $contents = Utils::removeComment($contents);
                        }
                        if ($setting['remove-whitespace']) {
                            $contents = Utils::removeWhitespace($contents);
                        }
                        file_put_contents($newFilePath, $contents);
                    } else {
                        copy($path, $newFilePath);
                    }
                }
            }
        }
        $phar->startBuffering();
        $phar->buildFromDirectory($buildFolder);
        if ($setting['compress'] && \Phar::canCompress(\Phar::GZ)) {
            $phar->compressFiles(\Phar::GZ);
        }
        $phar->stopBuffering();
        Utils::removeDirectory($buildFolder = "{$this->getDataFolder()}build/");
    }
}