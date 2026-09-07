# Seeder Management System Documentation

## Overview

The Seeder Management System provides a centralized UI for platform administrators to manage database seeders. This system tracks seeder execution status, allows running seeders on-demand, and ensures that required seeders are properly initialized during deployment.

## Problem Statement

On deployment to production, seeders may not be automatically seeded. Administrators need a way to:

1. View which seeders have been executed
2. Identify required seeders that haven't been run
3. Execute seeders on-demand
4. Mark seeders as unseeded if necessary
5. Track who executed each seeder and when

## Architecture

### Database Schema

**Table: `seeder_statuses`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `seeder_class` | string | Full seeder class name (unique) |
| `display_name` | string | Human-readable name for UI |
| `description` | string | Optional description of what the seeder does |
| `category` | string | Category for grouping (platform, users, business, etc.) |
| `is_seeded` | boolean | Whether the seeder has been executed |
| `last_seeded_at` | timestamp | Last execution timestamp |
| `last_seeded_by` | string | Email of user who executed the seeder |
| `records_count` | integer | Approximate number of records seeded |
| `metadata` | jsonb | Additional execution data (output, exit code, etc.) |
| `is_required` | boolean | Whether this seeder is required for platform operation |
| `priority` | integer | Display priority (higher = more important) |
| `created_at` | timestamp | Registration timestamp |
| `updated_at` | timestamp | Last update timestamp |

**Indexes:**
- `category`
- `[category, is_seeded]`
- `[is_required, is_seeded]`

### Model: SeederStatus

```php
class SeederStatus extends Model
{
    protected $fillable = [
        'seeder_class', 'display_name', 'description', 'category',
        'is_seeded', 'last_seeded_at', 'last_seeded_by',
        'records_count', 'metadata', 'is_required', 'priority',
    ];

    protected $casts = [
        'is_seeded' => 'boolean',
        'is_required' => 'boolean',
        'last_seeded_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Scopes
    public function scopeByCategory($query, string $category)
    public function scopeSeeded($query)
    public function scopeNotSeeded($query)
    public function scopeRequired($query)
    public function scopeOrderByPriority($query)

    // Helper Methods
    public function markAsSeeded(string $seededBy, int $recordsCount = 0, array $metadata = [])
    public function markAsUnseeded()
}
```

## API Endpoints

### Admin Routes (Authenticated)

**Base Path:** `/v1/admin/seeders`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | Get all seeder statuses with optional filters |
| GET | `/{seederClass}` | Get specific seeder status |
| POST | `/` | Register a new seeder |
| PUT | `/{seederClass}` | Update seeder metadata |
| DELETE | `/{seederClass}` | Delete seeder registration |
| POST | `/{seederClass}/run` | Execute a specific seeder |
| POST | `/batch-run` | Execute multiple seeders at once |

### GET /v1/admin/seeders

Get all seeder statuses with optional filtering.

**Query Parameters:**
- `category` (optional) - Filter by category
- `required_only` (optional, default: false) - Only show required seeders
- `not_seeded_only` (optional, default: false) - Only show unseeded seeders

**Response:**
```json
{
  "data": [
    {
      "id": "uuid",
      "seeder_class": "ReferenceDataSeeder",
      "display_name": "Reference Data",
      "description": "Platform reference data for grooming profiles...",
      "category": "platform",
      "is_seeded": true,
      "last_seeded_at": "2026-08-02T10:30:00Z",
      "last_seeded_by": "admin@yosalon.com",
      "records_count": 60,
      "is_required": true,
      "priority": 100,
      "created_at": "2026-08-02T10:00:00Z",
      "updated_at": "2026-08-02T10:30:00Z"
    }
  ],
  "summary": {
    "total": 4,
    "seeded": 2,
    "not_seeded": 2,
    "required_not_seeded": 1
  }
}
```

### POST /v1/admin/seeders/{seederClass}/run

Execute a specific seeder.

**Request Body:**
```json
{
  "unseed": false
}
```

**Parameters:**
- `unseed` (optional, default: false) - If true, mark seeder as unseeded instead of running it

**Response (Success):**
```json
{
  "message": "Seeder executed successfully",
  "data": {
    "id": "uuid",
    "seeder_class": "ReferenceDataSeeder",
    "is_seeded": true,
    "last_seeded_at": "2026-08-02T10:30:00Z",
    "last_seeded_by": "admin@yosalon.com",
    "records_count": 60,
    "metadata": {
      "output": "Seeding: ReferenceDataSeeder...",
      "exit_code": 0
    }
  },
  "output": "Seeding: ReferenceDataSeeder...\nDatabase seeding completed successfully."
}
```

**Response (Unseed):**
```json
{
  "message": "Seeder marked as unseeded. Note: Data cleanup must be handled manually or via migration rollback.",
  "data": {
    "id": "uuid",
    "seeder_class": "ReferenceDataSeeder",
    "is_seeded": false,
    "last_seeded_at": null,
    "last_seeded_by": null,
    "records_count": 0
  }
}
```

### POST /v1/admin/seeders/batch-run

Execute multiple seeders at once.

**Request Body:**
```json
{
  "seeder_classes": ["ReferenceDataSeeder", "UserSeeder"],
  "unseed": false
}
```

**Response:**
```json
{
  "message": "Batch seeder execution completed",
  "results": {
    "ReferenceDataSeeder": {
      "status": "success",
      "records_count": 60
    },
    "UserSeeder": {
      "status": "success",
      "records_count": 10
    }
  },
  "errors": {}
}
```

### POST /v1/admin/seeders

Register a new seeder manually.

**Request Body:**
```json
{
  "seeder_class": "NewSeeder",
  "display_name": "New Data",
  "description": "Description of what this seeder does",
  "category": "platform",
  "is_required": true,
  "priority": 50
}
```

**Response:**
```json
{
  "message": "Seeder registered successfully",
  "data": {
    "id": "uuid",
    "seeder_class": "NewSeeder",
    "display_name": "New Data",
    ...
  }
}
```

### POST /v1/admin/seeders/discover

Automatically discover and register all seeders from the seeders directory.

**Request Body:** None

**Response:**
```json
{
  "message": "Seeder discovery completed",
  "discovered": [
    {
      "class": "Database\\Seeders\\ReferenceDataSeeder",
      "file": "ReferenceDataSeeder.php"
    },
    {
      "class": "Database\\Seeders\\UserSeeder",
      "file": "UserSeeder.php"
    }
  ],
  "registered": 2,
  "updated": 0
}
```

**Features:**
- Scans `database/seeders` directory
- Validates each file is a valid Seeder class
- Auto-registers unregistered seeders
- Auto-generates display names from class names (e.g., "ReferenceDataSeeder" → "Reference Data")
- Auto-guesses categories based on naming patterns:
  - `user`, `customer`, `staff` → `users`
  - `salon`, `service`, `business` → `business`
  - `reference`, `platform`, `config` → `platform`
  - `content`, `blog`, `media` → `content`
  - Default → `general`
- Skips `RegisterSeedersSeeder` to avoid self-registration

## Frontend Implementation

### Component: SeederManagement

**Location:** `frontend/src/components/admin/SeederManagement.tsx`

**Features:**
- Display summary statistics (total, seeded, not seeded, required not seeded)
- Filter by category
- Filter to show only required seeders
- Filter to show only unseeded seeders
- "Run All Required" button for quick execution
- Individual seeder execution with play button
- Unseed option for each seeder
- Visual status badges (Seeded/Not Seeded)
- Last execution timestamp display
- Records count display

**State Management:**
- Uses React Query for data fetching and caching
- Mutations for running seeders
- Automatic refetch after operations

**UI Components:**
- Summary cards with icons
- Filter controls
- Seeder list with status indicators
- Action buttons (Run/Unseed)

## Seeder Registration

### RegisterSeedersSeeder

**Location:** `backend/database/seeders/RegisterSeedersSeeder.php`

This seeder registers all platform seeders in the `seeder_statuses` table. It should be run once during initial setup.

**Registered Seeders:**

| Seeder Class | Display Name | Category | Required | Priority |
|--------------|--------------|----------|----------|----------|
| ReferenceDataSeeder | Reference Data | platform | true | 100 |
| UserSeeder | Users | users | false | 50 |
| SalonSeeder | Salons | business | false | 40 |
| ServiceSeeder | Services | business | false | 30 |

**Running the Registration Seeder:**

```bash
php artisan db:seed --class=RegisterSeedersSeeder
```

## Adding New Seeders

### Step 1: Create the Seeder

```bash
php artisan make:seeder NewFeatureSeeder
```

### Step 2: Implement the Seeder

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class NewFeatureSeeder extends Seeder
{
    public function run(): void
    {
        // Your seeding logic here
        DB::table('new_feature_table')->insert([
            // ...
        ]);

        $this->command->info('NewFeatureSeeder completed successfully.');
    }
}
```

### Step 3: Register the Seeder

**Option A: Via API (Recommended for Production)**

```bash
curl -X POST http://api.yosalon.com/v1/admin/seeders \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "seeder_class": "NewFeatureSeeder",
    "display_name": "New Feature Data",
    "description": "Initial data for new feature",
    "category": "platform",
    "is_required": true,
    "priority": 75
  }'
```

**Option B: Via RegisterSeedersSeeder (For Development)**

Add to the `$seeders` array in `RegisterSeedersSeeder.php`:

```php
[
    'seeder_class' => 'NewFeatureSeeder',
    'display_name' => 'New Feature Data',
    'description' => 'Initial data for new feature',
    'category' => 'platform',
    'is_required' => true,
    'priority' => 75,
],
```

Then re-run the registration seeder:

```bash
php artisan db:seed --class=RegisterSeedersSeeder
```

## Usage Workflow

### Initial Setup

1. Run the registration seeder:
   ```bash
   php artisan db:seed --class=RegisterSeedersSeeder
   ```

2. Access the Seeder Management UI in the admin dashboard

3. Review the list of seeders and their status

### Running Seeders

**Single Seeder:**
1. Navigate to Seeder Management
2. Find the seeder you want to run
3. Click the play button
4. Confirm execution
5. Wait for completion
6. Status updates automatically

**All Required Seeders:**
1. Click "Run All Required" button
2. Confirm execution
3. System runs all required unseeded seeders
4. Status updates automatically

### Unseeding

**Warning:** Unseeding only marks the seeder as not executed. Data cleanup must be handled manually or via migration rollback.

1. Find the seeded seeder
2. Click the refresh icon (unseed button)
3. Confirm action
4. Status updates to "Not Seeded"

## Best Practices

### Seeder Design

1. **Idempotent:** Seeders should be safe to run multiple times
2. **Use firstOrCreate:** Avoid duplicate data
3. **Clear output:** Provide informative console output
4. **Error handling:** Handle errors gracefully
5. **Transaction support:** Use database transactions for data integrity

### Required vs Optional

- **Required:** Critical platform data (ReferenceData, permissions, etc.)
- **Optional:** Sample data, test data, non-critical data

### Priority Levels

- **100+:** Critical platform infrastructure
- **50-99:** Important platform features
- **10-49:** Optional features
- **0-9:** Sample/test data

### Categories

- **platform:** Core platform data
- **users:** User-related data
- **business:** Salon/service data
- **content:** Content management data
- **analytics:** Analytics/reporting data

## Security Considerations

- All seeder endpoints require admin authentication
- Seeder execution is logged with user email
- Unseeding requires explicit confirmation
- Artisan commands run with `--force` flag
- Database transactions for data integrity

## Troubleshooting

### Seeder Fails to Execute

**Check:**
1. Seeder class exists and is valid
2. Database connection is working
3. Required migrations have been run
4. Seeder has proper permissions

**Solution:**
- Check the output in the UI
- Review Laravel logs
- Test seeder manually: `php artisan db:seed --class=YourSeeder`

### Seeder Shows as Not Seeded but Data Exists

**Cause:** Seeder was run outside the management system

**Solution:**
- Manually mark as seeded via API or database
- Or re-run the seeder through the UI

### Records Count is Inaccurate

**Cause:** The `estimateRecordsCount` method uses hardcoded values

**Solution:**
- Update the `estimateRecordsCount` method in SeederController
- Or implement actual counting in your seeder

## Migration History

### 2026_08_02_075317 - Create seeder_statuses table
- Created seeder tracking table
- Added indexes for filtering
- Supports metadata and priority

## API Reference

### Controller: Admin\SeederController

**Methods:**
- `index(Request $request)` - List all seeders with filters
- `show(string $seederClass)` - Get specific seeder status
- `run(Request $request, string $seederClass)` - Execute seeder
- `runMultiple(Request $request)` - Execute multiple seeders
- `register(Request $request)` - Register new seeder
- `update(Request $request, string $seederClass)` - Update seeder metadata
- `destroy(string $seederClass)` - Delete seeder registration

## Future Enhancements

### Planned Features

1. **Automatic Seeding on Deployment**
   - Hook into deployment pipeline
   - Auto-run required seeders
   - Rollback on failure

2. **Seeder Dependencies**
   - Define seeder execution order
   - Auto-run dependent seeders
   - Dependency graph visualization

3. **Seeder Versioning**
   - Track seeder versions
   - Incremental updates
   - Migration-like version control

4. **Dry Run Mode**
   - Preview what would be seeded
   - Validate without execution
   - Impact analysis

5. **Seeder Scheduling**
   - Schedule recurring seeders
   - Cron job integration
   - Execution history

6. **Rollback Support**
   - Automatic rollback on failure
   - Seeder-specific down methods
   - Safe data cleanup

## Conclusion

The Seeder Management System provides a robust solution for managing database seeders in production environments. It ensures that critical platform data is properly initialized, provides visibility into seeder status, and enables on-demand execution without requiring command-line access.

This system is particularly valuable for:
- Production deployments where CLI access is limited
- Multi-platform deployments with different requirements
- Teams that need visibility into data initialization
- Platforms with complex seeding requirements

By centralizing seeder management, Yo.Salon ensures data consistency across deployments and provides administrators with the tools needed to maintain platform data integrity.
