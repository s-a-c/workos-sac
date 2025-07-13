# Chinook Database Seeders

This directory contains Laravel seeders generated from the Chinook SQLite database dump (`database/sqldump/chinook.sql`).

## Completed Seeders

The following seeders have been created and are ready to use:

### 1. ArtistSeeder.php
- **Records**: 275 artists
- **Dependencies**: None
- **Status**: ✅ Complete

### 2. GenreSeeder.php
- **Records**: 25 genres
- **Dependencies**: None
- **Status**: ✅ Complete

### 3. MediaTypeSeeder.php
- **Records**: 5 media types
- **Dependencies**: None
- **Status**: ✅ Complete

### 4. EmployeeSeeder.php
- **Records**: 8 employees
- **Dependencies**: None (self-referencing for reports_to)
- **Status**: ✅ Complete

### 5. PlaylistSeeder.php
- **Records**: 18 playlists
- **Dependencies**: None
- **Status**: ✅ Complete

### 6. AlbumSeeder.php
- **Records**: 347 albums
- **Dependencies**: Artists
- **Status**: ✅ Complete

### 7. ChinookDatabaseSeeder.php
- **Purpose**: Main seeder that orchestrates all individual seeders
- **Status**: ✅ Complete (for current seeders)

## Remaining Work

The following seeders still need to be created:

### CustomerSeeder
- **Records**: ~59 customers
- **Dependencies**: Employees (for support_rep_id foreign key)
- **Table**: `customers`

### TrackSeeder
- **Records**: ~3,503 tracks
- **Dependencies**: Albums, Genres, MediaTypes
- **Table**: `tracks`

### InvoiceSeeder
- **Records**: ~412 invoices
- **Dependencies**: Customers
- **Table**: `invoices`

### InvoiceLineSeeder
- **Records**: ~2,240 invoice lines
- **Dependencies**: Invoices, Tracks
- **Table**: `invoice_lines`

### PlaylistTrackSeeder
- **Records**: ~8,715 playlist-track relationships
- **Dependencies**: Playlists, Tracks
- **Table**: `playlist_track`

## Usage

### Running Individual Seeders

```bash
# Run a specific seeder
php artisan db:seed --class=Database\\Seeders\\ArtistSeeder

# Run the main Chinook seeder (runs all completed seeders)
php artisan db:seed --class=Database\\Seeders\\ChinookDatabaseSeeder
```

### Running All Seeders

To run all Chinook seeders, add the following to your main `DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([
        \Database\Seeders\ChinookDatabaseSeeder::class,
    ]);
}
```

Then run:
```bash
php artisan db:seed
```

## Seeding Order

The seeders must be run in the following order to respect foreign key constraints:

1. **Independent Tables** (no dependencies):
   - ArtistSeeder
   - GenreSeeder
   - MediaTypeSeeder
   - EmployeeSeeder
   - PlaylistSeeder

2. **Dependent Tables**:
   - AlbumSeeder (depends on Artists)
   - CustomerSeeder (depends on Employees)
   - TrackSeeder (depends on Albums, Genres, MediaTypes)
   - InvoiceSeeder (depends on Customers)

3. **Junction Tables**:
   - InvoiceLineSeeder (depends on Invoices, Tracks)
   - PlaylistTrackSeeder (depends on Playlists, Tracks)

## Data Source

All data is extracted from `database/sqldump/chinook.sql` which contains the complete Chinook sample database.

## Notes

- All seeders use `updateOrCreate()` to prevent duplicate entries
- All completed seeders contain the complete dataset from the SQL dump
- Large datasets (tracks, invoice_lines, playlist_track) will require efficient processing
- All seeders follow Laravel 12 conventions with strict typing

## Next Steps

1. Create the remaining 5 seeders (Customer, Track, Invoice, InvoiceLine, PlaylistTrack)
2. Update ChinookDatabaseSeeder to include all seeders
3. Test the complete seeding process
4. Optimize for performance if needed
