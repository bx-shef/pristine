<?php

declare(strict_types=1);

namespace Shef\Pristine;

/**
 * Обвязка интерфейса, которой нет в настройках ядра (владелец 2026-10-26).
 * Скрывается стилем — только у тех, к кому применён профиль (есть копия меню
 * модуля); у администратора без профиля всё на месте. Что скрыть — группы
 * селекторов `GROUPS`; профиль выбирает их ключом `chrome` в
 * `config/profiles.php` (нет ключа — все группы). Профиль человека ищется так
 * же, как в `Apply`: личный, затем по отделу.
 *
 * Суммы в канбане скрывает право роли CRM «Просмотр суммы на стадиях
 * канбана», не стиль. Пункты карточки CRM (закладка «Маркетплейс», кнопки
 * шапки, пункты ленты, шестерёнка списков) скрывает модуль shef.agentflow по
 * своим ролям — здесь их нет. Селекторы — разметка коробки (стенд
 * 2026-10-26). Обработчик main / OnProlog.
 */
final class Chrome
{
	/** Группы селекторов: название → селекторы. */
	public const GROUPS = [
		// Под левым меню «Показать все»; в шапке «Пригласить», «Демо» (тариф), «Помощь».
		'header' => [
			'#menu-items-block .menu-expand.--footer',
			'.air-header__button[data-id="invitationButton"]',
			'.air-header__button[data-id="licenseWidgetWrapper"]',
			'.air-header__button:has(#bx-help-block)',
		],
		// Канбан сделок: вкладки «Дела» и «Календарь», «Роботы», «Расширения»,
		// шестерёнка, блоки «Контакт-центр» и «Отраслевые CRM».
		'crm_kanban' => [
			'#ui-nav-panel-item-activity',
			'#ui-nav-panel-item-calendar',
			'#crm-view-switcher-buttons-right .ui-btn-icon-robots',
			'#crm-view-switcher-buttons-right .intranet-binding-menu-btn',
			'#uiToolbarContainer .ui-btn-icon-setting',
			'.main-kanban-item:has(.crm-kanban-item-contact-center)',
			'.main-kanban-item:has(.crm-kanban-item-industry)',
		],
		// Верхнее меню CRM: «Товары и Склады», «Продажи», «Настройки».
		'crm_menu' => [
			'#crm_control_panel_menu .main-buttons-item[data-id="crm_catalogue"]',
			'#crm_control_panel_menu .main-buttons-item[data-id="crm_sales"]',
			'#crm_control_panel_menu .main-buttons-item[data-id="crm_settings"]',
		],
	];

	/** @var array<int, string> стиль по пользователю — на один хит */
	private static array $cache = [];

	public static function onProlog(): void
	{
		try
		{
			$userId = isset($GLOBALS['USER']) && is_object($GLOBALS['USER']) ? (int)$GLOBALS['USER']->GetID() : 0;
			$css = self::css($userId);
			$css !== '' && \Bitrix\Main\Page\Asset::getInstance()->addString('<style>'.$css.'</style>');
		}
		catch(\Throwable)
		{
			// Страница важнее стиля.
		}
	}

	/** Стиль для пользователя; '' — интерфейс не трогаем. */
	public static function css(int $userId, ?Profiles $profiles = null): string
	{
		if($userId <= 0)
		{
			return '';
		}
		if(isset(self::$cache[$userId]) && $profiles === null)
		{
			return self::$cache[$userId];
		}
		try
		{
			$css = '';
			if(\CUserOptions::GetOption(Apply::BACKUP_CATEGORY, Apply::BACKUP_NAME, null, $userId) !== null)
			{
				$profiles ??= Profiles::fromModule();
				$code = $profiles->codeFor($userId, Apply::departmentsOf($userId));
				// Профиль уже не находится, а меню изменено модулем — как раньше, всё.
				$css = self::cssFor(($code === null ? null : $profiles->chrome($code)) ?? array_keys(self::GROUPS));
			}
		}
		catch(\Throwable)
		{
			$css = '';
		}

		return self::$cache[$userId] = $css;
	}

	/** @param list<string> $groups названия групп; неизвестные пропускаются */
	public static function cssFor(array $groups): string
	{
		$selectors = [];
		foreach($groups as $g)
		{
			array_push($selectors, ...(self::GROUPS[$g] ?? []));
		}

		return $selectors === [] ? '' : implode(',', $selectors).'{display:none!important}';
	}

	/** Сбросить кеш хита (тесты). */
	public static function reset(): void
	{
		self::$cache = [];
	}
}
