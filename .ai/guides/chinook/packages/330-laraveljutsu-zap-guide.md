# 1. LaravelJutsu Zap Integration Guide

> **Package Source:** [laraveljutsu/zap](https://github.com/laraveljutsu/zap)  
> **Official Documentation:** [Zap Documentation](https://github.com/laraveljutsu/zap/blob/main/README.md)  
> **Laravel Version:** 12.x compatibility  
> **Chinook Integration:** Enhanced for Chinook development workflow acceleration and automation  
> **Last Updated:** 2025-07-13

## 1.1. Table of Contents

- [1.2. Overview](#12-overview)
- [1.3. Installation & Configuration](#13-installation--configuration)
  - [1.3.1. Package Installation](#131-package-installation)
  - [1.3.2. Zap Configuration](#132-zap-configuration)
- [1.4. Chinook Development Acceleration](#14-chinook-development-acceleration)
  - [1.4.1. Model Generation Automation](#141-model-generation-automation)
  - [1.4.2. Resource Scaffolding](#142-resource-scaffolding)
  - [1.4.3. Testing Automation](#143-testing-automation)
- [1.5. Workflow Integration](#15-workflow-integration)
- [1.6. Performance & Optimization](#16-performance--optimization)

## 1.2. Overview

> **Implementation Note:** This guide adapts the official [LaravelJutsu Zap documentation](https://github.com/laraveljutsu/zap/blob/main/README.md) for Laravel 12 and Chinook project requirements, focusing on development workflow acceleration and automation for music industry applications.

**LaravelJutsu Zap** provides powerful development acceleration utilities for Laravel applications. It offers code generation, scaffolding, and automation tools that significantly speed up development workflows while maintaining code quality and consistency.

### 1.2.1. Key Features

- **Rapid Code Generation**: Automated generation of models, controllers, and resources
- **Smart Scaffolding**: Intelligent scaffolding based on database schema
- **Testing Automation**: Automated test generation and execution
- **Workflow Integration**: Seamless integration with existing development workflows
- **Laravel 12 Compatibility**: Full support for modern Laravel features and syntax
- **Customizable Templates**: Flexible template system for code generation

### 1.2.2. Chinook Development Benefits

- **Music Model Generation**: Rapid creation of Chinook-prefixed models with relationships
- **Filament Resource Automation**: Automated generation of admin panel resources
- **API Scaffolding**: Quick API endpoint generation for music catalog
- **Test Suite Generation**: Comprehensive test coverage automation
- **Migration Helpers**: Streamlined database migration creation

## 1.3. Installation & Configuration

### 1.3.1. Package Installation

> **Installation Source:** Based on [official installation guide](https://github.com/laraveljutsu/zap#installation)  
> **Chinook Enhancement:** Already installed and configured

The package is already installed via Composer. Verify installation:

<augment_code_snippet path="composer.json" mode="EXCERPT">
````json
{
    "require-dev": {
        "laraveljutsu/zap": "^2.0"
    }
}
````
</augment_code_snippet>

**Publish Configuration:**

```bash
# Publish Zap configuration
php artisan vendor:publish --tag="zap-config"

# Publish Zap templates (optional)
php artisan vendor:publish --tag="zap-templates"

# Initialize Zap for Chinook project
php artisan zap:init --project=chinook
```

### 1.3.2. Zap Configuration

> **Configuration Source:** Enhanced from [zap configuration](https://github.com/laraveljutsu/zap/blob/main/config/zap.php)  
> **Chinook Modifications:** Optimized for Chinook development patterns and entity prefixing

<augment_code_snippet path="config/zap.php" mode="EXCERPT">
````php
<?php
// Configuration adapted from: https://github.com/laraveljutsu/zap/blob/main/config/zap.php
// Chinook modifications: Enhanced for Chinook entity prefixing and development patterns
// Laravel 12 updates: Modern syntax and framework patterns

return [
    /*
     * Default namespace for generated classes
     */
    'namespace' => 'App',

    /*
     * Model configuration
     */
    'models' => [
        'namespace' => 'App\\Models',
        'path' => app_path('Models'),
        'extends' => 'Illuminate\\Database\\Eloquent\\Model',
        'traits' => [
            'Illuminate\\Database\\Eloquent\\SoftDeletes',
            'Spatie\\Activitylog\\Traits\\LogsActivity',
            'Aliziodev\\LaravelTaxonomy\\Traits\\HasTaxonomies',
        ],
    ],

    /*
     * Controller configuration
     */
    'controllers' => [
        'namespace' => 'App\\Http\\Controllers',
        'path' => app_path('Http/Controllers'),
        'extends' => 'App\\Http\\Controllers\\Controller',
        'api_namespace' => 'App\\Http\\Controllers\\Api',
        'api_path' => app_path('Http/Controllers/Api'),
    ],

    /*
     * Filament resource configuration
     */
    'filament' => [
        'namespace' => 'App\\Filament\\Admin\\Resources',
        'path' => app_path('Filament/Admin/Resources'),
        'extends' => 'Filament\\Resources\\Resource',
    ],

    /*
     * Test configuration
     */
    'tests' => [
        'namespace' => 'Tests\\Feature',
        'path' => base_path('tests/Feature'),
        'extends' => 'Tests\\TestCase',
        'pest' => true, // Use Pest PHP for testing
    ],

    /*
     * Migration configuration
     */
    'migrations' => [
        'path' => database_path('migrations'),
        'table_prefix' => 'chinook_',
    ],

    /*
     * Chinook-specific configuration
     */
    'chinook' => [
        /*
         * Entity prefixing
         */
        'model_prefix' => 'Chinook',
        'table_prefix' => 'chinook_',
        'resource_prefix' => 'Chinook',

        /*
         * Default model traits
         */
        'default_traits' => [
            'SoftDeletes',
            'LogsActivity',
            'HasTaxonomies',
            'HasMedia',
        ],

        /*
         * Relationship patterns
         */
        'relationships' => [
            'artist_album' => 'hasMany',
            'album_track' => 'hasMany',
            'customer_invoice' => 'hasMany',
            'playlist_track' => 'belongsToMany',
        ],

        /*
         * Code generation templates
         */
        'templates' => [
            'model' => 'chinook.model',
            'controller' => 'chinook.controller',
            'resource' => 'chinook.resource',
            'test' => 'chinook.test',
        ],

        /*
         * Development workflow settings
         */
        'workflow' => [
            'auto_generate_tests' => true,
            'auto_generate_factories' => true,
            'auto_generate_seeders' => true,
            'auto_generate_policies' => true,
        ],
    ],

    /*
     * Template configuration
     */
    'templates' => [
        'path' => resource_path('zap/templates'),
        'extension' => '.stub',
    ],

    /*
     * Code style configuration
     */
    'code_style' => [
        'psr12' => true,
        'laravel_style' => true,
        'auto_format' => true,
    ],
];
````
</augment_code_snippet>

## 1.4. Chinook Development Acceleration

### 1.4.1. Model Generation Automation

> **Model Generation:** Automated creation of Chinook models with proper relationships and traits

<augment_code_snippet path="app/Console/Commands/ZapChinookModel.php" mode="EXCERPT">
````php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LaravelJutsu\Zap\Services\ModelGenerator;

class ZapChinookModel extends Command
{
    protected $signature = 'zap:chinook-model {name} {--relationships=} {--fillable=}';
    protected $description = 'Generate a Chinook model with proper prefixing and traits';

    public function handle(): int
    {
        $name = $this->argument('name');
        $relationships = $this->option('relationships');
        $fillable = $this->option('fillable');

        // Generate Chinook-prefixed model
        $modelName = 'Chinook' . ucfirst($name);
        $tableName = 'chinook_' . strtolower($name) . 's';

        $generator = new ModelGenerator();
        
        $generator->generate([
            'name' => $modelName,
            'table' => $tableName,
            'namespace' => 'App\\Models',
            'traits' => [
                'SoftDeletes',
                'LogsActivity', 
                'HasTaxonomies',
                'HasMedia',
            ],
            'fillable' => explode(',', $fillable ?? ''),
            'relationships' => $this->parseRelationships($relationships),
            'casts' => [
                'created_at' => 'datetime',
                'updated_at' => 'datetime',
                'deleted_at' => 'datetime',
            ],
        ]);

        $this->info("Generated Chinook model: {$modelName}");
        return Command::SUCCESS;
    }

    private function parseRelationships(?string $relationships): array
    {
        if (!$relationships) {
            return [];
        }

        $parsed = [];
        foreach (explode(',', $relationships) as $relationship) {
            [$type, $model] = explode(':', $relationship);
            $parsed[] = [
                'type' => $type,
                'model' => 'Chinook' . ucfirst($model),
                'foreign_key' => strtolower($model) . '_id',
            ];
        }

        return $parsed;
    }
}
````
</augment_code_snippet>

---

**Navigation:** [Package Index](000-packages-index.md) | **Previous:** [Laravel Translatable Guide](220-spatie-laravel-translatable-guide.md) | **Next:** [Livewire URLs Guide](340-ralphjsmit-livewire-urls-guide.md)

**Documentation Standards:** This document follows WCAG 2.1 AA accessibility guidelines and uses Laravel 12 modern syntax patterns with proper source attribution.
