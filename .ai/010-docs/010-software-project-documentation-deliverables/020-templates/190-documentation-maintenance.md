---
owner: "[DOCUMENTATION_LEAD]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft"
version: "1.0.0"
target_audience: "Junior developers with 6 months-2 years experience"
maintenance_cycle: "Monthly"
---

# Documentation Maintenance Guide
## [PROJECT_NAME]

**Estimated Reading Time:** 25 minutes

## Overview

This guide establishes comprehensive procedures for maintaining project documentation throughout the software development lifecycle. It ensures documentation remains accurate, current, and valuable for [PROJECT_NAME] built with Laravel 12.x and FilamentPHP v4.

### Maintenance Objectives
- **Accuracy**: Keep documentation synchronized with code changes
- **Relevance**: Ensure documentation serves current project needs
- **Accessibility**: Maintain clear, searchable, and well-organized documentation
- **Compliance**: Meet regulatory and audit requirements
- **Knowledge Preservation**: Capture institutional knowledge and decisions

### Documentation Lifecycle
- **Creation**: Initial documentation during development
- **Review**: Regular accuracy and completeness reviews
- **Update**: Modifications based on changes and feedback
- **Archive**: Proper archival of outdated documentation
- **Retirement**: Removal of obsolete documentation

## Documentation Inventory

### Core Documentation Categories

#### Technical Documentation
- **API Documentation**: Endpoint specifications and examples
- **Architecture Documentation**: System design and component relationships
- **Database Documentation**: Schema, relationships, and data flow
- **Security Documentation**: Security controls and compliance procedures
- **Deployment Documentation**: Infrastructure and deployment procedures

#### Process Documentation
- **Development Workflows**: Coding standards and review processes
- **Testing Procedures**: Test strategies and execution guidelines
- **Release Management**: Version control and release procedures
- **Incident Response**: Emergency procedures and escalation paths
- **Maintenance Procedures**: Routine maintenance and monitoring

#### User Documentation
- **User Guides**: End-user instructions and tutorials
- **Admin Guides**: Administrative procedures and configurations
- **API Guides**: Developer integration documentation
- **Troubleshooting Guides**: Common issues and solutions
- **FAQ Documentation**: Frequently asked questions and answers

### Documentation Tracking Matrix

| Document Type | Owner | Review Frequency | Last Updated | Next Review | Status |
|---------------|-------|------------------|--------------|-------------|---------|
| **API Documentation** | [TECH_LEAD] | Monthly | [YYYY-MM-DD] | [YYYY-MM-DD] | Current |
| **Architecture Docs** | [ARCHITECT] | Quarterly | [YYYY-MM-DD] | [YYYY-MM-DD] | Current |
| **Security Procedures** | [SECURITY_LEAD] | Monthly | [YYYY-MM-DD] | [YYYY-MM-DD] | Current |
| **User Guides** | [PRODUCT_OWNER] | Bi-monthly | [YYYY-MM-DD] | [YYYY-MM-DD] | Needs Update |
| **Deployment Guides** | [DEVOPS_LEAD] | Monthly | [YYYY-MM-DD] | [YYYY-MM-DD] | Current |

## Maintenance Procedures

### Monthly Maintenance Tasks

#### Documentation Accuracy Review
```bash
#!/bin/bash
# scripts/monthly-doc-review.sh

echo "=== Monthly Documentation Review - $(date) ==="

# 1. Check for outdated documentation
echo "1. Checking for outdated documentation..."
find .ai/ -name "*.md" -mtime +90 -exec echo "Outdated: {}" \;

# 2. Validate code examples
echo "2. Validating code examples..."
./scripts/validate-code-examples.sh

# 3. Check for broken links
echo "3. Checking for broken links..."
./scripts/check-links.sh

# 4. Review documentation metrics
echo "4. Generating documentation metrics..."
./scripts/doc-metrics.sh

# 5. Update documentation index
echo "5. Updating documentation index..."
./scripts/update-doc-index.sh

echo "=== Monthly Documentation Review Complete ==="
```

#### Code Example Validation
```bash
#!/bin/bash
# scripts/validate-code-examples.sh

echo "=== Validating Code Examples ==="

# Find all PHP code blocks in documentation
grep -r "```php" .ai/ | while read -r line; do
    file=$(echo "$line" | cut -d: -f1)
    echo "Checking PHP examples in: $file"
    
    # Extract and validate PHP syntax
    awk '/```php/,/```/' "$file" | grep -v '```' | php -l
done

# Find all Laravel Artisan commands
grep -r "php artisan" .ai/ | while read -r line; do
    file=$(echo "$line" | cut -d: -f1)
    command=$(echo "$line" | grep -o "php artisan [^'\"]*")
    echo "Found command in $file: $command"
    
    # Validate command exists (in development environment)
    if [[ "$APP_ENV" == "development" ]]; then
        $command --help > /dev/null 2>&1 || echo "WARNING: Command may not exist: $command"
    fi
done

echo "=== Code Example Validation Complete ==="
```

### Quarterly Maintenance Tasks

#### Comprehensive Documentation Audit
```php
<?php
// scripts/documentation-audit.php

class DocumentationAudit
{
    private array $auditResults = [];
    
    public function runAudit(): array
    {
        $this->auditResults = [
            'coverage' => $this->auditCoverage(),
            'accuracy' => $this->auditAccuracy(),
            'accessibility' => $this->auditAccessibility(),
            'compliance' => $this->auditCompliance(),
            'recommendations' => $this->generateRecommendations(),
        ];
        
        return $this->auditResults;
    }
    
    private function auditCoverage(): array
    {
        $codeFiles = $this->getCodeFiles();
        $documentedFiles = $this->getDocumentedFiles();
        
        $coverage = count($documentedFiles) / count($codeFiles) * 100;
        
        return [
            'total_code_files' => count($codeFiles),
            'documented_files' => count($documentedFiles),
            'coverage_percentage' => round($coverage, 2),
            'undocumented_files' => array_diff($codeFiles, $documentedFiles),
        ];
    }
    
    private function auditAccuracy(): array
    {
        $inaccuracies = [];
        
        // Check for outdated version references
        $docs = glob('.ai/**/*.md');
        foreach ($docs as $doc) {
            $content = file_get_contents($doc);
            
            // Check for old Laravel versions
            if (preg_match('/Laravel [0-9]+\.x/', $content, $matches)) {
                if (!str_contains($matches[0], '12.x')) {
                    $inaccuracies[] = [
                        'file' => $doc,
                        'issue' => 'Outdated Laravel version reference',
                        'found' => $matches[0],
                    ];
                }
            }
            
            // Check for old FilamentPHP versions
            if (preg_match('/FilamentPHP v[0-9]+/', $content, $matches)) {
                if (!str_contains($matches[0], 'v4')) {
                    $inaccuracies[] = [
                        'file' => $doc,
                        'issue' => 'Outdated FilamentPHP version reference',
                        'found' => $matches[0],
                    ];
                }
            }
        }
        
        return [
            'total_inaccuracies' => count($inaccuracies),
            'inaccuracies' => $inaccuracies,
        ];
    }
    
    private function auditAccessibility(): array
    {
        $accessibilityIssues = [];
        
        $docs = glob('.ai/**/*.md');
        foreach ($docs as $doc) {
            $content = file_get_contents($doc);
            $lines = explode("\n", $content);
            
            // Check for missing YAML front-matter
            if (!str_starts_with(trim($lines[0]), '---')) {
                $accessibilityIssues[] = [
                    'file' => $doc,
                    'issue' => 'Missing YAML front-matter',
                ];
            }
            
            // Check for missing target audience
            if (!str_contains($content, 'target_audience')) {
                $accessibilityIssues[] = [
                    'file' => $doc,
                    'issue' => 'Missing target audience specification',
                ];
            }
            
            // Check for missing estimated reading time
            if (!str_contains($content, 'Estimated Reading Time')) {
                $accessibilityIssues[] = [
                    'file' => $doc,
                    'issue' => 'Missing estimated reading time',
                ];
            }
        }
        
        return [
            'total_issues' => count($accessibilityIssues),
            'issues' => $accessibilityIssues,
        ];
    }
    
    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        // Analyze audit results and generate recommendations
        if ($this->auditResults['coverage']['coverage_percentage'] < 80) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'coverage',
                'recommendation' => 'Increase documentation coverage to at least 80%',
                'action' => 'Document missing files and components',
            ];
        }
        
        if ($this->auditResults['accuracy']['total_inaccuracies'] > 5) {
            $recommendations[] = [
                'priority' => 'medium',
                'category' => 'accuracy',
                'recommendation' => 'Address documentation inaccuracies',
                'action' => 'Update outdated version references and examples',
            ];
        }
        
        return $recommendations;
    }
}

// Run the audit
$audit = new DocumentationAudit();
$results = $audit->runAudit();

// Generate report
file_put_contents(
    'documentation-audit-' . date('Y-m-d') . '.json',
    json_encode($results, JSON_PRETTY_PRINT)
);

echo "Documentation audit complete. Report saved to documentation-audit-" . date('Y-m-d') . ".json\n";
```

## Documentation Standards

### Writing Standards

#### Style Guide
- **Tone**: Professional, clear, and helpful
- **Audience**: Junior developers with 6 months-2 years experience
- **Language**: Simple, direct language avoiding jargon
- **Structure**: Logical flow with clear headings and sections
- **Examples**: Practical, working code examples

#### Formatting Standards
```markdown
---
owner: "[DOCUMENT_OWNER]"
last_reviewed: "[YYYY-MM-DD]"
status: "draft|approved|deprecated"
version: "X.Y.Z"
target_audience: "Junior developers with 6 months-2 years experience"
---

# Document Title
## Subtitle

**Estimated Reading Time:** X minutes

## Overview

Brief description of the document purpose and scope.

### Key Points
- Use bullet points under 20 words
- Maintain consistent hierarchy (•, ◦, ▪)
- Focus on actionable information

## Code Examples

```php
<?php
// Always include complete, working examples
// Add comments explaining complex logic
// Use realistic variable names and scenarios

class ExampleClass
{
    public function exampleMethod(): string
    {
        return 'Clear, practical example';
    }
}
```

## References

1. Laravel Documentation. (2025). *Laravel 12.x Documentation*. Retrieved from https://laravel.com/docs/12.x
2. FilamentPHP Documentation. (2025). *FilamentPHP v4 Documentation*. Retrieved from https://filamentphp.com/docs/4.x
```

### Quality Assurance Checklist

#### Pre-Publication Review
- [ ] **Accuracy**: All information is current and correct
- [ ] **Completeness**: All necessary information is included
- [ ] **Clarity**: Content is clear and understandable
- [ ] **Consistency**: Follows established style and format standards
- [ ] **Examples**: Code examples are tested and working
- [ ] **Links**: All links are functional and relevant
- [ ] **Metadata**: YAML front-matter is complete and accurate

#### Post-Publication Validation
- [ ] **Accessibility**: Document is easily findable and navigable
- [ ] **Feedback**: User feedback has been collected and addressed
- [ ] **Usage**: Document usage metrics are positive
- [ ] **Updates**: Document reflects current system state
- [ ] **Integration**: Document integrates well with related documentation

## Automated Documentation Tools

### Documentation Generation
```php
<?php
// scripts/generate-api-docs.php

use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

class ApiDocumentationGenerator
{
    public function generateApiDocs(): void
    {
        $routes = Route::getRoutes();
        $apiDocs = [];
        
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $apiDocs[] = $this->documentRoute($route);
            }
        }
        
        $this->saveApiDocumentation($apiDocs);
    }
    
    private function documentRoute($route): array
    {
        $action = $route->getAction();
        $controller = $action['controller'] ?? null;
        
        if (!$controller) {
            return [];
        }
        
        [$controllerClass, $method] = explode('@', $controller);
        
        return [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'controller' => $controllerClass,
            'method' => $method,
            'middleware' => $route->middleware(),
            'parameters' => $this->extractParameters($route),
            'description' => $this->extractDescription($controllerClass, $method),
            'examples' => $this->generateExamples($route),
        ];
    }
    
    private function extractDescription(string $controllerClass, string $method): string
    {
        try {
            $reflection = new ReflectionClass($controllerClass);
            $methodReflection = $reflection->getMethod($method);
            $docComment = $methodReflection->getDocComment();
            
            if ($docComment) {
                // Extract description from PHPDoc
                preg_match('/\*\s*(.+)/', $docComment, $matches);
                return $matches[1] ?? 'No description available';
            }
        } catch (Exception $e) {
            // Handle reflection errors
        }
        
        return 'No description available';
    }
    
    private function saveApiDocumentation(array $apiDocs): void
    {
        $markdown = $this->generateMarkdown($apiDocs);
        file_put_contents('.ai/api-documentation.md', $markdown);
    }
}
```

### Link Validation
```bash
#!/bin/bash
# scripts/check-links.sh

echo "=== Checking Documentation Links ==="

# Find all markdown files
find .ai/ -name "*.md" -type f | while read -r file; do
    echo "Checking links in: $file"
    
    # Extract all URLs from markdown files
    grep -oE '\[.*\]\(http[s]?://[^)]+\)' "$file" | while read -r link; do
        url=$(echo "$link" | sed 's/.*(\(.*\)).*/\1/')
        
        # Check if URL is accessible
        if curl -s --head "$url" | head -n 1 | grep -q "200 OK"; then
            echo "✓ $url"
        else
            echo "✗ $url (in $file)"
        fi
    done
    
    # Check internal links
    grep -oE '\[.*\]\([^http][^)]+\)' "$file" | while read -r link; do
        path=$(echo "$link" | sed 's/.*(\(.*\)).*/\1/')
        
        # Check if internal file exists
        if [ -f ".ai/$path" ] || [ -f "$path" ]; then
            echo "✓ $path (internal)"
        else
            echo "✗ $path (internal, in $file)"
        fi
    done
done

echo "=== Link Check Complete ==="
```

## Documentation Metrics and Analytics

### Usage Analytics
```php
<?php
// scripts/documentation-metrics.php

class DocumentationMetrics
{
    public function generateMetrics(): array
    {
        return [
            'coverage_metrics' => $this->getCoverageMetrics(),
            'quality_metrics' => $this->getQualityMetrics(),
            'usage_metrics' => $this->getUsageMetrics(),
            'maintenance_metrics' => $this->getMaintenanceMetrics(),
        ];
    }
    
    private function getCoverageMetrics(): array
    {
        $totalFiles = count(glob('app/**/*.php'));
        $documentedFiles = count(glob('.ai/**/*.md'));
        
        return [
            'total_code_files' => $totalFiles,
            'total_documentation_files' => $documentedFiles,
            'coverage_ratio' => $documentedFiles / $totalFiles,
            'documentation_density' => $this->calculateDocumentationDensity(),
        ];
    }
    
    private function getQualityMetrics(): array
    {
        $docs = glob('.ai/**/*.md');
        $qualityScores = [];
        
        foreach ($docs as $doc) {
            $qualityScores[] = $this->calculateQualityScore($doc);
        }
        
        return [
            'average_quality_score' => array_sum($qualityScores) / count($qualityScores),
            'quality_distribution' => array_count_values(array_map('round', $qualityScores)),
            'low_quality_documents' => array_filter($qualityScores, fn($score) => $score < 7),
        ];
    }
    
    private function calculateQualityScore(string $docPath): float
    {
        $content = file_get_contents($docPath);
        $score = 0;
        
        // Check for YAML front-matter
        if (str_starts_with($content, '---')) $score += 2;
        
        // Check for estimated reading time
        if (str_contains($content, 'Estimated Reading Time')) $score += 1;
        
        // Check for code examples
        if (str_contains($content, '```')) $score += 2;
        
        // Check for proper headings
        if (preg_match_all('/^#+\s/m', $content) >= 3) $score += 2;
        
        // Check for bullet points
        if (str_contains($content, '- ') || str_contains($content, '• ')) $score += 1;
        
        // Check for references
        if (str_contains($content, 'References') || str_contains($content, 'Citations')) $score += 2;
        
        return min($score, 10); // Cap at 10
    }
}

// Generate and save metrics
$metrics = new DocumentationMetrics();
$results = $metrics->generateMetrics();

file_put_contents(
    'documentation-metrics-' . date('Y-m-d') . '.json',
    json_encode($results, JSON_PRETTY_PRINT)
);

echo "Documentation metrics generated and saved.\n";
```

## Continuous Improvement

### Feedback Collection
- **User Surveys**: Regular surveys to assess documentation usefulness
- **Usage Analytics**: Track which documents are most/least accessed
- **Issue Tracking**: GitHub issues for documentation problems
- **Team Retrospectives**: Regular team feedback on documentation quality

### Improvement Process
1. **Collect Feedback**: Gather feedback from multiple sources
2. **Analyze Patterns**: Identify common issues and improvement opportunities
3. **Prioritize Changes**: Focus on high-impact improvements
4. **Implement Updates**: Make necessary changes and improvements
5. **Validate Changes**: Ensure improvements achieve desired outcomes
6. **Monitor Results**: Track the impact of changes over time

### Documentation Roadmap
- **Q1**: Complete core template library and establish maintenance procedures
- **Q2**: Implement automated documentation generation and validation
- **Q3**: Enhance user experience with improved navigation and search
- **Q4**: Integrate advanced analytics and AI-powered content suggestions

---

**Documentation Maintenance Guide Version**: 1.0.0  
**Maintenance Cycle**: Monthly reviews with quarterly audits  
**Framework**: Laravel 12.x with FilamentPHP v4  
**Created**: [YYYY-MM-DD]  
**Last Updated**: [YYYY-MM-DD]  
**Next Review**: [YYYY-MM-DD]  
**Documentation Owner**: [DOCUMENTATION_LEAD]
