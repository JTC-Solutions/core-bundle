# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the JTC Solutions Core Bundle - a Symfony 7.2 bundle that provides foundational services, base classes, and conventions for PHP Symfony projects. It's designed as a reusable library for standardizing CRUD operations, exception handling, and API documentation across JTC Solutions projects.

## Development Commands

All commands should be run through Docker using the Makefile:

```bash
- The Application runs in docker
- Backend is using PHPStan, Unit Tests and ECS for code quality on backend
  - **PHPStan**. You can run PHPStan as `docker-compose exec -T php vendor/bin/phpstan analyse --configuration=phpstan.neon --ansi --error-format=compact --verbose --memory-limit=1G`
  - **ECS**. You can run ECS as `docker-compose exec -T php vendor/bin/ecs check --ansi --fix`
  - **Tests**. You can run our tests with following command: `docker-compose exec -T php php -d memory_limit=512M bin/phpunit`
```

## Architecture & Code Organization

### Key Architectural Patterns

1. **Entity Pattern**: All entities must implement `IEntity` interface and use `Ramsey\Uuid\UuidInterface` for primary identifiers
2. **Repository Pattern**: Repositories implement `IEntityRepository` and extend `BaseRepository`
3. **Service Layer**: Services implement `IEntityService` and extend `BaseEntityService` for CRUD operations
4. **Controller Pattern**: Controllers extend `BaseController` or `BaseEntityCRUDController` for standardized CRUD endpoints
5. **DTO Pattern**: Request validation uses DTOs implementing `IEntityRequestBody` with Symfony Validator constraints

### Core Components

- **Controllers**: Provide standardized CRUD operations with built-in validation
- **Services**: Handle business logic with abstract methods for mapping data (`mapDataAndCallCreate`, `mapDataAndCallUpdate`)
- **Repositories**: Data access layer with standard find/findAll/save/remove operations
- **Listeners**: 
  - `ExceptionListener`: Converts exceptions to JSON responses with translation support
  - `HistoryListener`: (WIP) Tracks entity changes for audit logging
- **ParamResolvers**: 
  - `EntityParamResolver`: Resolves route parameters to entities
  - `UuidQueryParamResolver`: Resolves query parameters to UUID objects

### History Feature (Work in Progress)

The bundle includes a generic history tracking system for Doctrine entities on the `history` branch. This system is designed to automatically track changes to any Doctrine entity that implements the appropriate interfaces.

#### Architecture Overview

The history tracking system follows these principles:
1. **Generic and Reusable**: Can be applied to any Doctrine entity implementing `IEntity` and `IHistoryTrackable`
2. **Extensible**: Each tracked entity needs its own parser, factory, and history entity implementations
3. **Comprehensive Tracking**: Tracks scalar changes, entity relations, enum changes, and collection modifications
4. **Type-Safe**: Designed to use strict PHPDoc types for robustness

#### Core Components

**Interfaces:**
- `IHistoryTrackable`: Marker interface for entities that should have history tracked. Must implement `getHistoryEntityFQCN()` returning the FQCN of the history entity
- `IHistory`: Marker interface for history entities (currently empty)
- `IDoctrineEventParser`: Defines methods for parsing Doctrine change sets
- `IHistoryFactory`: Defines methods for creating history entries from Doctrine events

**Base Classes:**
- `BaseHistory`: Base class for history entities (currently empty, should implement `IHistory`)
- `BaseDoctrineEventParser`: Abstract parser providing comprehensive change detection:
  - Parses scalar field changes
  - Handles entity relation changes
  - Processes enum field changes
  - Tracks collection additions/removals
  - Supports field ignoring via `$ignoredFields`
  - Formats entity references with ID and label (if `ILabelable`)
- `BaseHistoryFactory`: Abstract factory for creating history entries
- `HistoryListener`: Doctrine event listener orchestrating the tracking process

**Enums:**
- `HistoryActionTypeEnum`: Categorizes changes (CREATE, UPDATE, CHANGE_RELATION, ADDED_TO_COLLECTION, REMOVED_FROM_COLLECTION)
- `HistorySeverityEnum`: Defines severity levels (LOW, MEDIUM, HIGH)

#### Implementation Requirements

To track history for an entity:
1. Entity must implement `IHistoryTrackable` and define `getHistoryEntityFQCN()`
2. Create a history entity extending `BaseHistory` and implementing `IHistory`
3. Create a parser extending `BaseDoctrineEventParser` and implement:
   - `getDefinedCollections()`: Return collections to track
   - `getDefinedEnums()`: Return enum fields to track
4. Create a factory extending `BaseHistoryFactory` and implement `createHistoryEntity()`
5. Tag services appropriately for dependency injection

#### Known Limitations
- M:N relations with custom pivot entities are not tracked (planned for future)
- `HistoryListener` has incomplete implementation for update/remove events
- Missing interface method: `extractCreationData()` in `IDoctrineEventParser`

#### PHPDoc Standards for History Components

When working on history-related code, use strict PHPDoc types:
- Use `int<0, max>` instead of `int` for positive integers
- Use `non-empty-string` instead of `string` where applicable
- Use `positive-int` for IDs and counts
- Use `array<string, mixed>` with specific key-value types
- Use `Collection<int, IEntity>` with specific entity types
- Always specify array shapes: `array{field: non-empty-string, oldValue: mixed, newValue: mixed}`

### Exception Handling

Exceptions are automatically translated using the configured translation domain (default: `exceptions`):
- Standard HTTP exceptions: `core.<type>.title`, `core.<type>.message`
- Custom exceptions: `custom.<translation_code>.title`, `custom.<translation_code>.message`

## Code Standards

- **PHP 8.3+** with strict types enabled
- **PSR-12** coding standards enforced via ECS
- **PHPStan** at maximum level for static analysis
- **Symfony 7.2** conventions and best practices
- All code must pass `make stan` and `make fix` before committing

### Strict PHPDoc Standards

This package emphasizes robust type safety through strict PHPDoc annotations:
- Use `int<0, max>` or `positive-int` instead of generic `int` for positive integers
- Use `non-empty-string` instead of `string` where empty strings are not allowed
- Use `non-empty-array<T>` for arrays that must contain at least one element
- Use `class-string<T>` for class name strings
- Always specify array shapes: `array{key: type, ...}` for structured arrays
- Use union types precisely: `string|null` not `mixed` when only those types are valid
- For collections: `Collection<int, SpecificEntity>` not `Collection<mixed, mixed>`
- Use literal types where applicable: `'create'|'update'|'delete'` instead of `string`
- Specify numeric ranges: `int<1, 100>` for bounded integers
- Use `numeric-string` for strings containing only numbers

## Testing

- Unit tests in `tests/Unit/`
- Functional tests in `tests/Functional/`
- Test fixtures in `tests/Fixtures/` with dummy implementations
- Tests use PHPUnit 10.x with Symfony test utilities

## Bundle Configuration

The bundle is configured under `jtc_solutions_core` key with options for:
- Parameter resolvers (uuid_resolver, entity_resolver)
- Listeners (exception_listener with translation domain)
- OpenAPI property describers for NelmioApiDocBundle integration

### Code Best Practices

- never use assert() php function in real code, only in tests