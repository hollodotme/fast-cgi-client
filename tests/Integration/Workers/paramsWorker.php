<?php declare(strict_types=1);

$lengths = [];

foreach ( $_SERVER as $key => $value )
{
	if ( str_starts_with( (string)$key, 'LARGE_PARAM_' ) )
	{
		$lengths[ $key ] = strlen( (string)$value );
	}
}

ksort( $lengths );

echo json_encode( $lengths );
