# Mermaid Diagram Style Guide
**Version**: 1.0  
**Generated**: 2025-07-13  
**Scope**: Chinook Documentation Standards  
**Compliance**: WCAG 2.1 AA, Mermaid v10.6+  

## Overview

This comprehensive style guide documents the established standards, templates, and best practices for creating Mermaid diagrams in the chinook documentation. These standards ensure accessibility compliance, professional presentation, and consistent user experience across all technical documentation.

## Core Principles

### 1. Accessibility First
- **WCAG 2.1 AA Compliance**: All diagrams must meet accessibility standards
- **Color Independence**: Information must be conveyed through multiple visual cues
- **Semantic Structure**: Meaningful titles and descriptions required
- **Screen Reader Compatibility**: Enhanced accessibility for assistive technologies

### 2. Professional Presentation
- **Enterprise Standards**: Professional-grade visual quality
- **Consistent Theming**: Standardized appearance across all diagrams
- **Visual Hierarchy**: Clear distinction between element types
- **Brand Alignment**: Consistent with organizational design standards

### 3. Technical Excellence
- **Mermaid v10.6+ Compliance**: Latest syntax standards required
- **Cross-Platform Compatibility**: Verified rendering across platforms
- **Performance Optimization**: Efficient rendering and loading
- **Maintainability**: Consistent patterns for easy updates

## WCAG 2.1 AA Color Standards

### Approved Color Palette
All diagrams must exclusively use the following high-contrast color palette:

#### Primary Colors
- **Primary Blue**: `#1976d2` (4.5:1 contrast ratio)
  - Use for: Primary entities, main components, key elements
  - Stroke: `#0d47a1` for enhanced contrast

- **Success Green**: `#388e3c` (4.5:1 contrast ratio)
  - Use for: Successful states, positive relationships, data flow
  - Stroke: `#1b5e20` for enhanced contrast

- **Warning Orange**: `#f57c00` (4.5:1 contrast ratio)
  - Use for: Important elements, warnings, process steps
  - Stroke: `#e65100` for enhanced contrast

- **Error Red**: `#d32f2f` (4.5:1 contrast ratio)
  - Use for: Critical elements, errors, constraints
  - Stroke: `#b71c1c` for enhanced contrast

#### Color Usage Guidelines
- **Primary Blue**: Main entities, core components, primary navigation
- **Success Green**: Data relationships, successful processes, connections
- **Warning Orange**: Important processes, hierarchy, parent-child relationships
- **Error Red**: Constraints, validation, critical elements

#### Prohibited Colors
- **Purple variants**: `#7b1fa2`, `#4a148c` (non-compliant)
- **Low contrast colors**: Any color not meeting 4.5:1 contrast ratio
- **Brand-specific colors**: Colors not in the approved palette

## Standard Theme Configuration

### Universal Theme Template
All diagrams must include the following theme configuration:

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#1565c0',
    'lineColor': '#212121',
    'sectionBkColor': '#f5f5f5',
    'altSectionBkColor': '#e3f2fd',
    'gridColor': '#757575',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#ffffff',
    'mainBkg': '#ffffff',
    'secondBkg': '#f5f5f5',
    'tertiaryBkg': '#e3f2fd'
  }
}}%%
```

### Theme Configuration Benefits
- **Consistent Appearance**: Uniform visual presentation
- **Automatic Compliance**: Built-in WCAG color adherence
- **Professional Quality**: Enterprise-grade theming
- **Reduced Maintenance**: Centralized color management

## Diagram Templates

### 1. Entity Relationship Diagram (ERD) Template

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#1565c0',
    'lineColor': '#212121',
    'sectionBkColor': '#f5f5f5',
    'altSectionBkColor': '#e3f2fd',
    'gridColor': '#757575',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#ffffff',
    'mainBkg': '#ffffff',
    'secondBkg': '#f5f5f5',
    'tertiaryBkg': '#e3f2fd'
  }
}}%%
---
title: [Descriptive ERD Title]
---
erDiagram
    ENTITY_ONE {
        bigint id PK
        varchar name
        timestamp created_at
        timestamp updated_at
    }
    
    ENTITY_TWO {
        bigint id PK
        bigint entity_one_id FK
        varchar title
        text description
    }
    
    ENTITY_ONE ||--o{ ENTITY_TWO : "has many"
```

### 2. Architecture Diagram Template

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#1565c0',
    'lineColor': '#212121',
    'sectionBkColor': '#f5f5f5',
    'altSectionBkColor': '#e3f2fd',
    'gridColor': '#757575',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#ffffff',
    'mainBkg': '#ffffff',
    'secondBkg': '#f5f5f5',
    'tertiaryBkg': '#e3f2fd'
  }
}}%%
---
title: [Descriptive Architecture Title]
---
graph TB
    subgraph "Layer One"
        A[Component A] --> B[Component B]
    end
    
    subgraph "Layer Two"
        C[Service C] --> D[Service D]
    end
    
    A --> C
    B --> D
    
    style A fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style C fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
```

### 3. Flow Diagram Template

```mermaid
%%{init: {
  'theme': 'base',
  'themeVariables': {
    'primaryColor': '#1976d2',
    'primaryTextColor': '#ffffff',
    'primaryBorderColor': '#1565c0',
    'lineColor': '#212121',
    'sectionBkColor': '#f5f5f5',
    'altSectionBkColor': '#e3f2fd',
    'gridColor': '#757575',
    'secondaryColor': '#388e3c',
    'tertiaryColor': '#f57c00',
    'background': '#ffffff',
    'mainBkg': '#ffffff',
    'secondBkg': '#f5f5f5',
    'tertiaryBkg': '#e3f2fd'
  }
}}%%
---
title: [Descriptive Flow Title]
---
flowchart TD
    Start([Start Process]) --> Decision{Decision Point}
    Decision -->|Yes| ActionA[Action A]
    Decision -->|No| ActionB[Action B]
    ActionA --> End([End Process])
    ActionB --> End
    
    style Start fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
    style End fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
    style Decision fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
```

## Styling Guidelines

### Node Styling Standards
Use the following styling patterns for consistent appearance:

#### Primary Elements
```mermaid
style NodeName fill:#1976d2,stroke:#0d47a1,stroke-width:2px,color:#ffffff
```

#### Secondary Elements
```mermaid
style NodeName fill:#388e3c,stroke:#1b5e20,stroke-width:2px,color:#ffffff
```

#### Process Elements
```mermaid
style NodeName fill:#f57c00,stroke:#e65100,stroke-width:2px,color:#ffffff
```

#### Critical Elements
```mermaid
style NodeName fill:#d32f2f,stroke:#b71c1c,stroke-width:2px,color:#ffffff
```

### Styling Best Practices
- **Consistent Stroke Width**: Use 2px stroke width for enhanced visibility
- **White Text**: Use `color:#ffffff` for optimal contrast
- **Enhanced Borders**: Use darker stroke colors for better definition
- **Semantic Styling**: Apply colors based on element meaning, not aesthetics

## Title and Metadata Standards

### Required Title Format
All diagrams must include a semantic title using the following format:

```mermaid
---
title: [Descriptive Title That Explains the Diagram Purpose]
---
```

### Title Guidelines
- **Descriptive**: Clearly explain the diagram's purpose
- **Specific**: Include relevant context (e.g., "with Taxonomy Integration")
- **Professional**: Use proper capitalization and grammar
- **Accessible**: Meaningful for screen readers and assistive technologies

### Examples of Good Titles
- "Chinook Database Schema Overview with Taxonomy Integration"
- "Single Taxonomy Factory Architecture"
- "WCAG 2.1 AA Compliant Color Palette for Taxonomy Diagrams"
- "Component Data Flow Architecture"

### Examples of Poor Titles
- "Database" (too generic)
- "diagram1" (not descriptive)
- "ERD" (acronym without context)

## Quality Assurance Checklist

### Pre-Publication Checklist
Before publishing any Mermaid diagram, verify:

#### Technical Requirements
- [ ] Mermaid v10.6+ syntax compliance
- [ ] Theme configuration included
- [ ] Semantic title provided
- [ ] Proper diagram type specified

#### Accessibility Requirements
- [ ] WCAG 2.1 AA color compliance
- [ ] Approved color palette exclusively used
- [ ] No non-standard colors (especially purple variants)
- [ ] Enhanced contrast with stroke colors

#### Professional Standards
- [ ] Consistent styling applied
- [ ] Visual hierarchy clear
- [ ] Professional appearance
- [ ] Brand alignment maintained

#### Validation Testing
- [ ] Diagram renders successfully
- [ ] Cross-platform compatibility verified
- [ ] No syntax errors detected
- [ ] Visual quality acceptable

## Common Mistakes to Avoid

### Color-Related Mistakes
- **Using non-approved colors**: Especially purple variants (#7b1fa2, #4a148c)
- **Insufficient contrast**: Colors not meeting 4.5:1 ratio
- **Inconsistent color usage**: Different meanings for same colors
- **Missing stroke colors**: Reduced visual definition

### Structure-Related Mistakes
- **Missing theme configuration**: Inconsistent appearance
- **No semantic title**: Reduced accessibility
- **Poor node naming**: Unclear element identification
- **Inconsistent styling**: Mixed styling patterns

### Technical Mistakes
- **Outdated syntax**: Using deprecated Mermaid features
- **Missing validation**: Not testing diagram rendering
- **Platform assumptions**: Not verifying cross-platform compatibility
- **Performance issues**: Overly complex diagrams

## Maintenance Guidelines

### Regular Review Process
- **Monthly Audits**: Review diagram compliance
- **Update Procedures**: Apply new standards systematically
- **Quality Monitoring**: Track compliance metrics
- **Continuous Improvement**: Evolve standards based on usage

### Update Procedures
When updating existing diagrams:
1. **Backup Original**: Preserve existing version
2. **Apply Standards**: Use current style guide
3. **Validate Changes**: Test rendering and compliance
4. **Document Updates**: Record changes made

### Version Control
- **Style Guide Versioning**: Track standard evolution
- **Change Documentation**: Record all modifications
- **Backward Compatibility**: Maintain existing diagram functionality
- **Migration Planning**: Systematic update strategies

## Future Considerations

### Emerging Standards
- **Accessibility Evolution**: Monitor WCAG updates
- **Mermaid Development**: Track new features and syntax
- **Technology Changes**: Adapt to platform updates
- **User Feedback**: Incorporate usage insights

### Automation Opportunities
- **Validation Tools**: Automated compliance checking
- **Template Generation**: Streamlined diagram creation
- **Quality Monitoring**: Continuous compliance verification
- **Integration Workflows**: Embedded quality assurance

## Conclusion

This style guide establishes comprehensive standards for creating professional, accessible, and technically excellent Mermaid diagrams. Adherence to these guidelines ensures:

- **Accessibility Compliance**: WCAG 2.1 AA standards met
- **Professional Quality**: Enterprise-grade presentation
- **Technical Excellence**: Latest standards and best practices
- **Consistent Experience**: Uniform appearance and functionality

Regular review and updates of this guide will ensure continued alignment with evolving standards and best practices in technical documentation.
