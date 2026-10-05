<?php declare(strict_types=1);

sleep( (int)($_REQUEST['sleep'] ?? 0) );

$lines = [];
foreach ( $_REQUEST as $key => $value )
{
	$lines[] = " * {$key}: {$value}";
}

echo implode( "\n", $lines );
