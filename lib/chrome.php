<?php

declare(strict_types=1);

namespace Shef\Pristine;

/**
 * Обвязка интерфейса, которой нет в настройках ядра (владелец 2026-10-26):
 * кнопка «Показать все» под левым меню раскрывает скрытые пункты; в шапке —
 * «Пригласить», «Демо» (тариф), «Помощь». Скрываются стилем — только у тех, к
 * кому применён профиль (есть копия меню модуля); у администратора без
 * профиля всё на месте. В CRM (канбан сделок) — вкладки «Дела» и
 * «Календарь», «Роботы», «Расширения», шестерёнка, блоки «Контакт-центр» и
 * «Отраслевые CRM»; в верхнем меню CRM — «Товары и Склады», «Продажи»,
 * «Настройки». Суммы в канбане скрывает право роли CRM «Просмотр суммы
 * на стадиях канбана», не стиль. Селекторы — разметка коробки (стенд
 * 2026-10-26). Обработчик main / OnProlog.
 */
final class Chrome
{
	public const CSS = '#menu-items-block .menu-expand.--footer,'
		.'.air-header__button[data-id="invitationButton"],'
		.'.air-header__button[data-id="licenseWidgetWrapper"],'
		.'.air-header__button:has(#bx-help-block),'
		.'#ui-nav-panel-item-activity,#ui-nav-panel-item-calendar,'
		.'#crm-view-switcher-buttons-right .ui-btn-icon-robots,'
		.'#crm-view-switcher-buttons-right .intranet-binding-menu-btn,'
		.'#uiToolbarContainer .ui-btn-icon-setting,'
		.'.main-kanban-item:has(.crm-kanban-item-contact-center),'
		.'.main-kanban-item:has(.crm-kanban-item-industry),'
		.'#crm_control_panel_menu .main-buttons-item[data-id="crm_catalogue"],'
		.'#crm_control_panel_menu .main-buttons-item[data-id="crm_sales"],'
		.'#crm_control_panel_menu .main-buttons-item[data-id="crm_settings"]'
		.'{display:none!important}';

	public static function onProlog(): void
	{
		$userId = isset($GLOBALS['USER']) && is_object($GLOBALS['USER']) ? (int)$GLOBALS['USER']->GetID() : 0;
		$css = self::css($userId);
		$css !== '' && \Bitrix\Main\Page\Asset::getInstance()->addString('<style>'.$css.'</style>');
	}

	/** Стиль для пользователя; '' — интерфейс не трогаем. */
	public static function css(int $userId): string
	{
		if($userId <= 0)
		{
			return '';
		}

		return \CUserOptions::GetOption(Apply::BACKUP_CATEGORY, Apply::BACKUP_NAME, null, $userId) !== null ? self::CSS : '';
	}
}
