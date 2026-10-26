<?php

declare(strict_types=1);

namespace Shef\Pristine;

/**
 * Левое меню пользователя: настройка ядра intranet
 * `left_menu_sorted_items_<сайт>` = {show: [...], hide: [...], version}.
 * В show — ID пунктов по порядку, группа — ключ с массивом своих пунктов
 * (`menu_teamwork => [...]`). Чистые функции, без ядра.
 */
final class Layout
{
	/** Версия формата, которую пишет ядро (Controller\LeftMenu). */
	public const VERSION = '2021.10';

	/**
	 * Меню с профилем: остаются пункты из $keep (порядок пользователя
	 * сохраняется, группа остаётся, если в ней есть что показать), остальные
	 * известные пункты — в hide. Ничего не удаляется.
	 *
	 * @param array|null $current настройка пользователя; null — у него меню по умолчанию
	 * @param list<string> $keep ID пунктов, которые оставить
	 * @param list<string> $known все известные ID пунктов (из шаблона и настроек людей)
	 * @return array{show: array, hide: list<string>, version: string}
	 */
	public static function apply(?array $current, array $keep, array $known): array
	{
		$keepSet = array_flip($keep);
		$show = [];
		$hidden = [];
		foreach((array)($current['show'] ?? []) as $key => $item)
		{
			if(is_array($item))
			{
				$inGroup = [];
				foreach($item as $sub)
				{
					is_string($sub) && isset($keepSet[$sub]) ? $inGroup[] = $sub : $hidden[] = $sub;
				}
				// Группа без видимых пунктов не показывается.
				$inGroup !== [] && $show[$key] = $inGroup;
				continue;
			}
			is_string($item) && isset($keepSet[$item]) ? $show[] = $item : $hidden[] = $item;
		}
		// Пункты профиля, которых нет в меню пользователя (меню по умолчанию), — в конец show.
		$present = array_flip(self::flatten($show));
		foreach($keep as $id)
		{
			isset($present[$id]) || $show[] = $id;
		}
		// Всё известное, что не оставлено, — скрыто; прежнее hide сохраняем.
		$visible = array_flip(self::flatten($show));
		$hide = array_merge(self::flatten((array)($current['hide'] ?? [])), $hidden, $known);
		$hide = array_values(array_unique(array_filter($hide, static fn($id) => is_string($id) && !isset($visible[$id]))));

		return ['show' => self::renumber($show), 'hide' => $hide, 'version' => (string)($current['version'] ?? self::VERSION)];
	}

	/** Все ID пунктов меню (группы раскрыты). @return list<string> */
	public static function flatten(array $items): array
	{
		$out = [];
		foreach($items as $key => $item)
		{
			if(is_array($item))
			{
				array_push($out, ...self::flatten($item));
			}
			elseif(is_string($item))
			{
				$out[] = $item;
			}
		}

		return $out;
	}

	/** Числовые ключи по порядку, ключи групп как есть — как пишет ядро. */
	private static function renumber(array $show): array
	{
		$out = [];
		$n = 0;
		foreach($show as $key => $item)
		{
			is_int($key) ? $out[$n++] = $item : $out[$key] = $item;
		}

		return $out;
	}
}
