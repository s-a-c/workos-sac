# Chinook Documentation Style Guide

## Overview

This style guide ensures consistency, accessibility, and maintainability across all Chinook documentation. Following these guidelines helps create professional, user-friendly documentation that meets WCAG 2.1 AA accessibility standards.

## Table of Contents

- [Overview](#overview)
- [File Organization](#file-organization)
- [Markdown Standards](#markdown-standards)
- [Heading Structure](#heading-structure)
- [Link Formatting](#link-formatting)
- [Code Examples](#code-examples)
- [Accessibility Guidelines](#accessibility-guidelines)
- [Content Standards](#content-standards)
- [Laravel-Specific Guidelines](#laravel-specific-guidelines)
- [Quality Assurance](#quality-assurance)

## File Organization

### Directory Structure

```
.ai/guides/chinook/
├── 000-chinook-index.md           # Main index
├── 010-chinook-models-guide.md    # Core guides (010-070)
├── filament/                      # Filament-specific docs
│   ├── setup/                     # Setup and configuration
│   ├── resources/                 # Resource development
│   ├── features/                  # Advanced features
│   ├── testing/                   # Testing strategies
│   └── deployment/                # Production deployment
├── packages/                      # Package integration guides
├── frontend/                      # Frontend architecture
└── testing/                       # Testing documentation
```

### File Naming Conventions

- **Use kebab-case:** `laravel-data-guide.md`
- **Include numbers for ordering:** `010-setup-guide.md`
- **Be descriptive:** `050-chinook-advanced-features-guide.md`
- **Avoid abbreviations:** Use `authentication` not `auth`

### Index Files

Every directory must contain:
- `000-index.md` or `README.md` - Directory overview
- Comprehensive table of contents
- Navigation links to parent/child sections

## Markdown Standards

### Document Structure

```markdown
# Document Title

## Table of Contents

- [Overview](#overview)
- [Section 1](#section-1)
  - [Subsection 1.1](#subsection-11)
- [Navigation](#navigation)

## Overview

Brief description of the document's purpose and scope.

## Section 1

Content here...

### Subsection 1.1

Detailed content...

## Navigation

**← Previous:** [Previous Guide](previous-guide.md)

**Next →** [Next Guide](next-guide.md)
```

### Required Sections

Every guide must include:
1. **Title** (H1)
2. **Table of Contents**
3. **Overview** section
4. **Navigation** section (at the end)

## Heading Structure

### Hierarchy Rules

- **H1 (#):** Document title only (one per document)
- **H2 (##):** Major sections
- **H3 (###):** Subsections
- **H4 (####):** Sub-subsections (use sparingly)

### Heading Format

```markdown
# Document Title

## Major Section

### Subsection

#### Sub-subsection (if needed)
```

### Anchor Link Generation

Headings automatically generate anchor links:
- `## Installation & Setup` → `#installation--setup`
- `### 1.1. Package Installation` → `#11-package-installation`
- `### RBAC Authentication` → `#rbac-authentication`

**Rules:**
- Convert to lowercase
- Replace spaces with hyphens
- Remove special characters except hyphens
- Numbers and periods become hyphens

## Link Formatting

### Internal Links

```markdown
<!-- Relative links within same directory -->
[Models Guide](010-chinook-models-guide.md)

<!-- Links to other directories -->
[Filament Setup](filament/setup/000-index.md)

<!-- Anchor links within same document -->
[Installation Section](#installation--setup)

<!-- Anchor links to other documents -->
[Model Testing](testing/020-unit-testing-guide.md#model-testing)
```

### External Links

```markdown
<!-- External links with descriptive text -->
[Laravel Documentation](https://laravel.com/docs)

<!-- Open in new tab for external resources -->
[Spatie Media Library](https://spatie.be/docs/laravel-medialibrary) (external)
```

### Navigation Links

```markdown
## Navigation

**← Previous:** [Previous Guide](previous-guide.md)

**Next →** [Next Guide](next-guide.md)
```

## Code Examples

### PHP Code Blocks

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserStamps;

class Artist extends Model
{
    use HasUserStamps;

    protected $fillable = [
        'name',
        'bio',
        'formed_year',
    ];

    protected function cast(): array
    {
        return [
            'formed_year' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
```

### Configuration Examples

```bash
# Installation commands
composer require spatie/laravel-data

# Environment configuration
php artisan vendor:publish --tag=data-config
```

### Code Standards

- **Always include opening PHP tags:** `<?php`
- **Use Laravel 12 syntax:** `cast()` method, not `$casts` property
- **Include proper namespaces**
- **Add type hints and return types**
- **Follow PSR-12 coding standards**

## Accessibility Guidelines

### WCAG 2.1 AA Compliance

#### Color and Contrast

- **High contrast colors only:** Use approved palette
  - Primary: `#1976d2` (7.04:1 contrast ratio)
  - Success: `#388e3c` (6.74:1 contrast ratio)
  - Warning: `#f57c00` (4.52:1 contrast ratio)
  - Error: `#d32f2f` (5.25:1 contrast ratio)

#### Heading Structure

- **Logical hierarchy:** Don't skip heading levels
- **Descriptive headings:** Clear, meaningful titles
- **Consistent formatting:** Follow established patterns

#### Link Accessibility

```markdown
<!-- Good: Descriptive link text -->
[Install Laravel Data package](packages/060-laravel-data-guide.md)

<!-- Avoid: Generic link text -->
[Click here](packages/060-laravel-data-guide.md)
```

#### Alternative Text

```markdown
<!-- Images with descriptive alt text -->
![Database schema diagram showing relationships between Artist, Album, and Track models](diagrams/database-schema.png)
```

## Content Standards

### Writing Style

- **Clear and concise:** Use simple, direct language
- **Active voice:** "Configure the database" not "The database should be configured"
- **Present tense:** "The system validates" not "The system will validate"
- **Consistent terminology:** Use the same terms throughout

### Code Comments

```php
<?php

/**
 * Artist model with hierarchical category support
 */
class Artist extends Model
{
    /**
     * Get all categories for this artist
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'categorizables')
            ->where('categorizable_type', self::class);
    }
}
```

### Examples and Scenarios

- **Real-world examples:** Use Chinook music store context
- **Complete examples:** Show full implementation, not fragments
- **Error handling:** Include error scenarios and solutions
- **Security considerations:** Highlight security implications

## Laravel-Specific Guidelines

### Modern Laravel Patterns

```php
// ✅ Laravel 12 - Use casts() method
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}

// ❌ Legacy - Avoid $casts property
protected $casts = [
    'email_verified_at' => 'datetime',
    'is_active' => 'boolean',
];
```

### Required Traits

Document all required traits:
- `HasUserStamps` - User creation/update tracking
- `HasSecondaryUniqueKey` - Public ID management
- `HasSlug` - URL-friendly slugs
- `SoftDeletes` - Soft deletion support

### Relationship Documentation

```php
/**
 * Artist belongs to many categories (polymorphic)
 */
public function categories(): BelongsToMany
{
    return $this->belongsToMany(Category::class, 'categorizables')
        ->where('categorizable_type', self::class);
}
```

## Quality Assurance

### Pre-Publication Checklist

- [ ] **Structure:** Proper heading hierarchy
- [ ] **Links:** All internal links working
- [ ] **Code:** Laravel 12 syntax used
- [ ] **Accessibility:** WCAG 2.1 AA compliant
- [ ] **Navigation:** Previous/Next links included
- [ ] **TOC:** Table of contents accurate
- [ ] **Examples:** Complete, working code examples
- [ ] **Spelling:** No spelling or grammar errors

### Link Validation

Use the automated validation system:

```bash
# Run link validation
python .ai/tools/automated_link_validation.py --base-dir .ai/guides/chinook

# Check specific file
python .ai/tools/chinook_link_integrity_audit.py --file specific-guide.md
```

### Content Review Process

1. **Technical Review:** Verify code examples work
2. **Accessibility Review:** Check WCAG compliance
3. **Link Review:** Validate all links function
4. **Style Review:** Ensure style guide compliance
5. **User Testing:** Test with actual users

## Maintenance Guidelines

### Regular Updates

- **Monthly:** Run comprehensive link validation
- **Quarterly:** Review and update code examples
- **Annually:** Full accessibility audit

### Version Control

- **Meaningful commits:** Clear commit messages
- **Branch naming:** `docs/feature-name` or `docs/fix-broken-links`
- **Pull requests:** Required for all changes
- **Review process:** At least one reviewer required

### Documentation Debt

Track and address:
- Outdated code examples
- Broken external links
- Missing sections
- Accessibility issues
- Inconsistent formatting

---

## Navigation

**← Previous:** [Main Documentation Index](000-chinook-index.md)

**Next →** [Quality Assurance Guide](quality/documentation-quality-validation.md)
