# Changes in Sequenceable behavior extension for Doctrine2

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [0.0.2] - 2017-05-24

### Added
* Added support for behavior [soft-deletable](https://github.com/KnpLabs/DoctrineBehaviors#softDeletable) of [KNPLabs](http://knplabs.com/).

### Fixed
* Fixed incorrect table alias on multiple SequenceID annotations in [SequenceableEntityContainer](src/Entity/SequenceableEntityContainer.php).
* Removed type hinting of functions (removed PHP7 functionality).
* Added composer package [doctrine/orm](https://packagist.org/packages/doctrine/orm) to `require` and [knplabs/doctrine-behaviors](https://packagist.org/packages/knplabs/doctrine-behaviors) to `require-dev` 

### Changed

## [0.0.1] - 2017-05-09

### Fixed
* Fixed [README.md](README.md) and namespaces.

## [0.0.0] - 2017-05-09

### Added
* First version