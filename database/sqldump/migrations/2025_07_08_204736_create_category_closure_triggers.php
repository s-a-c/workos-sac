<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Skip complex triggers for SQLite as it doesn't support stored procedures
        // and advanced trigger features. The closure table will be maintained
        // programmatically in the application layer for SQLite.
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we'll rely on application-level maintenance of the closure table
            // This is a common approach when database-specific features aren't available
            return;
        }

        // MySQL-specific implementation with stored procedures and triggers
        if (DB::getDriverName() === 'mysql') {
            // Create stored procedure for rebuilding closure table for a category
            DB::unprepared('
                CREATE PROCEDURE rebuild_closure_for_category(IN category_id BIGINT UNSIGNED)
                BEGIN
                    DECLARE done INT DEFAULT FALSE;
                    DECLARE current_ancestor BIGINT UNSIGNED;
                    DECLARE current_depth INT;

                    -- Cursor for all ancestors of the category
                    DECLARE ancestor_cursor CURSOR FOR
                        WITH RECURSIVE category_ancestors AS (
                            SELECT id, parent_id, 0 as depth
                            FROM categories
                            WHERE id = category_id

                            UNION ALL

                            SELECT c.id, c.parent_id, ca.depth + 1
                            FROM categories c
                            INNER JOIN category_ancestors ca ON c.id = ca.parent_id
                        )
                        SELECT id, depth FROM category_ancestors;

                    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

                    -- Delete existing closure records for this category
                    DELETE FROM category_closure WHERE descendant_id = category_id;

                    -- Insert self-reference
                    INSERT INTO category_closure (ancestor_id, descendant_id, depth, created_at, updated_at)
                    VALUES (category_id, category_id, 0, NOW(), NOW());

                    -- Insert ancestor relationships
                    OPEN ancestor_cursor;
                    read_loop: LOOP
                        FETCH ancestor_cursor INTO current_ancestor, current_depth;
                        IF done THEN
                            LEAVE read_loop;
                        END IF;

                        IF current_ancestor != category_id THEN
                            INSERT INTO category_closure (ancestor_id, descendant_id, depth, created_at, updated_at)
                            VALUES (current_ancestor, category_id, current_depth, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE updated_at = NOW();
                        END IF;
                    END LOOP;
                    CLOSE ancestor_cursor;
                END
            ');

            // Create trigger to maintain closure table when adjacency list changes
            DB::unprepared('
                CREATE TRIGGER maintain_closure_on_category_insert
                AFTER INSERT ON categories
                FOR EACH ROW
                BEGIN
                    CALL rebuild_closure_for_category(NEW.id);
                END
            ');

            DB::unprepared('
                CREATE TRIGGER maintain_closure_on_category_update
                AFTER UPDATE ON categories
                FOR EACH ROW
                BEGIN
                    IF OLD.parent_id != NEW.parent_id OR
                       (OLD.parent_id IS NULL AND NEW.parent_id IS NOT NULL) OR
                       (OLD.parent_id IS NOT NULL AND NEW.parent_id IS NULL) THEN
                        CALL rebuild_closure_for_category(NEW.id);

                        -- Also rebuild for all descendants
                        UPDATE categories
                        SET updated_at = NOW()
                        WHERE path LIKE CONCAT(NEW.path, "/%");
                    END IF;
                END
            ');

            // Create trigger to update materialized path and depth
            DB::unprepared('
                CREATE TRIGGER update_adjacency_fields
                BEFORE UPDATE ON categories
                FOR EACH ROW
                BEGIN
                    IF OLD.parent_id != NEW.parent_id OR
                       (OLD.parent_id IS NULL AND NEW.parent_id IS NOT NULL) OR
                       (OLD.parent_id IS NOT NULL AND NEW.parent_id IS NULL) THEN

                        IF NEW.parent_id IS NULL THEN
                            SET NEW.depth = 0;
                            SET NEW.path = CONCAT("/", NEW.id);
                        ELSE
                            SELECT depth + 1, CONCAT(path, "/", NEW.id)
                            INTO NEW.depth, NEW.path
                            FROM categories
                            WHERE id = NEW.parent_id;
                        END IF;
                    END IF;
                END
            ');
        }
    }

    public function down(): void
    {
        // Skip for SQLite as no triggers/procedures were created
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // MySQL-specific cleanup
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS maintain_closure_on_category_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS maintain_closure_on_category_update');
            DB::unprepared('DROP TRIGGER IF EXISTS update_adjacency_fields');
            DB::unprepared('DROP PROCEDURE IF EXISTS rebuild_closure_for_category');
        }
    }
};
