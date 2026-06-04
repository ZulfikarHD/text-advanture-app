# Documentation Templates

Template copy-paste untuk berbagai jenis dokumentasi. Ganti placeholder `[...]` dengan konten yang sesuai.

## Laravel Service Class

```php
<?php

declare(strict_types=1);

namespace App\Services;

/*
|--------------------------------------------------------------------------
| [Service Name]
|--------------------------------------------------------------------------
|
| [Deskripsi singkat service ini, yang mencakup: responsibility utama
| dan use case yang ditangani]
|
| Dependencies:
| - [Dependency 1] untuk [tujuan]
| - [Dependency 2] untuk [tujuan]
|
*/
class [ServiceName]
{
    public function __construct(
        private [DependencyType] $[dependency]
    ) {}

    /**
     * [Deskripsi singkat method dalam satu baris]
     *
     * [Deskripsi lebih detail jika diperlukan, yang mencakup:
     * behavior penting atau side effects]
     *
     * @param [Type] $[param] Deskripsi parameter
     * @return [Type] Deskripsi return value
     *
     * @throws [ExceptionType] Kondisi yang menyebabkan exception
     */
    public function [methodName]([Type] $[param]): [ReturnType]
    {
        // ...
    }
}
```

## Laravel Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| [Model Name]
|--------------------------------------------------------------------------
|
| [Deskripsi entity ini dalam business domain]
|
| Relationships:
| - belongsTo: [Related Model] (penjelasan singkat)
| - hasMany: [Related Model] (penjelasan singkat)
|
| Key Attributes:
| - [attribute]: penjelasan
| - [attribute]: penjelasan
|
*/
class [ModelName] extends Model
{
    protected $fillable = [
        // ...
    ];

    /**
     * Deskripsi relationship
     */
    public function [relationName](): [RelationType]
    {
        return $this->[relationType]([RelatedModel]::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Deskripsi scope
     *
     * @param Builder $query
     * @param [Type] $[param] Deskripsi param
     */
    public function scope[ScopeName]($query, [Type] $[param])
    {
        return $query->where('[column]', $[param]);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Deskripsi accessor dengan formula jika ada
     */
    public function get[AttributeName]Attribute(): [Type]
    {
        // ...
    }
}
```

## Laravel Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/*
|--------------------------------------------------------------------------
| [Controller Name]
|--------------------------------------------------------------------------
|
| Controller untuk menangani [domain/feature], yang mencakup:
| [list responsibilities]
|
*/
class [ControllerName] extends Controller
{
    public function __construct(
        private [ServiceType] $[service]
    ) {}

    /**
     * Menampilkan listing dari [resource].
     */
    public function index(): Response
    {
        return Inertia::render('[Feature]/Index', [
            // ...
        ]);
    }

    /**
     * Menampilkan detail [resource] tertentu.
     *
     * @param [Model] $[model] Deskripsi dengan route model binding
     */
    public function show([Model] $[model]): Response
    {
        return Inertia::render('[Feature]/Show', [
            '[model]' => $[model],
        ]);
    }

    /**
     * Menyimpan [resource] baru.
     *
     * @param [FormRequest] $request Validated request data
     */
    public function store([FormRequest] $request): RedirectResponse
    {
        // ...
    }
}
```

## Vue Page Component

```vue
<script setup lang="ts">
/**
 * [PageName] - Deskripsi singkat page ini
 *
 * Deskripsi lebih detail yang mencakup:
 * - Features utama
 * - User interactions yang di-handle
 * - Data yang ditampilkan
 *
 * @route /[route-path]
 */

import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

/**
 * Props dari controller (Inertia page props)
 */
interface Props {
  /** Deskripsi prop */
  [propName]: [Type]
}

const props = defineProps<Props>()
</script>

<template>
  <AppLayout>
    <Head title="[Page Title]" />

    <!-- Page content -->
  </AppLayout>
</template>
```

## Vue Reusable Component

```vue
<script setup lang="ts">
/**
 * [ComponentName] - Deskripsi singkat komponen
 *
 * Deskripsi features yang mencakup:
 * - Feature 1
 * - Feature 2
 *
 * @example
 * <[ComponentName]
 *   :[prop]="value"
 *   @[event]="handler"
 * />
 */

/**
 * Props yang diterima komponen
 */
interface Props {
  /** Deskripsi prop */
  [propName]: [Type]
  /** Deskripsi optional prop */
  [optionalProp]?: [Type]
}

const props = withDefaults(defineProps<Props>(), {
  [optionalProp]: [defaultValue],
})

/**
 * Events yang di-emit komponen
 */
const emit = defineEmits<{
  /** Deskripsi event */
  [eventName]: [[PayloadType]]
}>()
</script>

<template>
  <!-- Component template -->
</template>
```

## TypeScript Composable

```typescript
/**
 * [useComposableName] - Deskripsi singkat composable
 *
 * Deskripsi detail yang mencakup:
 * - Use cases
 * - State yang di-manage
 * - Side effects jika ada
 *
 * @example
 * const { [returnValue] } = [useComposableName]()
 * [returnValue]([params])
 */
export function [useComposableName]() {
  /**
   * Deskripsi function
   *
   * @param [param] - Deskripsi param
   * @returns Deskripsi return
   */
  const [functionName] = ([param]: [Type]): [ReturnType] => {
    // ...
  }

  return {
    [functionName],
  }
}
```

## TypeScript Interface

```typescript
/**
 * [InterfaceName] - Deskripsi entity/structure ini
 *
 * Context tambahan jika diperlukan, seperti:
 * - Kapan interface ini digunakan
 * - Relationship dengan interface lain
 */
export interface [InterfaceName] {
  /** Deskripsi property */
  [propertyName]: [Type]

  /** Deskripsi optional property */
  [optionalProperty]?: [Type]

  /**
   * Deskripsi property dengan detail lebih
   * Detail tambahan atau constraints
   */
  [complexProperty]: [Type]
}
```

## README - Feature Module

```markdown
# [Feature Name]

Satu paragraf deskripsi feature ini

## Overview

Penjelasan lebih detail tentang feature, yang mencakup:
business context dan user needs yang ditangani

## Directory Structure

\`\`\`
Pages/[Feature]/
├── Index.vue              # Tujuan
├── Show.vue               # Tujuan
├── Partials/
│   ├── [Component].vue    # Tujuan
│   └── [Component].vue    # Tujuan
\`\`\`

## Features

### [Feature 1]
Deskripsi feature

### [Feature 2]
Deskripsi feature

## API Endpoints

| Method | Endpoint | Tujuan |
|--------|----------|---------|
| GET | `/[resource]` | Tujuan |
| POST | `/[resource]` | Tujuan |

## Usage Example

\`\`\`vue
<script setup>
// Example code
</script>

<template>
  <!-- Example template -->
</template>
\`\`\`

## Related Modules

- **[Module]** - Deskripsi relasi

## Maintainer

[Name] - [Contact]
```

## README - Project Root

```markdown
# [Project Name]

Satu paragraf penjelasan project

## Quick Start

\`\`\`bash
# Clone repository
git clone [repo-url]
cd [project-name]

# Install dependencies
composer install
yarn install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start development
composer dev
\`\`\`

## Requirements

- PHP >= [version]
- Node.js >= [version]
- PostgreSQL >= [version]

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | [Tech] |
| Frontend | [Tech] |
| Database | [Tech] |

## Development

### Menjalankan Aplikasi

\`\`\`bash
composer dev
\`\`\`

### Menjalankan Tests

\`\`\`bash
php artisan test
\`\`\`

### Code Style

\`\`\`bash
# PHP
vendor/bin/pint

# JavaScript/TypeScript
yarn lint
\`\`\`

## Documentation

- [Architecture](docs/architecture.md)
- [Database Schema](docs/database.md)
- [API Reference](docs/api.md)

## Contributing

1. Buat feature branch dari `main`
2. Implement changes dengan tests
3. Submit pull request

## Maintainers

- [Name] - [Contact]
```

## Architecture Documentation

```markdown
# System Architecture

## Overview

Diagram atau penjelasan high-level system

## Tech Stack

| Layer | Technology | Version | Tujuan |
|-------|------------|---------|---------|
| Backend | [Tech] | [Ver] | Tujuan |
| Frontend | [Tech] | [Ver] | Tujuan |

## Application Layers

### [Layer Name]

**Purpose:** Apa yang dilakukan layer ini

**Components:**
- [Component]: Responsibility
- [Component]: Responsibility

**Dependencies:**
- [Dependency]

### [Layer Name]

...

## Data Flow

Penjelasan atau diagram bagaimana data mengalir dalam system

## Key Patterns

### [Pattern Name]

Penjelasan pattern dan kapan digunakan

\`\`\`
[Code example jika perlu]
\`\`\`

## Security

- Security measure 1
- Security measure 2

## Performance Considerations

- Optimization 1
- Optimization 2
```

## Inline Comment Patterns

```php
// ============================================================
// SINGLE LINE - untuk penjelasan singkat
// ============================================================

// Validasi untuk mencegah [masalah spesifik]
if ($condition) { ... }


// ============================================================
// MULTI-LINE - untuk penjelasan yang lebih detail
// ============================================================

/*
 * Penjelasan konteks
 * yang mencakup: [detail 1], [detail 2].
 *
 * Dengan demikian, kesimpulan/behavior yang diharapkan.
 */


// ============================================================
// TODO dengan context
// ============================================================

// TODO: Apa yang perlu dilakukan
// Context: Mengapa perlu dilakukan
// Issue: #[issue-number] (jika ada)


// ============================================================
// WARNING/CAUTION
// ============================================================

// CAUTION: Apa yang perlu diperhatikan
// Mengubah logic ini dapat menyebabkan [potential issue]


// ============================================================
// SECTION DIVIDER (untuk file panjang)
// ============================================================

/*
|--------------------------------------------------------------------------
| [Section Name]
|--------------------------------------------------------------------------
*/
```
