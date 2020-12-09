<?php declare(strict_types=1);

namespace hollodotme\FastCGI\RequestContents;

use hollodotme\FastCGI\Interfaces\ComposesRequestContent;
use InvalidArgumentException;
use function basename;
use function file_exists;
use function file_get_contents;
use function function_exists;
use function implode;
use function sprintf;

final class MultipartFormData implements ComposesRequestContent
{
	private const BOUNDARY_ID               = '__X_FASTCGI_CLIENT_BOUNDARY__';

	private const EOL                       = "\r\n";

	private const FILE_CONTENT_TYPE_DEFAULT = 'application/octet-stream';

	/** @var array<string, string> */
	private $formData;

	/** @var array<string, string> */
	private $files;

	/**
	 * @param array<string, string> $formData
	 * @param array<string, string> $files
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( array $formData, array $files )
	{
		$this->formData = $formData;
		$this->files    = [];

		foreach ( $files as $name => $filePath )
		{
			$this->addFile( (string)$name, (string)$filePath );
		}
	}

	/**
	 * @param string $name
	 * @param string $filePath
	 *
	 * @throws InvalidArgumentException
	 */
	public function addFile( string $name, string $filePath ) : void
	{
		if ( !file_exists( $filePath ) )
		{
			throw new InvalidArgumentException( 'File does not exist: ' . $filePath );
		}

		$this->files[ $name ] = $filePath;
	}

	public function getContentType() : string
	{
		return 'multipart/form-data; boundary=' . self::BOUNDARY_ID;
	}

	public function getContent() : string
	{
		$data = [];

		foreach ( $this->formData as $key => $value )
		{
			$data[] = $this->getFormDataContent( $key, $value );
		}

		foreach ( $this->files as $name => $filePath )
		{
			$data[] = $this->getFileDataContent( $name, $filePath );
		}

		$data[] = '--' . self::BOUNDARY_ID . '--' . self::EOL . self::EOL;

		return implode( self::EOL, $data );
	}

	private function getFormDataContent( string $key, string $value ) : string
	{
		$data   = ['--' . self::BOUNDARY_ID];
		$data[] = sprintf( 'Content-Disposition: form-data; name="%s"%s', $key, self::EOL );
		$data[] = $value;

		return implode( self::EOL, $data );
	}

	private function getFileDataContent( string $name, string $filePath ) : string
	{
		$data   = ['--' . self::BOUNDARY_ID];
		$data[] = sprintf(
			'Content-Disposition: form-data; name="%s"; filename="%s"',
			$name,
			basename( $filePath )
		);

		$data[] = sprintf( 'Content-Type: %s%s', $this->getContentTypeOfFile( $filePath ), self::EOL );
		$data[] = (string)file_get_contents( $filePath );

		return implode( self::EOL, $data );
	}

	private function getContentTypeOfFile( string $filePath ) : string
	{
		if ( function_exists( 'mime_content_type' ) )
		{
			/** @noinspection PhpComposerExtensionStubsInspection */
			return (string)mime_content_type( $filePath ) ?: self::FILE_CONTENT_TYPE_DEFAULT;
		}

		return self::FILE_CONTENT_TYPE_DEFAULT;
	}
}