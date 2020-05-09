# Audit Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased

### Added
- Added list of snapshot values to event view

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
