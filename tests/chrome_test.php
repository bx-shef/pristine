<?php

declare(strict_types=1);

/** Стиль `Chrome` по профилю: группы селекторов, профиль — как в Apply (#2). */

require __DIR__.'/assert.php';
require __DIR__.'/stub/bitrix.php';
foreach(['layout', 'profiles', 'apply', 'chrome'] as $f)
{
	require dirname(__DIR__).'/lib/'.$f.'.php';
}

use Shef\Pristine\Apply;
use Shef\Pristine\Chrome;
use Shef\Pristine\Profiles;

$profiles = new Profiles([
	'profiles' => [
		'cert' => ['keep' => ['menu_crm_favorite']],
		'boss' => ['keep' => ['menu_crm_favorite'], 'chrome' => ['header', 'crm_kanban']],
	],
	'departments' => ['Эксперты' => 'cert', 'Руководство продаж' => 'boss'],
	'users' => [],
]);
$s = &$GLOBALS['stub'];
$s['departments'] = [5 => 'Эксперты', 8 => 'Руководство продаж'];
$s['users'] = [10 => [5], 30 => [8], 40 => [8]];
foreach([10, 30] as $id)
{
	$s['options'][$id][Apply::BACKUP_CATEGORY][Apply::BACKUP_NAME] = Apply::NONE;
}

check(Apply::departmentsOf(30) === ['Руководство продаж'] && Apply::departmentsOf(99) === [] && Apply::departmentsOf(0) === [], 'отделы человека — по его UF_DEPARTMENT');

$all = Chrome::css(10, $profiles);
check($all === Chrome::cssFor(array_keys(Chrome::GROUPS)) && str_contains($all, 'crm_catalogue') && str_contains($all, 'invitationButton') && str_contains($all, 'ui-btn-icon-robots'), 'профиль без ключа chrome — все группы, как раньше');

$boss = Chrome::css(30, $profiles);
check($boss !== '' && str_contains($boss, 'invitationButton') && str_contains($boss, 'ui-btn-icon-robots'), 'руководитель: шапка и канбан скрыты');
check(!str_contains($boss, 'crm_control_panel_menu'), 'руководитель: верхнее меню CRM не тронуто');

check(Chrome::css(40, $profiles) === '', 'профиль есть, но не применён (нет копии меню) — ничего');

$real = Profiles::fromModule();
check($real->chrome('certification') === null && $real->chrome('sales_head') === ['header', 'crm_kanban'], 'конфиг: certification — все группы, sales_head — без crm_menu');
check($real->profile('sales_head')['keep'] === ['menu_crm_favorite', 'menu_im_messenger', 'menu_calendar', 'menu_documents', 'menu_files', 'menu_company', 'shef_agentflow_admin', 'menu_configs_sect'], 'конфиг: левое меню руководителя по решению владельца 2026-10-29');
$known = $real->known();
check(array_diff(['menu_im_copilot', 'menu_automation', 'menu_marketplace_sect', 'menu_services_sect'], $known) === [] && array_intersect(['menu_im_copilot', 'menu_automation', 'menu_marketplace_sect', 'menu_services_sect'], $real->profile('sales_head')['keep']) === [], 'BitrixGPT, Автоматизация, Приложения, Сервисы — в known и скрываются у руководителя');
$names = array_keys(Chrome::GROUPS);
check(array_diff(array_merge(...array_map(static fn($c) => $real->chrome($c) ?? [], ['certification', 'sales_head'])), $names) === [], 'в конфиге только известные группы стиля');

// Ядро упало — страница важнее стиля.
$s['users'] = null;
Chrome::reset();
check(Chrome::css(30, $profiles) === '', 'ошибка при выборе профиля — пустой стиль, без исключения');

done();
