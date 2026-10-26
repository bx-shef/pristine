<?php

declare(strict_types=1);

/** Общая обвязка тестов: check() печатает [ OK ]/[FAIL], done() — код выхода. */
$GLOBALS['__failed'] = 0;

function check(bool $ok, string $what): void
{
	printf("[%s] %s\n", $ok ? ' OK ' : 'FAIL', $what);
	$GLOBALS['__failed'] += $ok ? 0 : 1;
}

function done(): never
{
	exit($GLOBALS['__failed'] ? 1 : 0);
}
