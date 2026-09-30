---
title: What it is
sidebar_position: 1
slug: /
description: A dependency-free PHP client that talks the FastCGI protocol to PHP-FPM, synchronously or asynchronously.
---

# FastCGI Client

A PHP FastCGI client to send requests (a)synchronously to PHP-FPM, or any other FastCGI server, using
the [FastCGI Protocol](http://www.mit.edu/~yandros/doc/specs/fcgi-spec.html).

This library is based on the work
of [Pierrick Charron](https://github.com/adoy)'s [PHP-FastCGI-Client](https://github.com/adoy/PHP-FastCGI-Client/)
and was ported and modernized to latest PHP versions, extended with some features for handling multiple requests (in
loops) and unit and integration tests as well.

## What you can do with it

* Execute PHP scripts through PHP-FPM without a web server in between, e.g. from CLI workers or cron jobs.
* Send requests **synchronously**, or **asynchronously** and in parallel, and collect the responses in the order
  they were sent or as soon as they are ready.
* Connect via **network sockets** or **unix domain sockets**.
* React to responses and failures with **callbacks**, or stream the output of long-running scripts with
  **pass-through callbacks**.
* Send any request method with URL-encoded, multipart (file upload), JSON or plain text content.

The library has no dependencies besides PHP itself and is continuously tested against PHP-FPM 8.0 – 8.5 as well as
FastCGI servers written in Go, Rust, C# and Java.

## Versions

This is the documentation of version 4.x, which requires PHP >= 8.0.

Please have a look at the [backwards incompatible changes (BC breaks) in the changelog](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/CHANGELOG.md), if you
upgrade from a previous version.

| Version | PHP    | Documentation            | Changelog                                     |
|---------|--------|--------------------------|-----------------------------------------------|
| 4.x     | >= 8.0 | This documentation       | [CHANGELOG.md](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/CHANGELOG.md)              |
| 3.x     | >= 7.1 | [3.x](/docs/3.x/)        | [3.x](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/docs/changelog/3.x.md)              |
| 2.x     | >= 7.1 | [2.x](/docs/2.x/)        | [2.x](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/docs/changelog/2.x.md)              |
| 1.x     | >= 7.0 | [1.x](/docs/1.x/)        | [1.x](https://github.com/hollodotme/fast-cgi-client/blob/4.x-dev/docs/changelog/1.x.md)              |

Version 3.x still gets bug fixes, but no new features.

Read more about the journey to and changes in `v2.6.0`
in [this blog post](https://github.com/hollodotme/fast-cgi-client/wiki/Background-Info-FastCgiClient-Version-2.6.0).

## Background

You can find an experimental use-case in my related blog posts:

* [Experimental async PHP vol. 1](https://github.com/hollodotme/fast-cgi-client/wiki/Experimental-Async-Php-Volume-1)
* [Experimental async PHP vol. 2](https://github.com/hollodotme/fast-cgi-client/wiki/Experimental-Async-Php-Volume-2)

You can also find slides of my talks about this project on [speakerdeck.com](https://speakerdeck.com/hollodotme).
