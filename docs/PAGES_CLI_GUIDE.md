# IRIS SDK - Pages CLI Guide

Manage composable landing pages via the command line. This guide covers local development and production usage.

## Quick Start

```bash
# From the SDK directory: /fl-docker-dev/sdk/php

# List all pages
./bin/iris pages

# View a page's content
./bin/iris pages view network-onboarding

# List components on a page
./bin/iris pages components network-onboarding

# Update a single component property (preserves other props)
./bin/iris pages update-component network-onboarding --component-id=hero --props='{"title":"New Title"}'
```

## Environment Configuration

The SDK supports both local and production environments via the `.env` file.

### Local Development (Default)

```bash
# .env
IRIS_ENV=local
FL_API_LOCAL_URL=http://localhost:8000
IRIS_LOCAL_URL=http://localhost:7200
IRIS_LOCAL_API_KEY=your-local-api-key
IRIS_USER_ID=193
```

### Production

```bash
# .env
IRIS_ENV=production
IRIS_API_KEY=your-production-api-key
IRIS_USER_ID=193
```

### Switching Environments

```bash
# Quick switch to production (without editing .env)
IRIS_ENV=production ./bin/iris pages

# Or edit .env
sed -i '' 's/IRIS_ENV=local/IRIS_ENV=production/' .env
./bin/iris pages
```

## Available Commands

### List Pages

```bash
./bin/iris pages
./bin/iris pages --json  # Output as JSON
```

### View Page

```bash
./bin/iris pages view <slug>
./bin/iris pages view network-onboarding
./bin/iris pages view network-onboarding --json  # JSON output
```

### Create Page

```bash
# Interactive mode
./bin/iris pages create

# With options
./bin/iris pages create --slug=my-landing --title="My Landing Page"

# From template
./bin/iris pages create --slug=my-landing --title="My Landing" --template=landing
```

Available templates: `landing`, `product`

### Edit Page

```bash
./bin/iris pages edit <slug>
./bin/iris pages edit my-landing --title="Updated Title"
./bin/iris pages edit my-landing --seo-title="SEO Title" --seo-description="SEO Description"
```

### Publish/Unpublish

```bash
./bin/iris pages publish <slug>
./bin/iris pages unpublish <slug>
```

### Delete Page

```bash
./bin/iris pages delete <slug>
```

### Duplicate Page

```bash
./bin/iris pages duplicate <slug> --new-slug=<new-slug>
./bin/iris pages duplicate network-onboarding --new-slug=network-onboarding-v2
```

### Version Management

```bash
# View version history
./bin/iris pages versions <slug>

# Rollback to a specific version
./bin/iris pages rollback <slug> --page-version=2
```

## Component Management

### List Components

```bash
./bin/iris pages components <slug>
./bin/iris pages components network-onboarding
```

Example output:
```
Components: Network Onboarding
==============================

 ------- ------------- ----------- -------------------
  Index   ID            Type        Preview
 ------- ------------- ----------- -------------------
  0       hero          Hero        Create. Share...
  1       about-intro   TextBlock   A Little Bit...
  2       platform...   Comparison  N/A
 ------- ------------- ----------- -------------------
```

### Update Component

Updates merge with existing props - you don't need to specify all properties.

```bash
./bin/iris pages update-component <slug> --component-id=<id> --props='{"key":"value"}'

# Examples:

# Update just the title (preserves subtitle, backgroundGradient, etc.)
./bin/iris pages update-component network-onboarding --component-id=hero --props='{"title":"New Title"}'

# Update multiple props
./bin/iris pages update-component network-onboarding --component-id=hero --props='{"title":"New Title","subtitle":"New Subtitle"}'

# Update nested props (use dot notation in the JSON)
./bin/iris pages update-component network-onboarding --component-id=hero --props='{"backgroundGradient":"from-blue-500 to-purple-600"}'
```

### Add Component

```bash
# Interactive mode
./bin/iris pages add-component <slug>

# With options
./bin/iris pages add-component network-onboarding --type=TextBlock --props='{"content":"Hello World"}'

# Add at specific position
./bin/iris pages add-component network-onboarding --type=Hero --position=0 --props='{"title":"Top Hero"}'
```

### Remove Component

```bash
./bin/iris pages remove-component <slug> --component-id=<id>
./bin/iris pages remove-component network-onboarding --component-id=old-section
```

## Common Workflows

### Update Page Content Without SQL

Instead of editing giant JSON blobs in SQL files, use the CLI:

```bash
# Update a button URL
./bin/iris pages update-component network-onboarding \
  --component-id=getting-started \
  --props='{"ctaUrl":"https://the.freelabel.net"}'

# Update hero text
./bin/iris pages update-component network-onboarding \
  --component-id=hero \
  --props='{"title":"Create. Share. Monetize.","subtitle":"Your new tagline here"}'

# Update a text block
./bin/iris pages update-component network-onboarding \
  --component-id=what-is \
  --props='{"content":"Updated content with **markdown** support."}'
```

### Batch Updates (Shell Script)

```bash
#!/bin/bash
# update-network-onboarding.sh

PAGE="network-onboarding"

# Update hero
./bin/iris pages update-component $PAGE --component-id=hero \
  --props='{"title":"Create. Share. Monetize."}'

# Update CTA URLs
./bin/iris pages update-component $PAGE --component-id=getting-started \
  --props='{"ctaUrl":"https://the.freelabel.net"}'

./bin/iris pages update-component $PAGE --component-id=platform-comparison \
  --props='{"cards":[{"buttonUrl":"https://heyiris.io"},{"buttonUrl":"https://the.freelabel.net/discover"}]}'

echo "Updates complete!"
```

### Backup Before Major Changes

```bash
# View current version
./bin/iris pages versions network-onboarding

# Make changes...
./bin/iris pages update-component network-onboarding --component-id=hero --props='{"title":"Test"}'

# If something goes wrong, rollback
./bin/iris pages rollback network-onboarding --page-version=5
```

## SDK Usage (Programmatic)

For more complex updates, use the SDK directly in PHP:

```php
<?php
require_once "vendor/autoload.php";

use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

// Auto-loads from .env
$config = new Config([]);
$iris = new IRIS([
    "api_key" => $config->apiKey,
    "user_id" => (int) $config->userId,
]);

// Get page by slug
$page = $iris->pages->getBySlug("network-onboarding", true);

// Update specific component
$iris->pages->updateComponentById($page['id'], "hero", [
    "props" => [
        "title" => "New Title",
        "subtitle" => "New Subtitle",
    ]
]);

// Or update using dot notation paths
$iris->pages->updatePath($page['id'], "components.0.props.title", "Another Title");

// Update theme
$iris->pages->updateTheme($page['id'], [
    "branding.primaryColor" => "#FF0000",
]);

// Add a new component
$iris->pages->addComponent($page['id'], [
    "type" => "TextBlock",
    "id" => "new-section",
    "props" => [
        "title" => "New Section",
        "content" => "Some content here.",
    ]
], 2);  // Insert at position 2

// Remove a component
$iris->pages->removeComponentById($page['id'], "old-section");

// Get all components
$components = $iris->pages->getComponents($page['id']);
print_r($components);
```

## Component Types Reference

Common component types available:

| Type | Description | Key Props |
|------|-------------|-----------|
| `Hero` | Full-width hero section | `title`, `subtitle`, `backgroundGradient`, `primaryButtonText`, `primaryButtonUrl` |
| `TextBlock` | Markdown text content | `title`, `content`, `alignment` |
| `ButtonCTA` | Call-to-action button | `text`, `url`, `variant` |
| `ImageBlock` | Image with caption | `src`, `alt`, `title`, `caption`, `maxWidth` |
| `VideoBlock` | Video embed | `title`, `description`, `src`, `autoplay`, `controls` |
| `ComparisonCards` | Side-by-side cards | `cards[].title`, `cards[].bulletPoints`, `cards[].buttonText`, `cards[].buttonUrl` |
| `EarningsTable` | Pricing/earnings table | `title`, `commission`, `paths[].name`, `paths[].price` |
| `GettingStartedSteps` | Numbered steps | `title`, `subtitle`, `steps[].number`, `steps[].title`, `steps[].description`, `ctaText`, `ctaUrl` |
| `SkillsGrid` | Icon grid | `title`, `subtitle`, `skills[].icon`, `skills[].title`, `skills[].description` |
| `AgentExamples` | Tabbed examples | `title`, `subtitle`, `examples[].id`, `examples[].tab`, `examples[].title` |

## Troubleshooting

### "Missing API credentials" Error

Make sure your `.env` file has the correct credentials:

```bash
# Check current .env
cat .env | grep -E "IRIS_ENV|IRIS_API_KEY|IRIS_LOCAL_API_KEY|IRIS_USER_ID"

# Ensure IRIS_ENV matches your API key type
# local -> needs IRIS_LOCAL_API_KEY
# production -> needs IRIS_API_KEY
```

### "Page not found" Error

Check if the page exists and you have access:

```bash
# List all pages to find the correct slug
./bin/iris pages

# Try with full API key if using production
IRIS_ENV=production ./bin/iris pages view my-page
```

### Component Update Not Persisting

1. Check the page versions to see if the update was recorded:
   ```bash
   ./bin/iris pages versions my-page
   ```

2. View the page to see current state:
   ```bash
   ./bin/iris pages view my-page --json
   ```

3. Ensure you're using the correct `--component-id` (check with `components` command)

### JSON Parsing Errors

Make sure your JSON is valid and properly escaped:

```bash
# Use single quotes for the outer string
./bin/iris pages update-component my-page --component-id=hero --props='{"title":"New Title"}'

# For complex JSON, use a file
echo '{"title":"Complex \"quoted\" title","items":["a","b","c"]}' > props.json
./bin/iris pages update-component my-page --component-id=hero --props="$(cat props.json)"
```

## Best Practices

1. **Use version control for important pages**: Before major changes, note the current version number so you can rollback if needed.

2. **Test in local first**: Always use `IRIS_ENV=local` for testing before running against production.

3. **Use meaningful component IDs**: When creating pages, give components descriptive IDs like `hero-main`, `pricing-table`, `contact-form` instead of auto-generated ones.

4. **Batch updates in scripts**: For multiple related updates, create a shell script to ensure consistency.

5. **Check components before updating**: Run `./bin/iris pages components <slug>` to verify the component ID exists.
