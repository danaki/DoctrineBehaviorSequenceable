# Changes in Sequenceable behavior extension for Doctrine2

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.0.5] - 2018-01-07

### Fixed
- Fixed incorrect assignment of column name (used field name before).

## [0.0.4] - 2017-12-04

### Fixed
- Fixed query to get the next sequence number.

## [0.0.3] - 2017-05-26

### Fixed
- Fixed "Binding entities to query parameters only allowed for entities that have an identifier".

## [0.0.2] - 2017-05-24

### Added
- Added support for behavior [soft-deletable](https://github.com/KnpLabs/DoctrineBehaviors#softDeletable) of [KNPLabs](http://knplabs.com/).

### Fixed
- Fixed incorrect table alias on multiple SequenceID annotations in [SequenceableEntityContainer](src/Entity/SequenceableEntityContainer.php).
- Removed type hinting of functions (removed PHP7 functionality).
- Added composer package [doctrine/orm](https://packagist.org/packages/doctrine/orm) to `require` and [knplabs/doctrine-behaviors](https://packagist.org/packages/knplabs/doctrine-behaviors) to `require-dev` 

### Changed

## [0.0.1] - 2017-05-09

### Fixed
- Fixed [README.md](README.md) and namespaces.

## [0.0.0] - 2017-05-09

### Added
- First version