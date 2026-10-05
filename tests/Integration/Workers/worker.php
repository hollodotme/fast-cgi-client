<?php declare(strict_types=1);

header( 'X-Powered-By: PHP/7.1.0' );
header( 'X-Custom: Header' );
usleep( 50000 );
echo $_REQUEST['test-key'];

if ( isset( $_REQUEST['test-second-key'] ) )
{
	echo $_REQUEST['test-second-key'];
}
if ( isset( $_REQUEST['test-third-key'] ) )
{
	echo $_REQUEST['test-third-key'];
}
