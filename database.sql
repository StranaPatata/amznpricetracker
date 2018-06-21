-- --------------------------------------------------------
-- Host:                         hyperspeed.it
-- Versione server:              5.5.59-0+deb8u1 - (Debian)
-- S.O. server:                  debian-linux-gnu
-- HeidiSQL Versione:            9.5.0.5196
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


-- Dump della struttura del database amazonpricetracker
CREATE DATABASE IF NOT EXISTS `amazonpricetracker` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `amazonpricetracker`;

-- Dump della struttura di tabella amazonpricetracker.admin_users
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` varchar(50) NOT NULL DEFAULT '0',
  `username` varchar(50) NOT NULL,
  `level` int(11) NOT NULL DEFAULT '3',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.banned_users
CREATE TABLE IF NOT EXISTS `banned_users` (
  `id` varchar(50) NOT NULL,
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.blacklist
CREATE TABLE IF NOT EXISTS `blacklist` (
  `ASIN` varchar(50) NOT NULL DEFAULT '',
  `comment` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`ASIN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.feedback
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userId` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `feedback` mediumtext,
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.last_keyword_index
CREATE TABLE IF NOT EXISTS `last_keyword_index` (
  `ServerId` int(11) NOT NULL AUTO_INCREMENT,
  `ServerName` varchar(50) NOT NULL,
  `lki` int(11) DEFAULT NULL,
  PRIMARY KEY (`ServerId`),
  UNIQUE KEY `ServerName` (`ServerName`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.logs
CREATE TABLE IF NOT EXISTS `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(1000) DEFAULT '0',
  `urlShort` varchar(1000) DEFAULT '0',
  `asin` varchar(50) DEFAULT '0',
  `newPriceNew` int(11) DEFAULT '0',
  `oldPriceNew` int(11) DEFAULT '0',
  `newPriceWhd` int(11) DEFAULT '0',
  `oldPriceWhd` int(11) DEFAULT '0',
  `discountNew` int(11) DEFAULT NULL,
  `discountWHD` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'discount',
  `type2` varchar(50) DEFAULT NULL,
  `server` varchar(50) DEFAULT NULL,
  `title` varchar(1000) DEFAULT NULL,
  `imgUrl` varchar(1000) DEFAULT NULL,
  `partNumber` varchar(1000) DEFAULT NULL,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59172 DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.parser_log_test
CREATE TABLE IF NOT EXISTS `parser_log_test` (
  `link` varchar(1000) DEFAULT NULL,
  `condition` varchar(1000) DEFAULT NULL,
  `condition1` varchar(1000) DEFAULT NULL,
  `html` longtext,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.products
CREATE TABLE IF NOT EXISTS `products` (
  `ASIN` varchar(50) NOT NULL,
  `URL` longtext NOT NULL,
  `Title` varchar(500) NOT NULL,
  `Price` int(11) DEFAULT NULL,
  `UsedPrice` int(11) DEFAULT NULL,
  `PartNumber` varchar(500) NOT NULL,
  `addedOn` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ASIN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.product_tracking
CREATE TABLE IF NOT EXISTS `product_tracking` (
  `ASIN` varchar(50) NOT NULL DEFAULT '',
  `id` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(1000) NOT NULL DEFAULT 'no titolo',
  PRIMARY KEY (`ASIN`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.search_keywords
CREATE TABLE IF NOT EXISTS `search_keywords` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `string` varchar(50) NOT NULL DEFAULT '0',
  `sort` varchar(50) DEFAULT 'relevancerank',
  PRIMARY KEY (`id`),
  UNIQUE KEY `string_sort` (`string`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=406 DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.search_logs
CREATE TABLE IF NOT EXISTS `search_logs` (
  `keyword` varchar(50) DEFAULT NULL,
  `response` varchar(50) DEFAULT NULL,
  `time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.settings
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting` varchar(50) DEFAULT NULL,
  `value` mediumtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting` (`setting`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

INSERT INTO `settings` (`id`, `setting`, `value`) VALUES
	(1, 'pagesToScan', '4'),
	(2, 'activeServers', '4'),
	(3, 'associateId', 'noref'),
	(4, 'mode', 'normal'),
	(5, 'site', 'hyperspeed.it'),
	(6, 'mainServer', 'Main'),
	(7, 'category', 'PCHardware'),
	(8, 'lastCommand', 'B079FTHL3L new 18405'),
	(9, 'lastDelayedOfferSent', '0'),
	(10, 'onlyPremium', '1');

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.users_and_groups
CREATE TABLE IF NOT EXISTS `users_and_groups` (
  `id` varchar(50) NOT NULL,
  `premium` int(1) DEFAULT '0',
  `discountPercentage` int(2) DEFAULT '30',
  `newProductNotification` int(1) DEFAULT '0',
  `whdNotification` int(1) DEFAULT '1',
  `name` varchar(50) DEFAULT NULL,
  `adminName` varchar(50) DEFAULT NULL,
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `premiumStartingDate` timestamp NULL DEFAULT NULL,
  `premium_permanent` int(1) DEFAULT '0',
  `top20` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella amazonpricetracker.user_status
CREATE TABLE IF NOT EXISTS `user_status` (
  `id` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- L’esportazione dei dati non era selezionata.
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
