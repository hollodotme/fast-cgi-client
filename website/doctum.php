<?php declare(strict_types=1);

/**
 * Doctum configuration for the API reference of all major versions.
 * Run through website/scripts/build-api.sh, which prepares the git clone in DOCTUM_SOURCE_DIR
 * with one branch per major version.
 */

use Doctum\Doctum;
use Doctum\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$sourceDir = (string)getenv( 'DOCTUM_SOURCE_DIR' );

$iterator = Finder::create()
	->files()
	->name( '*.php' )
	->in( $sourceDir . '/src' );

$versions = GitVersionCollection::create( $sourceDir )
	->add( '4.x', '4.x (PHP >= 8.0)' )
	->add( '3.x', '3.x (PHP >= 7.1)' )
	->add( '2.x', '2.x (PHP >= 7.1)' )
	->add( '1.x', '1.x (PHP >= 7.0)' );

return new Doctum(
	$iterator,
	[
		'title'                => 'FastCGI Client API',
		'versions'             => $versions,
		'language'             => 'en',
		'build_dir'            => __DIR__ . '/static/api/%version%',
		'cache_dir'            => __DIR__ . '/.doctum-cache/%version%',
		'default_opened_level' => 2,
		'base_url'             => 'https://fast-cgi-client.hollo.me/api/%version%/',
		'footer_link'          => [
			'href'        => 'https://fast-cgi-client.hollo.me',
			'rel'         => 'noopener',
			'target'      => '_self',
			'before_text' => 'Back to the',
			'link_text'   => 'FastCGI Client documentation',
			'after_text'  => '',
		],
	]
);
