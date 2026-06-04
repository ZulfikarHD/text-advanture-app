---
name: code-documentation
description: Write professional code documentation following Laravel and Vue.js standards. Covers PHPDoc/JSDoc with @param/@return tags, HTML section comments for navigation, inline WHY comments, and flow markers for complex logic. Use when creating classes/services/components, documenting APIs, adding navigation comments, reviewing code quality, or when user mentions documentation, PHPDoc, JSDoc, comments, or asks to document code.
---

# Code Documentation Standards

Documentation lives WITH the code, not separate from it.

## When to Use This Skill

Apply immediately when:
- **Creating** public methods, classes, services, or components
- **Writing** complex multi-step business logic
- **Structuring** large HTML/Vue templates needing navigation
- **Reviewing** code for documentation quality
- **Building** feature modules with 5+ files
- User says: "document this", "add comments", "write PHPDoc", "create README"

## Documentation Hierarchy

Follow this priority order:

1. **Self-documenting code** - Clear naming, small focused functions
2. **Type systems** - TypeScript interfaces, PHP type hints
3. **Public API docs** - PHPDoc/JSDoc with @param/@return (documents WHAT)
4. **Section comments** - HTML/template navigation markers
5. **Inline WHY comments** - Explain non-obvious business logic

## Core Principles

**For Public APIs (methods/functions):**
- ✅ Document WHAT: parameters, returns, exceptions
- ✅ Use PHPDoc/JSDoc with proper tags
- ✅ Include one-line description of what it does

**For Code Inside Functions:**
- ✅ Document WHY: business reasoning, non-obvious decisions
- ❌ Don't document WHAT: obvious operations already clear from code

**For Templates/HTML:**
- ✅ Use section comments for navigation in large files
- ✅ Mark major sections (Header, Form, Footer)
- ❌ Don't comment every obvious element

## Quick Reference Decision Tree

```
Creating code?
│
├─► Public method/function     → PHPDoc/JSDoc with @param/@return (ALWAYS)
├─► Complex WHY logic          → Inline WHY comment (ALWAYS)
├─► Multi-step flow (5+ steps) → Step 1, Step 2 markers (RECOMMENDED)
├─► Large template (100+ lines)→ HTML section comments (RECOMMENDED)
├─► New class/service          → Class-level docblock (ALWAYS)
├─► New Vue component          → Component JSDoc (ALWAYS)
│
├─► New feature folder?
│   ├─► 5+ files               → Create README.md
│   └─► < 5 files              → Skip README
│
└─► Architecture changed?      → Update docs/architecture.md
```

## Never Create These

| Don't Create | Why |
|--------------|-----|
| `docs/features/x/` | Becomes outdated, use inline docs |
| `docs/services/x.md` | Document in the service file itself |
| `docs/sprint-N/` | Dead documentation after sprint ends |
| Separate API docs | Only for external API consumers |

---

## Documentation Types

### 1. Public API Documentation (PHPDoc/JSDoc)

Document WHAT for all public methods:

```php
/**
 * Store label inspeksi with progressive printing support.
 *
 * Allows operators to print labels in batches as needed for QC,
 * tracking each batch to prevent duplicates or missed labels.
 *
 * @param Request $request Validated label data and PO number
 * @return JsonResponse Success with remaining labels or validation errors
 * @throws ValidationException When label count exceeds available
 */
public function store(Request $request): JsonResponse
```

**Required elements:**
- One-line description of what it does
- `@param` for each parameter (type + description)
- `@return` for return value (type + description)
- `@throws` if exceptions are thrown

### 2. Inline WHY Comments

Inside function bodies, explain WHY for non-obvious logic:

```php
// ✅ GOOD - Explains WHY
// Progressive printing requires knowing already-printed count
// to prevent duplicate rim numbers across multiple sessions
$lastRim = LabelControl::where('production_order_id', $id)->max('nomor_rim');

// ❌ BAD - Explains WHAT (obvious from code)
// Get the last rim number from database
$lastRim = LabelControl::where('production_order_id', $id)->max('nomor_rim');
```

### 3. Flow Markers for Complex Logic

Use numbered steps for complex multi-step processes (5+ steps):

```php
public function store(Request $request)
{
    // Step 1: Validate and check existing PO
    $existingPo = ProductionOrder::where('nomor_po', $request->po_number)->first();
    
    if (!$existingPo) {
        // Step 2: Register new Production Order
        $this->registerPoController->store($request);
    }
    
    // Step 3: Calculate remaining labels for progressive printing
    // WHY: Prevent printing more labels than available in order
    $total_labels = $existingPo->jumlah_rim;
    $sum_created = LabelControl::where('production_order_id', $id)->count();
    $remaining = $total_labels - $sum_created;
    
    // Step 4: Validate quantity doesn't exceed remaining
    // Step 5: Create label control records
    // Step 6: Update production order status
}
```

**When to use flow markers:**
- ✅ Complex processes with 5+ distinct steps
- ✅ Steps need to happen in specific order
- ❌ Simple 2-3 step operations (self-documenting)

### 4. HTML Section Comments

Use for navigation in large templates (100+ lines):

```vue
<template>
    <AppLayout>
        <!-- Header Section -->
        <motion.div>
            <h1>Print Label Inspeksi</h1>
            <p>Scan PO untuk input label inspeksi.</p>
        </motion.div>

        <!-- Form Card -->
        <Card>
            <!-- PO Number Input (scan target) -->
            <Input ref="poInputRef" />
            
            <!-- Specifications Badges -->
            <div class="badges">...</div>
            
            <!-- Team Selection -->
            <Select v-model="form.team" />
            
            <!-- Print Action Buttons -->
            <CardFooter>
                <Button @click="clearForm">Bersihkan</Button>
                <Button @click="submit">Simpan & Cetak</Button>
            </CardFooter>
        </Card>
    </AppLayout>
</template>
```

**Best practices:**
- ✅ Mark major sections (Header, Form, Footer, Navigation)
- ✅ Use for templates over 100 lines
- ✅ Place before the section they describe
- ❌ Don't comment every element

### 5. Class/Service Docblock

```php
/*
|--------------------------------------------------------------------------
| Order Service
|--------------------------------------------------------------------------
|
| Handles Order business logic: CRUD operations, status transitions,
| and production metrics calculations.
|
*/
class OrderService { }
```

### 6. Vue Component JSDoc

```vue
<script setup lang="ts">
/**
 * OrderTable - Data table for production orders.
 *
 * Features: virtual scrolling, sorting, filtering, row selection.
 */

interface Props {
  /** Orders to display */
  orders: Order[]
  /** Loading state for skeleton UI */
  loading?: boolean
}
</script>
```

### 7. TypeScript Types

```typescript
/**
 * Order entity - main production tracking unit.
 */
interface Order {
  /** PO number - unique business identifier */
  po_number: number
  /** Status in production pipeline */
  status: OrderStatus
}
```

---

## Module README (Only if 5+ files)

Create README.md only for complex modules:

```markdown
# Orders Module

Production order management module.

## Files

| File | Purpose |
|------|---------|
| `Index.vue` | List with filters and pagination |
| `Show.vue` | Single order detail view |
| `Partials/` | Feature-specific components |
```

---

## Completion Checklist

After creating/updating code:

```
[ ] All public methods have PHPDoc/JSDoc with @param/@return
[ ] Class/service has class-level docblock
[ ] Complex WHY logic has inline comments
[ ] Multi-step flows (5+ steps) have step markers
[ ] Large templates (100+ lines) have section comments
[ ] Vue components have component JSDoc
[ ] TypeScript interfaces have property descriptions
[ ] 5+ files in module? → Add README.md
[ ] Architecture changed? → Update docs/architecture.md
```

No separate feature documentation needed.

---

## Common Mistakes

| Avoid | Better |
|-------|--------|
| Missing @param/@return in public methods | Always include full PHPDoc for public APIs |
| `// Get user from database` (obvious WHAT) | No comment or explain WHY if non-obvious |
| `// fix bug` | `// WHY: Prevent negative qty (bug #123)` |
| `// TODO: fix this` | `// TODO: Handle edge case X when Y` |
| No section comments in 500+ line templates | Add navigation markers for major sections |
| Commenting every HTML element | Only mark major sections |
| Outdated docblocks | Keep docblocks synced with code |

---

## Quick Templates

### PHPDoc (Laravel Controller Method)

```php
/**
 * [What this method does - one line].
 *
 * [Optional: 1-2 sentences about business context or special behavior]
 *
 * @param Request $request [What this param contains]
 * @return JsonResponse [What this returns]
 * @throws ValidationException [When this is thrown]
 */
public function methodName(Request $request): JsonResponse
```

### JSDoc (Vue Composable)

```typescript
/**
 * [What this function does - one line].
 *
 * [Optional: Usage context or special behavior]
 *
 * @param name - [What this param is for]
 * @returns [What gets returned]
 * @example
 * const result = functionName('value')
 */
export function functionName(name: string): ReturnType
```

### HTML Section Comment

```html
<!-- Major Section Name -->
<div>
    <!-- Subsection (only if complex) -->
    <nested-content />
</div>
```

---

## Additional Resources

**Need detailed examples?** See [examples.md](examples.md) for complete Laravel Service, Model, Controller, Vue Component, and TypeScript examples.

**Need copy-paste templates?** See [templates.md](templates.md) for ready-to-use templates for all documentation types.

---

## Quality Criteria

**Good Public API Documentation:**
- ✅ Describes WHAT: clear params, returns, exceptions
- ✅ Complete @param/@return tags with types
- ✅ Includes business context when helpful
- ✅ Stays synced with code changes

**Good Inline Comments:**
- ✅ Explains WHY, not WHAT
- ✅ Clarifies non-obvious business logic
- ✅ Warns about critical constraints
- ✅ Documents workarounds with reasoning

**Good Section Comments:**
- ✅ Helps navigate large files quickly
- ✅ Marks major sections only
- ✅ Consistent naming convention

**Poor Documentation:**
- ❌ Missing @param/@return in public methods
- ❌ Narrates obvious code operations
- ❌ Uses vague terms ("handles stuff")
- ❌ Out of sync with implementation
- ❌ Comments every single line
