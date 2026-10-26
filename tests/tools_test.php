<?php

declare(strict_types=1);

/** «Инструменты» портала: выключить по списку, повтор без изменений, откат к прежнему. */

require __DIR__.'/assert.php';
require __DIR__.'/stub/bitrix.php';
foreach(['layout', 'profiles', 'tools'] as $f)
{
	require dirname(__DIR__).'/lib/'.$f.'.php';
}

use Bitrix\Intranet\Settings\Tools\Tool;
use Shef\Pristine\Profiles;
use Shef\Pristine\Tools;

$s = &$GLOBALS['stub'];
$s['tools'] = [
	new Tool('team_work', ['news' => 'Лента', 'calendar' => 'Календарь']),
	new Tool('sites'),
	new Tool('crm', ['crm' => 'Базовая CRM', 'analytics' => 'Аналитика']),
	new Tool('automation', ['robots' => 'Роботы']),
];
// Сайты уже выключены вручную — откат должен оставить их выключенными.
$s['opt']['intranet']['tool_sites_main'] = 'N';
$profiles = new Profiles(['tools_off' => ['team_work.news', 'sites', 'crm.analytics', 'nope', 'crm.nope']]);

$dry = Tools::run(dryRun: true, profiles: $profiles);
check(($s['opt']['intranet']['tool_subgroup_team_work_news'] ?? 'Y') === 'Y' && !isset($s['opt']['shef.pristine']), 'пробный прогон ничего не меняет');
check($dry['missing'] === ['nope', 'crm.nope'] && array_column($dry['off'], 'was', 'id') === ['team_work.news' => true, 'sites' => false, 'crm.analytics' => true], 'отчёт: что выключится, чего на портале нет');

Tools::run(dryRun: false, profiles: $profiles);
$o = $s['opt']['intranet'];
check($o['tool_subgroup_team_work_news'] === 'N' && $o['tool_subgroup_crm_analytics'] === 'N', 'разделы выключены');
check(!isset($o['tool_subgroup_team_work_calendar']) && !isset($o['tool_crm_main']) && !isset($o['tool_automation_main']), 'остальное не тронуто (календарь, CRM, автоматизация)');
check(in_array('bitrix24_left_menu', $s['tags'], true), 'кеш левого меню сброшен');

$before = $s['opt'];
Tools::run(dryRun: false, profiles: $profiles);
check($s['opt'] === $before, 'повторный запуск ничего не меняет и не затирает копию');

check(Tools::rollback()['restored'] === 2, 'откат: две опции из копии');
check(($s['opt']['intranet'] ?? []) === ['tool_sites_main' => 'N'] && !isset($s['opt']['shef.pristine']), 'откат вернул как было: выключенное вручную осталось, копия удалена');

done();
