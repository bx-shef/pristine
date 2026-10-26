<?php

declare(strict_types=1);

/** Установка и удаление: нужен shef.options 3.x; удаление возвращает меню и уносит своё. */

namespace Bitrix\Main\Localization {
	final class Loc
	{
		public static function loadMessages($f): void
		{
		}

		public static function getMessage($code, $replace = []): string
		{
			return strtr($code, $replace ?? []);
		}
	}
}

namespace {
	require __DIR__.'/assert.php';
	require __DIR__.'/stub/bitrix.php';
	foreach(['layout', 'profiles', 'apply', 'tools', 'chrome'] as $f)
	{
		require dirname(__DIR__).'/lib/'.$f.'.php';
	}

	abstract class CModule
	{
	}

	final class StubApp
	{
		public string $error = '';

		public function ThrowException($m): void
		{
			$this->error = (string)$m;
		}
	}

	$GLOBALS['APPLICATION'] = new StubApp();
	require dirname(__DIR__).'/install/index.php';

	$m = new shef_pristine();
	check($m->missing() === [] , 'с shef.options 3.1.0 ставится');
	$GLOBALS['stub']['modules']['shef.options'] = '2.9.0';
	check($m->missing() === ['shef.options 3.0.0'], 'shef.options ниже 3.0.0 — не ставится, причина названа');
	$GLOBALS['stub']['modules']['shef.options'] = '3.1.0';
	check($m->DoInstall() && ($GLOBALS['stub']['events'] ?? []) === ['main:OnProlog:Shef\Pristine\Chrome::onProlog'], 'установка: обработчик OnProlog (кнопки меню и шапки)');

	// Стиль — только тем, к кому применён профиль (есть копия меню).
	$GLOBALS['stub']['options'][77]['shef.pristine']['left_menu_backup'] = '__default__';
	$css = \Shef\Pristine\Chrome::css(77);
	check(str_contains($css, '.menu-expand.--footer') && str_contains($css, 'invitationButton') && str_contains($css, 'licenseWidgetWrapper') && str_contains($css, '#bx-help-block'), 'с профилем: скрыты «Показать все», «Пригласить», «Демо», «Помощь»');
	$crm = ['ui-nav-panel-item-activity', 'ui-nav-panel-item-calendar', 'ui-btn-icon-robots', 'intranet-binding-menu-btn', 'ui-btn-icon-setting', 'crm-kanban-item-contact-center', 'crm-kanban-item-industry', 'crm_catalogue', 'crm_sales', 'crm_settings'];
	check(array_filter($crm, static fn($s) => !str_contains($css, $s)) === [], 'с профилем в CRM: «Дела», «Календарь», «Роботы», «Расширения», шестерёнка, «Контакт-центр», «Отраслевые CRM»; верхнее меню CRM — «Товары и Склады», «Продажи», «Настройки»');
	check(\Shef\Pristine\Chrome::css(78) === '' && \Shef\Pristine\Chrome::css(0) === '', 'без профиля и гостю — ничего');
	unset($GLOBALS['stub']['options'][77]);

	// Меню человека изменено модулем — удаление возвращает как было и уносит копии.
	$menu = ['show' => ['menu_crm_favorite', 'menu_marketing'], 'hide' => [], 'version' => '2021.10'];
	$GLOBALS['stub']['options'][10]['intranet']['left_menu_sorted_items_s1'] = ['show' => ['menu_crm_favorite'], 'hide' => ['menu_marketing'], 'version' => '2021.10'];
	$GLOBALS['stub']['options'][10]['shef.pristine']['left_menu_backup'] = $menu;
	$GLOBALS['stub']['options'][50]['intranet']['left_menu_collapsed'] = 'Y';
	$m->DoUninstall();
	check($GLOBALS['stub']['options'][10]['intranet']['left_menu_sorted_items_s1'] === $menu && !isset($GLOBALS['stub']['options'][10]['shef.pristine']['left_menu_backup']), 'удаление: меню как было, копии модуля убраны');
	check(($GLOBALS['stub']['events'] ?? []) === [], 'удаление снимает обработчик');
	check(($GLOBALS['stub']['deleted'] ?? []) === ['shef.pristine'] && $GLOBALS['stub']['options'][50]['intranet']['left_menu_collapsed'] === 'Y', 'удалены только настройки модуля; чужие не тронуты');

	done();
}
