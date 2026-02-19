# CLAUDE.md - Module Card Identity

This file provides guidance to Claude Code when working with this module.

## Module Overview

**`hanafalah/module-card-identity`** is a Laravel package that provides a polymorphic identity card storage system for the Wellmed ecosystem. It allows any model to have multiple identity cards (KTP, SIM, BPJS, passport, etc.) attached via morphable relationships.

**Use cases:**
- Storing patient identity documents (KTP number, BPJS number)
- Storing employee identity documents (SIM, passport)
- Any entity needing multiple identification card storage

## CRITICAL: Memory Exhaustion Warning

**This module uses `registers(['*'])` in its ServiceProvider.**

```php
// ModuleCardIdentityServiceProvider.php
public function register()
{
    $this->registerMainClass(ModuleCardIdentity::class)
        ->registerCommandService(Providers\CommandServiceProvider::class)
        ->registers(['*']);  // POTENTIALLY DANGEROUS
}
```

While `registers(['*'])` in `laravel-support` v2.0+ only registers SAFE methods, be aware that:

1. **Schema classes extend `PackageManagement`** which uses `HasModelConfiguration` trait
2. If `registerSchema()` is triggered, it can cause memory chains
3. Monitor memory usage when this module boots

**If experiencing memory issues:**
```php
// Change to explicit registration
public function register()
{
    $this->registerMainClass(ModuleCardIdentity::class)
        ->registerCommandService(Providers\CommandServiceProvider::class)
        ->registers(['Config', 'Model', 'Database', 'Migration']);
}
```

## Architecture Overview

```
module-card-identity/
├── assets/
│   ├── config/
│   │   └── config.php              # Module configuration
│   └── database/
│       └── migrations/
│           └── 0001_01_01_000007_create_card_identities_table.php
├── src/
│   ├── Commands/
│   │   ├── EnvironmentCommand.php  # Base command class
│   │   └── InstallMakeCommand.php  # Installation command
│   ├── Concerns/
│   │   └── HasCardIdentity.php     # Trait for models to use
│   ├── Contracts/
│   │   ├── Data/
│   │   │   └── CardIdentityData.php
│   │   ├── Schemas/
│   │   │   └── CardIdentity.php
│   │   └── ModuleCardIdentity.php
│   ├── Data/
│   │   └── CardIdentityData.php    # DTO for card identity operations
│   ├── Models/
│   │   └── CardIdentity.php        # Eloquent model
│   ├── Providers/
│   │   └── CommandServiceProvider.php
│   ├── Resources/
│   │   └── CardIdentity/
│   │       ├── ViewCardIdentity.php
│   │       └── ShowCardIdentity.php
│   ├── Schemas/
│   │   └── CardIdentity.php        # Business logic schema
│   ├── Supports/
│   │   └── BaseModuleCardIdentity.php
│   ├── ModuleCardIdentity.php      # Main module class
│   └── ModuleCardIdentityServiceProvider.php
└── composer.json
```

## Key Classes

### CardIdentity Model

Located at: `src/Models/CardIdentity.php`

The Eloquent model representing an identity card record.

**Database Schema:**
```php
$table->ulid('id')->primary();
$table->string('reference_type', 50);  // Morphable type (e.g., 'patient', 'employee')
$table->string('reference_id', 36);    // Morphable ID
$table->string('flag', 50);            // Card type (e.g., 'KTP', 'SIM', 'BPJS')
$table->string('value', 255);          // Card number/value
$table->timestamps();
$table->softDeletes();
```

**Key features:**
- Uses ULIDs for primary key
- Soft deletes enabled
- Morphable relationship via `reference()` method
- Auto-casts value to string on create

### HasCardIdentity Trait

Located at: `src/Concerns/HasCardIdentity.php`

**Use this trait on any model that needs identity cards:**

```php
use Hanafalah\ModuleCardIdentity\Concerns\HasCardIdentity;

class Patient extends Model
{
    use HasCardIdentity;

    // Now you can use:
    // $patient->cardIdentity()    - morphOne relationship
    // $patient->cardIdentities()  - morphMany relationship
    // $patient->setCardIdentity($flag, $value)
}
```

**Methods provided:**
| Method | Return | Description |
|--------|--------|-------------|
| `setCardIdentity($flag, $value)` | `?CardIdentity` | Create/update card identity |
| `cardIdentity()` | `MorphOne` | Single card relationship |
| `cardIdentities()` | `MorphMany` | Multiple cards relationship |

### CardIdentity Schema

Located at: `src/Schemas/CardIdentity.php`

Business logic layer for card identity operations.

**Key methods:**
- `prepareStoreCardIdentity(CardIdentityData $dto)` - Create/update identity card

**Caching:**
- Index cache with tag `card_identity`
- 24-hour cache duration

### CardIdentityData DTO

Located at: `src/Data/CardIdentityData.php`

Data Transfer Object using Spatie Laravel Data:
```php
public mixed $id = null;
public string $reference_type;
public mixed $reference_id;
public string $flag;       // From parent class
public string $value;      // From parent class
```

## Configuration

Config file: `assets/config/config.php`

```php
return [
    'namespace'  => 'Hanafalah\ModuleCardIdentity',
    'libs'       => [
        'model' => 'Models',
        'contract' => 'Contracts',
        'schema' => 'Schemas',
        'database' => 'Database',
        'data' => 'Data',
        'resource' => 'Resources',
        'migration' => '../assets/database/migrations'
    ],
    'app' => [
        'contracts' => [],
    ],
    'database' => [
        'models' => []  // Override models here if needed
    ]
];
```

**Overriding the CardIdentity model:**
```php
// In your application config/database.php or module config
'models' => [
    'CardIdentity' => \App\Models\CustomCardIdentity::class,
]
```

## Installation

**Using artisan command:**
```bash
php artisan module-card-identity:install
```

This publishes migrations to your application's database directory.

**Manual installation:**
```bash
php artisan vendor:publish --provider="Hanafalah\ModuleCardIdentity\ModuleCardIdentityServiceProvider" --tag="migrations"
php artisan migrate
```

## Usage Examples

### Adding identity cards to a model

```php
use Hanafalah\ModuleCardIdentity\Concerns\HasCardIdentity;

class Patient extends Model
{
    use HasCardIdentity;
}

// Usage
$patient = Patient::find(1);

// Set single identity
$patient->setCardIdentity('KTP', '3201234567890001');
$patient->setCardIdentity('BPJS', '0001234567890');

// Query identity cards
$ktpCard = $patient->cardIdentities()->where('flag', 'KTP')->first();
$allCards = $patient->cardIdentities;
```

### Using the Schema directly

```php
use Hanafalah\ModuleCardIdentity\Schemas\CardIdentity;
use Hanafalah\ModuleCardIdentity\Data\CardIdentityData;

$schema = app(CardIdentity::class);

$dto = CardIdentityData::from([
    'reference_type' => 'patient',
    'reference_id' => $patientId,
    'flag' => 'KTP',
    'value' => '3201234567890001'
]);

$cardIdentity = $schema->prepareStoreCardIdentity($dto);
```

## Common Identity Card Flags

Use consistent flag values across your application:

| Flag | Description |
|------|-------------|
| `KTP` | Indonesian National ID |
| `SIM` | Indonesian Driver's License |
| `BPJS` | Indonesian Health Insurance Number |
| `PASSPORT` | Passport Number |
| `NIK` | National Identity Number |
| `NIP` | Civil Servant Number |
| `NPWP` | Tax ID Number |

## Dependencies

This module requires:
- `hanafalah/laravel-support` (dev-main)

Transitive dependencies via laravel-support:
- `spatie/laravel-data` - For DTOs
- Other laravel-support dependencies

## Safe Development Patterns

### DO: Use the trait for morphable relationships

```php
// Good - use the provided trait
class Employee extends Model
{
    use HasCardIdentity;
}
```

### DO NOT: Auto-load Schema classes unnecessarily

```php
// Avoid in controllers - use dependency injection instead
$schema = new CardIdentity();  // Don't instantiate directly

// Better - let Laravel resolve it
$schema = app(CardIdentity::class);
```

### DO: Use consistent flag values

```php
// Good - use constants or enums
class CardIdentityFlag
{
    const KTP = 'KTP';
    const BPJS = 'BPJS';
    const SIM = 'SIM';
}

$patient->setCardIdentity(CardIdentityFlag::KTP, $ktpNumber);
```

## Testing Changes

After modifying this module:

```bash
# Clear caches
docker exec -it wellmed-backbone php artisan config:clear
docker exec -it wellmed-backbone php artisan cache:clear

# Reload Octane
docker exec -it wellmed-backbone php artisan octane:reload

# Check for memory issues
docker logs wellmed-backbone 2>&1 | grep -i "memory\|fatal"
```

## Related Modules

This module is often used alongside:
- `module-patient` - Patient management
- `module-employee` - Employee management
- Any module managing entities with identity documents

## API Resources

Two resource classes are provided for API responses:

- `ViewCardIdentity` - For list views
- `ShowCardIdentity` - For detailed views (extends ViewCardIdentity)

Both extend `Hanafalah\LaravelSupport\Resources\ApiResource`.
