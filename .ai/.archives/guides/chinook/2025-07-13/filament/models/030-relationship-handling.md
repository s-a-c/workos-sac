# Filament Relationship Handling Guide

## Overview

This guide covers complex relationship management in Filament resources, focusing on the Chinook database's sophisticated relationship patterns including polymorphic categorization, hierarchical data, and RBAC integration.

## Table of Contents

- [Overview](#overview)
- [Relationship Types](#relationship-types)
- [Polymorphic Relationships](#polymorphic-relationships)
- [Hierarchical Relationships](#hierarchical-relationships)
- [RBAC Relationships](#rbac-relationships)
- [Form Integration](#form-integration)
- [Table Integration](#table-integration)
- [Performance Optimization](#performance-optimization)
- [Best Practices](#best-practices)

## Relationship Types

### Core Music Relationships

#### Artist-Album Relationship

```php
// In ArtistResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        
        // Albums relationship
        Repeater::make('albums')
            ->relationship()
            ->schema([
                TextInput::make('title')->required(),
                DatePicker::make('release_date'),
            ])
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
    ]);
}
```

#### Album-Track Relationship

```php
// In AlbumResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('title')->required(),
        Select::make('artist_id')
            ->relationship('artist', 'name')
            ->searchable()
            ->preload(),
            
        // Tracks relationship with media type
        Repeater::make('tracks')
            ->relationship()
            ->schema([
                TextInput::make('name')->required(),
                Select::make('media_type_id')
                    ->relationship('mediaType', 'name')
                    ->required(),
                TextInput::make('unit_price')
                    ->numeric()
                    ->prefix('$'),
            ])
            ->orderColumn('track_number'),
    ]);
}
```

## Polymorphic Relationships

### Categorizable Implementation

```php
// In any categorizable resource (Artist, Album, Track)
public static function form(Form $form): Form
{
    return $form->schema([
        // ... other fields
        
        // Polymorphic categories
        Select::make('categories')
            ->relationship('categories', 'name')
            ->multiple()
            ->searchable()
            ->preload()
            ->createOptionForm([
                TextInput::make('name')->required(),
                Select::make('type')
                    ->options(CategoryType::class)
                    ->required(),
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->searchable(),
            ]),
    ]);
}

// Table integration
public static function table(Table $table): Table
{
    return $table->columns([
        // ... other columns
        
        TextColumn::make('categories.name')
            ->badge()
            ->separator(',')
            ->searchable(),
    ]);
}
```

### Media Library Integration

```php
// For models with media attachments
public static function form(Form $form): Form
{
    return $form->schema([
        // ... other fields
        
        SpatieMediaLibraryFileUpload::make('avatar')
            ->collection('avatars')
            ->image()
            ->imageEditor()
            ->circleCropper(),
            
        SpatieMediaLibraryFileUpload::make('gallery')
            ->collection('gallery')
            ->multiple()
            ->reorderable(),
    ]);
}
```

## Hierarchical Relationships

### Category Tree Management

```php
// In CategoryResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        
        Select::make('parent_id')
            ->relationship('parent', 'name')
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => 
                Category::where('name', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('name', 'id')
                    ->toArray()
            ),
            
        Select::make('type')
            ->options(CategoryType::class)
            ->required(),
    ]);
}

// Tree table display
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
            TextColumn::make('parent.name')
                ->label('Parent Category'),
            TextColumn::make('children_count')
                ->counts('children'),
            BadgeColumn::make('type')
                ->enum(CategoryType::class),
        ])
        ->defaultSort('name');
}
```

### Employee Hierarchy

```php
// In EmployeeResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('first_name')->required(),
        TextInput::make('last_name')->required(),
        
        Select::make('reports_to')
            ->relationship('supervisor', 'full_name')
            ->searchable()
            ->preload(),
    ]);
}
```

## RBAC Relationships

### User-Role Assignment

```php
// In UserResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
        
        Select::make('roles')
            ->relationship('roles', 'name')
            ->multiple()
            ->preload()
            ->searchable(),
            
        Select::make('permissions')
            ->relationship('permissions', 'name')
            ->multiple()
            ->searchable()
            ->preload(),
    ]);
}
```

### Role-Permission Management

```php
// In RoleResource
public static function form(Form $form): Form
{
    return $form->schema([
        TextInput::make('name')->required(),
        TextInput::make('guard_name')->default('web'),
        
        CheckboxList::make('permissions')
            ->relationship('permissions', 'name')
            ->columns(3)
            ->searchable(),
    ]);
}
```

## Form Integration

### Relationship Managers

```php
// In ArtistResource
public static function getRelations(): array
{
    return [
        AlbumsRelationManager::class,
        CategoriesRelationManager::class,
    ];
}

// AlbumsRelationManager
class AlbumsRelationManager extends RelationManager
{
    protected static string $relationship = 'albums';
    
    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')->required(),
            DatePicker::make('release_date'),
            TextInput::make('unit_price')->numeric(),
        ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('release_date')->date(),
                TextColumn::make('tracks_count')->counts('tracks'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
```

## Table Integration

### Complex Relationship Queries

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')->searchable(),
            
            // Count relationships
            TextColumn::make('albums_count')
                ->counts('albums')
                ->label('Albums'),
                
            // Access nested relationships
            TextColumn::make('albums.tracks_count')
                ->counts('albums.tracks')
                ->label('Total Tracks'),
                
            // Polymorphic relationship
            TextColumn::make('categories.name')
                ->badge()
                ->separator(','),
        ])
        ->filters([
            SelectFilter::make('categories')
                ->relationship('categories', 'name')
                ->multiple(),
        ]);
}
```

## Performance Optimization

### Eager Loading

```php
// In Resource class
protected static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['categories', 'albums.tracks', 'createdBy', 'updatedBy']);
}
```

### Query Optimization

```php
// Custom query scopes
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn (Builder $query) => 
            $query->withCount(['albums', 'tracks'])
                  ->with(['categories:id,name,type'])
        );
}
```

## Best Practices

### Relationship Loading

- Use `preload()` for small datasets
- Use `searchable()` for large datasets
- Implement custom search logic for complex queries
- Use `with()` for eager loading in queries

### Form Design

- Group related fields logically
- Use repeaters for one-to-many relationships
- Implement relationship managers for complex relationships
- Provide clear labels and help text

### Performance

- Limit relationship queries with `limit()`
- Use database indexes on foreign keys
- Implement caching for frequently accessed relationships
- Use `withCount()` instead of loading full relationships for counts

### Security

- Validate relationship existence
- Implement proper authorization policies
- Use scoped queries to restrict access
- Sanitize relationship inputs

---

## Navigation

**← Previous:** [Required Traits](020-required-traits.md)

**Next →** [Validation Rules](040-validation-rules.md)
