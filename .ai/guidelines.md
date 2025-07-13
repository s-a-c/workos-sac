
# AI Assistant Project Guidelines

> **Note:** The project guidelines have been restructured into a more organized format.
> Please refer to the new guidelines in the `.ai/guidelines/` directory.

## Core Communication Principle

**All documents and responses should be clear, actionable, and suitable for a junior developer to understand and implement.**

## Guidelines Structure

The guidelines are now organized into the following files:

1. [Main Index](guidelines/000-index.md) - Overview and navigation of all guidelines
2. [Project Overview](guidelines/010-project-overview.md) - Core information about the project
3. [Documentation Standards](guidelines/020-documentation-standards.md) - Guidelines for documentation
4. [Development Standards](guidelines/030-development-standards.md) - Code style and architecture patterns
5. [Workflow Guidelines](guidelines/040-workflow-guidelines.md) - Git workflow and terminal management
6. [Testing Standards](guidelines/050-testing-standards.md) - Core testing requirements and practices
7. [Testing Guidelines](guidelines/060-testing/) - Comprehensive testing documentation and resources

## Purpose

These guidelines serve two main purposes:

1. To provide comprehensive information about the project structure, architecture, and development standards
2. To establish consistent formatting, behavior, and workflow standards for the AI Assistant when working with the codebase

By following these guidelines, you'll ensure that your contributions maintain the project's high standards for code quality, performance, and user experience.

## Documentation Remediation Methodologies

### TOC-Heading Synchronization Methodology

**Note:** A comprehensive TOC-heading synchronization methodology has been developed and validated through the Chinook documentation remediation project, achieving 100% link integrity across 180+ files and 3,400+ links.

#### Key Components

1. **GitHub Anchor Generation Algorithm** - Proven algorithm for converting headings to GitHub-compatible anchor links
2. **Systematic Remediation Process** - Step-by-step approach for fixing broken anchor links
3. **Validation Framework** - Tools and procedures for verifying link integrity
4. **Best Practices** - Guidelines for maintaining documentation quality during updates

For detailed implementation guidance, see [TOC-Heading Synchronization Guide](guidelines/070-toc-heading-synchronization.md).

#### Quick Reference

**GitHub Anchor Generation Rules:**

- Convert to lowercase
- Replace spaces with hyphens (-)
- Remove periods (.)
- Convert ampersands to double hyphens (& → --)
- Remove special characters except hyphens and alphanumeric
- Preserve numbers and letters

**Example:** "1.2. SSL/TLS Configuration & Setup" → `#12-ssltls-configuration--setup`

### DRIP (Documentation Remediation Implementation Plan) Methodology

**Note:** DRIP methodology provides a systematic approach for managing large-scale documentation remediation projects with structured 4-week phases, standardized task management, and quality assurance frameworks.

#### Core Framework

1. **4-Week Structured Phases** - Analysis & Planning → Content Remediation → Link Integrity & Navigation → Quality Assurance & Validation
2. **Hierarchical Task Management** - Color-coded status indicators with priority classification system
3. **Quality Integration** - WCAG 2.1 AA compliance and 100% link integrity targets
4. **Progress Transparency** - Real-time tracking with standardized reporting

For comprehensive implementation guidance, see [DRIP Methodology Guide](guidelines/080-drip-methodology.md).

#### DRIP Quick Reference

**DRIP Phase Structure:**

- **Week 1**: Analysis & Planning (audit, strategy, resource allocation)
- **Week 2**: Content Remediation (WCAG fixes, content enhancement, syntax modernization)
- **Week 3**: Link Integrity & Navigation (broken link repair, navigation structure)
- **Week 4**: Quality Assurance & Validation (testing, compliance audit, delivery)

**Status Indicators:**

- 🔴 Not Started (0%) | 🟡 In Progress (1-99%) | 🟠 Blocked/Paused | 🟢 Completed (100%) | ⚪ Cancelled/Deferred

**Priority System:**

- 🟣 P1 Critical | 🔴 P2 High | 🟡 P3 Medium | 🟢 P4 Low | ⚪ P5 Optional

#### Integration with TOC-Heading Synchronization

DRIP methodology builds upon and integrates with the TOC-heading synchronization approach:

- **Phase 3 Integration**: TOC synchronization occurs during Link Integrity & Navigation phase
- **Algorithm Alignment**: Uses GitHub anchor generation algorithm for consistent formatting
- **Quality Standards**: Maintains 100% link integrity target established in TOC methodology
- **Tool Compatibility**: Leverages existing validation tools and frameworks

## Maintenance

These guidelines should be updated whenever there are significant changes to:

- Project architecture or structure
- Development standards or workflows
- Documentation requirements
- AI Assistant behavior or capabilities
- **Documentation remediation methodologies** (including TOC-heading synchronization)

When updating these guidelines, ensure that all affected documents are updated consistently and that the main index reflects the current structure.
