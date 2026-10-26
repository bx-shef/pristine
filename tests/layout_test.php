<?php

declare(strict_types=1);

require __DIR__.'/assert.php';
require dirname(__DIR__).'/lib/layout.php';

use Shef\Pristine\Layout;

// Меню администратора стенда (2026-10-23, docs/box/intranet-ui.md).
$admin = [
	'show' => [
		0 => 'menu_crm_favorite', 1 => 'menu_external_mail', 2 => 'menu_tasks',
		'menu_teamwork' => ['menu_im_messenger', 'menu_live_feed', 'menu_calendar', 'menu_files'],
		3 => 'menu_marketing', 4 => 'menu_shop', 5 => 'shef_agentflow_admin',
	],
	'hide' => ['menu_sign'],
	'version' => '2021.10',
];
$keep = ['menu_crm_favorite', 'menu_tasks', 'menu_im_messenger', 'menu_calendar', 'shef_agentflow_admin'];
$known = Layout::flatten(array_merge($admin['show'], $admin['hide']));

$r = Layout::apply($admin, $keep, $known);
check(Layout::flatten($r['show']) === ['menu_crm_favorite', 'menu_tasks', 'menu_im_messenger', 'menu_calendar', 'shef_agentflow_admin'], 'остаются пункты профиля в порядке пользователя');
check(isset($r['show']['menu_teamwork']) && $r['show']['menu_teamwork'] === ['menu_im_messenger', 'menu_calendar'], 'группа остаётся с видимыми пунктами');
sort($r['hide']);
check($r['hide'] === ['menu_external_mail', 'menu_files', 'menu_live_feed', 'menu_marketing', 'menu_shop', 'menu_sign'], 'остальное — в hide; прежнее hide сохранено; ничего не удалено');
check(array_keys($r['show']) === [0, 1, 'menu_teamwork', 2], 'числовые ключи по порядку, ключ группы как у ядра');

$fresh = Layout::apply(null, $keep, $known);
check(Layout::flatten($fresh['show']) === $keep && in_array('menu_marketing', $fresh['hide'], true) && $fresh['version'] === Layout::VERSION, 'меню по умолчанию (настройки нет): профиль в show, известное остальное — в hide');

$again = Layout::apply($r, $keep, $known);
sort($again['hide']);
check(Layout::flatten($again['show']) === Layout::flatten($r['show']) && $again['hide'] === $r['hide'], 'повторное применение ничего не меняет');

$empty = Layout::apply(['show' => ['menu_teamwork' => ['menu_live_feed']], 'hide' => []], ['menu_tasks'], []);
check(!isset($empty['show']['menu_teamwork']) && in_array('menu_live_feed', $empty['hide'], true), 'группа без видимых пунктов не показывается');

done();
