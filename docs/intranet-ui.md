# intranet: левое меню и «Инструменты» по людям

> Последняя сверка: 2026-10-23, стенд, `intranet` текущей коробки, шаблон
> `bitrix24`.

## Где хранится меню человека

**Как в ядре.** Порядок и скрытые пункты левого меню — личная настройка
`CUserOptions` категории `intranet`, имя `left_menu_sorted_items_<сайт>`
(на стенде `left_menu_sorted_items_s1`):

```
{show: {0: "menu_crm_favorite", 1: "menu_tasks",
        "menu_teamwork": ["menu_im_messenger", "menu_calendar", …], …},
 hide: [ … ], version: "2021.10"}
```

- `show` — пункты по порядку; группа — ключ с массивом своих пунктов.
- `hide` — скрытые пункты (раздел «Скрытые» в меню).
- Нет настройки — человек видит меню по умолчанию (пресет портала). У
  тестовых сотрудников стенда настройки нет, у администратора есть.
- Рядом: `left_menu_groups_<сайт>` (свёрнутые группы),
  `left_menu_first_page_<сайт>` (первая страница),
  `left_menu_standard_items_<сайт>` (свои пункты человека),
  `left_menu_collapsed` (свёрнуто ли меню).

**Пункты «для всех»** — `Option intranet left_menu_items_to_all_<сайт>`
(так модуль shef.agentflow добавляет «Сертификация: управление»; ID пункта
— `shef_agentflow_admin`). **Пресет** — `Option intranet left_menu_preset`
и личный `left_menu_preset_<сайт>`.

**Как ядро меняет меню человека.** Конвертеры меню ядра
(`intranet/lib/integration/templates/air/menuconverter.php`,
`.../bizproc/menu/menuconverter.php`) сохраняют копию
(`left_menu_sorted_items_old_s1`) и пишут `SetOption('intranet',
'left_menu_sorted_items_s1', …, false, $userId)`; кеш — `clearByTag
('bitrix24_left_menu')` (кеш компонента меню — неделя, на каждого
пользователя: `MENU_CACHE_USE_USERS`). Сброс к умолчанию — удаление
настройки (`Controller\LeftMenu::setDefaultMenuAction`).

**Откуда пункты.** Шаблон `bitrix24` подключает `bitrix:menu` с
`ROOT_MENU_TYPE = top` (если нет `.superleft.menu_ext.php`): пункты — из
`.top.menu_ext.php`, собираются в веб-контексте (из CLI их не получить —
`CMenu('top')` вне страницы отдаёт 1 пункт). Видимость считает
`Intranet\UI\LeftMenu\Menu` + пресет (`result_modifier.php` шаблона).

## «Инструменты» (CRM, задачи, сайты…)

`Intranet\Settings\Tools\ToolsManager` — включение для **всего портала**
(`Crm`, `Tasks`, `TeamWork`, `Sites`, `Sign`, `BIConstructor`, `Company`,
`Automation`, …). По отделам и людям не делятся: «чистый интерфейс» по
людям — только через левое меню.

Состояние — опции модуля `intranet`: `tool_<id>_main` (инструмент) и
`tool_subgroup_<id>_<раздел>` (раздел: «Лента» в `team_work`, «Аналитика»,
«Смарт-процессы», «Счета» в `crm`…), `N` — выключено, по умолчанию `Y`.
Выключают `Tool::disable()` (с разделами) и `Tool::disableSubgroup($code)`.
Разделы CRM убирают и пункты верхнего меню CRM («Аналитика»,
«Смарт-процессы», «Продажи»). Список выключенных пунктов —
`ToolsManager::getDisabledMenuItemListId()` (стенд 2026-10-26).

## Как у нас

`shef.pristine` (`modules/shef.pristine/`, #276): профиль = какие пункты
оставить; применение переносит остальное в `hide` с копией прежней
настройки в своей категории (`shef.pristine / left_menu_backup`), сбрасывает
тег `bitrix24_left_menu`; откат возвращает копию (было «по умолчанию» —
удаляет настройку). Пункты, которых нет ни в чьей настройке меню («Сайты»),
скрываются по списку `known` конфига.

«Инструменты» портала — `Shef\Pristine\Tools::run(dryRun)` по списку
`tools_off` (`config/profiles.php`), копия прежних опций —
`shef.pristine / tools_backup`, откат — `Tools::rollback()`. Автоматизация
не выключается: на ней роботы shef.agentflow.

Настроек ядра нет для кнопки «Показать все» под левым меню
(`#menu-items-block .menu-expand.--footer`) и кнопок шапки «Пригласить»
(`.air-header__button[data-id=invitationButton]`), «Демо»
(`[data-id=licenseWidgetWrapper]`), «Помощь» (`#bx-help-block`; стенд
2026-10-26). Верхнее меню CRM — `main.interface.buttons`
(`#crm_control_panel_menu .main-buttons-item[data-id=…]`: `crm_catalogue`
«Товары и Склады», `crm_sales` «Продажи», `crm_settings` «Настройки»);
скрытые стилем пункты в «Еще» не переезжают. `Shef\Pristine\Chrome`
(обработчик `main / OnProlog`) скрывает всё это стилем у людей с
применённым профилем. Право приглашать от этого не
зависит: его даёт `humanresources` (право 102 «Приглашать» — у руководителя
и заместителя отдела, HR, администратора структуры; у роли «Сотрудник» нет),
без права виджет отвечает «Недостаточно прав на приглашение сотрудников».

### Стиль по профилю

Селекторы `Chrome` собраны в группы `Chrome::GROUPS`: `header` («Показать
все», «Пригласить», «Демо», «Помощь»), `crm_kanban` (канбан сделок: «Дела»,
«Календарь», «Роботы», «Расширения», шестерёнка, «Контакт-центр»,
«Отраслевые CRM»), `crm_menu` (верхнее меню CRM). Профиль в
`config/profiles.php` выбирает группы ключом `chrome`; нет ключа — все
группы. Профиль человека `Chrome` находит так же, как `Apply` (личный, затем
по отделу), раз за хит; стиль выводится, только если профиль применён (есть
копия меню). У `sales_head` верхнее меню CRM не скрывается (владелец
2026-10-29, #2).

### Карточка CRM — это shef.agentflow

Пункты карточки CRM скрывает модуль shef.agentflow по уровням своих ролей
(adminsite-standartnoby/Agentic-Workflows#385, #387; разбор —
`docs/box/crm-ui.md` там). В shef.pristine их нет, чтобы не скрывать дважды
и не расходиться с ролями. Селекторы для справки (стенд 2026-10-29):

| Что | Селектор |
|---|---|
| Закладка «Маркетплейс» (сделка, компания, контакт) | `.main-buttons-item[data-id="crm_rest_marketplace"]` |
| Шапка карточки: «Расширения» | `#uiToolbarContainer .intranet-binding-menu-btn` |
| Шапка карточки: «Предложение» (конвертация) | `#uiToolbarContainer .ui-btn-split:has([id$="_convert_label"])` |
| Шапка карточки: «Бизнес-процессы» | `.crm-entity-bizproc-container` |
| Лента: «Сообщение», «Онлайн-запись», «Ещё» | `[id^="crm_scope_timeline_"][data-id="message"]`, `[id^="crm_scope_timeline_"][data-id="booking"]`, `[id^="crm_scope_timeline_"][id$="_more_button"]` |
| Шестерёнка колонок в списках | `.main-grid-interface-settings-icon` |
