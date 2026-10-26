<?php

declare(strict_types=1);

namespace Shef\Pristine;

/**
 * Применить профили к левому меню людей — для PHP-консоли админки
 * («Инструменты → Командная PHP-строка»):
 *
 *     \Shef\Pristine\Apply::run(dryRun: true);    // показать, кому и что поменяется
 *     \Shef\Pristine\Apply::run(dryRun: false);   // применить
 *     \Shef\Pristine\Apply::rollback();           // вернуть как было
 *
 * Перед изменением прежняя настройка человека сохраняется (своя настройка
 * модуля у пользователя); откат — одной командой. Повторный запуск ничего не
 * меняет; люди без профиля не трогаются. Кеш левого меню сбрасывается тегом
 * ядра `bitrix24_left_menu`.
 */
final class Apply
{
	/** Копия прежнего меню у пользователя: категория и имя настройки модуля. */
	public const BACKUP_CATEGORY = 'shef.pristine';
	public const BACKUP_NAME = 'left_menu_backup';
	/** У человека не было своей настройки — откат её удаляет. */
	public const NONE = '__default__';

	/** @return array{dryRun: bool, users: list<array>, known: list<string>, changed: int} отчёт */
	public static function run(bool $dryRun = true, ?Profiles $profiles = null): array
	{
		$profiles ??= Profiles::fromModule();
		$option = self::optionName($profiles->site());
		$people = self::people($profiles);
		// Известные пункты — из меню людей и из списка конфига: пункта нет ни у кого
		// в настройке («Сайты» на стенде, 2026-10-26) — без списка он не скрылся бы.
		$known = array_values(array_unique(array_merge(self::knownItems($option, array_keys($people)), $profiles->known())));
		$report = ['dryRun' => $dryRun, 'users' => [], 'known' => $known, 'changed' => 0];
		foreach($people as $userId => $departments)
		{
			$code = $profiles->codeFor($userId, $departments);
			if($code === null)
			{
				continue;
			}
			$profile = $profiles->profile($code);
			$current = \CUserOptions::GetOption('intranet', $option, null, $userId);
			$current = is_array($current) ? $current : null;
			$next = Layout::apply($current, $profile['keep'], $known);
			$same = $current !== null && Layout::flatten((array)$current['show']) === Layout::flatten($next['show'])
				&& self::sorted((array)($current['hide'] ?? [])) === self::sorted($next['hide']);
			$row = ['user' => $userId, 'profile' => $code, 'title' => $profile['title'], 'show' => Layout::flatten($next['show']), 'hidden' => count($next['hide']), 'changed' => !$same];
			if(!$same && !$dryRun)
			{
				// Копию — только первый раз: повторный запуск не затирает исходное меню.
				if(\CUserOptions::GetOption(self::BACKUP_CATEGORY, self::BACKUP_NAME, null, $userId) === null)
				{
					\CUserOptions::SetOption(self::BACKUP_CATEGORY, self::BACKUP_NAME, $current ?? self::NONE, false, $userId);
				}
				\CUserOptions::SetOption('intranet', $option, $next, false, $userId);
				$report['changed']++;
			}
			$report['users'][] = $row;
		}
		$dryRun || self::clearCache();

		return $report;
	}

	/** Вернуть меню, как было до run(). @return array{restored: int} */
	public static function rollback(?Profiles $profiles = null): array
	{
		$profiles ??= Profiles::fromModule();
		$option = self::optionName($profiles->site());
		$restored = 0;
		foreach(self::backupOwners() as $userId)
		{
			$backup = \CUserOptions::GetOption(self::BACKUP_CATEGORY, self::BACKUP_NAME, null, $userId);
			$backup === self::NONE || !is_array($backup)
				? \CUserOptions::DeleteOption('intranet', $option, false, $userId)
				: \CUserOptions::SetOption('intranet', $option, $backup, false, $userId);
			\CUserOptions::DeleteOption(self::BACKUP_CATEGORY, self::BACKUP_NAME, false, $userId);
			$restored++;
		}
		self::clearCache();

		return ['restored' => $restored];
	}

	public static function optionName(string $site): string
	{
		return 'left_menu_sorted_items_'.$site;
	}

	/** @return array<int, list<string>> активные люди отделов с профилем и с личным профилем → названия их отделов */
	private static function people(Profiles $profiles): array
	{
		$names = self::departmentNames();
		$wanted = array_flip($profiles->departmentNames());
		$out = [];
		$r = \CUser::GetList('ID', 'ASC', ['ACTIVE' => 'Y'], ['SELECT' => ['UF_DEPARTMENT'], 'FIELDS' => ['ID']]);
		while($u = $r->Fetch())
		{
			$deps = array_values(array_filter(array_map(static fn($id) => $names[(int)$id] ?? '', (array)($u['UF_DEPARTMENT'] ?? []))));
			$hit = array_filter($deps, static fn($n) => isset($wanted[$n]));
			if($hit !== [] || in_array((int)$u['ID'], $profiles->userIds(), true))
			{
				$out[(int)$u['ID']] = $deps;
			}
		}

		return $out;
	}

	/** @return array<int, string> ID отдела → название (структура компании) */
	private static function departmentNames(): array
	{
		$out = [];
		if(!\Bitrix\Main\Loader::includeModule('iblock'))
		{
			return $out;
		}
		$iblock = (int)\Bitrix\Main\Config\Option::get('intranet', 'iblock_structure', 0);
		$r = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblock], false, ['ID', 'NAME']);
		while($s = $r->Fetch())
		{
			$out[(int)$s['ID']] = (string)$s['NAME'];
		}

		return $out;
	}

	/**
	 * Известные ID пунктов: из настроек всех, у кого они есть (полное меню
	 * портала у тех, кто его сохранял), — чтобы скрыть и у людей с меню по умолчанию.
	 *
	 * @return list<string>
	 */
	private static function knownItems(string $option, array $userIds): array
	{
		$ids = [];
		$r = \CUserOptions::GetList([], ['CATEGORY' => 'intranet', 'NAME' => $option]);
		while($o = $r->Fetch())
		{
			$v = @unserialize((string)$o['VALUE'], ['allowed_classes' => false]);
			is_array($v) && array_push($ids, ...Layout::flatten(array_merge((array)($v['show'] ?? []), (array)($v['hide'] ?? []))));
		}

		return array_values(array_unique($ids));
	}

	/** @return list<int> у кого есть копия меню модуля */
	private static function backupOwners(): array
	{
		$out = [];
		$r = \CUserOptions::GetList([], ['CATEGORY' => self::BACKUP_CATEGORY, 'NAME' => self::BACKUP_NAME]);
		while($o = $r->Fetch())
		{
			$out[] = (int)$o['USER_ID'];
		}

		return array_values(array_unique(array_filter($out)));
	}

	private static function clearCache(): void
	{
		$GLOBALS['CACHE_MANAGER']->clearByTag('bitrix24_left_menu');
	}

	private static function sorted(array $ids): array
	{
		$ids = Layout::flatten($ids);
		sort($ids);

		return $ids;
	}
}
