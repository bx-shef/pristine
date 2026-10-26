<?php

declare(strict_types=1);

/**
 * Заглушки ядра для тестов без коробки: настройки пользователей
 * (CUserOptions), пользователи с отделами (CUser), структура компании,
 * кеш, модули. Состояние — в $GLOBALS['stub'].
 */

namespace {
	$GLOBALS['stub'] = ['options' => [], 'users' => [], 'departments' => [], 'tags' => [], 'modules' => ['shef.options' => '3.1.0']];

	final class CUserOptions
	{
		public static function GetOption($category, $name, $default = false, $userId = false)
		{
			return $GLOBALS['stub']['options'][(int)$userId][$category][$name] ?? $default;
		}

		public static function SetOption($category, $name, $value, $common = false, $userId = false)
		{
			$GLOBALS['stub']['options'][(int)$userId][$category][$name] = $value;

			return true;
		}

		public static function DeleteOption($category, $name, $common = false, $userId = false)
		{
			unset($GLOBALS['stub']['options'][(int)$userId][$category][$name]);

			return true;
		}

		public static function GetList($order, $filter)
		{
			$rows = [];
			foreach($GLOBALS['stub']['options'] as $uid => $cats)
			{
				foreach($cats[$filter['CATEGORY']] ?? [] as $name => $value)
				{
					if(!isset($filter['NAME']) || $filter['NAME'] === $name)
					{
						$rows[] = ['USER_ID' => $uid, 'CATEGORY' => $filter['CATEGORY'], 'NAME' => $name, 'VALUE' => serialize($value)];
					}
				}
			}

			return new StubResult($rows);
		}
	}

	final class CUser
	{
		public static function GetList($by, $order, $filter, $params)
		{
			return new StubResult(array_map(static fn($id, $deps) => ['ID' => $id, 'UF_DEPARTMENT' => $deps], array_keys($GLOBALS['stub']['users']), $GLOBALS['stub']['users']));
		}
	}

	final class CIBlockSection
	{
		public static function GetList($order, $filter, $count, $select)
		{
			return new StubResult(array_map(static fn($id, $n) => ['ID' => $id, 'NAME' => $n], array_keys($GLOBALS['stub']['departments']), $GLOBALS['stub']['departments']));
		}
	}

	final class StubResult
	{
		public function __construct(private array $rows)
		{
		}

		public function Fetch()
		{
			return array_shift($this->rows) ?: false;
		}
	}

	final class StubCache
	{
		public function clearByTag($tag): void
		{
			$GLOBALS['stub']['tags'][] = $tag;
		}
	}

	$GLOBALS['CACHE_MANAGER'] = new StubCache();
}

namespace Bitrix\Main {
	final class Loader
	{
		public static function includeModule($m): bool
		{
			return true;
		}
	}

	/** Обработчики событий: $GLOBALS['stub']['events'] — «модуль:событие:класс::метод». */
	final class EventManager
	{
		public static function getInstance(): self
		{
			return new self();
		}

		public function registerEventHandler($from, $event, $module, $class, $method): void
		{
			$GLOBALS['stub']['events'][] = "$from:$event:$class::$method";
		}

		public function unRegisterEventHandler($from, $event, $module, $class, $method): void
		{
			$GLOBALS['stub']['events'] = array_values(array_diff($GLOBALS['stub']['events'] ?? [], ["$from:$event:$class::$method"]));
		}
	}

	final class ModuleManager
	{
		public static function isModuleInstalled($m): bool
		{
			return isset($GLOBALS['stub']['modules'][$m]);
		}

		public static function getVersion($m)
		{
			return $GLOBALS['stub']['modules'][$m] ?? false;
		}

		public static function registerModule($m): void
		{
			$GLOBALS['stub']['modules'][$m] = 'installed';
		}

		public static function unRegisterModule($m): void
		{
			unset($GLOBALS['stub']['modules'][$m]);
		}
	}
}

namespace Bitrix\Main\Config {
	final class Option
	{
		public static function get($module, $name, $default = '')
		{
			return $module === 'intranet' && $name === 'iblock_structure' ? 3 : ($GLOBALS['stub']['opt'][$module][$name] ?? $default);
		}

		public static function set($module, $name, $value): void
		{
			$GLOBALS['stub']['opt'][$module][$name] = (string)$value;
		}

		public static function delete($module, array $filter = []): void
		{
			if(isset($filter['name']))
			{
				unset($GLOBALS['stub']['opt'][$module][$filter['name']]);
				if(($GLOBALS['stub']['opt'][$module] ?? null) === [])
				{
					unset($GLOBALS['stub']['opt'][$module]);
				}

				return;
			}
			unset($GLOBALS['stub']['opt'][$module]);
			$GLOBALS['stub']['deleted'][] = $module;
		}
	}
}

/**
 * «Инструменты» портала: состояние — опции intranet `tool_<id>_main` и
 * `tool_subgroup_<id>_<раздел>`, как в ядре (по умолчанию 'Y').
 */
namespace Bitrix\Intranet\Settings\Tools {
	use Bitrix\Main\Config\Option;

	final class Tool
	{
		/** @param array<string, string> $subgroups раздел → название */
		public function __construct(private string $id, private array $subgroups = [])
		{
		}

		public function getId(): string
		{
			return $this->id;
		}

		public function getName(): string
		{
			return $this->id;
		}

		public function getOptionCode(): string
		{
			return 'tool_'.$this->id.'_main';
		}

		public function getSubgroupCode(string $id): string
		{
			return 'tool_subgroup_'.$this->id.'_'.$id;
		}

		public function getSubgroupsIds(): array
		{
			return array_combine(array_keys($this->subgroups), array_keys($this->subgroups));
		}

		public function getSubgroups(): array
		{
			$out = [];
			foreach($this->subgroups as $id => $name)
			{
				$out[$id] = ['code' => $this->getSubgroupCode($id), 'name' => $name];
			}

			return $out;
		}

		public function isEnabled(): bool
		{
			return Option::get('intranet', $this->getOptionCode(), 'Y') === 'Y';
		}

		public function isEnabledSubgroupById(string $id): bool
		{
			return $this->isEnabled() && Option::get('intranet', $this->getSubgroupCode($id), 'Y') === 'Y';
		}

		public function disable(): void
		{
			Option::set('intranet', $this->getOptionCode(), 'N');
			foreach($this->getSubgroups() as $s)
			{
				Option::set('intranet', $s['code'], 'N');
			}
		}

		public function disableSubgroup(string $code): void
		{
			Option::set('intranet', $code, 'N');
		}
	}

	final class ToolsManager
	{
		public static function getInstance(): self
		{
			return new self();
		}

		/** @return list<Tool> из $GLOBALS['stub']['tools'] */
		public function getToolList(): array
		{
			return $GLOBALS['stub']['tools'] ?? [];
		}

		public function clearCache(): void
		{
		}
	}
}
