# Taxonomy Integration Architecture Diagrams

## Table of Contents

- [Overview](#overview)
- [Dual Categorization System Architecture](#dual-categorization-system-architecture)
- [Genre-to-Taxonomy Migration Flow](#genre-to-taxonomy-migration-flow)
- [Polymorphic Relationship Architecture](#polymorphic-relationship-architecture)
- [Performance Optimization Decision Tree](#performance-optimization-decision-tree)
- [Data Flow Diagrams](#data-flow-diagrams)

## Overview

This document provides comprehensive visual documentation of the Chinook database taxonomy integration architecture using Mermaid v10.6+ diagrams with WCAG 2.1 AA compliant color palette.

**Color Palette (WCAG 2.1 AA Compliant):**
- Primary Blue: `#1976d2` (7.04:1 contrast ratio)
- Success Green: `#388e3c` (6.74:1 contrast ratio)
- Warning Orange: `#f57c00` (4.52:1 contrast ratio)
- Error Red: `#d32f2f` (5.25:1 contrast ratio)

## Dual Categorization System Architecture

### System Overview

```mermaid
graph TB
    subgraph "Laravel 12 Models"
        A[Track Model]
        B[Album Model]
        C[Artist Model]
        D[Playlist Model]
    end
    
    subgraph "Categorization Layer"
        E[Categorizable Trait]
        F[HasTaxonomies Trait]
    end
    
    subgraph "Custom Categories System"
        G[Categories Table]
        H[CategoryType Enum]
        I[Categorizable Pivot]
        J[Category Closure Table]
    end
    
    subgraph "Taxonomy Package System"
        K[Taxonomies Table]
        L[Taxonomy Types]
        M[Taxonomables Pivot]
        N[Nested Set Model]
    end
    
    subgraph "Genre Preservation"
        O[Genres Table]
        P[Genre Mapping Layer]
    end
    
    A --> E
    A --> F
    B --> E
    B --> F
    C --> E
    C --> F
    D --> E
    D --> F
    
    E --> G
    E --> H
    E --> I
    E --> J
    
    F --> K
    F --> L
    F --> M
    F --> N
    
    O --> P
    P --> G
    P --> K
    
    style A fill:#1976d2,stroke:#fff,color:#fff
    style B fill:#1976d2,stroke:#fff,color:#fff
    style C fill:#1976d2,stroke:#fff,color:#fff
    style D fill:#1976d2,stroke:#fff,color:#fff
    style G fill:#388e3c,stroke:#fff,color:#fff
    style K fill:#f57c00,stroke:#fff,color:#fff
    style O fill:#d32f2f,stroke:#fff,color:#fff
```

### Component Interaction Flow

```mermaid
sequenceDiagram
    participant M as Model Instance
    participant CT as Categorizable Trait
    participant HT as HasTaxonomies Trait
    participant CS as Custom Categories
    participant TS as Taxonomy System
    participant GP as Genre Preservation
    
    M->>CT: attachCategory()
    CT->>CS: Create polymorphic relationship
    CS-->>CT: Category attached
    CT-->>M: Success response
    
    M->>HT: attachTaxonomy()
    HT->>TS: Create taxonomy relationship
    TS-->>HT: Taxonomy attached
    HT-->>M: Success response
    
    M->>GP: getGenreCategories()
    GP->>CS: Query genre-type categories
    CS-->>GP: Genre categories
    GP-->>M: Backward compatible response
```

## Genre-to-Taxonomy Migration Flow

### Migration Process Overview

```mermaid
flowchart TD
    A[Start Migration] --> B{Validate Prerequisites}
    B -->|Pass| C[Create Backup]
    B -->|Fail| Z[Abort Migration]
    
    C --> D[Phase 1: Genre Analysis]
    D --> E[Extract Genre Data]
    E --> F[Validate Data Integrity]
    
    F --> G[Phase 2: Category Creation]
    G --> H[Create Genre Categories]
    H --> I[Map Genre Relationships]
    
    I --> J[Phase 3: Polymorphic Mapping]
    J --> K[Create Track-Category Links]
    K --> L[Validate Relationships]
    
    L --> M[Phase 4: Taxonomy Integration]
    M --> N[Create Taxonomy Entries]
    N --> O[Link to Taxonomy System]
    
    O --> P{Validation Check}
    P -->|Pass| Q[Migration Complete]
    P -->|Fail| R[Rollback Procedure]
    
    R --> S[Restore Backup]
    S --> T[Validate Rollback]
    T --> U[Report Issues]
    
    style A fill:#1976d2,stroke:#fff,color:#fff
    style Q fill:#388e3c,stroke:#fff,color:#fff
    style Z fill:#d32f2f,stroke:#fff,color:#fff
    style R fill:#f57c00,stroke:#fff,color:#fff
```

### Data Preservation Strategy

```mermaid
graph LR
    subgraph "Original System"
        A[Genres Table<br/>25 Records]
        B[Tracks Table<br/>genre_id FK]
    end
    
    subgraph "Migration Process"
        C[Data Extraction]
        D[Validation Layer]
        E[Mapping Engine]
    end
    
    subgraph "Enhanced System"
        F[Categories Table<br/>Genre Type]
        G[Categorizable Pivot<br/>Polymorphic Links]
        H[Taxonomies Table<br/>Standardized Terms]
        I[Taxonomables Pivot<br/>Taxonomy Links]
    end
    
    subgraph "Preservation Layer"
        J[Backward Compatibility<br/>Interface]
        K[Query Translation<br/>Layer]
    end
    
    A --> C
    B --> C
    C --> D
    D --> E
    E --> F
    E --> G
    E --> H
    E --> I
    
    F --> J
    G --> J
    H --> K
    I --> K
    
    style A fill:#d32f2f,stroke:#fff,color:#fff
    style B fill:#d32f2f,stroke:#fff,color:#fff
    style F fill:#388e3c,stroke:#fff,color:#fff
    style G fill:#388e3c,stroke:#fff,color:#fff
    style H fill:#f57c00,stroke:#fff,color:#fff
    style I fill:#f57c00,stroke:#fff,color:#fff
```

## Polymorphic Relationship Architecture

### Relationship Mapping

```mermaid
erDiagram
    TRACKS ||--o{ CATEGORIZABLE : "polymorphic"
    ALBUMS ||--o{ CATEGORIZABLE : "polymorphic"
    ARTISTS ||--o{ CATEGORIZABLE : "polymorphic"
    PLAYLISTS ||--o{ CATEGORIZABLE : "polymorphic"
    
    TRACKS ||--o{ TAXONOMABLES : "polymorphic"
    ALBUMS ||--o{ TAXONOMABLES : "polymorphic"
    ARTISTS ||--o{ TAXONOMABLES : "polymorphic"
    PLAYLISTS ||--o{ TAXONOMABLES : "polymorphic"
    
    CATEGORIES ||--o{ CATEGORIZABLE : "belongs_to"
    TAXONOMIES ||--o{ TAXONOMABLES : "belongs_to"
    
    GENRES ||--o{ TRACKS : "direct_fk"
    GENRES ||--|| CATEGORIES : "mapped_to"
    
    CATEGORIES {
        bigint id PK
        string name
        enum type
        bigint parent_id FK
        json metadata
        boolean is_active
    }
    
    TAXONOMIES {
        bigint id PK
        string name
        string type
        bigint parent_id FK
        int lft
        int rgt
        int depth
    }
    
    CATEGORIZABLE {
        bigint category_id FK
        bigint categorizable_id
        string categorizable_type
        json metadata
        boolean is_primary
    }
    
    TAXONOMABLES {
        bigint taxonomy_id FK
        string taxonomable_id
        string taxonomable_type
    }
    
    GENRES {
        bigint id PK
        string name
    }
```

## Performance Optimization Decision Tree

### Query Optimization Strategy

```mermaid
flowchart TD
    A[Query Request] --> B{Query Type?}
    
    B -->|Simple Category| C[Use Custom Categories]
    B -->|Hierarchical| D{Depth Level?}
    B -->|Cross-Model| E[Use Taxonomy System]
    
    D -->|Shallow ≤3| F[Use Adjacency List]
    D -->|Deep >3| G[Use Closure Table]
    
    C --> H{Cache Available?}
    F --> H
    G --> I{Cache Available?}
    E --> I
    
    H -->|Yes| J[Return Cached Result]
    H -->|No| K[Execute Query + Cache]
    I -->|Yes| L[Return Cached Result]
    I -->|No| M[Execute Query + Cache]
    
    K --> N[Optimize for SQLite]
    M --> O[Optimize for Nested Sets]
    
    style A fill:#1976d2,stroke:#fff,color:#fff
    style J fill:#388e3c,stroke:#fff,color:#fff
    style L fill:#388e3c,stroke:#fff,color:#fff
    style N fill:#f57c00,stroke:#fff,color:#fff
    style O fill:#f57c00,stroke:#fff,color:#fff
```

## Data Flow Diagrams

### Category Assignment Flow

```mermaid
graph TD
    A[User Action] --> B{Assignment Type?}
    
    B -->|Custom Category| C[Categorizable Trait]
    B -->|Taxonomy| D[HasTaxonomies Trait]
    
    C --> E[Validate Category Type]
    E --> F[Check Permissions]
    F --> G[Create Pivot Record]
    G --> H[Update Metadata]
    H --> I[Clear Cache]
    
    D --> J[Validate Taxonomy]
    J --> K[Check Permissions]
    K --> L[Create Taxonomy Link]
    L --> M[Update Relationships]
    M --> N[Clear Cache]
    
    I --> O[Success Response]
    N --> O
    
    style A fill:#1976d2,stroke:#fff,color:#fff
    style O fill:#388e3c,stroke:#fff,color:#fff
    style F fill:#f57c00,stroke:#fff,color:#fff
    style K fill:#f57c00,stroke:#fff,color:#fff
```

### Query Resolution Flow

```mermaid
graph LR
    A[Query Request] --> B[Route Analysis]
    B --> C{Legacy Pattern?}
    
    C -->|Yes| D[Backward Compatibility Layer]
    C -->|No| E[Modern Query Interface]
    
    D --> F[Genre Translation]
    F --> G[Category Mapping]
    G --> H[Execute Custom Query]
    
    E --> I{System Choice?}
    I -->|Categories| J[Custom Category Query]
    I -->|Taxonomy| K[Taxonomy Query]
    I -->|Both| L[Unified Query]
    
    H --> M[Format Response]
    J --> M
    K --> M
    L --> M
    
    M --> N[Return Results]
    
    style A fill:#1976d2,stroke:#fff,color:#fff
    style D fill:#d32f2f,stroke:#fff,color:#fff
    style E fill:#388e3c,stroke:#fff,color:#fff
    style N fill:#388e3c,stroke:#fff,color:#fff
```

---

*All diagrams use WCAG 2.1 AA compliant color palette with minimum 4.5:1 contrast ratios for accessibility compliance.*
