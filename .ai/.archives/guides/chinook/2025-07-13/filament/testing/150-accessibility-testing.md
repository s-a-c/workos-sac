# Accessibility Testing Guide

This guide covers comprehensive WCAG 2.1 AA compliance and accessibility testing for the Chinook Filament admin panel,
including screen reader compatibility, keyboard navigation, and inclusive design validation.

## Table of Contents

- [Overview](#overview)
- [WCAG 2.1 AA Compliance Testing](#wcag-21-aa-compliance-testing)
- [Screen Reader Testing](#screen-reader-testing)
- [Keyboard Navigation Testing](#keyboard-navigation-testing)
- [Color and Contrast Testing](#color-and-contrast-testing)
- [Focus Management Testing](#focus-management-testing)
- [ARIA Implementation Testing](#aria-implementation-testing)
- [Form Accessibility Testing](#form-accessibility-testing)
- [Automated Accessibility Testing](#automated-accessibility-testing)

## Overview

Accessibility testing ensures the Chinook admin panel is usable by people with disabilities, meeting WCAG 2.1 AA
standards. This includes testing with assistive technologies, keyboard navigation, and visual accessibility
requirements.

### Testing Objectives

- **WCAG 2.1 AA Compliance**: Meet international accessibility standards
- **Screen Reader Compatibility**: Ensure proper screen reader functionality
- **Keyboard Navigation**: Validate complete keyboard accessibility
- **Visual Accessibility**: Test color contrast and visual indicators
- **Inclusive Design**: Verify usability for diverse abilities

## WCAG 2.1 AA Compliance Testing

### Automated WCAG Testing

```php
// tests/Feature/AccessibilityTest.php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');
    }

    public function test_page_has_proper_html_structure(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $content = $response->getContent();

        // Test HTML5 semantic structure
        $this->assertStringContainsString('<main', $content);
        $this->assertStringContainsString('<nav', $content);
        $this->assertStringContainsString('<header', $content);
        $this->assertStringContainsString('role="main"', $content);

        // Test proper heading hierarchy
        $this->assertMatchesRegularExpression('/<h1[^>]*>/', $content);
        $this->assertStringContainsString('lang="en"', $content);
    }

    public function test_images_have_alt_text(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $content = $response->getContent();

        // Find all img tags
        preg_match_all('/<img[^>]*>/', $content, $images);

        foreach ($images[0] as $img) {
            // Each image should have alt attribute
            $this->assertMatchesRegularExpression('/alt=/', $img,
                "Image missing alt attribute: {$img}");
        }
    }

    public function test_forms_have_proper_labels(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists/create');

        $content = $response->getContent();

        // Find all input fields
        preg_match_all('/<input[^>]*name="([^"]*)"[^>]*>/', $content, $inputs);

        foreach ($inputs[1] as $inputName) {
            // Each input should have associated label
            $this->assertMatchesRegularExpression(
                '/for="[^"]*' . preg_quote($inputName) . '[^"]*"/',
                $content,
                "Input '{$inputName}' missing associated label"
            );
        }
    }

    public function test_color_contrast_ratios(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get('/chinook-admin/artists');

        $content = $response->getContent();

        // Test that high-contrast color palette is used
        $approvedColors = ['#1976d2', '#388e3c', '#f57c00', '#d32f2f'];

        foreach ($approvedColors as $color) {
            if (str_contains($content, $color)) {
                // Verify contrast ratio meets WCAG AA (4.5:1)
                $this->assertTrue(
                    $this->meetsContrastRatio($color, '#ffffff', 4.5),
                    "Color {$color} does not meet WCAG AA contrast ratio"
                );
            }
        }
    }

    private function meetsContrastRatio(string $foreground, string $background, float $ratio): bool
    {
        // Simplified contrast ratio calculation
        $fgLuminance = $this->getLuminance($foreground);
        $bgLuminance = $this->getLuminance($background);

        $contrast = ($fgLuminance + 0.05) / ($bgLuminance + 0.05);
        if ($contrast < 1) {
            $contrast = 1 / $contrast;
        }

        return $contrast >= $ratio;
    }

    private function getLuminance(string $color): float
    {
        // Convert hex to RGB and calculate relative luminance
        $hex = ltrim($color, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        // Apply gamma correction
        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
```

### Manual WCAG Testing

```php
public function test_skip_navigation_links(): void
{
    $response = $this->actingAs($this->adminUser)
        ->get('/chinook-admin/artists');

    $content = $response->getContent();

    // Test skip to main content link
    $this->assertStringContainsString('href="#main-content"', $content);
    $this->assertStringContainsString('Skip to main content', $content);
    $this->assertStringContainsString('id="main-content"', $content);
}

public function test_page_titles_are_descriptive(): void
{
    $pages = [
        '/chinook-admin' => 'Dashboard - Chinook Admin',
        '/chinook-admin/artists' => 'Artists - Chinook Admin',
        '/chinook-admin/artists/create' => 'Create Artist - Chinook Admin',
        '/chinook-admin/albums' => 'Albums - Chinook Admin',
    ];

    foreach ($pages as $url => $expectedTitle) {
        $response = $this->actingAs($this->adminUser)->get($url);

        $this->assertStringContainsString(
            "<title>{$expectedTitle}</title>",
            $response->getContent()
        );
    }
}

public function test_error_messages_are_accessible(): void
{
    $response = $this->actingAs($this->adminUser)
        ->post('/chinook-admin/artists', []); // Invalid submission

    $content = $response->getContent();

    // Error messages should have proper ARIA attributes
    $this->assertMatchesRegularExpression('/role="alert"/', $content);
    $this->assertMatchesRegularExpression('/aria-live="polite"/', $content);
}
```

## Screen Reader Testing

### Screen Reader Compatibility Testing

```php
// tests/Browser/ScreenReaderTest.php
<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Artist;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ScreenReaderTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_screen_reader_navigation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/chinook-admin/artists')
                    ->assertPresent('[role="main"]')
                    ->assertPresent('[role="navigation"]')
                    ->assertPresent('h1')
                    ->script('
                        // Simulate screen reader navigation
                        const headings = document.querySelectorAll("h1, h2, h3, h4, h5, h6");
                        return Array.from(headings).map(h => ({
                            level: h.tagName,
                            text: h.textContent.trim()
                        }));
                    ');

            $headings = $browser->script('
                const headings = document.querySelectorAll("h1, h2, h3, h4, h5, h6");
                return Array.from(headings).map(h => h.textContent.trim());
            ')[0];

            $this->assertNotEmpty($headings);
            $this->assertStringContainsString('Artists', $headings[0]);
        });
    }

    public function test_aria_labels_and_descriptions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $artist = Artist::factory()->create();

        $this->browse(function (Browser $browser) use ($user, $artist) {
            $browser->loginAs($user)
                    ->visit("/chinook-admin/artists/{$artist->id}")
                    ->assertPresent('[aria-label]')
                    ->assertPresent('[aria-describedby]');

            // Test that ARIA labels are meaningful
            $ariaLabels = $browser->script('
                const elements = document.querySelectorAll("[aria-label]");
                return Array.from(elements).map(el => el.getAttribute("aria-label"));
            ')[0];

            foreach ($ariaLabels as $label) {
                $this->assertNotEmpty($label);
                $this->assertGreaterThan(3, strlen($label));
            }
        });
    }

    public function test_table_accessibility(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        Artist::factory()->count(5)->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/chinook-admin/artists')
                    ->assertPresent('table')
                    ->assertPresent('th[scope="col"]')
                    ->assertPresent('caption, [aria-label]');

            // Test table headers are properly associated
            $tableStructure = $browser->script('
                const table = document.querySelector("table");
                const headers = table.querySelectorAll("th");
                const cells = table.querySelectorAll("td");

                return {
                    hasCaption: !!table.querySelector("caption"),
                    headerCount: headers.length,
                    cellCount: cells.length,
                    hasScope: Array.from(headers).every(th => th.hasAttribute("scope"))
                };
            ')[0];

            $this->assertTrue($tableStructure['hasScope']);
            $this->assertGreaterThan(0, $tableStructure['headerCount']);
        });
    }
}
```

### Screen Reader Announcement Testing

```php
public function test_dynamic_content_announcements(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertPresent('[aria-live="polite"]')
                ->type('@search-input', 'test')
                ->waitFor('@search-results')
                ->script('
                    // Check that search results are announced
                    const liveRegion = document.querySelector("[aria-live]");
                    return liveRegion ? liveRegion.textContent : "";
                ');

        $announcement = $browser->script('
            return document.querySelector("[aria-live]").textContent;
        ')[0];

        $this->assertStringContainsString('results', strtolower($announcement));
    });
}

public function test_form_error_announcements(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->press('Create') // Submit invalid form
                ->waitFor('[role="alert"]')
                ->assertPresent('[aria-live="assertive"]');

        $errorAnnouncement = $browser->script('
            const alert = document.querySelector("[role=alert]");
            return alert ? alert.textContent : "";
        ')[0];

        $this->assertNotEmpty($errorAnnouncement);
        $this->assertStringContainsString('required', strtolower($errorAnnouncement));
    });
}
```

## Keyboard Navigation Testing

### Keyboard Accessibility Testing

```php
public function test_keyboard_navigation_flow(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->keys('body', '{tab}') // Skip link
                ->keys('body', '{enter}') // Activate skip link
                ->assertFocused('#main-content')
                ->keys('body', '{tab}') // First focusable element
                ->keys('body', '{tab}') // Second focusable element
                ->keys('body', '{shift}{tab}') // Navigate backwards
                ->keys('body', '{enter}'); // Activate element
    });
}

public function test_modal_keyboard_trap(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $artist = Artist::factory()->create();

    $this->browse(function (Browser $browser) use ($user, $artist) {
        $browser->loginAs($user)
                ->visit("/chinook-admin/artists/{$artist->id}")
                ->click('@delete-button')
                ->waitFor('@confirmation-modal')
                ->assertFocused('@modal-close-button')
                ->keys('body', '{tab}') // Move to next element
                ->keys('body', '{tab}') // Move to next element
                ->keys('body', '{tab}') // Should wrap to first element
                ->assertFocused('@modal-close-button')
                ->keys('body', '{escape}') // Close modal
                ->waitUntilMissing('@confirmation-modal');
    });
}

public function test_dropdown_keyboard_navigation(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->click('@country-select')
                ->waitFor('@dropdown-options')
                ->keys('body', '{arrow_down}') // Navigate down
                ->keys('body', '{arrow_down}') // Navigate down
                ->keys('body', '{arrow_up}') // Navigate up
                ->keys('body', '{enter}') // Select option
                ->waitUntilMissing('@dropdown-options');
    });
}
```

### Focus Management Testing

```php
public function test_focus_indicators_visible(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->keys('body', '{tab}')
                ->script('
                    const focused = document.activeElement;
                    const styles = window.getComputedStyle(focused, ":focus");
                    return {
                        outline: styles.outline,
                        outlineWidth: styles.outlineWidth,
                        boxShadow: styles.boxShadow
                    };
                ');

        $focusStyles = $browser->script('
            const focused = document.activeElement;
            const styles = window.getComputedStyle(focused, ":focus");
            return styles.outline !== "none" || styles.boxShadow !== "none";
        ')[0];

        $this->assertTrue($focusStyles, 'Focus indicators must be visible');
    });
}

public function test_logical_tab_order(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create');

        $tabOrder = [];
        for ($i = 0; $i < 10; $i++) {
            $browser->keys('body', '{tab}');
            $focused = $browser->script('return document.activeElement.id || document.activeElement.name || document.activeElement.tagName;')[0];
            $tabOrder[] = $focused;
        }

        // Verify logical order (form fields before buttons)
        $nameIndex = array_search('name', $tabOrder);
        $countryIndex = array_search('country', $tabOrder);
        $submitIndex = array_search('BUTTON', $tabOrder);

        $this->assertLessThan($countryIndex, $nameIndex);
        $this->assertLessThan($submitIndex, $countryIndex);
    });
}
```

## Color and Contrast Testing

### Color Accessibility Testing

```php
public function test_information_not_conveyed_by_color_alone(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    Artist::factory()->create(['is_active' => true]);
    Artist::factory()->create(['is_active' => false]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists');

        // Check that status is conveyed by text, not just color
        $statusElements = $browser->script('
            const elements = document.querySelectorAll("[data-status]");
            return Array.from(elements).map(el => ({
                color: window.getComputedStyle(el).color,
                text: el.textContent.trim(),
                hasIcon: !!el.querySelector("svg, i, .icon")
            }));
        ')[0];

        foreach ($statusElements as $element) {
            $this->assertTrue(
                !empty($element['text']) || $element['hasIcon'],
                'Status must be conveyed by text or icon, not just color'
            );
        }
    });
}

public function test_high_contrast_mode_compatibility(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->script('
                    // Simulate high contrast mode
                    document.body.style.filter = "contrast(200%)";
                    return true;
                ')
                ->pause(1000) // Allow styles to apply
                ->assertPresent('button')
                ->assertPresent('input');

        // Verify elements are still visible and functional
        $visibility = $browser->script('
            const button = document.querySelector("button");
            const input = document.querySelector("input");
            const buttonStyles = window.getComputedStyle(button);
            const inputStyles = window.getComputedStyle(input);

            return {
                buttonVisible: buttonStyles.opacity !== "0" && buttonStyles.visibility !== "hidden",
                inputVisible: inputStyles.opacity !== "0" && inputStyles.visibility !== "hidden"
            };
        ')[0];

        $this->assertTrue($visibility['buttonVisible']);
        $this->assertTrue($visibility['inputVisible']);
    });
}
```

## ARIA Implementation Testing

### ARIA Attributes Testing

```php
public function test_aria_expanded_states(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertAttribute('@filter-dropdown', 'aria-expanded', 'false')
                ->click('@filter-dropdown')
                ->waitFor('@filter-options')
                ->assertAttribute('@filter-dropdown', 'aria-expanded', 'true')
                ->click('@filter-dropdown')
                ->waitUntilMissing('@filter-options')
                ->assertAttribute('@filter-dropdown', 'aria-expanded', 'false');
    });
}

public function test_aria_describedby_associations(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->assertPresent('[aria-describedby]');

        $associations = $browser->script('
            const elements = document.querySelectorAll("[aria-describedby]");
            return Array.from(elements).map(el => {
                const describedBy = el.getAttribute("aria-describedby");
                const description = document.getElementById(describedBy);
                return {
                    hasDescription: !!description,
                    descriptionText: description ? description.textContent.trim() : ""
                };
            });
        ')[0];

        foreach ($associations as $association) {
            $this->assertTrue($association['hasDescription']);
            $this->assertNotEmpty($association['descriptionText']);
        }
    });
}

public function test_aria_live_regions(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->assertPresent('[aria-live="polite"]')
                ->type('@search-input', 'test')
                ->waitFor('@search-results');

        $liveRegions = $browser->script('
            const regions = document.querySelectorAll("[aria-live]");
            return Array.from(regions).map(region => ({
                politeness: region.getAttribute("aria-live"),
                hasContent: region.textContent.trim().length > 0
            }));
        ')[0];

        $this->assertNotEmpty($liveRegions);
        foreach ($liveRegions as $region) {
            $this->assertContains($region['politeness'], ['polite', 'assertive']);
        }
    });
}
```

## Form Accessibility Testing

### Form Label and Error Testing

```php
public function test_form_labels_properly_associated(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create');

        $labelAssociations = $browser->script('
            const inputs = document.querySelectorAll("input, select, textarea");
            return Array.from(inputs).map(input => {
                const id = input.id;
                const name = input.name;
                const label = document.querySelector(`label[for="${id}"]`);
                const ariaLabel = input.getAttribute("aria-label");
                const ariaLabelledBy = input.getAttribute("aria-labelledby");

                return {
                    hasLabel: !!label,
                    hasAriaLabel: !!ariaLabel,
                    hasAriaLabelledBy: !!ariaLabelledBy,
                    hasAccessibleName: !!(label || ariaLabel || ariaLabelledBy)
                };
            });
        ')[0];

        foreach ($labelAssociations as $association) {
            $this->assertTrue(
                $association['hasAccessibleName'],
                'All form controls must have accessible names'
            );
        }
    });
}

public function test_required_field_indicators(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create');

        $requiredFields = $browser->script('
            const required = document.querySelectorAll("[required]");
            return Array.from(required).map(field => {
                const label = document.querySelector(`label[for="${field.id}"]`);
                const ariaRequired = field.getAttribute("aria-required");
                const hasVisualIndicator = label && (
                    label.textContent.includes("*") ||
                    label.querySelector(".required")
                );

                return {
                    hasAriaRequired: ariaRequired === "true",
                    hasVisualIndicator: hasVisualIndicator
                };
            });
        ')[0];

        foreach ($requiredFields as $field) {
            $this->assertTrue($field['hasAriaRequired']);
            $this->assertTrue($field['hasVisualIndicator']);
        }
    });
}

public function test_error_message_accessibility(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists/create')
                ->press('Create') // Submit invalid form
                ->waitFor('.error-message');

        $errorAccessibility = $browser->script('
            const errors = document.querySelectorAll(".error-message");
            const inputs = document.querySelectorAll("input[aria-describedby]");

            return {
                errorsHaveRole: Array.from(errors).every(error =>
                    error.getAttribute("role") === "alert" ||
                    error.closest("[role=alert]")
                ),
                inputsDescribedByErrors: Array.from(inputs).every(input => {
                    const describedBy = input.getAttribute("aria-describedby");
                    return document.getElementById(describedBy);
                })
            };
        ')[0];

        $this->assertTrue($errorAccessibility['errorsHaveRole']);
        $this->assertTrue($errorAccessibility['inputsDescribedByErrors']);
    });
}
```

## Automated Accessibility Testing

### Axe-Core Integration

```javascript
// tests/Browser/axe-accessibility.js
const { AxePuppeteer } = require('@axe-core/puppeteer');

async function runAxeTest(page, url) {
    await page.goto(url);

    const results = await new AxePuppeteer(page).analyze();

    if (results.violations.length > 0) {
        console.log('Accessibility violations found:');
        results.violations.forEach(violation => {
            console.log(`- ${violation.id}: ${violation.description}`);
            violation.nodes.forEach(node => {
                console.log(`  Target: ${node.target}`);
                console.log(`  HTML: ${node.html}`);
            });
        });
        throw new Error(`${results.violations.length} accessibility violations found`);
    }

    return results;
}

module.exports = { runAxeTest };
```

### PHP Accessibility Testing

```php
public function test_automated_accessibility_scan(): void
{
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
                ->visit('/chinook-admin/artists')
                ->script('
                    // Inject axe-core if available
                    if (typeof axe !== "undefined") {
                        return new Promise((resolve) => {
                            axe.run(document, (err, results) => {
                                resolve(results);
                            });
                        });
                    }
                    return null;
                ');

        // Manual accessibility checks if axe-core not available
        $accessibilityIssues = $browser->script('
            const issues = [];

            // Check for images without alt text
            const images = document.querySelectorAll("img:not([alt])");
            if (images.length > 0) {
                issues.push(`${images.length} images missing alt text`);
            }

            // Check for inputs without labels
            const unlabeledInputs = document.querySelectorAll("input:not([aria-label]):not([aria-labelledby])");
            const unlabeled = Array.from(unlabeledInputs).filter(input => {
                return !document.querySelector(`label[for="${input.id}"]`);
            });
            if (unlabeled.length > 0) {
                issues.push(`${unlabeled.length} inputs without labels`);
            }

            // Check for low contrast text
            const textElements = document.querySelectorAll("p, span, div, h1, h2, h3, h4, h5, h6");
            // Simplified contrast check would go here

            return issues;
        ')[0];

        $this->assertEmpty($accessibilityIssues,
            'Accessibility issues found: ' . implode(', ', $accessibilityIssues));
    });
}
```

## Related Documentation

- **[Browser Testing](140-browser-testing.md)** - Cross-browser compatibility testing
- **[Form Testing](060-form-testing.md)** - Form validation and interaction testing
- **[Security Testing](160-security-testing.md)** - Security validation
- **[Performance Testing](130-performance-testing.md)** - Performance optimization

---

## Navigation

**← Previous:** [Browser Testing](140-browser-testing.md)

**Next →** [Security Testing](160-security-testing.md)

**Up:** [Testing Documentation Index](000-testing-index.md)
