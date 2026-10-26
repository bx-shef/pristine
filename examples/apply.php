<?php
/**
 * Пример для PHP-консоли админки («Инструменты → Командная PHP-строка»).
 * Сначала пробный прогон: кому какой профиль, что останется в меню.
 */

\Bitrix\Main\Loader::includeModule('shef.pristine');

$report = \Shef\Pristine\Apply::run(dryRun: true);
foreach($report['users'] as $row)
{
	echo $row['user'], ' — ', $row['title'], ': останется ', implode(', ', $row['show']), '; скрыто ', $row['hidden'], $row['changed'] ? '' : ' (уже так)', "\n";
}
echo 'Известные пункты меню портала: ', implode(', ', $report['known']), "\n";

// Применить: \Shef\Pristine\Apply::run(dryRun: false);
// Вернуть как было: \Shef\Pristine\Apply::rollback();
