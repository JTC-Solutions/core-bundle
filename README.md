# JTC Solutions Core Bundle

This bundle provides a foundational set of services, base classes, and conventions used as a "skeleton" for most PHP Symfony projects developed at JTC Solutions.
It aims to streamline development by offering reusable components for common tasks like CRUD operations, exception handling, and API documentation.

## Installation

1.  **Require the bundle using Composer:**
    ```bash
    composer require jtc-solutions/core-bundle
    ```
2.  **Enable the Bundle:**
    Add the bundle to your `config/bundles.php` file:

    ```php
    <?php

    return [
        // ... other bundles
        JtcSolutions\Core\JtcSolutionsCoreBundle::class => ['all' => true],
    ];
    ```

## Usage

1.  **Entities**:
    * Your application entities should implement `JtcSolutions\Core\Entity\IEntity`.
    * Use `Ramsey\Uuid\UuidInterface` for primary identifiers.

2.  **Repositories**:
    * Implement `JtcSolutions\Core\Repository\IEntityRepository`.
    * Extend `JtcSolutions\Core\Repository\BaseRepository`.

3.  **Services**:
    * Implement `JtcSolutions\Core\Service\IEntityService` for entities requiring CRUD operations.
    * Extend `JtcSolutions\Core\Service\BaseEntityService` to leverage built-in logic for handling creation, updates, and deletion via abstract methods (`mapDataAndCallCreate`, `mapDataAndCallUpdate`, `delete`).
    * Inject the corresponding repository into your service.

4.  **Controllers**:
    * Extend `JtcSolutions\Core\Controller\BaseController` for AbstractController from Symfony, with the possibility of future improvements.
    * For controllers handling standard entity CRUD, extend `JtcSolutions\Core\Controller\BaseEntityCRUDController`. Inject the corresponding `IEntityService`. This base controller provides protected methods (`handleCreate`, `handleUpdate`, `handleDelete`) that validate request DTOs and call the service.

5.  **Request Data Transfer Objects (DTOs)**:
    * For request bodies used in create/update operations handled by `BaseEntityCRUDController`/`BaseEntityService`, implement `JtcSolutions\Core\Dto\IEntityRequestBody`.
    * Use Symfony's Validator component constraints on your DTO properties.

6.  **Exception Handling & Translations**:
    * The `ExceptionListener` is enabled by default and will catch exceptions.
    * It automatically converts `TranslatableException` types and standard HTTP exceptions into `ErrorRequestJsonResponse`.
    * Provide translations for the exception keys in the configured translation domain (default: `exceptions`). Keys follow the pattern `core.<type>.title`, `core.<type>.message` for standard HTTP errors and `custom.<translation_code>.title`, `custom.<translation_code>.message` for `TranslatableException` types. Example translation file (`translations/exceptions.en.yaml`):
        ```yaml
        core.not_found.title: 'Resource Not Found'
        core.not_found.message: 'The requested resource could not be found.'
        custom.entity_not_found.title: 'Entity Not Found'
        custom.entity_not_found.message: 'The specific item you were looking for does not exist.'
        custom.entity_already_exists.title: 'Conflict'
        custom.entity_already_exists.message: 'An item with the provided details already exists.'
        # ... add other translations
        ```

7.  **Parameter Resolvers**:
    * **Entity Resolver**: If enabled (default), controller actions can directly type-hint arguments with an `IEntity` implementation (e.g., `public function show(Product $product)`). If a route parameter `{product}` exists, the resolver will fetch the `Product` using its repository's `find()` method.
    * **UUID Resolver**: If enabled (default), controller actions can type-hint arguments with `UuidInterface` (e.g., `public function list(UuidInterface $categoryId)`). The resolver will attempt to create a `Uuid` object from a query parameter with the same name (`?categoryId=...`).

## Configuration

Configure the bundle under the `jtc_solutions_core` key in your `config/packages/jtc_solutions_core.yaml` (or any other config file).

```yaml
# config/packages/jtc_solutions_core.yaml
jtc_solutions_core:
    # Parameter Resolver Configuration
    param_resolvers:
        uuid_resolver:
            # Enable/disable the UuidQueryParamResolver (default: true)
            enable: true
        entity_resolver:
            # Enable/disable the EntityParamResolver (default: true)
            enable: true

    # Listener Configuration
    listeners:
        exception_listener:
            # Enable/disable the ExceptionListener (default: true)
            enable: true
            # Set the translation domain for exception messages (default: 'exceptions')
            translation_domain: 'exceptions'

    # OpenAPI / NelmioApiDocBundle Integration
    open_api:
        property_describers:
            uuid_interface_property_describer:
                # Enable/disable the describer for UuidInterface (default: true)
                enable: true