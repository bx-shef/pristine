<?php

declare(strict_types=1);

namespace Shef\Pristine;

use Bitrix\Intranet\Settings\Tools\ToolsManager;
use Bitrix\Main\Config\Option;

/**
 * «Инструменты» портала (Настройки → Инструменты): выключить лишнее для всех —
 * для PHP-консоли админки:
 *
 *     \Shef\Pristine\Tools::run(dryRun: true);    // показать, что выключится
 *     \Shef\Pristine\Tools::run(dryRun: false);   // выключить
 *     \Shef\Pristine\Tools::rollback();           // вернуть как было
 *
 * Список — `config/profiles.php`, ключ `tools_off`: `<инструмент>` целиком или
 * `<инструмент>.<раздел>` (раздел «Совместной работы» и т.п.). Выключает ядро
 * (`Tool::disable`, `Tool::disableSubgroup`); перед этим прежние значения опций
 * `intranet / tool_…` сохраняются в настройку модуля — откат возвращает их.
 */
final class Tools
{
	public const BACKUP_NAME = 'tools_backup';

	/** @return array{dryRun: bool, off: list<array{id: string, name: string, was: bool}>, missing: list<string>} отчёт */
	public static function run(bool $dryRun = true, ?Profiles $profiles = null): array
	{
		$profiles ??= Profiles::fromModule();
		$report = ['dryRun' => $dryRun, 'off' => [], 'missing' => []];
		$backup = self::backup();
		$tools = self::tools();
		foreach($profiles->toolsOff() as $key)
		{
			[$toolId, $sub] = array_pad(explode('.', $key, 2), 2, null);
			$tool = $tools[$toolId] ?? null;
			if($tool === null || ($sub !== null && !isset($tool->getSubgroupsIds()[$sub])))
			{
				$report['missing'][] = $key;
				continue;
			}
			$codes = $sub === null ? array_merge([$tool->getOptionCode()], array_column($tool->getSubgroups(), 'code')) : [$tool->getSubgroupCode($sub)];
			$was = $sub === null ? $tool->isEnabled() : $tool->isEnabledSubgroupById($sub);
			$report['off'][] = ['id' => $key, 'name' => $sub === null ? $tool->getName() : (string)($tool->getSubgroups()[$sub]['name'] ?? $sub), 'was' => $was];
			if($dryRun || !$was)
			{
				continue;
			}
			// Копия — только первый раз: повторный запуск не затирает исходное.
			foreach($codes as $code)
			{
				$backup[$code] ??= Option::get('intranet', $code, '');
			}
			$sub === null ? $tool->disable() : $tool->disableSubgroup($tool->getSubgroupCode($sub));
		}
		if(!$dryRun)
		{
			Option::set('shef.pristine', self::BACKUP_NAME, json_encode($backup, JSON_UNESCAPED_UNICODE));
			ToolsManager::getInstance()->clearCache();
			$GLOBALS['CACHE_MANAGER']->clearByTag('bitrix24_left_menu');
		}

		return $report;
	}

	/** Вернуть инструменты, как было до run(). @return array{restored: int} */
	public static function rollback(): array
	{
		$backup = self::backup();
		foreach($backup as $code => $value)
		{
			$value === '' ? Option::delete('intranet', ['name' => $code]) : Option::set('intranet', $code, $value);
		}
		Option::delete('shef.pristine', ['name' => self::BACKUP_NAME]);
		ToolsManager::getInstance()->clearCache();
		$GLOBALS['CACHE_MANAGER']->clearByTag('bitrix24_left_menu');

		return ['restored' => count($backup)];
	}

	/** @return array<string, \Bitrix\Intranet\Settings\Tools\Tool> ID инструмента → инструмент */
	private static function tools(): array
	{
		\Bitrix\Main\Loader::includeModule('intranet');
		$out = [];
		foreach(ToolsManager::getInstance()->getToolList() as $tool)
		{
			$out[$tool->getId()] = $tool;
		}

		return $out;
	}

	/** @return array<string, string> опция intranet → прежнее значение ('' — не было) */
	private static function backup(): array
	{
		$v = json_decode((string)Option::get('shef.pristine', self::BACKUP_NAME, ''), true);

		return is_array($v) ? array_map('strval', $v) : [];
	}
}
