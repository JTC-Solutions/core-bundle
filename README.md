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
```

# History Tracking Tutorial

This bundle provides a comprehensive history tracking system that automatically captures changes to Doctrine entities. This tutorial demonstrates how to implement history tracking for a User-Role Many-to-Many relationship with additional pivot data.

## Complete Example: User-Role History Tracking

### Step 1: Create Your Entities

#### User Entity
```php
<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements IEntity, IHistoryTrackable, ILabelable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'string', length: 255)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 255)]
    private string $lastName;

    /**
     * Many-to-Many relationship with Role through UserRole pivot entity
     * @var Collection<int, UserRole>
     */
    #[ORM\OneToMany(targetEntity: UserRole::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $userRoles;

    public function __construct(UuidInterface $id, string $email, string $firstName, string $lastName)
    {
        $this->id = $id;
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->userRoles = new ArrayCollection();
    }

    // Required by IEntity interface
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    // Required by IHistoryTrackable interface - tells the system which history entity to use
    public function getHistoryEntityFQCN(): string
    {
        return UserHistory::class;
    }

    // Required by ILabelable interface - provides human-readable representation in history
    public function getLabel(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    // Getters and setters
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }
    
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): void { $this->firstName = $firstName; }
    
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): void { $this->lastName = $lastName; }

    /**
     * @return Collection<int, UserRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(UserRole $userRole): void
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $userRole->setUser($this);
        }
    }

    public function removeUserRole(UserRole $userRole): void
    {
        if ($this->userRoles->contains($userRole)) {
            $this->userRoles->removeElement($userRole);
        }
    }
}
```

#### Role Entity
```php
<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\ILabelable;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'roles')]
class Role implements IEntity, IHistoryTrackable, ILabelable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    /**
     * Many-to-Many relationship with User through UserRole pivot entity
     * @var Collection<int, UserRole>
     */
    #[ORM\OneToMany(targetEntity: UserRole::class, mappedBy: 'role', cascade: ['persist', 'remove'])]
    private Collection $userRoles;

    public function __construct(UuidInterface $id, string $name, ?string $description = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->userRoles = new ArrayCollection();
    }

    // Required by IEntity interface
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    // Required by IHistoryTrackable interface - tells the system which history entity to use
    public function getHistoryEntityFQCN(): string
    {
        return RoleHistory::class;
    }

    // Required by ILabelable interface - provides human-readable representation in history
    public function getLabel(): string
    {
        return $this->name;
    }

    // Getters and setters
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    /**
     * @return Collection<int, UserRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(UserRole $userRole): void
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $userRole->setRole($this);
        }
    }

    public function removeUserRole(UserRole $userRole): void
    {
        if ($this->userRoles->contains($userRole)) {
            $this->userRoles->removeElement($userRole);
        }
    }
}
```

#### UserRole Pivot Entity (The Key Component!)
```php
<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Entity\IEntity;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Entity\IPivotHistoryTrackable;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user_roles')]
class UserRole implements IEntity, IPivotHistoryTrackable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'userRoles')]
    #[ORM\JoinColumn(nullable: false)]
    private Role $role;

    // This is the additional pivot data - the "assignedAt" datetime field
    #[ORM\Column(type: 'datetime')]
    private DateTime $assignedAt;

    // Optional: You can add more pivot data fields
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $assignedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct(UuidInterface $id, User $user, Role $role, DateTime $assignedAt)
    {
        $this->id = $id;
        $this->user = $user;
        $this->role = $role;
        $this->assignedAt = $assignedAt;
    }

    // Required by IEntity interface
    public function getId(): UuidInterface
    {
        return $this->id;
    }

    // Required by IPivotHistoryTrackable - defines which entity "owns" this relationship
    // In this case, User is the owner of the role assignment
    public function getHistoryOwner(): IHistoryTrackable
    {
        return $this->user;
    }

    // Required by IPivotHistoryTrackable - defines the target entity of this relationship
    public function getHistoryTarget(): IEntity
    {
        return $this->role;
    }

    // Required by IPivotHistoryTrackable - defines how this relationship appears in history
    // This will create history entries like: "User got role Admin" and "Role Admin got user John"
    public function getRelationshipType(): string
    {
        return 'role'; // This creates history entries like "User got role Admin"
    }

    // Required by IPivotHistoryTrackable - defines the additional data stored in history
    // This includes the assignedAt datetime and any other pivot-specific data
    public function getPivotData(): array
    {
        return [
            'assignedAt' => $this->assignedAt->format('Y-m-d H:i:s'),
            'assignedBy' => $this->assignedBy,
            'notes' => $this->notes,
        ];
    }

    // Getters and setters
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): void { $this->user = $user; }
    
    public function getRole(): Role { return $this->role; }
    public function setRole(Role $role): void { $this->role = $role; }
    
    public function getAssignedAt(): DateTime { return $this->assignedAt; }
    public function setAssignedAt(DateTime $assignedAt): void { $this->assignedAt = $assignedAt; }
    
    public function getAssignedBy(): ?string { return $this->assignedBy; }
    public function setAssignedBy(?string $assignedBy): void { $this->assignedBy = $assignedBy; }
    
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }
}
```

### Step 2: Create History Entities

#### UserHistory Entity
```php
<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user_history')]
class UserHistory implements IHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?UserInterface $createdBy;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $message;

    #[ORM\Column(type: 'string', enumType: HistorySeverityEnum::class)]
    private HistorySeverityEnum $severity;

    /**
     * @var array<int, HistoryChange>
     */
    #[ORM\Column(type: 'json')]
    private array $changes;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<int, HistoryChange> $changes
     */
    public function __construct(
        UuidInterface $id,
        ?UserInterface $createdBy,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        User $user
    ) {
        $this->id = $id;
        $this->createdBy = $createdBy;
        $this->message = $message;
        $this->severity = $severity;
        $this->changes = $changes;
        $this->user = $user;
        $this->createdAt = new DateTimeImmutable();
    }

    // Getters
    public function getId(): UuidInterface { return $this->id; }
    public function getCreatedBy(): ?UserInterface { return $this->createdBy; }
    public function getMessage(): ?string { return $this->message; }
    public function getSeverity(): HistorySeverityEnum { return $this->severity; }
    public function getChanges(): array { return $this->changes; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
```

#### RoleHistory Entity
```php
<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JtcSolutions\Core\Dto\HistoryChange;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'role_history')]
class RoleHistory implements IHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?UserInterface $createdBy;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $message;

    #[ORM\Column(type: 'string', enumType: HistorySeverityEnum::class)]
    private HistorySeverityEnum $severity;

    /**
     * @var array<int, HistoryChange>
     */
    #[ORM\Column(type: 'json')]
    private array $changes;

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<int, HistoryChange> $changes
     */
    public function __construct(
        UuidInterface $id,
        ?UserInterface $createdBy,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        Role $role
    ) {
        $this->id = $id;
        $this->createdBy = $createdBy;
        $this->message = $message;
        $this->severity = $severity;
        $this->changes = $changes;
        $this->role = $role;
        $this->createdAt = new DateTimeImmutable();
    }

    // Getters
    public function getId(): UuidInterface { return $this->id; }
    public function getCreatedBy(): ?UserInterface { return $this->createdBy; }
    public function getMessage(): ?string { return $this->message; }
    public function getSeverity(): HistorySeverityEnum { return $this->severity; }
    public function getChanges(): array { return $this->changes; }
    public function getRole(): Role { return $this->role; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
```

### Step 3: Create Event Parsers

#### UserDoctrineEventParser
```php
<?php

namespace App\History;

use App\Entity\User;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\MetadataAwareDoctrineEventParser;

/**
 * Parses User entity changes for history tracking.
 * Uses automatic metadata detection for collections and enums.
 */
class UserDoctrineEventParser extends MetadataAwareDoctrineEventParser
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        // Call parent constructor with ignored fields
        // These fields won't be tracked in history
        parent::__construct(
            $entityManager,
            ['updatedAt', 'createdAt'] // Common fields to ignore
        );
    }

    /**
     * Define pivot entities for User.
     * This tells the system to track UserRole pivot entities for User history.
     * 
     * @return array<string, class-string<IPivotHistoryTrackable>>
     */
    protected function getDefinedPivotEntities(IHistoryTrackable $entity): array
    {
        if ($entity instanceof User) {
            return [
                'role' => UserRole::class, // Track UserRole pivot entities as "role" relationships
            ];
        }
        return [];
    }
}
```

#### RoleDoctrineEventParser
```php
<?php

namespace App\History;

use App\Entity\Role;
use App\Entity\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Parser\MetadataAwareDoctrineEventParser;

/**
 * Parses Role entity changes for history tracking.
 * Uses automatic metadata detection for collections and enums.
 */
class RoleDoctrineEventParser extends MetadataAwareDoctrineEventParser
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        // Call parent constructor with ignored fields
        parent::__construct(
            $entityManager,
            ['updatedAt', 'createdAt'] // Common fields to ignore
        );
    }

    /**
     * Define pivot entities for Role.
     * This tells the system to track UserRole pivot entities for Role history.
     * 
     * @return array<string, class-string<IPivotHistoryTrackable>>
     */
    protected function getDefinedPivotEntities(IHistoryTrackable $entity): array
    {
        if ($entity instanceof Role) {
            return [
                'user' => UserRole::class, // Track UserRole pivot entities as "user" relationships (reverse perspective)
            ];
        }
        return [];
    }
}
```

### Step 4: Create History Factories

#### UserHistoryFactory
```php
<?php

namespace App\History;

use App\Entity\User;
use App\Entity\UserHistory;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Factory for creating UserHistory entities.
 * Handles the creation of history entries for User entity changes.
 */
class UserHistoryFactory extends BaseHistoryFactory
{
    // Define which entity class this factory supports
    protected const string CLASS_NAME = User::class;

    /**
     * Check if this factory supports the given entity.
     */
    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof User;
    }

    /**
     * Create a new UserHistory entity.
     * This method is called automatically by the history system.
     */
    protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory {
        /** @var User $entity */
        return new UserHistory(
            Uuid::uuid4(),
            $user,
            $message,
            $severity,
            $changes,
            $entity
        );
    }
}
```

#### RoleHistoryFactory
```php
<?php

namespace App\History;

use App\Entity\Role;
use App\Entity\RoleHistory;
use JtcSolutions\Core\Entity\IHistory;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Enum\HistorySeverityEnum;
use JtcSolutions\Core\Factory\BaseHistoryFactory;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Factory for creating RoleHistory entities.
 * Handles the creation of history entries for Role entity changes.
 */
class RoleHistoryFactory extends BaseHistoryFactory
{
    // Define which entity class this factory supports
    protected const string CLASS_NAME = Role::class;

    /**
     * Check if this factory supports the given entity.
     */
    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof Role;
    }

    /**
     * Create a new RoleHistory entity.
     * This method is called automatically by the history system.
     */
    protected function createHistoryEntity(
        ?UserInterface $user,
        ?string $message,
        HistorySeverityEnum $severity,
        array $changes,
        IHistoryTrackable $entity,
    ): IHistory {
        /** @var Role $entity */
        return new RoleHistory(
            Uuid::uuid4(),
            $user,
            $message,
            $severity,
            $changes,
            $entity
        );
    }
}
```

### Step 5: Create Change Extractors

#### UserChangeExtractor
```php
<?php

namespace App\History;

use App\Entity\User;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;

/**
 * Change extractor for User entities.
 * Connects the User entity with its corresponding parser.
 */
class UserChangeExtractor extends BaseChangeExtractor
{
    /**
     * Check if this extractor supports the given entity.
     */
    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof User;
    }
}
```

#### RoleChangeExtractor
```php
<?php

namespace App\History;

use App\Entity\Role;
use JtcSolutions\Core\Entity\IHistoryTrackable;
use JtcSolutions\Core\Extractor\BaseChangeExtractor;

/**
 * Change extractor for Role entities.
 * Connects the Role entity with its corresponding parser.
 */
class RoleChangeExtractor extends BaseChangeExtractor
{
    /**
     * Check if this extractor supports the given entity.
     */
    public function supports(IHistoryTrackable $entity): bool
    {
        return $entity instanceof Role;
    }
}
```

### Step 6: Register Services

Create or update your `config/services.yaml`:

```yaml
# config/services.yaml
services:
    # User History Services
    App\History\UserDoctrineEventParser:
        arguments:
            $entityManager: '@doctrine.orm.entity_manager'
        tags: ['jtc_solutions_core.doctrine_event_parser']

    App\History\UserHistoryFactory:
        tags: ['jtc_solutions_core.history_factory']

    App\History\UserChangeExtractor:
        arguments:
            $parser: '@App\History\UserDoctrineEventParser'
        tags: ['jtc_solutions_core.change_extractor']

    # Role History Services  
    App\History\RoleDoctrineEventParser:
        arguments:
            $entityManager: '@doctrine.orm.entity_manager'
        tags: ['jtc_solutions_core.doctrine_event_parser']

    App\History\RoleHistoryFactory:
        tags: ['jtc_solutions_core.history_factory']

    App\History\RoleChangeExtractor:
        arguments:
            $parser: '@App\History\RoleDoctrineEventParser'
        tags: ['jtc_solutions_core.change_extractor']
```

### Step 7: How It Works in Practice

When you create, update, or delete UserRole entities, the history system automatically:

#### Creating a User-Role Assignment
```php
// In your service or controller
$user = $userRepository->find($userId);
$role = $roleRepository->find($roleId);

// Create the pivot entity with additional data
$userRole = new UserRole(
    Uuid::uuid4(),
    $user,
    $role,
    new DateTime(), // assignedAt
);
$userRole->setAssignedBy('admin@example.com');
$userRole->setNotes('Assigned during onboarding');

// Persist the entity - this automatically triggers history tracking
$entityManager->persist($userRole);
$entityManager->flush();
```

This creates **dual history entries**:

**User History Entry:**
```json
{
  "field": "role",
  "actionType": "PIVOT_CREATED",
  "newValue": {
    "id": "role-uuid-here", 
    "label": "Admin",
    "pivotData": {
      "assignedAt": "2024-01-15 14:30:00",
      "assignedBy": "admin@example.com",
      "notes": "Assigned during onboarding"
    }
  },
  "relatedEntity": "Role",
  "pivotEntity": "UserRole"
}
```

**Role History Entry:**
```json
{
  "field": "user",
  "actionType": "PIVOT_CREATED", 
  "newValue": {
    "id": "user-uuid-here",
    "label": "John Doe",
    "pivotData": {
      "assignedAt": "2024-01-15 14:30:00",
      "assignedBy": "admin@example.com", 
      "notes": "Assigned during onboarding"
    }
  },
  "relatedEntity": "User",
  "pivotEntity": "UserRole"
}
```

#### Updating User Properties
```php
// Regular User entity changes are also tracked
$user->setEmail('new.email@example.com');
$entityManager->flush();
```

This creates a **User History Entry:**
```json
{
  "field": "email",
  "actionType": "UPDATE", 
  "oldValue": "old.email@example.com",
  "newValue": "new.email@example.com",
  "relatedEntity": null
}
```

#### Updating Pivot Data
```php
// Update the assignment with new information
$userRole->setNotes('Updated role permissions');
$entityManager->flush();
```

This creates **dual history entries** showing the pivot data changes.

### Key Benefits

1. **Automatic Tracking**: No manual history creation required
2. **Dual Perspective**: Both User and Role get history entries for the relationship
3. **Rich Context**: Pivot data (assignedAt, assignedBy, notes) is preserved in history
4. **Type Safety**: Strict PHPDoc types ensure robust code
5. **Minimal Boilerplate**: MetadataAwareDoctrineEventParser auto-detects collections and enums
6. **Complete Audit Trail**: Every change is tracked with user context and timestamps

This approach provides a complete audit trail for your Many-to-Many relationships while maintaining clean, maintainable code that follows SOLID principles.