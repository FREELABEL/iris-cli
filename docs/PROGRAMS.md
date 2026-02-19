# Programs Resource Documentation

The Programs resource provides complete functionality for managing membership programs, funnels, enrollments, and related content in the IRIS SDK.

## Table of Contents

- [Overview](#overview)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Programs Management](#programs-management)
  - [List Programs](#list-programs)
  - [Search Programs](#search-programs)
  - [Get Program Details](#get-program-details)
  - [Create Program](#create-program)
  - [Update Program](#update-program)
  - [Delete Program](#delete-program)
- [Enrollment Management](#enrollment-management)
  - [Enroll User](#enroll-user)
  - [Cancel Enrollment](#cancel-enrollment)
  - [Get User Enrollments](#get-user-enrollments)
  - [Get User Programs](#get-user-programs)
- [Content Management](#content-management)
  - [Get Program Content](#get-program-content)
  - [Attach Content](#attach-content)
  - [Detach Content](#detach-content)
- [Workflow Management](#workflow-management)
  - [Get Workflows](#get-workflows)
  - [Attach Workflow](#attach-workflow)
  - [Update Workflow](#update-workflow)
  - [Detach Workflow](#detach-workflow)
  - [Get Workflow Execution Logs](#get-workflow-execution-logs)
- [Additional Features](#additional-features)
  - [Check Access](#check-access)
  - [Chat Messages](#chat-messages)
  - [Packages](#packages)
  - [Memberships](#memberships)
- [Working with Collections](#working-with-collections)
- [Model Reference](#model-reference)
- [CLI Usage](#cli-usage)
- [Error Handling](#error-handling)
- [Examples](#examples)

---

## Overview

Programs represent membership offerings, online courses, funnels, and subscription-based content in your business. The Programs resource allows you to:

- Create and manage membership programs
- Handle user enrollments and subscriptions
- Attach content, workflows, and pricing packages
- Track program access and permissions
- Manage program chat and community features
- Monitor enrollment analytics

---

## Installation

The Programs resource is included in the IRIS SDK. Make sure you have the SDK installed:

```bash
composer require iris/sdk
```

Or if using the CLI:

```bash
cd fl-docker-dev/sdk/php
./bin/iris setup
```

---

## Quick Start

### PHP SDK

```php
<?php

require 'vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize SDK
$iris = new IRIS([
    'api_key' => 'your_api_key',
    'user_id' => 193,
]);

// List all programs
$programs = $iris->programs->list();

// Search for specific programs
$results = $iris->programs->search('AI Course');

// Enroll a user
$enrollment = $iris->programs->enroll(
    programId: 5,
    userId: 193
);

// Get program details
$program = $iris->programs->get(5);
echo $program->name;
echo $program->isPaid() ? 'Paid' : 'Free';
```

### CLI

```bash
# List programs
./bin/iris sdk:call programs.list

# Search programs
./bin/iris sdk:call programs.search "Newsletter"

# Get program details
./bin/iris sdk:call programs.get 1 --json

# Enroll user
./bin/iris sdk:call programs.enroll 5 user_id=193
```

---

## Programs Management

### List Programs

Retrieve all programs with optional filtering.

**PHP:**
```php
// Get all active programs
$programs = $iris->programs->list();

// Filter by bloq
$bloqPrograms = $iris->programs->list([
    'bloq_id' => 56
]);

// Include inactive programs
$allPrograms = $iris->programs->list([
    'include_inactive' => true
]);

// Filter by tier
$premiumPrograms = $iris->programs->list([
    'tier' => 'premium'
]);

// Pagination
$page2 = $iris->programs->list([
    'page' => 2,
    'per_page' => 20
]);

// Iterate through results
foreach ($programs as $program) {
    echo $program->name . ' - ' . $program->tier . PHP_EOL;
}
```

**CLI:**
```bash
# List all programs
./bin/iris sdk:call programs.list

# Filter by bloq
./bin/iris sdk:call programs.list bloq_id=56

# JSON output
./bin/iris sdk:call programs.list --json

# Include inactive
./bin/iris sdk:call programs.list include_inactive=true
```

**Parameters:**
- `bloq_id` (int, optional): Filter by bloq ID
- `active` (bool, optional): Filter by active status
- `include_inactive` (bool, optional): Include inactive programs
- `has_paid_membership` (bool, optional): Filter by paid membership
- `tier` (string, optional): Filter by tier (free, premium, VIP, elite)
- `page` (int, optional): Page number for pagination
- `per_page` (int, optional): Items per page (default: 20)

**Returns:** `ProgramCollection`

---

### Search Programs

Search for programs by name, description, or slug.

**PHP:**
```php
// Basic search
$results = $iris->programs->search('newsletter');

// Search with filters
$premiumNewsletters = $iris->programs->search('newsletter', [
    'tier' => 'premium'
]);

// Search within a bloq
$bloqResults = $iris->programs->search('automation', [
    'bloq_id' => 56
]);

// Process results
echo "Found " . count($results) . " programs\n";
foreach ($results as $program) {
    echo "- {$program->name} ({$program->tier})\n";
}
```

**CLI:**
```bash
# Search programs
./bin/iris sdk:call programs.search "Newsletter"

# Search with tier filter
./bin/iris sdk:call programs.search "AI" tier=premium

# JSON output
./bin/iris sdk:call programs.search "Course" --json
```

**Parameters:**
- `query` (string, required): Search query
- `bloq_id` (int, optional): Filter by bloq ID
- `tier` (string, optional): Filter by tier
- `active` (bool, optional): Filter by active status

**Returns:** `ProgramCollection`

**Note:** Search is performed client-side by filtering name, description, and slug fields.

---

### Get Program Details

Retrieve detailed information about a specific program.

**PHP:**
```php
$program = $iris->programs->get(5);

// Access program properties
echo "Name: {$program->name}\n";
echo "Description: {$program->description}\n";
echo "Tier: {$program->tier}\n";
echo "Price: \${$program->basePrice}\n";

// Use helper methods
if ($program->isFree()) {
    echo "This is a free program\n";
}

if ($program->isPaid()) {
    echo "Price: " . $program->getDisplayPrice() . "\n";
}

if ($program->hasPackages()) {
    echo "Available packages: " . count($program->packages) . "\n";
}

// Check activation status
if ($program->isActive()) {
    echo "Program is currently active\n";
}
```

**CLI:**
```bash
# Get program details
./bin/iris sdk:call programs.get 5

# JSON output
./bin/iris sdk:call programs.get 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID

**Returns:** `Program` object

---

### Create Program

Create a new membership program.

**PHP:**
```php
$program = $iris->programs->create([
    'name' => 'AI Mastery Program',
    'slug' => 'ai-mastery',
    'description' => 'Learn AI from experts',
    'bloq_id' => 56,
    'tier' => 'premium',
    'has_paid_membership' => true,
    'base_price' => 99.00,
    'active' => true,
    'allow_free_enrollment' => false,
    'requires_membership' => true,
    'membership_features' => [
        'access_to_community',
        'monthly_coaching_calls',
        'course_library'
    ],
    'landing_page_content' => '<h1>Welcome to AI Mastery</h1>',
    'image_url' => 'https://example.com/ai-mastery.jpg',
]);

echo "Created program: {$program->name} (ID: {$program->id})\n";
```

**CLI:**
```bash
# Create program
./bin/iris sdk:call programs.create \
    name="AI Mastery" \
    description="Learn AI" \
    bloq_id=56 \
    tier=premium \
    base_price=99.00

# Create free program
./bin/iris sdk:call programs.create \
    name="Free Newsletter" \
    bloq_id=56 \
    tier=free \
    allow_free_enrollment=true
```

**Parameters:**
- `name` (string, required): Program name
- `slug` (string, optional): URL-friendly slug
- `description` (string, optional): Program description
- `landing_page_content` (string, optional): Landing page HTML
- `image_url` (string, optional): Program thumbnail/cover image
- `active` (bool, optional): Activation status (default: true)
- `tier` (string, optional): Tier level (free, premium, VIP, elite)
- `bloq_id` (int, optional): Associated bloq ID
- `mailjet_list_id` (string, optional): Mailjet mailing list ID
- `has_paid_membership` (bool, optional): Requires payment
- `requires_membership` (bool, optional): Requires membership to access
- `allow_free_enrollment` (bool, optional): Allow free sign-ups
- `base_price` (float, optional): Base price
- `membership_features` (array, optional): List of features
- `custom_fields` (array, optional): Custom field definitions
- `enrollment_form_config` (array, optional): Form configuration

**Returns:** `Program` object

---

### Update Program

Update an existing program's information.

**PHP:**
```php
$program = $iris->programs->update(5, [
    'name' => 'AI Mastery Program - Updated',
    'description' => 'Learn AI from industry experts',
    'base_price' => 149.00,
    'active' => true,
]);

echo "Updated program: {$program->name}\n";
```

**CLI:**
```bash
# Update program
./bin/iris sdk:call programs.update 5 \
    name="New Name" \
    base_price=149.00

# Deactivate program
./bin/iris sdk:call programs.update 5 active=false
```

**Parameters:**
- `programId` (int, required): Program ID
- `data` (array, required): Fields to update (same as create)

**Returns:** `Program` object

---

### Delete Program

Permanently delete a program.

**PHP:**
```php
$success = $iris->programs->delete(5);

if ($success) {
    echo "Program deleted successfully\n";
}
```

**CLI:**
```bash
# Delete program
./bin/iris sdk:call programs.delete 5
```

**Parameters:**
- `programId` (int, required): Program ID

**Returns:** `bool` - Success status

**Warning:** This action is permanent and cannot be undone.

---

## Enrollment Management

### Enroll User

Enroll a user in a program.

**PHP:**
```php
// Basic enrollment
$enrollment = $iris->programs->enroll(
    programId: 5,
    userId: 193
);

echo "User enrolled successfully\n";
echo "Enrollment ID: {$enrollment->id}\n";

// Enrollment with package
$enrollment = $iris->programs->enroll(
    programId: 5,
    userId: 193,
    data: [
        'package_id' => 3,
        'custom_fields' => [
            'referral_code' => 'FRIEND2024'
        ]
    ]
);

// Check enrollment status
if ($enrollment->isActive()) {
    echo "Enrollment is active\n";
}
```

**CLI:**
```bash
# Enroll user
./bin/iris sdk:call programs.enroll 5 user_id=193

# Enroll with package
./bin/iris sdk:call programs.enroll 5 \
    user_id=193 \
    package_id=3
```

**Parameters:**
- `programId` (int, required): Program ID
- `userId` (int, required): User ID to enroll
- `package_id` (int, optional): Pricing package ID
- `custom_fields` (array, optional): Custom enrollment data
- `enrollment_data` (array, optional): Additional metadata

**Returns:** `ProgramEnrollment` object

---

### Cancel Enrollment

Remove a user from a program (unenroll).

**PHP:**
```php
$success = $iris->programs->cancelEnrollment(
    programId: 5,
    userId: 193
);

if ($success) {
    echo "User successfully removed from program\n";
}
```

**CLI:**
```bash
# Cancel enrollment
./bin/iris sdk:call programs.cancelEnrollment 5 user_id=193
```

**Parameters:**
- `programId` (int, required): Program ID
- `userId` (int, required): User ID to unenroll

**Returns:** `bool` - Success status

---

### Get User Enrollments

Retrieve all enrollments for a specific user.

**PHP:**
```php
$enrollments = $iris->programs->getUserEnrollments(193);

foreach ($enrollments as $enrollment) {
    echo "Program: {$enrollment['program_name']}\n";
    echo "Status: {$enrollment['status']}\n";
    echo "Enrolled: {$enrollment['enrolled_at']}\n\n";
}
```

**CLI:**
```bash
# Get user enrollments
./bin/iris sdk:call programs.getUserEnrollments 193 --json
```

**Parameters:**
- `userId` (int, required): User ID

**Returns:** `array` - List of enrollment records

---

### Get User Programs

Get all programs a user is enrolled in.

**PHP:**
```php
$programs = $iris->programs->getUserPrograms(193);

echo "User is enrolled in " . count($programs) . " programs\n";

foreach ($programs as $program) {
    echo "- {$program['name']} ({$program['tier']})\n";
}
```

**CLI:**
```bash
# Get user's programs
./bin/iris sdk:call programs.getUserPrograms 193 --json
```

**Parameters:**
- `userId` (int, required): User ID

**Returns:** `array` - List of programs

---

## Content Management

### Get Program Content

Retrieve all content items attached to a program.

**PHP:**
```php
$content = $iris->programs->getContent(5);

foreach ($content as $item) {
    echo "Content: {$item['title']}\n";
    echo "Type: {$item['content_type']}\n";
    echo "Order: {$item['display_order']}\n\n";
}
```

**CLI:**
```bash
# Get program content
./bin/iris sdk:call programs.getContent 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID

**Returns:** `array` - Content items

---

### Attach Content

Add content to a program.

**PHP:**
```php
$result = $iris->programs->attachContent(5, [
    'content_id' => 42,
    'content_type' => 'video',
    'display_order' => 1,
    'is_required' => true
]);

echo "Content attached successfully\n";
```

**CLI:**
```bash
# Attach content
./bin/iris sdk:call programs.attachContent 5 \
    content_id=42 \
    content_type=video \
    display_order=1
```

**Parameters:**
- `programId` (int, required): Program ID
- `content_id` (int, required): Content ID
- `content_type` (string, optional): Type of content
- `display_order` (int, optional): Display order
- `is_required` (bool, optional): Required for completion

**Returns:** `array` - Attachment result

---

### Detach Content

Remove content from a program.

**PHP:**
```php
$success = $iris->programs->detachContent(
    programId: 5,
    contentId: 42
);

if ($success) {
    echo "Content removed from program\n";
}
```

**CLI:**
```bash
# Detach content
./bin/iris sdk:call programs.detachContent 5 42
```

**Parameters:**
- `programId` (int, required): Program ID
- `contentId` (int, required): Content ID

**Returns:** `bool` - Success status

---

## Workflow Management

### Get Workflows

Retrieve all workflows attached to a program.

**PHP:**
```php
$workflows = $iris->programs->getWorkflows(5);

foreach ($workflows as $workflow) {
    echo "Workflow: {$workflow['name']}\n";
    echo "Trigger: {$workflow['trigger_type']}\n\n";
}
```

**CLI:**
```bash
# Get workflows
./bin/iris sdk:call programs.getWorkflows 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID

**Returns:** `array` - Workflow list

---

### Attach Workflow

Attach an automation workflow to a program.

**PHP:**
```php
// Basic attachment
$result = $iris->programs->attachWorkflow(
    programId: 5,
    workflowId: 45
);

// With configuration
$result = $iris->programs->attachWorkflow(
    programId: 5,
    workflowId: 45,
    config: [
        'is_required' => true,
        'display_order' => 1,
        'enrollment_trigger' => true  // Run on enrollment
    ]
);

echo "Workflow attached successfully\n";
```

**CLI:**
```bash
# Attach workflow
./bin/iris sdk:call programs.attachWorkflow 5 45

# With enrollment trigger
./bin/iris sdk:call programs.attachWorkflow 5 45 \
    enrollment_trigger=true
```

**Parameters:**
- `programId` (int, required): Program ID
- `workflowId` (int, required): Workflow ID
- `is_required` (bool, optional): Required workflow
- `display_order` (int, optional): Display order
- `enrollment_trigger` (bool, optional): Trigger on enrollment

**Returns:** `array` - Attachment result

---

### Update Workflow

Update workflow attachment configuration.

**PHP:**
```php
$result = $iris->programs->updateWorkflow(
    programId: 5,
    workflowId: 45,
    config: [
        'enrollment_trigger' => false,
        'display_order' => 5
    ]
);

echo "Workflow configuration updated\n";
```

**CLI:**
```bash
# Update workflow
./bin/iris sdk:call programs.updateWorkflow 5 45 \
    display_order=5
```

**Parameters:**
- `programId` (int, required): Program ID
- `workflowId` (int, required): Workflow ID
- `config` (array, required): Configuration to update

**Returns:** `array` - Update result

---

### Detach Workflow

Remove a workflow from a program.

**PHP:**
```php
$success = $iris->programs->detachWorkflow(
    programId: 5,
    workflowId: 45
);

if ($success) {
    echo "Workflow removed from program\n";
}
```

**CLI:**
```bash
# Detach workflow
./bin/iris sdk:call programs.detachWorkflow 5 45
```

**Parameters:**
- `programId` (int, required): Program ID
- `workflowId` (int, required): Workflow ID

**Returns:** `bool` - Success status

---

### Get Workflow Execution Logs

View execution history for program workflows.

**PHP:**
```php
$logs = $iris->programs->getWorkflowExecutionLogs(5, [
    'page' => 1,
    'per_page' => 50
]);

foreach ($logs as $log) {
    echo "Workflow: {$log['workflow_name']}\n";
    echo "Status: {$log['status']}\n";
    echo "Executed: {$log['executed_at']}\n\n";
}
```

**CLI:**
```bash
# Get execution logs
./bin/iris sdk:call programs.getWorkflowExecutionLogs 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID
- `page` (int, optional): Page number
- `per_page` (int, optional): Items per page

**Returns:** `array` - Execution logs

---

## Additional Features

### Check Access

Verify if a user has access to a program.

**PHP:**
```php
$access = $iris->programs->checkAccess(
    programId: 5,
    userId: 193
);

if ($access['has_access']) {
    echo "User has access\n";
    echo "Access level: {$access['access_level']}\n";
} else {
    echo "Access denied: {$access['reason']}\n";
}
```

**CLI:**
```bash
# Check access
./bin/iris sdk:call programs.checkAccess 5 user_id=193 --json
```

**Parameters:**
- `programId` (int, required): Program ID
- `userId` (int, required): User ID

**Returns:** `array` - Access information

---

### Chat Messages

Manage program community chat.

**Get Chat Messages:**

```php
$messages = $iris->programs->getChatMessages(5, [
    'page' => 1,
    'per_page' => 50
]);

foreach ($messages as $msg) {
    echo "{$msg['user']['name']}: {$msg['message']}\n";
}
```

**Send Chat Message:**

```php
$result = $iris->programs->sendChatMessage(5, [
    'message' => 'Welcome to the program!',
    'user_id' => 193,
    'metadata' => [
        'type' => 'announcement'
    ]
]);

echo "Message sent successfully\n";
```

**Get Chat Activity:**

```php
$activity = $iris->programs->getChatActivity(5);

echo "Total messages: {$activity['total_messages']}\n";
echo "Active users: {$activity['active_users']}\n";
```

**CLI:**
```bash
# Get messages
./bin/iris sdk:call programs.getChatMessages 5

# Send message
./bin/iris sdk:call programs.sendChatMessage 5 \
    message="Hello" \
    user_id=193

# Get activity
./bin/iris sdk:call programs.getChatActivity 5 --json
```

---

### Packages

Get pricing packages for a program.

**PHP:**
```php
$packages = $iris->programs->getPackages(5);

foreach ($packages as $pkg) {
    echo "Package: {$pkg['name']}\n";
    echo "Price: \${$pkg['price']}\n";
    echo "Duration: {$pkg['duration_months']} months\n\n";
}
```

**CLI:**
```bash
# Get packages
./bin/iris sdk:call programs.getPackages 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID

**Returns:** `array` - Pricing packages

---

### Memberships

Get active memberships for a program.

**PHP:**
```php
$memberships = $iris->programs->getMemberships(5, [
    'active' => true,
    'page' => 1,
    'per_page' => 100
]);

echo "Active memberships: " . count($memberships) . "\n";
```

**CLI:**
```bash
# Get memberships
./bin/iris sdk:call programs.getMemberships 5 --json
```

**Parameters:**
- `programId` (int, required): Program ID
- `active` (bool, optional): Filter by active status
- `page` (int, optional): Page number
- `per_page` (int, optional): Items per page

**Returns:** `array` - Membership list

---

## Working with Collections

The `ProgramCollection` class provides array-like access and useful methods.

```php
$programs = $iris->programs->list(['bloq_id' => 56]);

// Count programs
echo "Total: " . count($programs) . "\n";

// Array access
$firstProgram = $programs[0];

// Iteration
foreach ($programs as $program) {
    echo $program->name . "\n";
}

// Filter methods
$freePrograms = $programs->onlyFree();
$paidPrograms = $programs->onlyPaid();
$activePrograms = $programs->onlyActive();

// Utility methods
$isEmpty = $programs->isEmpty();
$hasMore = $programs->hasMorePages();
$first = $programs->first();

// Custom filtering
$filtered = $programs->filter(function($program) {
    return $program->tier === 'premium' && $program->basePrice < 100;
});

// Mapping
$names = $programs->map(fn($p) => $p->name);

// Convert to array
$array = $programs->toArray();
```

---

## Model Reference

### Program Model

**Properties:**
- `id` (int): Program ID
- `name` (string): Program name
- `slug` (string): URL slug
- `description` (string): Description
- `landingPageContent` (string): Landing page HTML
- `imageUrl` (string): Cover image URL
- `active` (bool): Active status
- `tier` (string): Tier level
- `bloqId` (int): Associated bloq ID
- `mailjetListId` (string): Email list ID
- `hasPaidMembership` (bool): Requires payment
- `requiresMembership` (bool): Membership required
- `allowFreeEnrollment` (bool): Free enrollment allowed
- `basePrice` (float): Base price
- `membershipFeatures` (array): Feature list
- `packages` (array): Pricing packages
- `customFields` (array): Custom fields
- `enrollmentFormConfig` (array): Form configuration
- `createdAt` (string): Creation date
- `updatedAt` (string): Last update date

**Methods:**
- `isFree()`: Check if program is free
- `isPaid()`: Check if program is paid
- `isActive()`: Check if program is active
- `hasPackages()`: Check if program has pricing packages
- `getDisplayPrice()`: Get formatted price string
- `toArray()`: Convert to array

---

### ProgramEnrollment Model

**Properties:**
- `id` (int): Enrollment ID
- `programId` (int): Program ID
- `userId` (int): User ID
- `packageId` (int): Package ID
- `status` (string): Enrollment status
- `amountPaid` (float): Amount paid
- `enrolledAt` (string): Enrollment date
- `expiresAt` (string): Expiration date
- `cancelledAt` (string): Cancellation date

**Methods:**
- `isActive()`: Check if enrollment is active
- `isCancelled()`: Check if enrollment is cancelled
- `hasExpired()`: Check if enrollment has expired
- `getDurationDays()`: Get enrollment duration in days
- `toArray()`: Convert to array

---

## CLI Usage

The CLI provides full access to all Programs methods through the dynamic proxy.

**General Format:**
```bash
./bin/iris sdk:call programs.<method> [args] [--options]
```

**Common Options:**
- `--json`: Output as JSON
- `--raw`: Raw output without formatting
- `-vvv`: Verbose mode (show errors)

**Examples:**

```bash
# List programs
./bin/iris sdk:call programs.list
./bin/iris sdk:call programs.list bloq_id=56 --json

# Search
./bin/iris sdk:call programs.search "AI"
./bin/iris sdk:call programs.search "Newsletter" tier=premium

# Get details
./bin/iris sdk:call programs.get 5
./bin/iris sdk:call programs.get 5 --json

# Create
./bin/iris sdk:call programs.create \
    name="New Program" \
    bloq_id=56 \
    tier=premium

# Update
./bin/iris sdk:call programs.update 5 \
    name="Updated Name"

# Delete
./bin/iris sdk:call programs.delete 5

# Enrollments
./bin/iris sdk:call programs.enroll 5 user_id=193
./bin/iris sdk:call programs.cancelEnrollment 5 user_id=193
./bin/iris sdk:call programs.getUserEnrollments 193 --json

# Content
./bin/iris sdk:call programs.getContent 5 --json
./bin/iris sdk:call programs.attachContent 5 content_id=42

# Workflows
./bin/iris sdk:call programs.getWorkflows 5 --json
./bin/iris sdk:call programs.attachWorkflow 5 45
```

---

## Error Handling

The SDK throws exceptions for errors. Always wrap calls in try-catch blocks.

```php
use IRIS\SDK\Exceptions\IRISException;
use IRIS\SDK\Exceptions\ValidationException;
use IRIS\SDK\Exceptions\AuthenticationException;

try {
    $program = $iris->programs->get(999);
} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}\n";
} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    print_r($e->getErrors());
} catch (IRISException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "Status code: {$e->getCode()}\n";
}
```

**Common Error Codes:**
- `401`: Unauthorized - Invalid or expired token
- `403`: Forbidden - Insufficient permissions
- `404`: Not Found - Program doesn't exist
- `422`: Validation Error - Invalid input data
- `429`: Rate Limit - Too many requests
- `500`: Server Error - Internal server issue

---

## Examples

### Example 1: Create Complete Membership Program

```php
<?php

require 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => getenv('IRIS_API_KEY'),
    'user_id' => 193,
]);

try {
    // Step 1: Create the program
    $program = $iris->programs->create([
        'name' => 'Premium AI Mastery',
        'slug' => 'premium-ai-mastery',
        'description' => 'Complete AI training with live coaching',
        'bloq_id' => 56,
        'tier' => 'premium',
        'has_paid_membership' => true,
        'base_price' => 297.00,
        'active' => true,
        'membership_features' => [
            'Weekly live coaching calls',
            'Private community access',
            'Course library (50+ lessons)',
            'AI tool templates',
            'Priority support'
        ],
        'landing_page_content' => '<h1>Transform Your Career with AI</h1>',
    ]);

    echo "✓ Program created: {$program->name} (ID: {$program->id})\n";

    // Step 2: Attach workflow (welcome sequence)
    $iris->programs->attachWorkflow($program->id, 45, [
        'enrollment_trigger' => true,
        'is_required' => true
    ]);

    echo "✓ Welcome workflow attached\n";

    // Step 3: Add content
    $contentIds = [10, 11, 12, 13, 14]; // Course modules
    foreach ($contentIds as $order => $contentId) {
        $iris->programs->attachContent($program->id, [
            'content_id' => $contentId,
            'display_order' => $order + 1,
            'is_required' => true
        ]);
    }

    echo "✓ Content attached: " . count($contentIds) . " modules\n";

    // Step 4: Enroll beta testers
    $betaUsers = [193, 194, 195];
    foreach ($betaUsers as $userId) {
        $iris->programs->enroll($program->id, $userId);
    }

    echo "✓ Enrolled " . count($betaUsers) . " beta testers\n";

    echo "\n✅ Program setup complete!\n";
    echo "View at: https://app.heyiris.io/programs/{$program->id}\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    exit(1);
}
```

---

### Example 2: Bulk Enrollment with Package Selection

```php
<?php

$programId = 5;
$userIds = [193, 194, 195, 196, 197];

// Get program packages
$packages = $iris->programs->getPackages($programId);
$monthlyPackage = $packages[0]; // Assume first is monthly

$enrolled = 0;
$errors = [];

foreach ($userIds as $userId) {
    try {
        // Check if already enrolled
        $enrollments = $iris->programs->getUserEnrollments($userId);
        $alreadyEnrolled = false;
        
        foreach ($enrollments as $enrollment) {
            if ($enrollment['program_id'] === $programId) {
                $alreadyEnrolled = true;
                break;
            }
        }
        
        if ($alreadyEnrolled) {
            echo "User {$userId} already enrolled, skipping...\n";
            continue;
        }
        
        // Enroll user
        $enrollment = $iris->programs->enroll($programId, $userId, [
            'package_id' => $monthlyPackage['id']
        ]);
        
        $enrolled++;
        echo "✓ Enrolled user {$userId}\n";
        
    } catch (Exception $e) {
        $errors[] = "User {$userId}: {$e->getMessage()}";
    }
}

echo "\n✅ Enrolled {$enrolled} users\n";

if (!empty($errors)) {
    echo "\n⚠️ Errors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}
```

---

### Example 3: Program Analytics Dashboard

```php
<?php

function getProgramAnalytics($iris, $programId) {
    // Get program details
    $program = $iris->programs->get($programId);
    
    // Get all memberships
    $memberships = $iris->programs->getMemberships($programId, [
        'active' => true,
        'per_page' => 1000
    ]);
    
    // Get workflow execution logs
    $logs = $iris->programs->getWorkflowExecutionLogs($programId, [
        'per_page' => 100
    ]);
    
    // Get chat activity
    $chatActivity = $iris->programs->getChatActivity($programId);
    
    // Get content
    $content = $iris->programs->getContent($programId);
    
    // Calculate metrics
    $analytics = [
        'program_name' => $program->name,
        'tier' => $program->tier,
        'active_status' => $program->isActive() ? 'Active' : 'Inactive',
        'price' => $program->getDisplayPrice(),
        'total_members' => count($memberships),
        'total_content' => count($content),
        'workflow_executions' => count($logs),
        'chat_messages' => $chatActivity['total_messages'] ?? 0,
        'active_chat_users' => $chatActivity['active_users'] ?? 0,
        'revenue_estimate' => count($memberships) * ($program->basePrice ?? 0),
    ];
    
    return $analytics;
}

// Usage
$programId = 5;
$analytics = getProgramAnalytics($iris, $programId);

echo "=== Program Analytics Dashboard ===\n\n";
echo "Program: {$analytics['program_name']}\n";
echo "Tier: {$analytics['tier']}\n";
echo "Status: {$analytics['active_status']}\n";
echo "Price: {$analytics['price']}\n\n";

echo "Membership:\n";
echo "  Active Members: {$analytics['total_members']}\n";
echo "  Revenue (Estimate): \$" . number_format($analytics['revenue_estimate'], 2) . "\n\n";

echo "Content:\n";
echo "  Total Items: {$analytics['total_content']}\n";
echo "  Workflow Runs: {$analytics['workflow_executions']}\n\n";

echo "Community:\n";
echo "  Chat Messages: {$analytics['chat_messages']}\n";
echo "  Active Users: {$analytics['active_chat_users']}\n";
```

---

### Example 4: Migration Script - Import Programs

```php
<?php

// Import programs from CSV
$csvFile = 'programs.csv';
$programs = [];

if (($handle = fopen($csvFile, 'r')) !== false) {
    $header = fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        $programs[] = array_combine($header, $row);
    }
    
    fclose($handle);
}

$imported = 0;
$errors = [];

foreach ($programs as $data) {
    try {
        $program = $iris->programs->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'bloq_id' => (int)$data['bloq_id'],
            'tier' => $data['tier'],
            'base_price' => (float)$data['price'],
            'has_paid_membership' => $data['is_paid'] === 'true',
            'allow_free_enrollment' => $data['is_free'] === 'true',
            'active' => true,
        ]);
        
        echo "✓ Imported: {$program->name}\n";
        $imported++;
        
    } catch (Exception $e) {
        $errors[] = "{$data['name']}: {$e->getMessage()}";
    }
}

echo "\n✅ Imported {$imported} programs\n";

if (!empty($errors)) {
    echo "\n⚠️ Errors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}
```

---

### Example 5: Auto-Enrollment Based on Purchase

```php
<?php

// Webhook handler for Stripe purchase events
function handleStripePurchase($webhookData) {
    global $iris;
    
    $productId = $webhookData['product_id'];
    $customerId = $webhookData['customer_id'];
    
    // Map product IDs to program IDs
    $productProgramMap = [
        'prod_123' => 5,   // Monthly membership
        'prod_456' => 6,   // Annual membership
        'prod_789' => 7,   // Lifetime access
    ];
    
    if (!isset($productProgramMap[$productId])) {
        throw new Exception("Unknown product: {$productId}");
    }
    
    $programId = $productProgramMap[$productId];
    
    // Get user ID from customer
    $userId = getUserIdFromStripeCustomer($customerId);
    
    try {
        // Enroll user in program
        $enrollment = $iris->programs->enroll($programId, $userId, [
            'custom_fields' => [
                'stripe_subscription_id' => $webhookData['subscription_id'],
                'purchase_date' => date('Y-m-d H:i:s'),
                'amount_paid' => $webhookData['amount'] / 100,
            ]
        ]);
        
        // Send welcome message
        $program = $iris->programs->get($programId);
        $iris->programs->sendChatMessage($programId, [
            'message' => "Welcome {$webhookData['customer_name']}! 🎉 Thanks for joining {$program->name}!",
            'user_id' => $userId,
            'metadata' => ['type' => 'welcome']
        ]);
        
        echo "✓ User {$userId} enrolled in program {$programId}\n";
        
        return [
            'success' => true,
            'enrollment_id' => $enrollment->id
        ];
        
    } catch (Exception $e) {
        echo "❌ Enrollment failed: {$e->getMessage()}\n";
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

---

## Best Practices

### 1. Always Check Enrollment Before Enrolling

```php
$enrollments = $iris->programs->getUserEnrollments($userId);
$isEnrolled = false;

foreach ($enrollments as $enrollment) {
    if ($enrollment['program_id'] === $programId && $enrollment['status'] === 'active') {
        $isEnrolled = true;
        break;
    }
}

if (!$isEnrolled) {
    $iris->programs->enroll($programId, $userId);
}
```

### 2. Use Transactions for Complex Operations

```php
try {
    // Create program
    $program = $iris->programs->create([...]);
    
    // Attach workflows
    $iris->programs->attachWorkflow($program->id, 45);
    
    // Add content
    foreach ($contentIds as $contentId) {
        $iris->programs->attachContent($program->id, ['content_id' => $contentId]);
    }
    
} catch (Exception $e) {
    // If anything fails, clean up
    if (isset($program)) {
        $iris->programs->delete($program->id);
    }
    
    throw $e;
}
```

### 3. Cache Program Data

```php
// Cache program list for 5 minutes
$cacheKey = 'programs_bloq_56';
$programs = cache()->remember($cacheKey, 300, function() use ($iris) {
    return $iris->programs->list(['bloq_id' => 56]);
});
```

### 4. Handle Rate Limits

```php
use IRIS\SDK\Exceptions\RateLimitException;

try {
    $program = $iris->programs->get($id);
} catch (RateLimitException $e) {
    // Wait and retry
    $retryAfter = $e->getRetryAfter(); // seconds
    sleep($retryAfter);
    $program = $iris->programs->get($id);
}
```

### 5. Validate Input Data

```php
function validateProgramData(array $data): array {
    $errors = [];
    
    if (empty($data['name'])) {
        $errors[] = 'Program name is required';
    }
    
    if (isset($data['base_price']) && !is_numeric($data['base_price'])) {
        $errors[] = 'Base price must be a number';
    }
    
    if (!in_array($data['tier'] ?? '', ['free', 'premium', 'VIP', 'elite'])) {
        $errors[] = 'Invalid tier';
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(', ', $errors));
    }
    
    return $data;
}

// Usage
$programData = validateProgramData($_POST);
$program = $iris->programs->create($programData);
```

---

## Support

- **Documentation**: [https://docs.heyiris.io](https://docs.heyiris.io)
- **API Reference**: [https://api.heyiris.io/docs](https://api.heyiris.io/docs)
- **GitHub**: [https://github.com/iris-sdk](https://github.com/iris-sdk)
- **Support**: support@heyiris.io

---

## License

MIT License - See LICENSE file for details.
