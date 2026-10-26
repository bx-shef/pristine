<?php

declare(strict_types=1);

require __DIR__.'/assert.php';
require __DIR__.'/stub/bitrix.php';
foreach(['layout', 'profiles', 'apply'] as $f)
{
	require dirname(__DIR__).'/lib/'.$f.'.php';
}

use Shef\Pristine\Apply;
use Shef\Pristine\Profiles;

$profiles = new Profiles([
	'site' => 's1',
	'profiles' => [
		'cert' => ['title' => 'Сертификация', 'keep' => ['menu_crm_favorite', 'menu_tasks']],
		'boss' => ['title' => 'Руководитель', 'keep' => ['menu_crm_favorite', 'menu_tasks', 'menu_company']],
	],
	'departments' => ['Эксперты' => 'cert', 'Специалисты' => 'cert'],
	'users' => [30 => 'boss'],
	// «Сайты» нет ни в чьей настройке меню — скрывается только через список known.
	'known' => ['menu_sites'],
]);
check($profiles->codeFor(30, ['Эксперты']) === 'boss' && $profiles->codeFor(10, ['Бухгалтерия', 'Эксперты']) === 'cert' && $profiles->codeFor(99, ['Бухгалтерия']) === null, 'профиль человека важнее отдельского; без профиля — null');

$s = &$GLOBALS['stub'];
$s['departments'] = [5 => 'Эксперты', 6 => 'Специалисты', 7 => 'Бухгалтерия'];
$s['users'] = [10 => [5], 20 => [6], 30 => [7], 40 => [7]];
$menu = ['show' => ['menu_crm_favorite', 'menu_tasks', 'menu_company', 'menu_marketing'], 'hide' => [], 'version' => '2021.10'];
$s['options'][10]['intranet']['left_menu_sorted_items_s1'] = $menu;   // эксперт со своим меню
$s['options'][40]['intranet']['left_menu_sorted_items_s1'] = $menu;   // без профиля — не трогаем
// 20 (специалист) — меню по умолчанию; 30 — бухгалтерия, но личный профиль.

$dry = Apply::run(true, $profiles);
check($dry['dryRun'] && count($dry['users']) === 3 && $s['options'][10]['intranet']['left_menu_sorted_items_s1'] === $menu && $s['tags'] === [], 'пробный прогон: отчёт по троим, ничего не меняет, кеш не трогает');

$run = Apply::run(false, $profiles);
$m10 = $s['options'][10]['intranet']['left_menu_sorted_items_s1'];
$m20 = $s['options'][20]['intranet']['left_menu_sorted_items_s1'];
$m30 = $s['options'][30]['intranet']['left_menu_sorted_items_s1'];
check($run['changed'] === 3 && $m10['show'] === ['menu_crm_favorite', 'menu_tasks'] && in_array('menu_company', $m10['hide'], true), 'эксперт: свой профиль, лишнее скрыто');
check($m20['show'] === ['menu_crm_favorite', 'menu_tasks'] && in_array('menu_marketing', $m20['hide'], true), 'специалист с меню по умолчанию: известное лишнее скрыто');
check(in_array('menu_sites', $m20['hide'], true) && in_array('menu_sites', $m10['hide'], true), 'пункт из списка known («Сайты») скрыт, хотя его нет ни в чьей настройке меню');
check($m30['show'] === ['menu_crm_favorite', 'menu_tasks', 'menu_company'], 'личный профиль руководителя: у него свой набор');
check($s['options'][40]['intranet']['left_menu_sorted_items_s1'] === $menu, 'человек без профиля не тронут');
check($s['options'][10][Apply::BACKUP_CATEGORY][Apply::BACKUP_NAME] === $menu && $s['options'][20][Apply::BACKUP_CATEGORY][Apply::BACKUP_NAME] === Apply::NONE, 'перед изменением — копия прежнего меню (или «было по умолчанию»)');
check($s['tags'] === ['bitrix24_left_menu'], 'кеш левого меню сброшен');

$again = Apply::run(false, $profiles);
check($again['changed'] === 0 && $s['options'][10][Apply::BACKUP_CATEGORY][Apply::BACKUP_NAME] === $menu, 'повторный запуск ничего не меняет и не затирает копию');

$back = Apply::rollback($profiles);
check($back['restored'] === 3 && $s['options'][10]['intranet']['left_menu_sorted_items_s1'] === $menu && !isset($s['options'][20]['intranet']['left_menu_sorted_items_s1']), 'откат: как было; у кого было по умолчанию — настройка удалена');
check(!isset($s['options'][10][Apply::BACKUP_CATEGORY][Apply::BACKUP_NAME]) && $s['options'][40]['intranet']['left_menu_sorted_items_s1'] === $menu, 'копии убраны; чужое не тронуто');

done();
