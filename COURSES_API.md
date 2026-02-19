# Courses API - Learning Management System

The IRIS Courses API allows you to create, manage, and deliver online courses with structured learning paths, progress tracking, and enrollment management.

## Table of Contents

- [Overview](#overview)
- [Key Concepts](#key-concepts)
- [SDK Usage](#sdk-usage)
- [CLI Usage](#cli-usage)
- [API Endpoints](#api-endpoints)
- [Complete Examples](#complete-examples)

---

## Overview

The Courses system enables you to:
- Create structured learning experiences with chapters and content
- Track student progress per content item
- Manage enrollments and access control
- Organize content (videos, articles) into sequential chapters
- Set difficulty levels and learning objectives
- Enable/disable certificates

### Architecture

```
Course (learning structure)
  ↓ extends
Program (billing & access control)
  ↓ contains
ProgramEnrollment (user access)

Course
  ↓ has many
CourseChapter (sequential organization)
  ↓ has many  
ChapterContent (videos, articles)
  ↓ tracks
CourseProgress (per user, per item)
```

**Key Design**: A Course extends a Program (1:1 relationship), reusing 80%+ of existing infrastructure for pricing, payments, and user access management.

---

## Key Concepts

### 1. Course
The main learning container that holds metadata and structure:
- **program_id**: Links to Program for billing/enrollment
- **instructor_user_id**: The course instructor
- **difficulty_level**: `beginner`, `intermediate`, or `advanced`
- **is_published**: Whether course appears in marketplace
- **learning_objectives**: Array of learning goals
- **estimated_duration_minutes**: Expected completion time

### 2. Chapter
Sequential organization within a course:
- **title**: Chapter name (e.g., "Introduction to React")
- **description**: What students will learn
- **display_order**: Position in course sequence

### 3. Content
Actual learning materials linked to chapters:
- **content_type**: `video` or `article`
- **content_id**: ID of the video/article
- **display_order**: Position within chapter

### 4. Progress Tracking
Granular tracking per content item:
- **status**: `not_started`, `in_progress`, `completed`
- **progress_percentage**: 0-100 (useful for video watch tracking)
- **time_spent_seconds**: Optional time tracking

---

## SDK Usage

### Installation

The Courses resource is automatically available in the IRIS SDK:

```php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => 'your_api_key',
    'user_id' => 123
]);

// Access courses resource
$courses = $iris->courses;
```

### List Marketplace Courses

```php
// Get all published courses
$courseList = $iris->courses->list();

foreach ($courseList->items() as $course) {
    echo "Course: {$course->title}\n";
    echo "Difficulty: {$course->difficulty_level}\n";
    echo "Duration: {$course->estimated_duration_minutes} min\n";
    echo "---\n";
}

// Filter by difficulty
$beginnerCourses = $iris->courses->list(['difficulty' => 'beginner']);

// Search courses
$results = $iris->courses->search('React', [
    'difficulty' => 'intermediate'
]);

// Pagination
$page2 = $iris->courses->list([
    'page' => 2,
    'per_page' => 10
]);
```

### Get Course Details

```php
// Get full course with chapters
$course = $iris->courses->get(123);

echo "Title: {$course->title}\n";
echo "Instructor: {$course->instructor['name']}\n";
echo "Chapters: " . count($course->chapters) . "\n";

// Access chapters
foreach ($course->chapters as $chapter) {
    echo "\nChapter {$chapter['display_order']}: {$chapter['title']}\n";
    
    foreach ($chapter['content'] as $content) {
        echo "  - {$content['content_type']}: {$content['title']}\n";
    }
}

// Check learning objectives
print_r($course->learning_objectives);
```

### Create a Course

```php
// First, create a Program for billing/access (or use existing)
$program = $iris->programs->create([
    'name' => 'Advanced React Development',
    'description' => 'Master React with hooks, context, and advanced patterns',
    'has_paid_membership' => true,
    'base_price' => 99.00,
    'allow_free_enrollment' => false
]);

// Then create the course
$course = $iris->courses->create([
    'program_id' => $program->id,
    'instructor_user_id' => 456,  // Instructor's user ID
    'difficulty_level' => 'advanced',
    'estimated_duration_minutes' => 480,  // 8 hours
    'is_published' => true,
    'certificate_enabled' => true,
    'thumbnail_url' => 'https://example.com/course-thumb.jpg',
    'learning_objectives' => [
        'Build complex React applications with hooks',
        'Implement advanced state management patterns',
        'Optimize React performance',
        'Deploy production-ready React apps'
    ]
]);

echo "Course created with ID: {$course->id}\n";
```

### Update Course

```php
$course = $iris->courses->update(123, [
    'difficulty_level' => 'intermediate',
    'is_published' => true,
    'estimated_duration_minutes' => 360
]);
```

### Delete Course

```php
// Deletes course and associated Program
$success = $iris->courses->delete(123);
```

### Add Chapters to Course

```php
// Add first chapter
$chapter1 = $iris->courses->addChapter(123, [
    'title' => 'Introduction to React',
    'description' => 'Learn the basics of React components and JSX',
    'display_order' => 1
]);

// Add second chapter
$chapter2 = $iris->courses->addChapter(123, [
    'title' => 'State Management with Hooks',
    'description' => 'Master useState and useEffect',
    'display_order' => 2
]);

// Add third chapter
$chapter3 = $iris->courses->addChapter(123, [
    'title' => 'Advanced Patterns',
    'description' => 'Context, custom hooks, and performance optimization',
    'display_order' => 3
]);
```

### Add Content to Chapters

```php
// Add video to chapter
$iris->courses->addContentToChapter(123, $chapter1['id'], [
    'content_type' => 'video',
    'content_data' => [
        'video_id' => 789,  // ID from 'tv' table
        'title' => 'What is React?',
        'duration_seconds' => 600
    ],
    'display_order' => 1
]);

// Add article to chapter
$iris->courses->addContentToChapter(123, $chapter1['id'], [
    'content_type' => 'article',
    'content_data' => [
        'article_id' => 456,  // ID from 'magazine' table
        'title' => 'Setting up your development environment',
        'estimated_read_time_minutes' => 10
    ],
    'display_order' => 2
]);

// Add another video
$iris->courses->addContentToChapter(123, $chapter1['id'], [
    'content_type' => 'video',
    'content_data' => [
        'video_id' => 790,
        'title' => 'Your First React Component',
        'duration_seconds' => 900
    ],
    'display_order' => 3
]);
```

### Manage Chapter Order

```php
// Update chapter details
$iris->courses->updateChapter(123, $chapter1['id'], [
    'title' => 'Getting Started with React',
    'description' => 'Updated description'
]);

// Reorder chapters (pass array of chapter IDs in desired order)
$iris->courses->reorderChapters(123, [
    $chapter2['id'],  // Move chapter 2 to position 1
    $chapter1['id'],  // Move chapter 1 to position 2
    $chapter3['id']   // Keep chapter 3 in position 3
]);

// Delete a chapter
$iris->courses->deleteChapter(123, $chapter1['id']);
```

### Manage Content Order

```php
// Reorder content within a chapter
$iris->courses->reorderChapterContent(123, $chapter1['id'], [
    $content3['id'],  // Move content 3 to first
    $content1['id'],  // Move content 1 to second
    $content2['id']   // Move content 2 to third
]);

// Remove content from chapter
$iris->courses->removeContentFromChapter(123, $chapter1['id'], $content1['id']);
```

### Enroll Users

```php
// Enroll a user in the course
$enrollment = $iris->courses->enroll(123, 456);

echo "User {$enrollment['user_id']} enrolled in course {$enrollment['course_id']}\n";
echo "Status: {$enrollment['status']}\n";
echo "Enrolled at: {$enrollment['enrolled_at']}\n";
```

### Track Progress

```php
// Get user's overall progress
$progress = $iris->courses->getProgress(123, 456);

echo "Total items: {$progress['total_items']}\n";
echo "Completed: {$progress['completed_items']}\n";
echo "Progress: {$progress['percentage']}%\n";
echo "Course completed: " . ($progress['is_completed'] ? 'Yes' : 'No') . "\n";

// Update progress when user completes a video
$iris->courses->updateProgress(123, 456, [
    'chapter_id' => 1,
    'content_id' => 789,
    'content_type' => 'video',
    'status' => 'completed',
    'progress_percentage' => 100,
    'time_spent_seconds' => 600
]);

// Update progress for in-progress video (e.g., watched 50%)
$iris->courses->updateProgress(123, 456, [
    'chapter_id' => 1,
    'content_id' => 790,
    'content_type' => 'video',
    'status' => 'in_progress',
    'progress_percentage' => 50,
    'time_spent_seconds' => 450
]);

// Mark article as completed
$iris->courses->updateProgress(123, 456, [
    'chapter_id' => 1,
    'content_id' => 456,
    'content_type' => 'article',
    'status' => 'completed',
    'progress_percentage' => 100,
    'time_spent_seconds' => 300
]);
```

---

## CLI Usage

The IRIS CLI provides a dynamic proxy to all SDK methods. No special course commands needed!

### List Courses

```bash
# List all marketplace courses
bin/iris call courses.list

# Filter by difficulty
bin/iris call courses.list difficulty=beginner

# Search courses
bin/iris call courses.search "React" difficulty=intermediate

# JSON output
bin/iris call courses.list --json

# Paginated results
bin/iris call courses.list page=2 per_page=10
```

### Get Course Details

```bash
# Get course with chapters
bin/iris call courses.get 123

# JSON output for parsing
bin/iris call courses.get 123 --json
```

### Create Course

```bash
# Create course (pass JSON data)
bin/iris call courses.create '{
  "program_id": 5,
  "instructor_user_id": 456,
  "difficulty_level": "advanced",
  "estimated_duration_minutes": 480,
  "is_published": true,
  "certificate_enabled": true,
  "learning_objectives": ["Master React", "Build production apps"]
}'
```

### Update Course

```bash
bin/iris call courses.update 123 '{"is_published": true, "difficulty_level": "intermediate"}'
```

### Delete Course

```bash
bin/iris call courses.delete 123
```

### Manage Chapters

```bash
# Add chapter
bin/iris call courses.addChapter 123 '{
  "title": "Introduction to React",
  "description": "Learn the basics",
  "display_order": 1
}'

# Update chapter
bin/iris call courses.updateChapter 123 45 '{"title": "Getting Started with React"}'

# Delete chapter
bin/iris call courses.deleteChapter 123 45

# Reorder chapters
bin/iris call courses.reorderChapters 123 '[45, 46, 47]'
```

### Manage Content

```bash
# Add video to chapter
bin/iris call courses.addContentToChapter 123 45 '{
  "content_type": "video",
  "content_data": {
    "video_id": 789,
    "title": "What is React?",
    "duration_seconds": 600
  },
  "display_order": 1
}'

# Add article to chapter
bin/iris call courses.addContentToChapter 123 45 '{
  "content_type": "article",
  "content_data": {
    "article_id": 456,
    "title": "Development Environment Setup",
    "estimated_read_time_minutes": 10
  },
  "display_order": 2
}'

# Remove content
bin/iris call courses.removeContentFromChapter 123 45 789

# Reorder content
bin/iris call courses.reorderChapterContent 123 45 '[100, 101, 102]'
```

### Enrollment & Progress

```bash
# Enroll user
bin/iris call courses.enroll 123 456

# Get progress
bin/iris call courses.getProgress 123 456

# Update progress (video completed)
bin/iris call courses.updateProgress 123 456 '{
  "chapter_id": 45,
  "content_id": 789,
  "content_type": "video",
  "status": "completed",
  "progress_percentage": 100,
  "time_spent_seconds": 600
}'

# Update progress (in-progress)
bin/iris call courses.updateProgress 123 456 '{
  "chapter_id": 45,
  "content_id": 790,
  "content_type": "video",
  "status": "in_progress",
  "progress_percentage": 50
}'
```

### CLI Automation Examples

```bash
#!/bin/bash

# Script: Check course completion rates
COURSE_ID=123
USER_IDS=(456 457 458 459)

for user_id in "${USER_IDS[@]}"; do
  progress=$(bin/iris call courses.getProgress $COURSE_ID $user_id --json)
  percentage=$(echo $progress | jq -r '.percentage')
  echo "User $user_id: $percentage% complete"
done

# Script: Bulk enroll users
for user_id in "${USER_IDS[@]}"; do
  bin/iris call courses.enroll $COURSE_ID $user_id
  echo "Enrolled user $user_id"
done

# Script: Generate course report
bin/iris call courses.get $COURSE_ID --json | jq '{
  title: .title,
  difficulty: .difficulty_level,
  chapters: (.chapters | length),
  total_content: [.chapters[].content | length] | add,
  published: .is_published
}'
```

---

## API Endpoints

### Public/Consumer Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/courses` | List marketplace courses (published only) |
| GET | `/api/v1/courses/{id}` | Get course details with chapters |
| POST | `/api/v1/courses/{id}/enroll` | Enroll in course (auth required) |
| GET | `/api/v1/courses/{id}/progress` | Get user's progress (auth required) |
| POST | `/api/v1/courses/{id}/progress` | Update progress (auth required) |

### Creator/Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/courses` | Create course |
| PUT | `/api/v1/courses/{id}` | Update course |
| DELETE | `/api/v1/courses/{id}` | Delete course |

### Chapter Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/courses/{id}/chapters` | Add chapter |
| PUT | `/api/v1/courses/{id}/chapters/{chapterId}` | Update chapter |
| DELETE | `/api/v1/courses/{id}/chapters/{chapterId}` | Delete chapter |
| PUT | `/api/v1/courses/{id}/chapters/reorder` | Reorder chapters |

### Content Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/courses/{id}/chapters/{chapterId}/content` | Add content |
| DELETE | `/api/v1/courses/{id}/chapters/{chapterId}/content/{contentId}` | Remove content |
| PUT | `/api/v1/courses/{id}/chapters/{chapterId}/content/reorder` | Reorder content |

---

## Complete Examples

### Example 1: Create a Full Course from Scratch

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => getenv('IRIS_API_KEY'),
    'user_id' => getenv('IRIS_USER_ID')
]);

// Step 1: Create Program for billing
$program = $iris->programs->create([
    'name' => 'Master Modern React',
    'description' => 'Complete guide to building production React applications',
    'has_paid_membership' => true,
    'base_price' => 149.00,
    'allow_free_enrollment' => false
]);

// Step 2: Create Course
$course = $iris->courses->create([
    'program_id' => $program->id,
    'instructor_user_id' => 456,
    'difficulty_level' => 'intermediate',
    'estimated_duration_minutes' => 720,
    'is_published' => false,  // Draft until content is added
    'certificate_enabled' => true,
    'thumbnail_url' => 'https://cdn.example.com/react-course.jpg',
    'learning_objectives' => [
        'Build complex React applications with hooks',
        'Implement advanced state management',
        'Optimize React performance',
        'Deploy production-ready apps'
    ]
]);

echo "Created course: {$course->id}\n";

// Step 3: Create chapter structure
$chapters = [
    [
        'title' => 'React Fundamentals',
        'description' => 'Core concepts: components, props, and state',
        'videos' => [
            ['id' => 100, 'title' => 'What is React?', 'duration' => 600],
            ['id' => 101, 'title' => 'Your First Component', 'duration' => 900],
            ['id' => 102, 'title' => 'Props and State', 'duration' => 1200]
        ],
        'articles' => [
            ['id' => 50, 'title' => 'Development Setup Guide', 'read_time' => 15]
        ]
    ],
    [
        'title' => 'Hooks Deep Dive',
        'description' => 'Master useState, useEffect, and custom hooks',
        'videos' => [
            ['id' => 103, 'title' => 'Understanding useState', 'duration' => 800],
            ['id' => 104, 'title' => 'useEffect Explained', 'duration' => 1000],
            ['id' => 105, 'title' => 'Custom Hooks', 'duration' => 1100]
        ],
        'articles' => [
            ['id' => 51, 'title' => 'Hooks Best Practices', 'read_time' => 12]
        ]
    ],
    [
        'title' => 'Advanced Patterns',
        'description' => 'Context, performance, and production optimization',
        'videos' => [
            ['id' => 106, 'title' => 'Context API', 'duration' => 900],
            ['id' => 107, 'title' => 'Performance Optimization', 'duration' => 1300],
            ['id' => 108, 'title' => 'Production Deployment', 'duration' => 1000]
        ],
        'articles' => [
            ['id' => 52, 'title' => 'Deployment Checklist', 'read_time' => 10]
        ]
    ]
];

// Step 4: Add chapters with content
foreach ($chapters as $index => $chapterData) {
    echo "Creating chapter: {$chapterData['title']}\n";
    
    $chapter = $iris->courses->addChapter($course->id, [
        'title' => $chapterData['title'],
        'description' => $chapterData['description'],
        'display_order' => $index + 1
    ]);
    
    $contentOrder = 1;
    
    // Add videos
    foreach ($chapterData['videos'] as $video) {
        $iris->courses->addContentToChapter($course->id, $chapter['id'], [
            'content_type' => 'video',
            'content_data' => [
                'video_id' => $video['id'],
                'title' => $video['title'],
                'duration_seconds' => $video['duration']
            ],
            'display_order' => $contentOrder++
        ]);
    }
    
    // Add articles
    foreach ($chapterData['articles'] as $article) {
        $iris->courses->addContentToChapter($course->id, $chapter['id'], [
            'content_type' => 'article',
            'content_data' => [
                'article_id' => $article['id'],
                'title' => $article['title'],
                'estimated_read_time_minutes' => $article['read_time']
            ],
            'display_order' => $contentOrder++
        ]);
    }
}

// Step 5: Publish course
$iris->courses->update($course->id, ['is_published' => true]);

echo "\n✓ Course published successfully!\n";
echo "Course ID: {$course->id}\n";
echo "Total chapters: " . count($chapters) . "\n";
```

### Example 2: Student Learning Experience

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => getenv('IRIS_API_KEY'),
    'user_id' => 789  // Student user ID
]);

$courseId = 123;
$userId = 789;

// Browse marketplace
echo "=== Available Courses ===\n";
$courses = $iris->courses->list(['difficulty' => 'beginner']);

foreach ($courses->items() as $course) {
    echo "\n{$course->title}\n";
    echo "  Difficulty: {$course->difficulty_level}\n";
    echo "  Duration: {$course->estimated_duration_minutes} min\n";
    echo "  Instructor: {$course->instructor['name']}\n";
}

// Get course details
$course = $iris->courses->get($courseId);
echo "\n=== Course Details ===\n";
echo "Title: {$course->title}\n";
echo "Description: {$course->description}\n";
echo "\nLearning Objectives:\n";
foreach ($course->learning_objectives as $objective) {
    echo "  - $objective\n";
}

echo "\nCourse Structure:\n";
foreach ($course->chapters as $chapter) {
    echo "  Chapter {$chapter['display_order']}: {$chapter['title']}\n";
    echo "    ({$chapter['content_count']} lessons)\n";
}

// Enroll in course
try {
    $enrollment = $iris->courses->enroll($courseId, $userId);
    echo "\n✓ Enrolled successfully!\n";
} catch (Exception $e) {
    echo "\n⚠ Already enrolled or payment required\n";
}

// Check initial progress
$progress = $iris->courses->getProgress($courseId, $userId);
echo "\n=== Your Progress ===\n";
echo "Completed: {$progress['completed_items']}/{$progress['total_items']} lessons\n";
echo "Progress: {$progress['percentage']}%\n";

// Student watches first video
echo "\n▶ Watching: What is React?\n";
sleep(2);  // Simulate watching

$iris->courses->updateProgress($courseId, $userId, [
    'chapter_id' => 1,
    'content_id' => 100,
    'content_type' => 'video',
    'status' => 'completed',
    'progress_percentage' => 100,
    'time_spent_seconds' => 600
]);

echo "✓ Lesson completed!\n";

// Check updated progress
$progress = $iris->courses->getProgress($courseId, $userId);
echo "\nProgress: {$progress['percentage']}%\n";
```

### Example 3: Instructor Dashboard (CLI Script)

```bash
#!/bin/bash
# File: instructor-dashboard.sh

COURSE_ID=123

echo "=== Instructor Dashboard ==="
echo ""

# Get course details
course_data=$(bin/iris call courses.get $COURSE_ID --json)

title=$(echo $course_data | jq -r '.title')
difficulty=$(echo $course_data | jq -r '.difficulty_level')
published=$(echo $course_data | jq -r '.is_published')
chapter_count=$(echo $course_data | jq '.chapters | length')

echo "Course: $title"
echo "Difficulty: $difficulty"
echo "Published: $published"
echo "Chapters: $chapter_count"
echo ""

# List all chapters
echo "=== Course Structure ==="
echo $course_data | jq -r '.chapters[] | "Chapter \(.display_order): \(.title) (\(.content | length) items)"'

echo ""
echo "=== Quick Actions ==="
echo "1. Publish course: bin/iris call courses.update $COURSE_ID '{\"is_published\": true}'"
echo "2. Add chapter: bin/iris call courses.addChapter $COURSE_ID '{...}'"
echo "3. View enrollments: bin/iris call programs.enrollments $program_id"
```

---

## Best Practices

### 1. Course Creation Workflow

```
1. Create Program (billing/access)
2. Create Course (learning structure)
3. Add Chapters (sequential organization)
4. Add Content (videos/articles)
5. Review & Test
6. Publish (is_published = true)
```

### 2. Progress Tracking

- Track progress at the **content item level** (per video/article)
- Use `progress_percentage` for partial completion (e.g., video at 50%)
- Use `time_spent_seconds` for analytics
- Mark status as `completed` only when 100% done

### 3. Content Organization

- Keep chapters focused (3-7 content items per chapter)
- Mix videos and articles for variety
- Use `display_order` to control sequence
- Use chapter descriptions to set expectations

### 4. Access Control

- Course access is managed through Program enrollment
- Set `allow_free_enrollment` for free courses
- Use `base_price` for paid courses
- Check enrollment status before showing content

### 5. Performance

- Use pagination for course lists (`per_page`, `page`)
- Filter courses by difficulty for better UX
- Cache course structure to reduce API calls
- Load progress data only when needed

---

## Troubleshooting

### "Course not found"
- Ensure course exists and `is_published = true` for marketplace
- Check user has proper authentication

### "User already enrolled"
- Check enrollment status before calling `enroll()`
- Enrollment is idempotent for free courses

### "Cannot update progress"
- Verify user is enrolled in course
- Ensure `chapter_id` and `content_id` exist
- Check `content_type` matches (`video` or `article`)

### "Invalid content type"
- Only `video` and `article` are supported
- Ensure content exists in `tv` or `magazine` tables

---

## Related Documentation

- [Programs API](PROGRAMS_API.md) - Billing and enrollment system
- [Workflows API](WORKFLOWS.md) - Automation with course events
- [SDK Documentation](README.md) - General SDK usage

---

## Support

- SDK Issues: https://github.com/your-org/iris-sdk-php/issues
- API Status: https://status.heyiris.io
- Contact: support@heyiris.io
