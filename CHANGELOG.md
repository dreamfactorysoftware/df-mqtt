# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [0.7.1] - 2026-11-18
### Changed
- A subscription keeps running when a triggered service call returns an error, and the outcome is logged.
- Subscribe requests need an API key or a session; a request with neither returns 400.

## Earlier releases (0.5.0 - 0.7.0)
### Added
- Support for php 8

### Changed
- Changed MQTT Broker, see [php-mqtt/client](https://github.com/php-mqtt/client)

## [0.4.0] - 2017-12-28
### Added
- Added package discovery
### Changed
- DF-1017 Code refactor and cleanup

## [0.3.0] - 2017-11-03
### Added
- Added RBAC support for package export
### Changed
- Upgrade Swagger to OpenAPI 3.0 specification

## [0.2.0] - 2017-08-17
### Changed
- Reworked API doc usage and generation

## [0.1.0] - 2017-07-28
### Added
- Fist release

[Unreleased]: https://github.com/dreamfactorysoftware/df-mqtt/compare/0.4.0...HEAD
[0.4.0]: https://github.com/dreamfactorysoftware/df-mqtt/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/dreamfactorysoftware/df-mqtt/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/dreamfactorysoftware/df-mqtt/compare/0.1.0...0.2.0
