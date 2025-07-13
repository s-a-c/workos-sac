# 2. Documentation Standards
# 2. Documentation Standards

## 2.1. Core Communication Principle

**All documents and responses should be clear, actionable, and suitable for a junior developer to understand and implement.**

This principle applies to all documentation, code comments, commit messages, pull request descriptions, and any other written communication within the project.

### 2.1.1. Guidelines for Junior Developer-Focused Documentation

- **Use clear, simple language** - Avoid complex sentence structures and jargon
- **Be explicit** - Don't assume prior knowledge of the codebase or technologies
- **Provide examples** - Include concrete examples for abstract concepts
- **Define terms** - Explain technical terms and acronyms on first use
- **Break down complexity** - Divide complex concepts into simpler parts
- **Include context** - Explain the 'why' behind decisions, not just the 'what'
- **Use visuals** - Diagrams and screenshots can clarify complex relationships
- **Link to resources** - Provide references to further learning materials

## 2.2. Documentation Structure

### 2.2.1. File Organization

- Store documentation in appropriate directories based on purpose
- Use consistent naming conventions
- Maintain a clear hierarchy with main index documents

### 2.2.2. Document Types

- **README.md** - Project overview and quick start guide
- **CONTRIBUTING.md** - Guidelines for contributing to the project
- **SECURITY.md** - Security policies and procedures
- **LICENSE.md** - License information
- **CHANGELOG.md** - Version history and changes
- **docs/** - Detailed documentation organized by topic

## 2.3. Content Guidelines

### 2.3.1. Formatting Standards

- Use Markdown for all documentation
- Follow consistent heading structure (H1 for title, H2 for sections, etc.)
- Use lists for steps or options
- Use code blocks with appropriate language specification
- Use emphasis (bold, italic) purposefully
- Keep line length under 100 characters for better readability

### 2.3.2. Writing Style

- Write in present tense
- Use active voice
- Be concise but complete
- Avoid ambiguity
- Use imperative mood for instructions
- Maintain consistent terminology

## 2.4. Code Documentation

### 2.4.1. Comments

- Document 'why' rather than 'what' (code should be self-explanatory)
- Add comments for complex logic
- Use DocBlocks for classes, methods, and functions
- Keep comments up-to-date with code changes
- Include examples for non-obvious usage

### 2.4.2. Type Annotations and Attributes

- Use PHP 8 attributes instead of PHPDoc annotations where applicable
- Include type declarations for parameters and return types
- Document exceptions that may be thrown
- Document breaking changes in methods

## 2.5. API Documentation

### 2.5.1. Endpoint Documentation

- Document all API endpoints with:  
  - URL and method
  - Request parameters
  - Request body structure
  - Response format
  - Authentication requirements
  - Example requests and responses
  - Error handling

### 2.5.2. Schema Documentation

- Document data models
- Include field descriptions
- Note required fields
- Document validation rules
- Include relationships between models

## 2.6. Maintenance

### 2.6.1. Review Process

- Regularly review documentation for accuracy
- Update documentation when code changes
- Remove outdated documentation
- Encourage feedback on documentation clarity

### 2.6.2. Version Control

- Include documentation changes in the same PR as code changes
- Maintain changelog entries for significant documentation updates
- Use version tags to mark documentation for specific releases
## 2.1. Structure and Organization

### 2.1.1. Hierarchical Numbering

- Number all headings sequentially (1, 1.1, 1.1.1, etc.)
- Precede and succeed all headings with blank lines
- Apply consistently across all documentation types
- All markdown files, except where the basename is UPPERCASE, must have a TOC
- Where a document is split into multiple parts:
  - The same, complete TOC should be included in each part, with an indication of which part is "current"
  - Heading numbering should be consistent, contiguous and continuous across all parts of the document

### 2.1.2. Multi-Document Projects

When multiple documents are required:

- Create `000-index.md` within each folder
  - The sequence of entries should be logically consistent
- Use consistent 3-digit prefix numbering system for all documentation files
- Ensure consistency with the sequence of entries in `000-index.md`
- Exception: files with uppercase basenames OR in folders with non-hyphenated names

**Standard Documentation File Naming:**

- **3-digit multiples of 10**
- **Starting at 010-**
- **Incrementing by 10** (010, 020, 030, 040, 050, etc.)
- **Prefix unique amongst sibling files/folders**, **EXCEPT**:
  - Multi-part documents where the same 3-digit prefix is required and a second 3-digit prefix is appended to the first
  - The second prefix follows the same rules as the first:
    - 3-digit multiples of 10
    - Starting at 010-
    - Incrementing by 10 (010, 020, 030, 040, 050, etc.)

**Examples:**

- Single documents: `010-introduction.md`, `020-setup.md`, `030-configuration.md`
- Multi-part documents: `010-010-part-one.md`, `010-020-part-two.md`, `010-030-part-three.md`

### 2.1.3. File and Folder Naming

- Use kebab-case for file names
- Exclude special characters
- Use descriptive names with file extensions
- Maintain consistent naming conventions
- Follow standard documentation file naming (section 2.1.2) for numbered documents

### 2.1.4. Exercise Organization

- Include exercise sections with questions and practical exercises
- Organize exercises in dedicated `888-exercises` folder
- Organize answers in `888-sample-answers` folder
- Ensure consistency between exercise files and sample answer files

### 2.1.5. Document Navigation

**All guideline documents must include navigation footers to enhance user experience and provide logical progression through documentation.**

#### 2.1.5.1. Navigation Requirements

**Mandatory Navigation Footer:**
- All guideline documents must include a navigation section at the very end
- Place navigation after any existing "See Also" sections
- Use horizontal rule (`---`) to separate navigation from main content
- Use `## Navigation` as the section header

**Navigation Format:**
```markdown
---

## Navigation

**← Previous:** [Document Title](relative-path.md)

**Next →** [Document Title](relative-path.md)
```

#### 2.1.5.2. Navigation Sequence

**Logical Document Progression:**
Follow this exact sequence for guideline documents:
1. [Project Overview](010-project-overview.md)
2. [Documentation Standards](020-documentation-standards.md)
3. [Development Standards](030-development-standards.md)
4. [Workflow Guidelines](040-workflow-guidelines.md)
5. [Testing Standards](050-testing-standards.md)
6. [Testing Guidelines](060-testing/000-index.md)
7. [Security Standards](090-security-standards.md)
8. [Performance Standards](100-performance-standards.md)

#### 2.1.5.3. Link Formatting Standards

**Link Text Requirements:**
- Use descriptive link text with document titles
- Include arrow symbols (← →) for visual clarity
- Use relative links (e.g., `[← Previous: Project Overview](010-project-overview.md)`)
- Maintain consistent spacing and alignment

**Conditional Navigation:**
- **First document (010)**: Only include "Next →" link
- **Last document (100)**: Only include "← Previous" link
- **Middle documents**: Include both "← Previous" and "Next →" links

**Example Navigation Patterns:**

*First Document:*
```markdown
---

## Navigation

**Next →** [Documentation Standards](020-documentation-standards.md)
```

*Middle Document:*
```markdown
---

## Navigation

**← Previous:** [Documentation Standards](020-documentation-standards.md)

**Next →** [Workflow Guidelines](040-workflow-guidelines.md)
```

*Last Document:*
```markdown
---

## Navigation

**← Previous:** [Security Standards](090-security-standards.md)
```

#### 2.1.5.4. Accessibility Compliance

**Navigation Accessibility Requirements:**
- Ensure all navigation links are functional and point to correct documents
- Use descriptive link text that clearly indicates destination
- Maintain sufficient color contrast for link text (minimum 4.5:1 ratio)
- Test navigation enhances junior developer experience
- Verify navigation sequence follows logical learning progression

**Quality Assurance Checklist:**
- [ ] Navigation section placed at document end
- [ ] Horizontal rule separates navigation from content
- [ ] Consistent `## Navigation` header used
- [ ] Arrow symbols (← →) included for visual clarity
- [ ] Descriptive link text with document titles
- [ ] Relative links used throughout
- [ ] Links tested and functional
- [ ] Sequence follows logical progression
- [ ] Accessibility standards met

## 2.2. Content Formatting

### 2.2.1. Code Blocks

- Format with explicit language specifications
- Use proper code fence syntax (e.g., `~~~python`, `~~~javascript`, `~~~html`)
- Enclose HTML snippets in code fences with 'html' specified

### 2.2.2. Markdown Links

- Use proper markdown syntax: `[link text](https://example.com)`
- Ensure all links are valid and accessible
- Avoid light gray colors on light backgrounds

### 2.2.3. Markdown Lists

- Avoid 4+ space indentation (prevents code block rendering that disables links)
- Use standard indentation (dash/asterisk + single space, or two spaces for nested)
- Surround lists with blank lines (MD032 compliance)
- Add spacing between list items (margin-bottom: 5px)

## 2.3. Visual Design and Accessibility

### 2.3.1. Color Contrast Requirements

- Maintain 4.5:1 contrast ratio for normal text (WCAG AA)
- Maintain 3:1 contrast ratio for large text (18pt+)
- Verify with WebAIM Contrast Checker

**Contrast Validation Protocol:**

- **Before publication:** Check all text/background combinations
- **Systematic review:** Validate colored containers, code blocks, tables, and highlighted text
- **Tools:** Use browser developer tools or online contrast checkers
- **Documentation:** Record contrast ratios for reusable color schemes

**Common Contrast Violations to Avoid:**

- Light gray text (`#aaa`, `#ccc`, `#999`) on light backgrounds
- Default syntax highlighting in colored containers
- Insufficient contrast in tables and emphasized text
- Poor contrast in interactive elements (buttons, links)

### 2.3.2. Text Color Standards

- Primary text: `#111` (not `#333`)
- Secondary text: `#444` (not `#7f8c8d`)
- Headings: `#222`
- Never use light gray (`#aaa`, `#ccc`) on light backgrounds

### 2.3.3. Category Color Coding

- Documentation Index: `#0066cc` (blue)
- Cross-Document Navigation: `#007700` (green)
- Diagram Accessibility: `#cc7700` (orange)
- Date and Version Formatting: `#6600cc` (purple)
- Implementation Planning: `#222` (dark gray)
- Success indicators: `#007700` (green)
- Warning indicators: `#cc7700` (orange)
- Error indicators: `#cc0000` (red)

### 2.3.4. Background Colors

- Main background: `#f0f0f0`
- Container backgrounds: `#fff` with border
- Category backgrounds: `#e0e8f0` (blue), `#e0f0e0` (green), `#f0e8d0` (orange), `#e8e0f0` (purple)
- Table headers: `#d9d9d9`
- Add 1px solid border to all containers (`#d0d0d0` or category-specific)

### 2.3.5. Typography Standards

**Font Weights:**

- Bold (`font-weight: bold`) for headings and important text
- Medium weight (`font-weight: 500`) for secondary text
- Avoid normal weight for small text

**Text Sizing:**

- Minimum body text: 14px
- Minimum secondary text: 12px

### 2.3.6. Text Enhancement

- Use background highlighting for emphasis in lists
- Add padding around emphasized text (3px 6px)
- Use borders to define boundaries

### 2.3.7. Code Block Contrast Standards

**Critical Accessibility Issue:** Code blocks within colored containers inherit parent text colors, creating severe accessibility violations.

**Problem Identification:**

- Default syntax highlighting uses light colors that fail contrast requirements on colored backgrounds
- PHP code blocks in colored divs become unreadable for users with visual impairments
- Standard markdown code blocks don't override parent container text colors

**Required Solution - Dark Code Block Containers:**

**Mandatory Contrast Standards:**

- **Code Block Background:** `#1e1e1e` (VS Code dark theme)
- **Code Block Text:** `#d4d4d4` (light gray)
- **Contrast Ratio:** 21:1 (exceeds WCAG AAA requirements)
- **Font Family:** Monospace (`'Fira Code', 'Consolas', 'Monaco', monospace`)

**Implementation Requirements:**

- **Always wrap code blocks** in dark containers when placed within colored divs
- **Never rely** on inherited text colors for code blocks
- **Test all code blocks** for contrast compliance
- **Use consistent styling** across all code examples

## 2.4. Visual Learning Aids

### 2.4.1. Mermaid Diagram Standards

- Create extensive explanations with colorful Mermaid diagrams
- Use high contrast for legibility
- Include visual learning aids with consistent diagram styles

**High Contrast Requirements for Colored Diagrams:**

All colored diagrams must meet WCAG AA accessibility standards with minimum contrast ratios:

- **Text on colored backgrounds:** 4.5:1 contrast ratio minimum
- **Large text (18pt+) on colored backgrounds:** 3:1 contrast ratio minimum
- **Interactive elements:** 3:1 contrast ratio for focus indicators
- **Graphical elements:** 3:1 contrast ratio against adjacent colors

**Mandatory Color Palette for Diagrams:**

Use only the following high-contrast color combinations:

**Primary Colors (with white text):**
- **Blue:** `#1976d2` (background) with `#ffffff` (text) - Contrast: 5.74:1
- **Purple:** `#7b1fa2` (background) with `#ffffff` (text) - Contrast: 8.59:1
- **Green:** `#388e3c` (background) with `#ffffff` (text) - Contrast: 5.95:1
- **Orange:** `#f57c00` (background) with `#ffffff` (text) - Contrast: 4.52:1
- **Red:** `#d32f2f` (background) with `#ffffff` (text) - Contrast: 5.74:1

**Secondary Colors (with dark text):**
- **Light Blue:** `#e3f2fd` (background) with `#0d47a1` (text) - Contrast: 12.63:1
- **Light Purple:** `#f3e5f5` (background) with `#4a148c` (text) - Contrast: 13.15:1
- **Light Green:** `#e8f5e8` (background) with `#1b5e20` (text) - Contrast: 12.04:1
- **Light Orange:** `#fff3e0` (background) with `#e65100` (text) - Contrast: 7.26:1
- **Light Red:** `#ffebee` (background) with `#b71c1c` (text) - Contrast: 11.24:1

**Prohibited Color Combinations:**

Never use these low-contrast combinations:
- Light gray text (`#aaa`, `#ccc`, `#999`) on light backgrounds
- Yellow backgrounds with white text
- Light blue backgrounds with white text
- Pastel colors without sufficient contrast validation

**Mermaid Theme Configuration:**

- Use high-contrast theme variables
- Set `primaryColor: '#1976d2'` (replaces previous `#0066cc`)
- Set `fontFamily: 'Arial, sans-serif'`
- Set `fontSize: '16px'`
- Use white text on colored backgrounds
- Add borders to all elements for better definition

**Diagram-Specific Color Standards:**

**Graph Diagrams (flowcharts):**
- Use `classDef` to define high-contrast color classes
- Always specify both background and text colors
- Add stroke borders for better definition
- Example format: `classDef primary fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff`

**State Diagrams:**
- Rely on default Mermaid colors (already high contrast)
- Add custom styling only when necessary
- Ensure state labels remain readable

**Sequence Diagrams:**
- Use actor colors sparingly
- Maintain default styling for optimal readability
- Add background colors only with proper contrast validation

**Gantt Charts:**
- Use section colors from approved palette
- Ensure task labels have sufficient contrast
- Validate critical path highlighting

**Contrast Validation Protocol for Diagrams:**

1. **Pre-creation:** Select colors only from approved palette
2. **During creation:** Test color combinations using WebAIM Contrast Checker
3. **Post-creation:** Validate all text/background combinations in rendered diagram
4. **Documentation:** Record contrast ratios for custom color schemes
5. **Review:** Include accessibility check in diagram review process

**Testing Requirements:**

- Test diagrams at 200% zoom level for readability
- Verify colors work in both light and dark mode contexts
- Check printed versions maintain legibility
- Validate with color blindness simulation tools

**Practical Examples and Validation:**

For comprehensive examples of proper high-contrast diagram implementation and validation testing, see the [High Contrast Diagram Guidelines Test](diagram-contrast-test.md). This test document provides:

- **Working Examples**: Complete Mermaid diagrams using approved color palette
- **Contrast Validation**: Verified contrast ratios for all color combinations
- **Implementation Checklist**: Step-by-step validation process
- **Testing Results**: Proof of WCAG AA compliance for all examples

The test document serves as both a validation of these guidelines and a practical reference for implementing high-contrast diagrams correctly.

### 2.4.2. Mode Support

- Support both dark/light mode selection in documentation
- Organize documentation with complete structure and no empty folders
- Use PHP attributes rather than PHPDocs meta tags in documentation

### 2.4.3. Accessibility Testing Workflow

**Pre-Publication Checklist:**

1. **Contrast validation:** Verify all text/background combinations meet WCAG AA standards
2. **Code block review:** Ensure all code blocks have proper dark container wrapping
3. **Diagram accessibility:** Apply diagram contrast validation protocol (section 2.4.1) - see [practical examples](diagram-contrast-test.md)
4. **Visual hierarchy test:** Check heading structure and color coding consistency
5. **Responsive testing:** Verify readability at various zoom levels (200-400%)
6. **Print compatibility:** Ensure dark code blocks and diagrams remain legible when printed

## 2.5. Technical Accuracy

### 2.5.1. Command Verification

- Verify all commands against official documentation before inclusion
- Include direct links to official documentation for package-related commands
- Test commands or verify from official sources before documenting
- Prioritize technical accuracy over content reorganization
- Include troubleshooting sections for common command errors
- Document exact source of each command with references

### 2.5.2. Package Configuration

- Double-check tag names for publishing configurations and migrations
- Verify exact tag names required for publishing when documenting package configuration

### 2.5.3. Asset Management

- All assets used in project documentation should be stored in suitably-named folders/files within `docs/assets/` directory of the project root.

## 2.6. Content Validation

- Validate all content adheres to formatting rules
- Check for consistent numbering
- Verify code block specifications
- Test all markdown links
- Ensure technical accuracy throughout
- **Accessibility verification:** Apply systematic contrast testing workflow (section 2.4.3)
- **Code block compliance:** Ensure all code blocks in colored containers use dark wrapping (section 2.3.7)
- **Diagram compliance:** Ensure all colored diagrams follow high contrast standards (section 2.4.1)

## 2.7. Markdown Formatting Rules

### 2.7.1. Headings

- Use ATX style headings (use `#`)
- Increment heading levels by one at a time (MD001)
- Add single space after hash (MD018)
- Surround headings with blank lines (MD022)
- Start headings at beginning of line (MD023)
- Allow multiple headings with same content in different sections (MD024)
- Avoid trailing punctuation except `.`, `,`, `;`, `:`, `!` (MD026)
- First line should be top-level heading (MD041)

### 2.7.2. Lists

- Use dash (`-`) for unordered lists (MD004)
- Consistent indentation, 4 spaces for unordered lists (MD007)
- Use `ordered` prefixes for ordered lists (1., 2., 3.) (MD029)
- One space after list markers (MD030)
- Surround lists with blank lines (MD032)

### 2.7.3. Code Blocks

- Surround fenced code blocks with blank lines (MD031)
- Use fenced code blocks style (MD046)
- Use three backticks for code fence style (MD048)
- Specify language for fenced code blocks (MD040)

### 2.7.4. Links and Images

- Use correct link syntax `[link text](url)` (avoid MD011)
- Prefer proper markdown link syntax over bare URLs
- Avoid spaces inside link text (MD039)
- No empty links `[]()` (MD042)
- Allowed URI schemes: `http`, `https`, `ftp`, `mailto`, `tel`, `file`, `data`, `/`
- Images should have alternate text (MD045)
- Link fragments should be valid (MD051)
- Reference links must exist and be used (MD052, MD053)

### 2.7.5. Emphasis and Style

- Avoid spaces inside emphasis markers (MD037)
- Use asterisks (`*`) for emphasis/italics (MD049)
- Use double asterisks (`**`) for strong/bold (MD050)

### 2.7.6. Spacing and General Formatting

- Allow trailing spaces for line breaks (2 spaces = `<br>`) (MD009)
- No hard tabs, use spaces (MD010)
- Maximum one consecutive blank line (MD012)
- Avoid spaces inside inline code spans (MD038)
- Use `---` for horizontal rules (MD035)
- Files end with single newline (MD047)

### 2.7.7. HTML and Tables

- Specific HTML elements allowed: `div`, `span`, `a`, `img`, `strong`, `em`, `br`, `hr`, `table`, `thead`, `tbody`, `tr`, `th`, `td`, `details`, `summary`, `sup`, `sub`, `kbd`, `h1-h6`, `ul`, `ol`, `li`, `p`, `blockquote`, `pre`, `code`
- Add spaces around pipes for readability in tables (MD055)
- Consistent column count across rows in tables (MD056)

### 2.7.8. Blockquotes

- Avoid multiple spaces after blockquote symbol (`>`) (MD027)
- Blank lines inside blockquotes are allowed (MD028)

## See Also

### Related Guidelines
- **[Project Overview](010-project-overview.md)** - Understanding project structure for documentation context
- **[Development Standards](030-development-standards.md)** - Code documentation and comment standards
- **[Testing Standards](050-testing-standards.md)** - Test documentation requirements
- **[Workflow Guidelines](040-workflow-guidelines.md)** - Git commit message and PR documentation standards

### Documentation Decision Guide for Junior Developers

#### "I need to document a new feature - what type of documentation do I create?"
1. **API Endpoint**: Follow section 2.5.1 for endpoint documentation
2. **Code Function**: Use section 2.4.1 for code comments and DocBlocks
3. **User Guide**: Apply section 2.3 content guidelines with accessibility standards
4. **Technical Specification**: Use section 2.2 structure with hierarchical numbering

#### "I'm not sure about accessibility compliance - what do I check?"
1. **Color Contrast**: Use section 2.3.1 contrast requirements (4.5:1 ratio minimum)
2. **Code Blocks**: Follow section 2.3.7 for dark container wrapping
3. **Diagrams**: Apply section 2.4.1 high-contrast color standards
4. **Testing**: Use section 2.4.3 accessibility testing workflow

#### "I need to create visual diagrams - what are the requirements?"
- **Color Standards**: Use only approved palette from section 2.4.1
- **Contrast Validation**: Follow section 2.4.1 validation protocol
- **Mermaid Configuration**: Apply section 2.4.1 theme configuration
- **Testing**: Verify at 200% zoom and with color blindness simulation
- **Practical Examples**: Reference [High Contrast Diagram Test](diagram-contrast-test.md) for working examples

#### "I'm writing for junior developers - how do I ensure clarity?"
- **Language**: Follow section 2.1.1 guidelines (simple language, explicit explanations)
- **Examples**: Include concrete examples for abstract concepts (section 2.1.1)
- **Structure**: Use section 2.2 formatting standards with clear headings
- **Visual Aids**: Incorporate section 2.4 visual learning aids with high contrast

---

## Navigation

**← Previous:** [Project Overview](010-project-overview.md)

**Next →** [Development Standards](030-development-standards.md)
