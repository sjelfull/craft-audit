# Audit Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.0.2 - 2023-03-29

### Fixed
- Fixed potential XSS vulnerability ([#73](https://github.com/sjelfull/craft-audit/pull/73))

## 3.0.1 - 2022-10-03

### Added
- Added `logChildElementEvents` config option (defaults to `false`)

### Fixed
- Fixed deleted element behaviour in multisite

## 3.0.0 - 2022-10-03

### Added
- Craft 4 support
- Added structure change support

### Fixed
- Fixed support for Craft 3.6+ (#68)
- Fixed behaviour for drafts (#67)
- Fixed multisite behaviour (#62)

# 2.0.0 - 2021-03-13

> {warning} You now have to register for a MaxMind account and obtain a license key in order to download the GeoLite2 databases. This is due to a change that was introduced December 30th 2019, [to comply with GDPR and CCPA](https://blog.maxmind.com/2019/12/18/significant-changes-to-accessing-and-using-geolite2-databases/). You need to add a MaxMind license key in the plugin settings after upgrading to 2.0.0 to continue downloading the databases. Check the readme for detailed instructions on this.

### Added
- Added new authentication method for downloading MaxMind geolocation databases

## 1.1.3 - 2020-08-27

### Fixed
- Fixed error when snapshot serialized data became corrupt due to being too large for the database column
- Fixed deprecation in Composer

### Changed
- Changed snapshot column to `mediumtext`

## 1.1.2 - 2020-07-17

### Fixed
- Fixed error when a user id wasn't set on a logged event

## 1.1.1 - 2020-05-09

### Added
- Added global set tracking ([#50](https://github.com/sjelfull/craft-audit/issues/50))
- Added draft tracking (disabled by default) ([#50](https://github.com/sjelfull/craft-audit/issues/50))
- Added list of snapshot values to event view
- Added settings for disabling event types

### Changed
- Improved performance when listing user details in tables

### Fixed
- Fixed unserialize error ([#48](https://github.com/sjelfull/craft-audit/issues/48))

## 1.1.0 - 2020-05-09

### Changed
- Changed visibility of constants to allow for PHP 7.0 compatibility
- Now requires Craft 3.2
- Now uses Craft's built in pagination
- Maxmind DB files is now saved to a folder in the `storage` directory by default

### Added
- Added `dbPath` 

### Fixed
- Fixed events when a element is propogating or a element is a draft/revision
- Fixed broken pagination urls

## 1.0.4 - 2018-12-04

### Fixed
- Timestamp in Audit index is now localized 

## 1.0.3 - 2018-12-04

### Added
- Added setting for pruning old records on admin requests

## 1.0.2 - 2018-12-03

### Added
- Added permissions
- Added button to prune old records
- Documented pruning of old records

## 1.0.1 - 2018-06-08

### Fixed
- Fixed first save of settings on install
- Fixed pagination urls for setups with non-standard cpTrigger

## 1.0.0 - 2018-04-04

### Added
- Initial release
