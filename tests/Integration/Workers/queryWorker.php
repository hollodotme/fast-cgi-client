<?php declare(strict_types=1);

echo json_encode(
	[
		'get'         => $_GET,
		'requestUri'  => $_SERVER['REQUEST_URI'] ?? null,
		'queryString' => $_SERVER['QUERY_STRING'] ?? null,
	]
);
