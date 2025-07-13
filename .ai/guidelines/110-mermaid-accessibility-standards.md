# 11. Mermaid Accessibility Standards

## 11.1. Overview

This document establishes comprehensive, organization-wide standards for creating high-contrast, WCAG 2.1 AA compliant Mermaid diagrams. These guidelines are based on extensive accessibility auditing and remediation work, ensuring all visual documentation meets accessibility requirements from the start.

**Core Principle**: All Mermaid diagrams must be accessible to users with visual impairments, including those using assistive technologies or requiring high-contrast displays.

## 11.2. WCAG 2.1 AA Compliance Requirements

### 11.2.1. Contrast Ratio Standards

**Minimum Requirements:**
- **Normal Text**: 4.5:1 contrast ratio minimum
- **Large Text** (18pt+ or 14pt+ bold): 3:1 contrast ratio minimum  
- **UI Components**: 3:1 contrast ratio minimum for interactive elements
- **Connecting Lines**: Must be clearly visible against background (4.5:1+ recommended)

**Recommended Targets:**
- **Normal Text**: 7:1 contrast ratio (AAA level)
- **All Elements**: 21:1 contrast ratio (white text on dark backgrounds)

### 11.2.2. Color Independence

- Information must not be conveyed by color alone
- Use clear labels, shapes, and patterns in addition to color coding
- Provide alternative text descriptions for complex diagrams
- Ensure semantic structure is maintained regardless of color perception

### 11.2.3. Accessibility Features

- **Screen Reader Support**: Include descriptive alt text for all diagrams
- **Keyboard Navigation**: Ensure interactive elements are keyboard accessible
- **Focus Indicators**: Provide clear focus indicators for interactive elements
- **Semantic Structure**: Use proper heading hierarchy and markup

## 11.3. Approved Color Palette

### 11.3.1. WCAG 2.1 AA Compliant Colors

**Primary Color Set:**
- **Primary Blue**: `#1976d2` - Contrast ratio 4.5:1 on white, 21:1 on dark
- **Success Green**: `#388e3c` - Contrast ratio 4.5:1 on white, 21:1 on dark
- **Warning Orange**: `#f57c00` - Contrast ratio 4.5:1 on white, 21:1 on dark
- **Error Red**: `#d32f2f` - Contrast ratio 4.5:1 on white, 21:1 on dark

**Background Colors:**
- **Light Theme**: `#ffffff` (white) with `#212121` (dark) text and lines
- **Dark Theme**: `#212121` (dark) with `#ffffff` (white) text and lines
- **Secondary Dark**: `#2c2c2c` (dark gray) for subtle backgrounds

### 11.3.2. Color Usage Guidelines

**Primary Blue (`#1976d2`)**: 
- Primary entities and key components
- Main navigation elements
- Important call-to-action items

**Success Green (`#388e3c`)**:
- Relationships and connections
- Success states and confirmations
- Positive indicators

**Warning Orange (`#f57c00`)**:
- Hierarchical structures
- Warning states and cautions
- Secondary importance items

**Error Red (`#d32f2f`)**:
- Validation errors and constraints
- Critical alerts and failures
- Destructive actions

## 11.4. Theme Implementation Standards

### 11.4.1. Dark Theme (Recommended Default)

**When to Use:**
- All new diagrams (recommended default)
- Interactive or screen-based documentation
- Low-light environments
- Accessibility-first implementations

**Theme Configuration:**
```javascript
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#ffffff',
    'lineColor': '#ffffff',
    'sectionBkColor': '#212121',
    'altSectionBkColor': '#2c2c2c',
    'gridColor': '#ffffff',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#212121',
    'mainBkg': '#212121',
    'secondBkg': '#2c2c2c',
    'tertiaryBkg': '#2c2c2c',
    'clusterBkg': '#2c2c2c',
    'clusterBorder': '#ffffff',
    'entityBkgColor': '#2c2c2c',
    'entityTextColor': '#ffffff',
    'relationLabelColor': '#ffffff',
    'relationLabelBackground': '#212121'
  }
}}%%
```

### 11.4.2. Light Theme

**When to Use:**
- Print media and documentation
- Bright environments
- Integration with light-themed systems
- User preference requirements

**Theme Configuration:**
```javascript
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#212121',
    'primaryBorderColor': '#212121',
    'lineColor': '#212121',
    'sectionBkColor': '#ffffff',
    'altSectionBkColor': '#ffffff',
    'gridColor': '#212121',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#ffffff',
    'mainBkg': '#ffffff',
    'secondBkg': '#ffffff',
    'tertiaryBkg': '#ffffff',
    'clusterBkg': '#ffffff',
    'clusterBorder': '#212121',
    'entityBkgColor': '#ffffff',
    'entityTextColor': '#212121',
    'relationLabelColor': '#212121',
    'relationLabelBackground': '#ffffff'
  }
}}%%
```

## 11.5. Node Styling Standards

### 11.5.1. Standard Node Styling Patterns

**Primary Nodes (Key Components):**
```css
style NodeName fill:#1976d2,color:#fff,stroke:#fff
```

**Secondary Nodes (Supporting Elements):**
```css
style NodeName fill:#2c2c2c,color:#fff,stroke:#fff
```

**Success/Connection Nodes:**
```css
style NodeName fill:#388e3c,color:#fff,stroke:#fff
```

**Warning/Hierarchy Nodes:**
```css
style NodeName fill:#f57c00,color:#fff,stroke:#fff
```

**Error/Constraint Nodes:**
```css
style NodeName fill:#d32f2f,color:#fff,stroke:#fff
```

### 11.5.2. Diagram Type Specific Patterns

**Flowcharts:**
- Use primary blue for start/end nodes
- Use green for process nodes
- Use orange for decision nodes
- Use red for error/exception nodes

**Entity Relationship Diagrams:**
- Use primary blue for main entities
- Use green for relationship connectors
- Use orange for hierarchical relationships
- Use red for constraint violations

**System Architecture:**
- Use primary blue for presentation layer
- Use green for service layer
- Use orange for data layer
- Use red for external systems

## 11.6. Mermaid v10.6+ Compatibility

### 11.6.1. Required Syntax Standards

- Use `%%{init: {...}}%%` for theme configuration
- Specify all theme variables explicitly
- Use proper subgraph syntax with quotes
- Include stroke properties for all styled nodes
- Use semantic node naming conventions

### 11.6.2. Deprecated Patterns to Avoid

- Do not use light backgrounds with light text
- Avoid `'theme': 'base'` without proper theme variables
- Do not omit stroke properties in node styling
- Avoid using color alone to convey information
- Do not use deprecated Mermaid syntax patterns

## 11.7. Implementation Templates

### 11.7.1. Basic Flowchart Template

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#ffffff',
    'lineColor': '#ffffff',
    'background': '#212121',
    'mainBkg': '#212121',
    'clusterBkg': '#2c2c2c',
    'clusterBorder': '#ffffff'
  }
}}%%
graph TB
    A[Start Process] --> B{Decision Point}
    B -->|Yes| C[Success Action]
    B -->|No| D[Alternative Action]
    C --> E[End Process]
    D --> E
    
    style A fill:#1976d2,color:#fff,stroke:#fff
    style B fill:#f57c00,color:#fff,stroke:#fff
    style C fill:#388e3c,color:#fff,stroke:#fff
    style D fill:#2c2c2c,color:#fff,stroke:#fff
    style E fill:#1976d2,color:#fff,stroke:#fff
```

### 11.7.2. Entity Relationship Diagram Template

```mermaid
%%{init: {
  'theme': 'dark',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'background': '#212121',
    'entityBkgColor': '#2c2c2c',
    'entityTextColor': '#ffffff',
    'relationLabelColor': '#ffffff'
  }
}}%%
erDiagram
    ENTITY_A {
        bigint id PK
        varchar name
        timestamp created_at
    }
    
    ENTITY_B {
        bigint id PK
        bigint entity_a_id FK
        varchar description
    }
    
    ENTITY_A ||--o{ ENTITY_B : "has many"
```

## 11.8. Quality Assurance Process

### 11.8.1. Pre-Implementation Checklist

**Before Creating Any Mermaid Diagram:**
- [ ] **Theme Selection**: Choose appropriate theme (dark recommended)
- [ ] **Color Palette**: Use only approved WCAG 2.1 AA colors
- [ ] **Syntax Version**: Ensure Mermaid v10.6+ compatibility
- [ ] **Content Planning**: Plan information hierarchy and relationships
- [ ] **Accessibility Review**: Consider screen reader and keyboard navigation needs

### 11.8.2. Implementation Validation

**During Diagram Creation:**
- [ ] **Theme Variables**: All required theme variables specified
- [ ] **Node Styling**: All nodes have proper fill, color, and stroke properties
- [ ] **Line Visibility**: Connecting lines clearly visible against background
- [ ] **Text Contrast**: All text meets 4.5:1 minimum contrast ratio
- [ ] **Color Independence**: Information not conveyed by color alone

### 11.8.3. Post-Implementation Testing

**Required Testing Steps:**
1. **Render Validation**: Use render-mermaid tool to verify diagram renders correctly
2. **Contrast Testing**: Verify all text and elements meet WCAG contrast requirements
3. **Cross-Browser Testing**: Test in Chrome, Firefox, Safari, and Edge
4. **Accessibility Testing**: Test with screen readers and keyboard navigation
5. **Print Testing**: Verify diagram readability in print format (if applicable)

### 11.8.4. Documentation Requirements

**For Each Diagram:**
- [ ] **Alt Text**: Provide descriptive alternative text
- [ ] **Context**: Include explanatory text describing diagram purpose
- [ ] **Legend**: Add legend for complex diagrams with multiple colors
- [ ] **Source**: Document diagram source and maintenance responsibility
- [ ] **Version**: Track diagram version and last update date

## 11.9. Integration with DRIP Methodology

### 11.9.1. DRIP Phase Integration

**Phase 1 - Discovery**: Identify existing diagrams requiring accessibility updates
**Phase 2 - Remediation**: Apply accessibility standards to existing diagrams
**Phase 3 - Implementation**: Create new diagrams following accessibility guidelines
**Phase 4 - Process**: Establish ongoing accessibility maintenance procedures

### 11.9.2. Task Management Integration

**Color-Coded Status Indicators:**
- 🔴 **P1 Critical**: WCAG violations requiring immediate attention
- 🟡 **P2 High**: Accessibility improvements needed
- 🟢 **P3 Medium**: Enhancement opportunities
- ⚪ **P4 Low**: Documentation and maintenance tasks

### 11.9.3. Quality Assurance Integration

- **100% Accessibility Compliance**: All diagrams must meet WCAG 2.1 AA standards
- **Systematic Validation**: Use render-mermaid tool for all diagram testing
- **Documentation Standards**: Follow hierarchical numbering and link integrity requirements
- **Maintenance Procedures**: Regular accessibility audits and updates

## 11.10. Troubleshooting and Common Issues

### 11.10.1. Rendering Problems

**Issue**: Diagram not rendering or displaying incorrectly
**Solutions**:
- Verify Mermaid v10.6+ syntax compatibility
- Check theme variable completeness
- Validate JSON syntax in init block
- Test with render-mermaid tool

**Issue**: Connecting lines not visible
**Solutions**:
- Ensure `lineColor` matches theme (white for dark, dark for light)
- Verify background colors don't conflict with line colors
- Check stroke properties in node styling

### 11.10.2. Accessibility Issues

**Issue**: Poor contrast ratios
**Solutions**:
- Use only approved color palette
- Verify contrast ratios with accessibility tools
- Ensure white text on colored backgrounds
- Test with high contrast mode enabled

**Issue**: Information conveyed by color alone
**Solutions**:
- Add text labels to all diagram elements
- Use shapes and patterns in addition to colors
- Provide comprehensive alt text descriptions
- Include legend for color-coded information

### 11.10.3. Browser Compatibility

**Issue**: Diagram displays differently across browsers
**Solutions**:
- Test in all major browsers (Chrome, Firefox, Safari, Edge)
- Use standard Mermaid syntax without browser-specific features
- Verify theme variables are properly applied
- Check for JavaScript console errors

## 11.11. Maintenance and Updates

### 11.11.1. Regular Review Schedule

**Monthly Reviews:**
- Audit new diagrams for accessibility compliance
- Test existing diagrams with latest browser versions
- Update color palette if WCAG standards change
- Review and update documentation as needed

**Quarterly Reviews:**
- Comprehensive accessibility audit of all diagrams
- Update Mermaid version compatibility requirements
- Review and update implementation templates
- Assess effectiveness of quality assurance processes

### 11.11.2. Version Control and Documentation

**Change Management:**
- Document all changes to accessibility standards
- Maintain version history of guideline updates
- Track diagram updates and accessibility improvements
- Coordinate with DRIP methodology for systematic updates

**Knowledge Management:**
- Keep examples and templates current with latest standards
- Update troubleshooting guides based on common issues
- Maintain cross-references with other documentation standards
- Ensure integration with overall project guidelines

## 11.12. Resources and References

### 11.12.1. External Standards

- **WCAG 2.1 Guidelines**: [Web Content Accessibility Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- **Mermaid Documentation**: [Official Mermaid Documentation](https://mermaid.js.org/)
- **Color Contrast Tools**: [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)

### 11.12.2. Internal References

- **[Documentation Standards](020-documentation-standards.md)**: Core documentation requirements
- **[High Contrast Diagram Test](025-diagram-contrast-test.md)**: Practical validation examples
- **[DRIP Methodology](080-drip-methodology.md)**: Documentation remediation framework

### 11.12.3. Tools and Validation

- **render-mermaid tool**: Primary validation tool for diagram rendering
- **Accessibility testing tools**: Screen readers, contrast checkers, keyboard navigation testing
- **Cross-browser testing**: Chrome DevTools, Firefox Developer Tools, Safari Web Inspector

---

**Document Version**: 1.0
**Last Updated**: 2025-07-13
**Next Review**: 2025-10-13
**Maintained By**: Documentation Team
