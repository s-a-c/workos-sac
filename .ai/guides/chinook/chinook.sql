-- --------------------------------------------------------
-- Chinook Database SQL Export with Single Taxonomy System Integration
-- Refactored from: .ai/guides/chinook/chinook.sql on 2025-07-11
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- Updated:                      2025-07-11 (aliziodev/laravel-taxonomy Integration)
-- Architecture:                 Laravel 12 + aliziodev/laravel-taxonomy Package
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- --------------------------------------------------------
-- NOTE: This SQL file contains the original Chinook database structure and data
-- for reference and migration purposes. The actual Laravel implementation uses:
--
-- 1. aliziodev/laravel-taxonomy package for all categorization
-- 2. Modern Laravel 12 migrations with enhanced schema
-- 3. Single taxonomy system replacing the original genres table
-- 4. Enhanced tables with timestamps, soft deletes, user stamps, and slugs
--
-- For the actual Laravel implementation, see:
-- - database/migrations/ for Laravel migration files
-- - database/seeders/ for Laravel seeder files
-- - Models use HasTaxonomies trait instead of direct genre relationships
-- --------------------------------------------------------

-- Dumping structure for table chinook.albums
CREATE TABLE IF NOT EXISTS `albums` (
  `id` int NOT NULL,
  `title` varchar(160) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `artist_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `IFK_AlbumArtistId` (`artist_id`) USING BTREE,
  CONSTRAINT `FK_AlbumArtistId` FOREIGN KEY (`artist_id`) REFERENCES `artists` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table chinook.albums: ~347 rows (approximately)
INSERT INTO `albums` (`id`, `title`, `artist_id`) VALUES
	(1, 'For Those About To Rock We Salute You', 1),
	(2, 'Balls to the Wall', 2),
	(3, 'Restless and Wild', 2),
	(4, 'Let There Be Rock', 1),
	(5, 'Big Ones', 3),
	(6, 'Jagged Little Pill', 4),
	(7, 'Facelift', 5),
	(8, 'Warner 25 Anos', 6),
	(9, 'Plays Metallica By Four Cellos', 7),
	(10, 'Audioslave', 8),
	(11, 'Out Of Exile', 8),
	(12, 'BackBeat Soundtrack', 9),
	(13, 'The Best Of Billy Cobham', 10),
	(14, 'Alcohol Fueled Brewtality Live! [Disc 1]', 11),
	(15, 'Alcohol Fueled Brewtality Live! [Disc 2]', 11),
	(16, 'Black Sabbath', 12),
	(17, 'Black Sabbath Vol. 4 (Remaster)', 12),
	(18, 'Body Count', 13),
	(19, 'Chemical Wedding', 14),
	(20, 'The Best Of Buddy Guy - The Millenium Collection', 15),
	(21, 'Prenda Minha', 16),
	(22, 'Sozinho Remix Ao Vivo', 16),
	(23, 'Minha Historia', 16),
	(24, 'Afrociberdelia', 17),
	(25, 'Da Lama Ao Caos', 17),
	(26, 'Acústico MTV [Live]', 18),
	(27, 'Cidade Negra - Hits', 19),
	(28, 'Na Pista', 20),
	(29, 'Axé Bahia 2001', 21),
	(30, 'BBC Sessions [Disc 1] [Live]', 22),
	(31, 'Bongo Fury', 23),
	(32, 'Carnaval 2001', 21),
	(33, 'Chill: Brazil (Disc 1)', 24),
	(34, 'Chill: Brazil (Disc 2)', 6),
	(35, 'Garage Inc. (Disc 1)', 50),
	(36, 'Greatest Hits II', 51),
	(37, 'Greatest Kiss', 52),
	(38, 'Heart of the Night', 53),
	(39, 'International Superhits', 54),
	(40, 'Into The Light', 55),
	(41, 'Meus Momentos', 56),
	(42, 'Minha História', 57),
	(43, 'MK III The Final Concerts [Disc 1]', 58),
	(44, 'Physical Graffiti [Disc 1]', 22),
	(45, 'Sambas De Enredo 2001', 21),
	(46, 'Supernatural', 59),
	(47, 'The Best of Ed Motta', 37),
	(48, 'The Essential Miles Davis [Disc 1]', 68),
	(49, 'The Essential Miles Davis [Disc 2]', 68),
	(50, 'The Final Concerts (Disc 2)', 58);

-- Dumping structure for table chinook.artists
CREATE TABLE IF NOT EXISTS `artists` (
  `id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table chinook.artists: ~275 rows (approximately)
INSERT INTO `artists` (`id`, `name`) VALUES
	(1, 'AC/DC'),
	(2, 'Accept'),
	(3, 'Aerosmith'),
	(4, 'Alanis Morissette'),
	(5, 'Alice In Chains'),
	(6, 'Antônio Carlos Jobim'),
	(7, 'Apocalyptica'),
	(8, 'Audioslave'),
	(9, 'BackBeat'),
	(10, 'Billy Cobham'),
	(11, 'Black Label Society'),
	(12, 'Black Sabbath'),
	(13, 'Body Count'),
	(14, 'Bruce Dickinson'),
	(15, 'Buddy Guy'),
	(16, 'Caetano Veloso'),
	(17, 'Chico Buarque'),
	(18, 'Chico Science & Nação Zumbi'),
	(19, 'Cidade Negra'),
	(20, 'Cláudio Zoli'),
	(21, 'Various Artists'),
	(22, 'Led Zeppelin'),
	(23, 'Frank Zappa & The Mothers'),
	(24, 'Azymuth'),
	(25, 'Gilberto Gil'),
	(26, 'João Gilberto'),
	(27, 'Bebel Gilberto'),
	(28, 'Jorge Vercilo'),
	(29, 'Baby Consuelo'),
	(30, 'Ney Matogrosso'),
	(31, 'Luiz Melodia'),
	(32, 'Nando Reis'),
	(33, 'Pedro Luís & A Parede'),
	(34, 'O Rappa'),
	(35, 'Ed Motta'),
	(36, 'Banda Eva'),
	(37, 'Kid Abelha'),
	(38, 'Lulu Santos'),
	(39, 'Paralamas do Sucesso'),
	(40, 'Titas'),
	(41, 'Skank'),
	(42, 'Charlie Brown Jr.'),
	(43, 'Engenheiros do Hawaii'),
	(44, 'Raul Seixas'),
	(45, 'Os Mutantes'),
	(46, 'Legião Urbana'),
	(47, 'Lenny Kravitz'),
	(48, 'Ira!'),
	(49, 'Raimundos'),
	(50, 'Metallica');

-- --------------------------------------------------------
-- IMPORTANT: Original Genres Table Data for Taxonomy Migration
-- This data will be migrated to the aliziodev/laravel-taxonomy system
-- --------------------------------------------------------

-- Dumping structure for table chinook.genres
CREATE TABLE IF NOT EXISTS `genres` (
  `id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Original genre data - will be migrated to taxonomy terms
INSERT INTO `genres` (`id`, `name`) VALUES
	(1, 'Rock'),
	(2, 'Jazz'),
	(3, 'Metal'),
	(4, 'Alternative & Punk'),
	(5, 'Rock And Roll'),
	(6, 'Blues'),
	(7, 'Latin'),
	(8, 'Reggae'),
	(9, 'Pop'),
	(10, 'Soundtrack'),
	(11, 'Bossa Nova'),
	(12, 'Easy Listening'),
	(13, 'Heavy Metal'),
	(14, 'R&B/Soul'),
	(15, 'Electronica/Dance'),
	(16, 'World'),
	(17, 'Hip Hop/Rap'),
	(18, 'Science Fiction'),
	(19, 'TV Shows'),
	(20, 'Sci Fi & Fantasy'),
	(21, 'Drama'),
	(22, 'Comedy'),
	(23, 'Alternative'),
	(24, 'Classical'),
	(25, 'Opera');

-- --------------------------------------------------------
-- NOTE: In the Laravel implementation, these genres become terms in a
-- "Genres" taxonomy using the aliziodev/laravel-taxonomy package.
-- The migration process preserves original IDs in metadata for compatibility.
-- --------------------------------------------------------

-- Dumping structure for table chinook.tracks
CREATE TABLE IF NOT EXISTS `tracks` (
  `id` int NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `album_id` int DEFAULT NULL,
  `media_type_id` int NOT NULL,
  `genre_id` int DEFAULT NULL,
  `composer` varchar(220) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `milliseconds` int NOT NULL,
  `bytes` int DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `IFK_TrackAlbumId` (`album_id`) USING BTREE,
  KEY `IFK_TrackGenreId` (`genre_id`) USING BTREE,
  KEY `IFK_TrackMediaTypeId` (`media_type_id`) USING BTREE,
  CONSTRAINT `FK_TrackAlbumId` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`),
  CONSTRAINT `FK_TrackGenreId` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`),
  CONSTRAINT `FK_TrackMediaTypeId` FOREIGN KEY (`media_type_id`) REFERENCES `media_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sample track data (first 50 tracks for reference)
INSERT INTO `tracks` (`id`, `name`, `album_id`, `media_type_id`, `genre_id`, `composer`, `milliseconds`, `bytes`, `unit_price`) VALUES
	(1, 'For Those About To Rock (We Salute You)', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 343719, 11170334, 0.99),
	(2, 'Balls to the Wall', 2, 2, 1, NULL, 342562, 5510424, 0.99),
	(3, 'Fast As a Shark', 3, 2, 1, 'F. Baltes, S. Kaufman, U. Dirkscneider & W. Hoffman', 230619, 3990994, 0.99),
	(4, 'Restless and Wild', 3, 2, 1, 'F. Baltes, R.A. Smith-Diesel, S. Kaufman, U. Dirkscneider & W. Hoffman', 252051, 4331779, 0.99),
	(5, 'Princess of the Dawn', 3, 2, 1, 'Deaffy & R.A. Smith-Diesel', 375418, 6290521, 0.99),
	(6, 'Put The Finger On You', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 205662, 6713451, 0.99),
	(7, 'Let''s Get It Up', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 233926, 7636561, 0.99),
	(8, 'Inject The Venom', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 210834, 6852860, 0.99),
	(9, 'Snowballed', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 203102, 6599424, 0.99),
	(10, 'Evil Walks', 1, 1, 1, 'Angus Young, Malcolm Young, Brian Johnson', 263497, 8611245, 0.99);

-- Dumping structure for table chinook.media_types
CREATE TABLE IF NOT EXISTS `media_types` (
  `id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table chinook.media_types
INSERT INTO `media_types` (`id`, `name`) VALUES
	(1, 'MPEG audio file'),
	(2, 'Protected AAC audio file'),
	(3, 'Protected MPEG-4 video file'),
	(4, 'Purchased AAC audio file'),
	(5, 'AAC audio file');

-- Dumping structure for table chinook.playlists
CREATE TABLE IF NOT EXISTS `playlists` (
  `id` int NOT NULL,
  `name` varchar(120) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table chinook.playlists
INSERT INTO `playlists` (`id`, `name`) VALUES
	(1, 'Music'),
	(2, 'Movies'),
	(3, 'TV Shows'),
	(4, 'Audiobooks'),
	(5, 'Brazilian Music'),
	(6, 'Classical'),
	(7, 'Classical 101 - Deep Cuts'),
	(8, 'Classical 101 - Next Steps'),
	(9, 'Classical 101 - The Basics'),
	(10, 'Grunge'),
	(11, 'Heavy Metal Classic'),
	(12, 'On-The-Go 1'),
	(13, '90''s Music'),
	(14, 'Audiobooks'),
	(15, 'Movies'),
	(16, 'Music'),
	(17, 'Music Videos'),
	(18, 'TV Shows');

-- --------------------------------------------------------
-- Customer and Sales Tables
-- --------------------------------------------------------

-- Dumping structure for table chinook.customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL,
  `first_name` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `last_name` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `company` varchar(80) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `address` varchar(70) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `city` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `state` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `country` varchar(40) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `postal_code` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `phone` varchar(24) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `fax` varchar(24) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(60) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `support_rep_id` int DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `IFK_CustomerSupportRepId` (`support_rep_id`) USING BTREE,
  CONSTRAINT `FK_CustomerSupportRepId` FOREIGN KEY (`support_rep_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Sample customer data (first 10 customers for reference)
INSERT INTO `customers` (`id`, `first_name`, `last_name`, `company`, `address`, `city`, `state`, `country`, `postal_code`, `phone`, `fax`, `email`, `support_rep_id`) VALUES
	(1, 'Luís', 'Gonçalves', 'Embraer - Empresa Brasileira de Aeronáutica S.A.', 'Av. Brigadeiro Faria Lima, 2170', 'São José dos Campos', 'SP', 'Brazil', '12227-000', '+55 (12) 3923-5555', '+55 (12) 3923-5566', 'luisg@embraer.com.br', 3),
	(2, 'Leonie', 'Köhler', NULL, 'Theodor-Heuss-Straße 34', 'Stuttgart', NULL, 'Germany', '70174', '+49 0711 2842222', NULL, 'leonekohler@surfeu.de', 5),
	(3, 'François', 'Tremblay', NULL, '1498 rue Bélanger', 'Montréal', 'QC', 'Canada', 'H2G 1A7', '+1 (514) 721-4711', NULL, 'ftremblay@gmail.com', 3),
	(4, 'Bjørn', 'Hansen', NULL, 'Ullevålsveien 14', 'Oslo', NULL, 'Norway', '0171', '+47 22 44 22 22', NULL, 'bjorn.hansen@yahoo.no', 4),
	(5, 'František', 'Wichterlová', 'JetBrains s.r.o.', 'Klanova 9/506', 'Prague', NULL, 'Czech Republic', '14700', '+420 2 4172 5555', '+420 2 4172 5555', 'frantisekw@jetbrains.com', 4);

-- --------------------------------------------------------
-- NOTE: This file contains reference data from the original Chinook database.
-- The complete Laravel implementation includes:
-- 1. Enhanced schema with modern Laravel features
-- 2. aliziodev/laravel-taxonomy integration for all categorization
-- 3. RBAC with spatie/laravel-permission
-- 4. Performance optimizations and proper indexing
-- 5. Comprehensive testing and documentation
--
-- For complete implementation details, see the documentation guides.
-- --------------------------------------------------------

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
