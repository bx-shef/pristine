<?php

use Bitrix\Main\Config;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

if (class_exists('shef_pristine')) {
    return;
}

Loc::loadMessages(__FILE__);

/**
 * shef.pristine — чистый интерфейс сотрудников: профили левого меню по
 * отделам и людям (#276). Установка только регистрирует модуль: интерфейс
 * меняет \Shef\Pristine\Apply::run(dryRun: false) из PHP-консоли.
 */
class shef_pristine extends CModule
{
    public $MODULE_ID = 'shef.pristine';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'N';
    public $PARTNER_NAME;
    public $PARTNER_URI;

    /** @var string */
    public $PHP_MIN_VER = '8.2.0';
    /**
     * Канон линейки bx-shef: слой настроек shef.options 3.x (страница
     * настроек — следующим шагом, ShOptionsConfig).
     *
     * @var array<string, string>
     */
    public $NEED_MODULES_BY_VERSION = [
        'shef.options' => '3.0.0',
    ];

    public function __construct()
    {
        $arModuleVersion = [];
        require __DIR__.'/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('shef.pristine_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('shef.pristine_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('shef.pristine_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('shef.pristine_PARTNER_URI');
    }

    /** Чего не хватает для установки; пусто — можно ставить. Без ядра — для тестов. */
    public function missing(): array
    {
        $out = [];
        if (version_compare(PHP_VERSION, $this->PHP_MIN_VER, '<')) {
            $out[] = 'PHP '.$this->PHP_MIN_VER;
        }
        foreach ($this->NEED_MODULES_BY_VERSION as $module => $version) {
            if (!ModuleManager::isModuleInstalled($module) || version_compare((string)ModuleManager::getVersion($module), $version, '<')) {
                $out[] = $module.' '.$version;
            }
        }

        return $out;
    }

    public function DoInstall(): bool
    {
        $missing = $this->missing();
        if ($missing !== []) {
            $GLOBALS['APPLICATION']->ThrowException(Loc::getMessage('shef.pristine_NEED', ['#LIST#' => implode(', ', $missing)]));

            return false;
        }
        ModuleManager::registerModule($this->MODULE_ID);
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler('main', 'OnProlog', $this->MODULE_ID, \Shef\Pristine\Chrome::class, 'onProlog');

        return true;
    }

    /**
     * Удаление уносит своё и не трогает чужое: меню людей возвращается как
     * было (откат по копиям модуля), копии и настройки модуля удаляются.
     */
    public function DoUninstall(): bool
    {
        if (\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
            \Shef\Pristine\Apply::rollback();
            \Shef\Pristine\Tools::rollback();
        }
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler('main', 'OnProlog', $this->MODULE_ID, \Shef\Pristine\Chrome::class, 'onProlog');
        Config\Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }
}
