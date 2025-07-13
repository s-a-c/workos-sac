# Laravel 12, Filament 4, Polymorphic STI Team Model with Spatie Packages
Created: 20/06/2025, 00:14:47
Last Updated: 20/06/2025, 00:37:15
---

### User

in a PHP 8.3, Laravel 12 application:
using:
- filament/filament:^4.0
- staudenmeir/laravel-adjacency-list
- tightenco/parental
- spatie/laravel-model-states
- spatie/laravel-model-status
- spatie/laravel-data

A self-referential, polymorphic, STI model, `Team`
Hierarchy: Enterprise → Organisation → Division → Department
Root constraint: Only Enterprise can be root-level
Additional types: Project (non-root)
Only `Team` is allowed in any graph more than once
Self-referential: Each entity can have a parent of the same base type
Polymorphic STI: All types share the same table with type discrimination
All models have a mandatory `Executive` and optional `Deputy`, both related to `User` model
Executive and Deputy may not be equal
a `Team` has a lifecycle, managed using spatie/laravel-model-states and spatie/laravel-model-status
incorporate filament helpers to facilitate colours and labels for the types and statuses and avatars for Team models

MAke use of:
- Enums
- DTOs
- Value Objects
wherever, appropriate


---

### Assistant (claude-4-sonnet)

I'll help you create a comprehensive Laravel application structure with the specifications you've outlined. Let me break this down into the key components:

## 1. Base Team Model with STI and Adjacency List

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use App\States\TeamState;
use App\ValueObjects\TeamHierarchy;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\LaravelData\WithData;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStatus\HasStatuses;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Tightenco\Parental\HasParent;

class Team extends Model
{
    use HasRecursiveRelationships,
        HasStates,
        HasStatuses,
        SoftDeletes,
        WithData;

    protected $fillable = [
        'name',
        'description',
        'type',
        'parent_id',
        'executive_id',
        'deputy_id',
    ];

    protected $casts = [
        'type' => TeamType::class,
        'state' => TeamState::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            $team->validateHierarchyRules();
        });

        static::updating(function (Team $team) {
            $team->validateHierarchyRules();
            $team->validateExecutiveDeputyConstraint();
        });
    }

    // Relationships
    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    // Validation Methods
    protected function validateHierarchyRules(): void
    {
        $hierarchy = new TeamHierarchy($this);
        $hierarchy->validate();
    }

    protected function validateExecutiveDeputyConstraint(): void
    {
        if ($this->executive_id && $this->deputy_id && 
            $this->executive_id === $this->deputy_id) {
            throw new \InvalidArgumentException(
                'Executive and Deputy cannot be the same person'
            );
        }
    }

    // Filament Helpers
    public function getTypeColor(): string
    {
        return match ($this->type) {
            TeamType::Enterprise => Color::Purple[500],
            TeamType::Organisation => Color::Blue[500],
            TeamType::Division => Color::Green[500],
            TeamType::Department => Color::Orange[500],
            TeamType::Project => Color::Gray[500],
        };
    }

    public function getTypeLabel(): string
    {
        return $this->type->getLabel();
    }

    public function getStatusColor(): string
    {
        return $this->latestStatus()?->color ?? Color::Gray[400];
    }

    public function getAvatarUrl(): ?string
    {
        return $this->executive?->avatar_url;
    }

    // Scopes
    public function scopeOfType($query, TeamType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
```

## 2. STI Child Models

```php
<?php

namespace App\Models\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use Tightenco\Parental\HasChildren;

class Enterprise extends Team
{
    use HasChildren;

    protected $childTypes = [
        'organisation' => Organisation::class,
    ];

    protected static function booted(): void
    {
        parent::booted();
        
        static::creating(function (Enterprise $enterprise) {
            $enterprise->type = TeamType::Enterprise;
            $enterprise->parent_id = null; // Enforce root constraint
        });
    }

    public function canHaveParent(): bool
    {
        return false;
    }
}
```

```php
<?php

namespace App\Models\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use Tightenco\Parental\HasParent;

class Organisation extends Team
{
    use HasParent;

    protected static function booted(): void
    {
        parent::booted();
        
        static::creating(function (Organisation $organisation) {
            $organisation->type = TeamType::Organisation;
        });
    }

    public function getAllowedParentTypes(): array
    {
        return [TeamType::Enterprise];
    }
}
```

```php
<?php

namespace App\Models\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use Tightenco\Parental\HasParent;

class Division extends Team
{
    use HasParent;

    protected static function booted(): void
    {
        parent::booted();
        
        static::creating(function (Division $division) {
            $division->type = TeamType::Division;
        });
    }

    public function getAllowedParentTypes(): array
    {
        return [TeamType::Organisation, TeamType::Division];
    }
}
```

```php
<?php

namespace App\Models\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use Tightenco\Parental\HasParent;

class Department extends Team
{
    use HasParent;

    protected static function booted(): void
    {
        parent::booted();
        
        static::creating(function (Department $department) {
            $department->type = TeamType::Department;
        });
    }

    public function getAllowedParentTypes(): array
    {
        return [TeamType::Division, TeamType::Department];
    }
}
```

```php
<?php

namespace App\Models\Teams;

use App\Enums\TeamType;
use App\Models\Team;
use Tightenco\Parental\HasParent;

class Project extends Team
{
    use HasParent;

    protected static function booted(): void
    {
        parent::booted();
        
        static::creating(function (Project $project) {
            $project->type = TeamType::Project;
        });
    }

    public function getAllowedParentTypes(): array
    {
        return [
            TeamType::Enterprise,
            TeamType::Organisation,
            TeamType::Division,
            TeamType::Department,
        ];
    }

    public function canBeRoot(): bool
    {
        return false;
    }
}
```

## 3. Enums

```php
<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TeamType: string implements HasLabel, HasColor
{
    case Enterprise = 'enterprise';
    case Organisation = 'organisation';
    case Division = 'division';
    case Department = 'department';
    case Project = 'project';

    public function getLabel(): string
    {
        return match ($this) {
            self::Enterprise => 'Enterprise',
            self::Organisation => 'Organisation',
            self::Division => 'Division',
            self::Department => 'Department',
            self::Project => 'Project',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Enterprise => 'purple',
            self::Organisation => 'blue',
            self::Division => 'green',
            self::Department => 'orange',
            self::Project => 'gray',
        };
    }

    public function canBeRoot(): bool
    {
        return $this === self::Enterprise;
    }

    public function getAllowedParentTypes(): array
    {
        return match ($this) {
            self::Enterprise => [],
            self::Organisation => [self::Enterprise],
            self::Division => [self::Organisation, self::Division],
            self::Department => [self::Division, self::Department],
            self::Project => [
                self::Enterprise,
                self::Organisation,
                self::Division,
                self::Department,
            ],
        };
    }
}
```

## 4. States

```php
<?php

namespace App\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class TeamState extends State
{
    abstract public function color(): string;
    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, Inactive::class)
            ->allowTransition(Inactive::class, Active::class)
            ->allowTransition([Draft::class, Inactive::class], Archived::class);
    }
}
```

```php
<?php

namespace App\States;

class Draft extends TeamState
{
    public function color(): string
    {
        return 'gray';
    }

    public function label(): string
    {
        return 'Draft';
    }
}
```

```php
<?php

namespace App\States;

class Active extends TeamState
{
    public function color(): string
    {
        return 'success';
    }

    public function label(): string
    {
        return 'Active';
    }
}
```

```php
<?php

namespace App\States;

class Inactive extends TeamState
{
    public function color(): string
    {
        return 'warning';
    }

    public function label(): string
    {
        return 'Inactive';
    }
}
```

```php
<?php

namespace App\States;

class Archived extends TeamState
{
    public function color(): string
    {
        return 'danger';
    }

    public function label(): string
    {
        return 'Archived';
    }
}
```

## 5. DTOs

```php
<?php

namespace App\Data;

use App\Enums\TeamType;
use App\Models\User;
use Spatie\LaravelData\Data;

class TeamData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public TeamType $type,
        public ?int $parent_id,
        public int $executive_id,
        public ?int $deputy_id,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'enum:' . TeamType::class],
            'parent_id' => ['nullable', 'exists:teams,id'],
            'executive_id' => ['required', 'exists:users,id'],
            'deputy_id' => ['nullable', 'exists:users,id', 'different:executive_id'],
        ];
    }
}
```

```php
<?php

namespace App\Data;

use App\Enums\TeamType;
use Spatie\LaravelData\Data;

class TeamHierarchyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public TeamType $type,
        public ?int $parent_id,
        public int $depth,
        public array $children = [],
    ) {}
}
```

## 6. Value Objects

```php
<?php

namespace App\ValueObjects;

use App\Enums\TeamType;
use App\Models\Team;
use InvalidArgumentException;

class TeamHierarchy
{
    public function __construct(private Team $team) {}

    public function validate(): void
    {
        $this->validateRootConstraint();
        $this->validateParentTypeConstraint();
        $this->validateCircularReference();
    }

    private function validateRootConstraint(): void
    {
        if (!$this->team->parent_id && !$this->team->type->canBeRoot()) {
            throw new InvalidArgumentException(
                "Only Enterprise teams can be root-level. {$this->team->type->getLabel()} must have a parent."
            );
        }
    }

    private function validateParentTypeConstraint(): void
    {
        if (!$this->team->parent_id) {
            return;
        }

        $parent = Team::find($this->team->parent_id);
        if (!$parent) {
            throw new InvalidArgumentException('Parent team does not exist.');
        }

        $allowedParentTypes = $this->team->type->getAllowedParentTypes();
        if (!in_array($parent->type, $allowedParentTypes)) {
            throw new InvalidArgumentException(
                "Invalid parent type. {$this->team->type->getLabel()} can only have parents of type: " .
                implode(', ', array_map(fn($type) => $type->getLabel(), $allowedParentTypes))
            );
        }
    }

    private function validateCircularReference(): void
    {
        if (!$this->team->parent_id) {
            return;
        }

        $ancestors = $this->team->ancestors()->pluck('id')->toArray();
        if (in_array($this->team->id, $ancestors)) {
            throw new InvalidArgumentException(
                'Circular reference detected in team hierarchy.'
            );
        }
    }

    public function getDepth(): int
    {
        return $this->team->ancestors()->count();
    }

    public function getPath(): array
    {
        return $this->team->ancestorsAndSelf()
            ->orderBy('depth')
            ->pluck('name')
            ->toArray();
    }
}
```

## 7. Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('teams');
            $table->foreignId('executive_id')->constrained('users');
            $table->foreignId('deputy_id')->nullable()->constrained('users');
            $table->string('state')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'parent_id']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

## 8. Filament Resource

```php
<?php

namespace App\Filament\Resources;

use App\Data\TeamData;
use App\Enums\TeamType;
use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),

            Forms\Components\Textarea::make('description')
                ->maxLength(65535),

            Forms\Components\Select::make('type')
                ->options(TeamType::class)
                ->required()
                ->reactive()
                ->afterStateUpdated(fn($state, callable $set) => 
                    $state === TeamType::Enterprise->value ? $set('parent_id', null) : null
                ),

            Forms\Components\Select::make('parent_id')
                ->relationship('parent', 'name')
                ->searchable()
                ->hidden(fn(callable $get) => 
                    $get('type') === TeamType::Enterprise->value
                ),

            Forms\Components\Select::make('executive_id')
                ->relationship('executive', 'name')
                ->searchable()
                ->required(),

            Forms\Components\Select::make('deputy_id')
                ->relationship('deputy', 'name')
                ->searchable()
                ->different('executive_id'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->color(fn(Team $record) => $record->getTypeColor())
                    ->formatStateUsing(fn($state) => $state->getLabel()),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable(),

                Tables\Columns\TextColumn::make('executive.name')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('state')
                    ->color(fn(Team $record) => $record->state->color())
                    ->formatStateUsing(fn($state) => $state->label()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(TeamType::class),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
```

This implementation provides:

1. **Self-referential polymorphic STI** with proper hierarchy validation
2. **Adjacency list** support for efficient tree operations
3. **State management** with Spatie packages
4. **Filament integration** with colors, labels, and avatars
5. **Proper validation** for business rules
6. **DTOs and Value Objects** for clean data handling
7. **Enums** for type safety

The structure enforces all your constraints while maintaining clean, maintainable code.


---

### User

incorporate slugs by spatie/laravel-sluggable
incorporate ULID as secondary unique key, from symfony/uid


---

### Assistant (claude-4-sonnet)

I'll incorporate slugs and ULIDs into the existing structure. Here are the updated components:

## 1. Updated Team Model

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use App\States\TeamState;
use App\ValueObjects\TeamHierarchy;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\LaravelData\WithData;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStatus\HasStatuses;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Symfony\Component\Uid\Ulid;
use Tightenco\Parental\HasParent;

class Team extends Model
{
    use HasRecursiveRelationships,
        HasStates,
        HasStatuses,
        HasSlug,
        SoftDeletes,
        WithData;

    protected $fillable = [
        'name',
        'slug',
        'ulid',
        'description',
        'type',
        'parent_id',
        'executive_id',
        'deputy_id',
    ];

    protected $casts = [
        'type' => TeamType::class,
        'state' => TeamState::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if (!$team->ulid) {
                $team->ulid = (string) new Ulid();
            }
            $team->validateHierarchyRules();
        });

        static::updating(function (Team $team) {
            $team->validateHierarchyRules();
            $team->validateExecutiveDeputyConstraint();
        });
    }

    // Slug Configuration
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['name', 'type'])
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate()
            ->slugsShouldBeNoLongerThan(100)
            ->usingSeparator('-')
            ->usingLanguage('en')
            ->preventOverwrite();
    }

    public function generateSlugFrom(): string
    {
        $parentSlug = $this->parent?->slug ?? '';
        $typePrefix = strtolower($this->type->value);
        
        return $parentSlug 
            ? "{$parentSlug}-{$typePrefix}-{$this->name}"
            : "{$typePrefix}-{$this->name}";
    }

    // Route Key Name for URL binding
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // Alternative route binding by slug
    public function resolveRouteBinding($value, $field = null)
    {
        return match ($field) {
            'slug' => $this->where('slug', $value)->first(),
            'ulid' => $this->where('ulid', $value)->first(),
            default => parent::resolveRouteBinding($value, $field),
        };
    }

    // Relationships
    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    // Validation Methods
    protected function validateHierarchyRules(): void
    {
        $hierarchy = new TeamHierarchy($this);
        $hierarchy->validate();
    }

    protected function validateExecutiveDeputyConstraint(): void
    {
        if ($this->executive_id && $this->deputy_id && 
            $this->executive_id === $this->deputy_id) {
            throw new \InvalidArgumentException(
                'Executive and Deputy cannot be the same person'
            );
        }
    }

    // Filament Helpers
    public function getTypeColor(): string
    {
        return match ($this->type) {
            TeamType::Enterprise => Color::Purple[500],
            TeamType::Organisation => Color::Blue[500],
            TeamType::Division => Color::Green[500],
            TeamType::Department => Color::Orange[500],
            TeamType::Project => Color::Gray[500],
        };
    }

    public function getTypeLabel(): string
    {
        return $this->type->getLabel();
    }

    public function getStatusColor(): string
    {
        return $this->latestStatus()?->color ?? Color::Gray[400];
    }

    public function getAvatarUrl(): ?string
    {
        return $this->executive?->avatar_url;
    }

    public function getFullSlugPath(): string
    {
        return $this->ancestorsAndSelf()
            ->orderBy('depth')
            ->pluck('slug')
            ->filter()
            ->implode('/');
    }

    public function getPublicUrl(): string
    {
        return route('teams.show', ['team' => $this->ulid]);
    }

    public function getSlugUrl(): string
    {
        return route('teams.show-by-slug', ['slug' => $this->slug]);
    }

    // Scopes
    public function scopeOfType($query, TeamType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByUlid($query, string $ulid)
    {
        return $query->where('ulid', $ulid);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }
}
```

## 2. Updated Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('teams');
            $table->foreignId('executive_id')->constrained('users');
            $table->foreignId('deputy_id')->nullable()->constrained('users');
            $table->string('state')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'parent_id']);
            $table->index('state');
            $table->index('ulid');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

## 3. Updated DTOs

```php
<?php

namespace App\Data;

use App\Enums\TeamType;
use Spatie\LaravelData\Data;

class TeamData extends Data
{
    public function __construct(
        public string $name,
        public ?string $slug,
        public ?string $ulid,
        public ?string $description,
        public TeamType $type,
        public ?int $parent_id,
        public int $executive_id,
        public ?int $deputy_id,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:teams,slug'],
            'ulid' => ['nullable', 'string', 'size:26', 'unique:teams,ulid'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'enum:' . TeamType::class],
            'parent_id' => ['nullable', 'exists:teams,id'],
            'executive_id' => ['required', 'exists:users,id'],
            'deputy_id' => ['nullable', 'exists:users,id', 'different:executive_id'],
        ];
    }
}
```

```php
<?php

namespace App\Data;

use App\Enums\TeamType;
use Spatie\LaravelData\Data;

class TeamHierarchyData extends Data
{
    public function __construct(
        public int $id,
        public string $ulid,
        public string $name,
        public string $slug,
        public TeamType $type,
        public ?int $parent_id,
        public int $depth,
        public string $full_slug_path,
        public array $children = [],
    ) {}
}
```

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class TeamIdentifierData extends Data
{
    public function __construct(
        public int $id,
        public string $ulid,
        public string $slug,
        public string $name,
    ) {}

    public function getPublicUrl(): string
    {
        return route('teams.show', ['team' => $this->ulid]);
    }

    public function getSlugUrl(): string
    {
        return route('teams.show-by-slug', ['slug' => $this->slug]);
    }
}
```

## 4. Updated Filament Resource

```php
<?php

namespace App\Filament\Resources;

use App\Data\TeamData;
use App\Enums\TeamType;
use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                            if ($operation !== 'create') {
                                return;
                            }
                            $set('slug', \Str::slug($state));
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->rules(['alpha_dash'])
                        ->helperText('Auto-generated from name, but can be customized'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Hierarchy')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(TeamType::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state === TeamType::Enterprise->value) {
                                $set('parent_id', null);
                            }
                        }),

                    Forms\Components\Select::make('parent_id')
                        ->relationship(
                            'parent',
                            'name',
                            fn(Builder $query, Forms\Get $get) => $query
                                ->when($get('type'), function ($query, $type) {
                                    $allowedParentTypes = TeamType::from($type)
                                        ->getAllowedParentTypes();
                                    if (!empty($allowedParentTypes)) {
                                        $query->whereIn('type', $allowedParentTypes);
                                    }
                                })
                        )
                        ->searchable()
                        ->preload()
                        ->hidden(fn(Forms\Get $get) => 
                            $get('type') === TeamType::Enterprise->value
                        )
                        ->helperText(function (Forms\Get $get) {
                            if (!$get('type')) return null;
                            
                            $allowedTypes = TeamType::from($get('type'))
                                ->getAllowedParentTypes();
                            
                            return empty($allowedTypes) 
                                ? 'This type cannot have a parent'
                                : 'Allowed parent types: ' . 
                                  implode(', ', array_map(
                                      fn($type) => $type->getLabel(), 
                                      $allowedTypes
                                  ));
                        }),
                ]),

            Forms\Components\Section::make('Leadership')
                ->schema([
                    Forms\Components\Select::make('executive_id')
                        ->relationship('executive', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('deputy_id')
                        ->relationship('deputy', 'name')
                        ->searchable()
                        ->preload()
                        ->different('executive_id')
                        ->helperText('Deputy must be different from Executive'),
                ]),

            Forms\Components\Section::make('System Information')
                ->schema([
                    Forms\Components\TextInput::make('ulid')
                        ->label('ULID')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Auto-generated unique identifier'),
                ])
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Team $record) => $record->slug),

                Tables\Columns\BadgeColumn::make('type')
                    ->color(fn(Team $record) => $record->type->getColor())
                    ->formatStateUsing(fn($state) => $state->getLabel()),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->placeholder('Root Level'),

                Tables\Columns\TextColumn::make('executive.name')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deputy.name')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Not assigned'),

                Tables\Columns\BadgeColumn::make('state')
                    ->color(fn(Team $record) => $record->state->color())
                    ->formatStateUsing(fn($state) => $state->label()),

                Tables\Columns\TextColumn::make('ulid')
                    ->label('ULID')
                    ->copyable()
                    ->copyMessage('ULID copied')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(TeamType::class),
                
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_public')
                    ->label('View Public')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Team $record) => $record->getPublicUrl())
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('copy_ulid')
                    ->label('Copy ULID')
                    ->icon('heroicon-o-clipboard')
                    ->action(function (Team $record) {
                        // This would typically use JavaScript to copy to clipboard
                        // For now, we'll just show a notification
                        \Filament\Notifications\Notification::make()
                            ->title('ULID: ' . $record->ulid)
                            ->success()
                            ->send();
                    }),
                
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['parent', 'executive', 'deputy']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'ulid', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Type' => $record->type->getLabel(),
            'Parent' => $record->parent?->name ?? 'Root Level',
            'Executive' => $record->executive->name,
            'ULID' => $record->ulid,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record:ulid}/edit'),
            'view' => Pages\ViewTeam::route('/{record:ulid}'),
        ];
    }
}
```

## 5. Routes for Public Access

```php
<?php

// routes/web.php

use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('teams')->name('teams.')->group(function () {
    Route::get('/{team:ulid}', [TeamController::class, 'show'])
        ->name('show');
    
    Route::get('/slug/{slug}', [TeamController::class, 'showBySlug'])
        ->name('show-by-slug');
    
    Route::get('/{team:ulid}/hierarchy', [TeamController::class, 'hierarchy'])
        ->name('hierarchy');
});
```

## 6. Team Controller

```php
<?php

namespace App\Http\Controllers;

use App\Data\TeamHierarchyData;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function show(Team $team)
    {
        $team->load(['parent', 'children', 'executive', 'deputy']);
        
        return view('teams.show', compact('team'));
    }

    public function showBySlug(string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        
        return $this->show($team);
    }

    public function hierarchy(Team $team)
    {
        $hierarchyData = TeamHierarchyData::from([
            'id' => $team->id,
            'ulid' => $team->ulid,
            'name' => $team->name,
            'slug' => $team->slug,
            'type' => $team->type,
            'parent_id' => $team->parent_id,
            'depth' => $team->ancestors()->count(),
            'full_slug_path' => $team->getFullSlugPath(),
            'children' => $team->children()
                ->with(['children', 'executive'])
                ->get()
                ->map(fn($child) => TeamHierarchyData::from($child))
                ->toArray(),
        ]);

        return response()->json($hierarchyData);
    }
}
```

## 7. Factory for Testing

```php
<?php

namespace Database\Factories;

use App\Enums\TeamType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Uid\Ulid;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'ulid' => (string) new Ulid(),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(TeamType::cases()),
            'executive_id' => User::factory(),
            'deputy_id' => $this->faker->boolean(70) ? User::factory() : null,
        ];
    }

    public function enterprise(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Enterprise,
            'parent_id' => null,
        ]);
    }

    public function organisation(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Organisation,
        ]);
    }

    public function division(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Division,
        ]);
    }

    public function department(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Department,
        ]);
    }

    public function project(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Project,
        ]);
    }
}
```

## Key Features Added:

1. **ULID Integration**: 26-character unique identifiers for public URLs
2. **Slug Generation**: Hierarchical slugs with type prefixes
3. **Dual Route Binding**: Support for both ULID and slug-based URLs
4. **Enhanced Filament UI**: Better form organization and validation
5. **Public URLs**: Clean URLs for external access
6. **Search Enhancement**: Global search includes ULID and slug
7. **Factory Support**: Testing with proper ULID generation

The implementation maintains all previous functionality while adding robust slug and ULID support for better URL management and external access patterns.


---

### User

names must be unique within their graph
slugs are generated from name and name of parent


---

### Assistant (claude-4-sonnet)

I'll update the implementation to enforce unique names within each graph and generate slugs from name + parent name. Here are the key changes:

## 1. Updated Team Model

```php
<?php

namespace App\Models;

use App\Enums\TeamType;
use App\States\TeamState;
use App\ValueObjects\TeamHierarchy;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\WithData;
use Spatie\ModelStates\HasStates;
use Spatie\ModelStatus\HasStatuses;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Symfony\Component\Uid\Ulid;
use Tightenco\Parental\HasParent;

class Team extends Model
{
    use HasRecursiveRelationships,
        HasStates,
        HasStatuses,
        HasSlug,
        SoftDeletes,
        WithData;

    protected $fillable = [
        'name',
        'slug',
        'ulid',
        'description',
        'type',
        'parent_id',
        'executive_id',
        'deputy_id',
    ];

    protected $casts = [
        'type' => TeamType::class,
        'state' => TeamState::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Team $team) {
            if (!$team->ulid) {
                $team->ulid = (string) new Ulid();
            }
            $team->validateUniqueNameInGraph();
            $team->validateHierarchyRules();
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name') || $team->isDirty('parent_id')) {
                $team->validateUniqueNameInGraph();
            }
            $team->validateHierarchyRules();
            $team->validateExecutiveDeputyConstraint();
        });

        static::saved(function (Team $team) {
            // Regenerate slugs for descendants when parent name changes
            if ($team->isDirty('name')) {
                $team->updateDescendantSlugs();
            }
        });
    }

    // Slug Configuration
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('generateSlugSource')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(150)
            ->usingSeparator('-')
            ->usingLanguage('en');
    }

    public function generateSlugSource(): string
    {
        $parentName = $this->parent?->name ?? '';
        
        return $parentName 
            ? "{$parentName} {$this->name}"
            : $this->name;
    }

    // Route Key Name for URL binding
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    // Alternative route binding by slug
    public function resolveRouteBinding($value, $field = null)
    {
        return match ($field) {
            'slug' => $this->where('slug', $value)->first(),
            'ulid' => $this->where('ulid', $value)->first(),
            default => parent::resolveRouteBinding($value, $field),
        };
    }

    // Relationships
    public function executive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executive_id');
    }

    public function deputy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deputy_id');
    }

    // Validation Methods
    protected function validateUniqueNameInGraph(): void
    {
        $rootId = $this->getRootId();
        
        $query = static::query()
            ->where('name', $this->name)
            ->where(function ($query) use ($rootId) {
                if ($rootId) {
                    // For non-root teams, check within the same graph
                    $query->whereHas('ancestors', function ($ancestorQuery) use ($rootId) {
                        $ancestorQuery->where('id', $rootId);
                    })->orWhere('id', $rootId);
                } else {
                    // For root teams, check only other root teams
                    $query->whereNull('parent_id');
                }
            });

        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => ['The name must be unique within the team hierarchy graph.']
            ]);
        }
    }

    protected function getRootId(): ?int
    {
        if (!$this->parent_id) {
            return null; // This is a root team
        }

        // Get the root ancestor
        $root = $this->ancestors()->whereNull('parent_id')->first();
        return $root?->id;
    }

    protected function validateHierarchyRules(): void
    {
        $hierarchy = new TeamHierarchy($this);
        $hierarchy->validate();
    }

    protected function validateExecutiveDeputyConstraint(): void
    {
        if ($this->executive_id && $this->deputy_id && 
            $this->executive_id === $this->deputy_id) {
            throw new \InvalidArgumentException(
                'Executive and Deputy cannot be the same person'
            );
        }
    }

    protected function updateDescendantSlugs(): void
    {
        $this->descendants()->each(function (Team $descendant) {
            $descendant->generateSlug();
            $descendant->saveQuietly(); // Avoid triggering events
        });
    }

    // Helper Methods
    public function getGraphRoot(): ?Team
    {
        return $this->ancestors()->whereNull('parent_id')->first() ?? 
               ($this->parent_id ? null : $this);
    }

    public function getGraphTeams(): \Illuminate\Database\Eloquent\Collection
    {
        $root = $this->getGraphRoot();
        
        if (!$root) {
            return collect([$this]);
        }

        return $root->descendantsAndSelf();
    }

    public function isNameUniqueInGraph(string $name, ?int $excludeId = null): bool
    {
        $rootId = $this->getRootId();
        
        $query = static::query()
            ->where('name', $name)
            ->where(function ($query) use ($rootId) {
                if ($rootId) {
                    $query->whereHas('ancestors', function ($ancestorQuery) use ($rootId) {
                        $ancestorQuery->where('id', $rootId);
                    })->orWhere('id', $rootId);
                } else {
                    $query->whereNull('parent_id');
                }
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }

    // Filament Helpers
    public function getTypeColor(): string
    {
        return match ($this->type) {
            TeamType::Enterprise => Color::Purple[500],
            TeamType::Organisation => Color::Blue[500],
            TeamType::Division => Color::Green[500],
            TeamType::Department => Color::Orange[500],
            TeamType::Project => Color::Gray[500],
        };
    }

    public function getTypeLabel(): string
    {
        return $this->type->getLabel();
    }

    public function getStatusColor(): string
    {
        return $this->latestStatus()?->color ?? Color::Gray[400];
    }

    public function getAvatarUrl(): ?string
    {
        return $this->executive?->avatar_url;
    }

    public function getFullSlugPath(): string
    {
        return $this->ancestorsAndSelf()
            ->orderBy('depth')
            ->pluck('slug')
            ->filter()
            ->implode('/');
    }

    public function getPublicUrl(): string
    {
        return route('teams.show', ['team' => $this->ulid]);
    }

    public function getSlugUrl(): string
    {
        return route('teams.show-by-slug', ['slug' => $this->slug]);
    }

    // Scopes
    public function scopeOfType($query, TeamType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByUlid($query, string $ulid)
    {
        return $query->where('ulid', $ulid);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeInGraph($query, Team $rootTeam)
    {
        return $query->where(function ($query) use ($rootTeam) {
            $query->whereHas('ancestors', function ($ancestorQuery) use ($rootTeam) {
                $ancestorQuery->where('id', $rootTeam->id);
            })->orWhere('id', $rootTeam->id);
        });
    }
}
```

## 2. Updated Migration with Unique Constraint

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('name');
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('teams');
            $table->foreignId('executive_id')->constrained('users');
            $table->foreignId('deputy_id')->nullable()->constrained('users');
            $table->string('state')->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'parent_id']);
            $table->index('state');
            $table->index('ulid');
            $table->index('slug');
            $table->index(['name', 'parent_id']);
        });

        // Add a partial unique constraint for root teams (where parent_id is null)
        DB::statement('CREATE UNIQUE INDEX teams_name_root_unique ON teams (name) WHERE parent_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
```

## 3. Custom Validation Rule for Graph Uniqueness

```php
<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueNameInGraph implements ValidationRule
{
    public function __construct(
        private ?Team $team = null,
        private ?int $parentId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $query = Team::where('name', $value);

        // Exclude current team if updating
        if ($this->team) {
            $query->where('id', '!=', $this->team->id);
        }

        // Determine the root of the graph
        $rootId = null;
        if ($this->parentId) {
            $parent = Team::find($this->parentId);
            $rootId = $parent?->getGraphRoot()?->id;
        } elseif ($this->team && $this->team->parent_id) {
            $rootId = $this->team->getGraphRoot()?->id;
        }

        // Apply graph constraint
        $query->where(function ($query) use ($rootId) {
            if ($rootId) {
                // Check within the same graph
                $query->whereHas('ancestors', function ($ancestorQuery) use ($rootId) {
                    $ancestorQuery->where('id', $rootId);
                })->orWhere('id', $rootId);
            } else {
                // Check only root teams
                $query->whereNull('parent_id');
            }
        });

        if ($query->exists()) {
            $fail('The :attribute must be unique within the team hierarchy graph.');
        }
    }
}
```

## 4. Updated Filament Resource

```php
<?php

namespace App\Filament\Resources;

use App\Data\TeamData;
use App\Enums\TeamType;
use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use App\Rules\UniqueNameInGraph;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->rules([
                            fn(Forms\Get $get, ?Model $record) => new UniqueNameInGraph(
                                $record,
                                $get('parent_id')
                            )
                        ])
                        ->validationMessages([
                            'unique_name_in_graph' => 'This name is already used within the team hierarchy.',
                        ])
                        ->helperText('Name must be unique within the team hierarchy graph'),

                    Forms\Components\TextInput::make('slug')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Auto-generated from name and parent name')
                        ->visibleOn('edit'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Hierarchy')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->options(TeamType::class)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if ($state === TeamType::Enterprise->value) {
                                $set('parent_id', null);
                            }
                        }),

                    Forms\Components\Select::make('parent_id')
                        ->relationship(
                            'parent',
                            'name',
                            fn(Builder $query, Forms\Get $get) => $query
                                ->when($get('type'), function ($query, $type) {
                                    $allowedParentTypes = TeamType::from($type)
                                        ->getAllowedParentTypes();
                                    if (!empty($allowedParentTypes)) {
                                        $query->whereIn('type', $allowedParentTypes);
                                    }
                                })
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->hidden(fn(Forms\Get $get) => 
                            $get('type') === TeamType::Enterprise->value
                        )
                        ->helperText(function (Forms\Get $get) {
                            if (!$get('type')) return null;
                            
                            $allowedTypes = TeamType::from($get('type'))
                                ->getAllowedParentTypes();
                            
                            return empty($allowedTypes) 
                                ? 'This type cannot have a parent'
                                : 'Allowed parent types: ' . 
                                  implode(', ', array_map(
                                      fn($type) => $type->getLabel(), 
                                      $allowedTypes
                                  ));
                        }),
                ]),

            Forms\Components\Section::make('Leadership')
                ->schema([
                    Forms\Components\Select::make('executive_id')
                        ->relationship('executive', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Forms\Components\Select::make('deputy_id')
                        ->relationship('deputy', 'name')
                        ->searchable()
                        ->preload()
                        ->different('executive_id')
                        ->helperText('Deputy must be different from Executive'),
                ]),

            Forms\Components\Section::make('System Information')
                ->schema([
                    Forms\Components\TextInput::make('ulid')
                        ->label('ULID')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Auto-generated unique identifier'),

                    Forms\Components\Placeholder::make('graph_info')
                        ->label('Graph Information')
                        ->content(function (?Model $record) {
                            if (!$record) return 'New team';
                            
                            $root = $record->getGraphRoot();
                            $teamsInGraph = $record->getGraphTeams()->count();
                            
                            return $root 
                                ? "Part of '{$root->name}' graph ({$teamsInGraph} teams)"
                                : "Root team ({$teamsInGraph} teams in graph)";
                        }),
                ])
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn(Team $record) => $record->slug),

                Tables\Columns\BadgeColumn::make('type')
                    ->color(fn(Team $record) => $record->type->getColor())
                    ->formatStateUsing(fn($state) => $state->getLabel()),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->sortable()
                    ->placeholder('Root Level')
                    ->description(function (Team $record) {
                        $root = $record->getGraphRoot();
                        return $root && $root->id !== $record->id 
                            ? "Graph: {$root->name}" 
                            : ($record->parent_id ? null : 'Graph Root');
                    }),

                Tables\Columns\TextColumn::make('executive.name')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('deputy.name')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Not assigned'),

                Tables\Columns\BadgeColumn::make('state')
                    ->color(fn(Team $record) => $record->state->color())
                    ->formatStateUsing(fn($state) => $state->label()),

                Tables\Columns\TextColumn::make('graph_size')
                    ->label('Graph Size')
                    ->getStateUsing(fn(Team $record) => $record->getGraphTeams()->count())
                    ->suffix(' teams')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(TeamType::class),
                
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'archived' => 'Archived',
                    ]),

                Tables\Filters\Filter::make('root_only')
                    ->label('Root Teams Only')
                    ->query(fn(Builder $query) => $query->whereNull('parent_id'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_graph')
                    ->label('View Graph')
                    ->icon('heroicon-o-share')
                    ->url(fn(Team $record) => route('teams.hierarchy', $record->ulid))
                    ->openUrlInNewTab(),
                
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Tables\Grouping\Group::make('parent.name')
                    ->label('Parent Team')
                    ->collapsible(),
                
                Tables\Grouping\Group::make('type')
                    ->label('Team Type')
                    ->collapsible(),
            ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['parent', 'executive', 'deputy']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'ulid', 'description'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $root = $record->getGraphRoot();
        
        return [
            'Type' => $record->type->getLabel(),
            'Parent' => $record->parent?->name ?? 'Root Level',
            'Graph' => $root?->name ?? 'Independent',
            'Executive' => $record->executive->name,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record:ulid}/edit'),
            'view' => Pages\ViewTeam::route('/{record:ulid}'),
        ];
    }
}
```

## 5. Updated Factory

```php
<?php

namespace Database\Factories;

use App\Enums\TeamType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Symfony\Component\Uid\Ulid;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'ulid' => (string) new Ulid(),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(TeamType::cases()),
            'executive_id' => User::factory(),
            'deputy_id' => $this->faker->boolean(70) ? User::factory() : null,
        ];
    }

    public function withUniqueNameInGraph(?Team $parent = null): static
    {
        return $this->state(function (array $attributes) use ($parent) {
            $baseName = $this->faker->company();
            $name = $baseName;
            $counter = 1;

            // Ensure uniqueness within the graph
            while (!$this->isNameUniqueInGraph($name, $parent)) {
                $name = "{$baseName} {$counter}";
                $counter++;
            }

            return ['name' => $name];
        });
    }

    private function isNameUniqueInGraph(string $name, ?Team $parent): bool
    {
        $query = Team::where('name', $name);

        if ($parent) {
            $rootId = $parent->getGraphRoot()?->id;
            if ($rootId) {
                $query->where(function ($query) use ($rootId) {
                    $query->whereHas('ancestors', function ($ancestorQuery) use ($rootId) {
                        $ancestorQuery->where('id', $rootId);
                    })->orWhere('id', $rootId);
                });
            }
        } else {
            $query->whereNull('parent_id');
        }

        return !$query->exists();
    }

    public function enterprise(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Enterprise,
            'parent_id' => null,
        ]);
    }

    public function organisation(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Organisation,
        ]);
    }

    public function division(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Division,
        ]);
    }

    public function department(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Department,
        ]);
    }

    public function project(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => TeamType::Project,
        ]);
    }
}
```

## Key Changes Made:

1. **Unique Names in Graph**: Added validation to ensure names are unique within each hierarchy graph
2. **Slug Generation**: Slugs now generate from `parent_name + team_name`
3. **Database Constraints**: Added partial unique index for root team names
4. **Custom Validation Rule**: `UniqueNameInGraph` rule for Filament forms
5. **Graph Helpers**: Methods to identify graph root and all teams in a graph
6. **Cascade Slug Updates**: When parent names change, descendant slugs update automatically
7. **Enhanced UI**: Filament resource shows graph information and validation messages
8. **Factory Updates**: Ensures unique names when creating test data

The implementation now enforces that team names must be unique within their respective hierarchy graphs, and slugs are properly generated from the combination of parent and team names.


---
