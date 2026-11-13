<?php
/**
 * Профили чистого интерфейса (#276). Правка — через PR: какие пункты левого
 * меню оставить отделу или человеку; остальное скрывается (не удаляется).
 *
 * ID пунктов — как в настройке ядра `left_menu_sorted_items_<сайт>`
 * (`menu_crm_favorite`, `menu_tasks`, …); пункт «для всех» модуля
 * shef.agentflow — `shef_agentflow_admin`. Список пунктов портала —
 * `\Shef\Pristine\Apply::run(dryRun: true)` печатает известные ID.
 *
 * Профиль человека важнее отдельского. Без профиля интерфейс не трогается.
 *
 * `chrome` — какие группы стиля `\Shef\Pristine\Chrome::GROUPS` скрыть
 * (`header`, `crm_kanban`, `crm_menu`); нет ключа — все.
 */
return [
	'site' => 's1',
	'profiles' => [
		// Владелец 2026-10-26: Задачи, Календарь, Диск, Сайты — скрывать.
		'certification' => [
			'title' => 'Сертификация: эксперты и специалисты',
			'keep' => ['menu_crm_favorite', 'menu_im_messenger', 'shef_agentflow_admin'],
		],
		// Владелец 2026-10-29 (#2): CRM, Мессенджер, Календарь, Документы, Диск,
		// Сотрудники, «Сертификация: управление», Настройки; BitrixGPT,
		// Автоматизация, Приложения, Сервисы и прочее — скрыть. Верхнее меню CRM
		// не трогаем — в `chrome` нет `crm_menu`.
		'sales_head' => [
			'title' => 'Начальник отдела продаж',
			'keep' => ['menu_crm_favorite', 'menu_im_messenger', 'menu_calendar', 'menu_documents', 'menu_files', 'menu_company', 'shef_agentflow_admin', 'menu_configs_sect'],
			'chrome' => ['header', 'crm_kanban'],
		],
	],
	// Стандартные пункты левого меню коробки (intranet, .top.menu_ext.php; стенд
	// 2026-10-26). Скрываются и у тех, у кого их нет в настройке меню: иначе пункт,
	// которого нет ни в чьей настройке («Сайты»), остаётся виден.
	'known' => [
		'menu_crm_favorite', 'menu_crm_store', 'menu_tasks', 'menu_external_mail', 'menu_im_messenger', 'menu_im_copilot',
		'menu_live_feed', 'menu_calendar', 'menu_documents', 'menu_files', 'menu_all_groups', 'menu_boards', 'menu_booking',
		'menu_marketing', 'menu_shop', 'menu_sites', 'menu_sign', 'menu_sign_b2e', 'menu_bi_constructor', 'menu_company',
		'menu_automation', 'menu_note_base', 'menu_contact_center', 'menu_rooms', 'menu_marketplace_sect', 'menu_devops_sect',
		'menu_services_sect', 'menu_configs_sect', 'menu_tariff',
	],
	// «Инструменты» портала (Настройки → Инструменты) выключаются для всех, не по
	// отделам: \Shef\Pristine\Tools::run(). `<инструмент>` целиком или
	// `<инструмент>.<раздел>`. Владелец 2026-10-26: лишнее выключить; в CRM —
	// верхнее меню без «Товары и склады», «Продажи», «Аналитика», «Смарт-процессы».
	// Автоматизация остаётся: на ней роботы shef.agentflow.
	'tools_off' => [
		'team_work.news', 'team_work.workgroups', 'tasks', 'booking', 'inventory_management', 'sites', 'sign', 'crm_bi',
		'crm.marketing', 'crm.analytics', 'crm.tracking', 'crm.invoices', 'crm.offers', 'crm.saleshub', 'crm.dynamic_items',
		'crm.bi_analytics', 'crm.bi_analytics_microsoft', 'crm.bi_analytics_google', 'crm.report_construct',
	],
	// Название отдела (структура компании) → профиль.
	'departments' => [
		'Эксперты' => 'certification',
		'Специалисты' => 'certification',
		'Руководство продаж' => 'sales_head',
	],
	// ID пользователя → профиль (важнее отдела).
	'users' => [],
];
