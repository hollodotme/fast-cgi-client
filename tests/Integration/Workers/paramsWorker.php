<?php declare(strict_types=1);

$lengths = [];

foreach ( $_SERVER as $key => $value )
{
	if ( 0 === strpos( (string)$key, 'LARGE_PARAM_' ) )
	{
		$lengths[ $key ] = strlen( (string)$value );
	}
}

ksort( $lengths );

echo json_encode( $lengths );
