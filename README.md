<img src="https://i.imgur.com/ckI6GTu.png" width="350px" alt="Polr Logo" />


:aerial_tramway: A modern, minimalist, and lightweight URL shortener.

[![GitHub license](https://img.shields.io/badge/license-GPLv2%2B-blue.svg)]()
[![GitHub release](https://img.shields.io/github/release/cydrobolt/polr.svg)](https://github.com/cydrobolt/polr/releases)
[![Docs](https://img.shields.io/badge/docs-latest-brightgreen.svg?style=flat)](http://polr.readthedocs.org/en/latest/)


Polr is an intrepid, self-hostable open-source link shortening web application with a robust API. It allows you to host your own URL shortener, to brand your URLs, and to gain control over your data. Polr is especially easy to use, and provides a modern, themable feel.

[Getting Started](http://docs.polrproject.org/en/latest/user-guide/installation/) - [API Documentation](http://docs.polrproject.org/en/latest/developer-guide/api/) - [Contributing](https://github.com/cydrobolt/polr/blob/master/.github/CONTRIBUTING.md) - [Bugs](https://github.com/cydrobolt/polr/issues) - [IRC](http://webchat.freenode.net/?channels=#polr)

### Quickstart

Polr is written in PHP and Laravel, using MySQL as its primary database.

This fork runs Polr 2 on Laravel 13 and PHP 8.3+ (the original was built on Lumen 5.1, which needs PHP 7.x and no longer receives security fixes). It is a drop-in replacement for Polr 2.3.0: existing databases, `.env` files, links, users and API clients keep working. See `docs/user-guide/upgrading.md` and `MODERNIZATION.md`.

 - To get started with Polr on your server, check out the [installation guide](http://docs.polrproject.org/en/latest/user-guide/installation/). You can clone this repository, or download a [release](https://github.com/cydrobolt/polr/releases).
 - To get started with the Polr API, check out the [API guide](http://docs.polrproject.org/en/latest/developer-guide/api/).


Installation TL;DR: clone or download this repository, run `composer install --no-dev -o`, set document root to `public/`, create MySQL database, copy `.env.setup` to `.env`, go to `yoursite.com/setup` and follow instructions.

### Demo

To test out the demo, head to [demo.polr.me](http://demo.polr.me) and use the following credentials:

- Username: `demo-admin`
- Password: `demo-admin`

### Upgrading Polr
*Upgrading from 1.x:*

There are breaking changes between 2.x and 1.x; it is not yet possible to automatically upgrade to 2.x.

*Upgrading from 2.x:*

See [the upgrade guide](docs/user-guide/upgrading.md): this version needs PHP 8.3 or later.

*Docker:* see [the Docker guide](docs/user-guide/docker.md).

#### Browser Extensions

* Safari - [Polr.safariextension](https://github.com/cleverdevil/Polr.safariextension)

#### Libraries

* Python - [mypolr](https://github.com/fauskanger/mypolr)

#### Acknowledgements
We would like to thank Oregon State University's Open Source Lab for providing resources for our infrastructure. The Polr website and demo are hosted on their infrastructure.

<a href="//osuosl.org"><img height="100em" src="http://i.imgur.com/1VtLxyX.png" /></a>

Thank you to [lastspark](https://thenounproject.com/lastspark/) for providing our logo's icon.

#### Versioning

Polr uses [Semantic Versioning](http://semver.org/)


#### License


    Copyright (C) 2013-2018 Chaoyi Zha

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
