<?php declare(strict_types=1);
/*
 * Copyright (c) 2010-2014 Pierrick Charron
 * Copyright (c) 2016-2020 Holger Woltersdorf & Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace hollodotme\FastCGI\Interfaces;

/**
 * Interface ProvidesRequestData
 * @package hollodotme\FastCGI\Interfaces
 */
interface ProvidesRequestData
{
	public function getGatewayInterface() : string;

	public function getRequestMethod() : string;

	public function getScriptFilename() : string;

	public function getServerSoftware() : string;

	public function getRemoteAddress() : string;

	public function getRemotePort() : int;

	public function getServerAddress() : string;

	public function getServerPort() : int;

	public function getServerName() : string;

	public function getServerProtocol() : string;

	public function getContentType() : string;

	public function getContentLength() : int;

	public function getContent() : string;

	/**
	 * @return array<string, mixed>
	 */
	public function getCustomVars() : array;

	/**
	 * @return array<string, mixed>
	 */
	public function getParams() : array;

	public function getRequestUri() : string;

	/**
	 * @return array<callable>
	 */
	public function getResponseCallbacks() : array;

	/**
	 * @return array<callable>
	 */
	public function getFailureCallbacks() : array;

	/**
	 * @return array<callable>
	 */
	public function getPassThroughCallbacks() : array;
}
