# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased
### Fixed
- `Collection` PHPDoc now correctly states that it extends `IteratorAggregate`, rather than just `Traversable`.

## [1.4.1] - 2022-03-09

## [1.4.0] - 2021-11-17

## [1.3.0] - 2020-10-13
### Changed
- Implement ArrayAccess consistently
### Fixed
- Return types were incorrectly nullable in some cases
- Deque capacity was inconsistent with the extension

## [1.2.0] - 2017-08-03
### Changed
- Minor capacity updates

## [1.1.1] - 2016-08-09
### Fixed
- `Stack` and `Queue` array access should throw `OutOfBoundsException`, not `Error`.

### Improved
- Added a lot of docblock comments that were missing.

## [1.1.0] - 2016-08-04
### Added
- `Pair::copy`

## [1.0.3] - 2016-08-01
### Added
- `Set::merge`

## [1.0.2] - 2016-07-31
### Added
- `Map::putAll`
