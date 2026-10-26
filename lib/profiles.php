<?php

declare(strict_types=1);

namespace Shef\Pristine;

/** Профили из config/profiles.php: кому какой. Без ядра. */
final class Profiles
{
	public function __construct(private readonly array $config)
	{
	}

	public static function fromModule(): self
	{
		return new self(require dirname(__DIR__).'/config/profiles.php');
	}

	public function site(): string
	{
		return (string)($this->config['site'] ?? 's1');
	}

	/** @return array{title: string, keep: list<string>}|null */
	public function profile(string $code): ?array
	{
		$p = $this->config['profiles'][$code] ?? null;

		return is_array($p) ? ['title' => (string)($p['title'] ?? $code), 'keep' => array_values(array_map('strval', (array)($p['keep'] ?? [])))] : null;
	}

	/**
	 * Профиль человека: свой важнее отдельского; из нескольких отделов — первый
	 * с профилем. null — интерфейс не трогаем.
	 *
	 * @param list<string> $departments названия отделов человека
	 */
	public function codeFor(int $userId, array $departments): ?string
	{
		$own = $this->config['users'][$userId] ?? null;
		if(is_string($own) && $this->profile($own) !== null)
		{
			return $own;
		}
		foreach($departments as $name)
		{
			$code = $this->config['departments'][$name] ?? null;
			if(is_string($code) && $this->profile($code) !== null)
			{
				return $code;
			}
		}

		return null;
	}

	/** @return list<string> стандартные пункты меню портала (config: known) */
	public function known(): array
	{
		return array_values(array_map('strval', (array)($this->config['known'] ?? [])));
	}

	/** @return list<string> что выключить в «Инструментах» портала (config: tools_off) */
	public function toolsOff(): array
	{
		return array_values(array_map('strval', (array)($this->config['tools_off'] ?? [])));
	}

	/** @return list<string> названия отделов с профилем */
	public function departmentNames(): array
	{
		return array_keys((array)($this->config['departments'] ?? []));
	}

	/** @return list<int> пользователи с личным профилем */
	public function userIds(): array
	{
		return array_map('intval', array_keys((array)($this->config['users'] ?? [])));
	}
}
