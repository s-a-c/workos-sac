# Chinook SQL Dump Seeders - Quick Start Guide

## 🚀 Quick Commands

### Basic Usage
```bash
# Seed complete Chinook dataset
php artisan db:seed --class=Database\\Seeders\\ChinookSqlDumpSeeder

# Fresh start with Chinook data
php artisan migrate:fresh --seed --seeder=ChinookDatabaseSeeder

# Test the seeders
php artisan chinook:test-seeders --fresh
```

### Individual Seeders
```bash
# Artists only
php artisan db:seed --class=Database\\Seeders\\ChinookArtistsSeeder

# Genres → Categories conversion
php artisan db:seed --class=Database\\Seeders\\ChinookGenreCategorySeeder

# Complete tracks with relationships
php artisan db:seed --class=Database\\Seeders\\ChinookTracksSeeder
```

## 📊 What You Get

After running the complete seeder:

- **275 Artists** - AC/DC, Queen, Led Zeppelin, etc.
- **347 Albums** - Complete discographies
- **3,483 Tracks** - Full music catalog with metadata
- **25 Genre Categories** - Rock, Jazz, Classical, etc. (as polymorphic categories)
- **8 Employees** - Company staff with hierarchy
- **59 Customers** - Global customer base
- **412 Invoices** - Sales transaction history
- **2,240 Invoice Lines** - Detailed purchase records
- **18 Playlists** - Curated music collections
- **8,715 Playlist-Track Relations** - Complete playlist contents

## ⚡ Performance Tips

### For Large Datasets
```bash
# Increase memory limit
php -d memory_limit=512M artisan db:seed --class=Database\\Seeders\\ChinookSqlDumpSeeder

# Run with verbose output for monitoring
php artisan db:seed --class=Database\\Seeders\\ChinookSqlDumpSeeder -v
```

### Batch Processing
The seeders automatically use optimized batch sizes:
- Small tables (Artists, Playlists): 50 records/batch
- Medium tables (Albums, Customers): 50 records/batch  
- Large tables (Tracks): 100 records/batch
- Junction tables (Playlist-Track): 500 records/batch

## 🔍 Validation & Testing

### Quick Validation
```bash
# Test everything
php artisan chinook:test-seeders

# Validation only (no seeding)
php artisan chinook:test-seeders --validate-only

# Include performance benchmarks
php artisan chinook:test-seeders --performance
```

### Manual Checks
```php
// In tinker
php artisan tinker

// Check data counts
DB::table('artists')->count();        // Should be 275
DB::table('tracks')->count();         // Should be 3,483
DB::table('categories')->where('type', 'genre')->count(); // Should be 25

// Validate relationships
$monitor = new \Database\Seeders\ChinookSeederMonitor();
$validation = $monitor->validateDataIntegrity();
```

## 🎯 Key Features

### Genre → Category Conversion
- Original genres become `Category` records with `type = 'genre'`
- Maintains original IDs for foreign key compatibility
- Tracks get polymorphic category relationships
- Full integration with existing category system

### Data Integrity
- All foreign key relationships preserved
- Original IDs maintained for compatibility
- Comprehensive validation after seeding
- Automatic orphaned record detection

### Error Handling
- Retry logic with exponential backoff
- Transaction safety for all operations
- Detailed error logging and reporting
- Memory management for large datasets

## 🚨 Troubleshooting

### Common Issues

**"Memory limit exceeded"**
```bash
php -d memory_limit=1G artisan db:seed --class=Database\\Seeders\\ChinookSqlDumpSeeder
```

**"Foreign key constraint fails"**
- Run the complete seeder, not individual ones
- Ensure proper seeding order (use ChinookSqlDumpSeeder)

**"Duplicate entry"**
- Seeders automatically skip existing records
- Use `migrate:fresh` for clean start

**"SQL dump file not found"**
- Ensure `database/sqldump/chinook.sql` exists
- Check file permissions

### Debug Mode
```bash
# Enable detailed logging
php artisan db:seed --class=Database\\Seeders\\ChinookSqlDumpSeeder -vvv
```

## 📈 Monitoring

### Real-time Progress
```
🎤 Starting Chinook Artists seeding...
📊 Artists seeding: 100/275 (36.4%) | Memory: 47.8 MB | Peak: 52.1 MB
✅ Artists seeding completed: 275 created, 0 skipped, 0 errors
```

### Detailed Reports
Reports are automatically saved to `storage/logs/`:
```
chinook_seeding_report_2024-01-15_14-30-25.json
```

### Summary Output
```
🎵 CHINOOK SEEDING REPORT
==========================================
📊 Summary:
  • Total Duration: 45.67s
  • Operations: 11
  • Errors: 0
  • Warnings: 2
  • Peak Memory: 128.5 MB
```

## 🔧 Customization

### Batch Sizes
Modify batch sizes in individual seeders:
```php
// In ChinookTracksSeeder.php
$batchSize = 200; // Increase for better performance
```

### Memory Limits
Adjust memory management thresholds:
```php
// In ChinookSeederHelpers.php
$memoryLimit = 256 * 1024 * 1024; // 256MB
```

### Error Handling
Customize retry logic:
```php
// In ChinookSeederHelpers.php
$maxRetries = 5; // Increase retry attempts
```

## 📚 Next Steps

1. **Explore the Data**: Use Laravel models to interact with the seeded data
2. **Build Features**: Create music browsing, playlist management, sales reporting
3. **Extend Categories**: Add more category types beyond genres
4. **Performance Tuning**: Add database indexes for your specific use cases
5. **API Development**: Build REST APIs using the rich dataset

## 🤝 Support

- Check the full documentation in `README.md`
- Review the dataset analysis in `CHINOOK_ANALYSIS.md`
- Use the test command for validation: `php artisan chinook:test-seeders`
- Enable verbose logging for debugging: `-vvv` flag
