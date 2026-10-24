-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: polr
-- ------------------------------------------------------
-- Server version	5.7.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `polr`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `polr` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;

USE `polr`;

--
-- Table structure for table `clicks`
--

DROP TABLE IF EXISTS `clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clicks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `referer` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `referer_host` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8_unicode_ci,
  `link_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `clicks_ip_index` (`ip`),
  KEY `clicks_referer_host_index` (`referer_host`),
  KEY `clicks_link_id_index` (`link_id`),
  CONSTRAINT `clicks_link_id_foreign` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clicks`
--

LOCK TABLES `clicks` WRITE;
/*!40000 ALTER TABLE `clicks` DISABLE KEYS */;
INSERT INTO `clicks` VALUES (1,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',6,'2020-02-02 15:00:00','2020-02-02 15:00:00'),(2,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',6,'2020-02-02 22:00:00','2020-02-02 22:00:00'),(3,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',6,'2020-02-03 05:00:00','2020-02-03 05:00:00'),(4,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',6,'2020-02-03 12:00:00','2020-02-03 12:00:00'),(5,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',6,'2020-02-03 19:00:00','2020-02-03 19:00:00'),(6,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',6,'2020-02-04 02:00:00','2020-02-04 02:00:00'),(7,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',6,'2020-02-04 09:00:00','2020-02-04 09:00:00'),(8,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',6,'2020-02-04 16:00:00','2020-02-04 16:00:00'),(9,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',6,'2020-02-04 23:00:00','2020-02-04 23:00:00'),(10,'10.1.2.3',NULL,'https://intranet.local/','intranet.local','polr-baseline/1.0',6,'2020-02-05 06:00:00','2020-02-05 06:00:00'),(11,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',2,'2020-02-05 13:00:00','2020-02-05 13:00:00'),(12,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',2,'2020-02-05 20:00:00','2020-02-05 20:00:00'),(13,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',2,'2020-02-06 03:00:00','2020-02-06 03:00:00'),(14,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',2,'2020-02-06 10:00:00','2020-02-06 10:00:00'),(15,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',2,'2020-02-06 17:00:00','2020-02-06 17:00:00'),(16,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',2,'2020-02-07 00:00:00','2020-02-07 00:00:00'),(17,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',2,'2020-02-07 07:00:00','2020-02-07 07:00:00'),(18,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',2,'2020-02-07 14:00:00','2020-02-07 14:00:00'),(19,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',2,'2020-02-07 21:00:00','2020-02-07 21:00:00'),(20,'10.1.2.3',NULL,'https://intranet.local/','intranet.local','polr-baseline/1.0',2,'2020-02-08 04:00:00','2020-02-08 04:00:00'),(21,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',15,'2020-02-08 11:00:00','2020-02-08 11:00:00'),(22,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',15,'2020-02-08 18:00:00','2020-02-08 18:00:00'),(23,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',15,'2020-02-09 01:00:00','2020-02-09 01:00:00'),(24,'81.2.69.142','GB','https://news.example.com/article','news.example.com','polr-baseline/1.0',15,'2020-02-09 08:00:00','2020-02-09 08:00:00'),(25,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',15,'2020-02-09 15:00:00','2020-02-09 15:00:00'),(26,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',15,'2020-02-09 22:00:00','2020-02-09 22:00:00'),(27,'216.160.83.56','US','https://www.google.com/search?q=x','www.google.com','polr-baseline/1.0',15,'2020-02-10 05:00:00','2020-02-10 05:00:00'),(28,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',15,'2020-02-10 12:00:00','2020-02-10 12:00:00'),(29,'89.160.20.112','SE',NULL,NULL,'polr-baseline/1.0',15,'2020-02-10 19:00:00','2020-02-10 19:00:00'),(30,'10.1.2.3',NULL,'https://intranet.local/','intranet.local','polr-baseline/1.0',15,'2020-02-11 02:00:00','2020-02-11 02:00:00');
/*!40000 ALTER TABLE `clicks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `links`
--

DROP TABLE IF EXISTS `links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `short_url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `long_url` longtext COLLATE utf8_unicode_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `creator` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT '0',
  `secret_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `is_disabled` tinyint(1) NOT NULL DEFAULT '0',
  `is_custom` tinyint(1) NOT NULL DEFAULT '0',
  `is_api` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `long_url_hash` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `links_short_url_unique` (`short_url`),
  KEY `links_long_url_index` (`long_url_hash`),
  KEY `api_quota_index` (`created_at`,`creator`,`is_api`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `links`
--

LOCK TABLES `links` WRITE;
/*!40000 ALTER TABLE `links` DISABLE KEYS */;
INSERT INTO `links` VALUES (1,'0','https://example.com/page/1','198.51.100.1','',0,'',0,0,0,'2020-02-01 12:01:00','2020-02-01 12:01:00','1620766630'),(2,'1','https://example.com/page/2','198.51.100.1','',10,'',0,0,0,'2020-02-01 12:02:00','2020-02-01 12:02:00','4187209244'),(3,'2','https://example.com/page/3','198.51.100.1','',0,'',0,0,0,'2020-02-01 12:03:00','2020-02-01 12:03:00','2392100490'),(4,'3','https://example.com/page/4','198.51.100.1','',0,'',0,0,0,'2020-02-01 12:04:00','2020-02-01 12:04:00','284171049'),(5,'4','https://example.com/page/5','198.51.100.1','',0,'',0,0,0,'2020-02-01 12:05:00','2020-02-01 12:05:00','1744251839'),(6,'custom-one','https://example.com/custom','198.51.100.1','',10,'',0,1,0,'2020-02-01 12:06:00','2020-02-01 12:06:00','545518980'),(7,'5','https://example.com/secret','198.51.100.1','',0,'5ec7',0,0,0,'2020-02-01 12:07:00','2020-02-01 12:07:00','2309295098'),(8,'disabled-link','https://example.com/to-disable','198.51.100.1','',0,'',1,1,0,'2020-02-01 12:08:00','2020-02-01 12:08:00','898531301'),(10,'6','https://example.net/admin-link','198.51.100.1','polradmin',0,'',0,0,0,'2020-02-01 12:10:00','2020-02-01 12:10:00','2476077058'),(11,'admin-custom','https://example.net/admin-custom-edited','198.51.100.1','polradmin',0,'',0,1,0,'2020-02-01 12:11:00','2020-02-01 12:11:00','2573573492'),(12,'7','https://example.org/alice-web','198.51.100.1','alice',0,'',0,0,0,'2020-02-01 12:12:00','2020-02-01 12:12:00','1173811867'),(13,'8','https://example.org/alice-api-1','198.51.100.1','alice',0,'',0,0,1,'2020-02-01 12:13:00','2020-02-01 12:13:00','1964631891'),(14,'9','https://example.org/alice-api-2','198.51.100.1','alice',0,'',0,0,1,'2020-02-01 12:14:00','2020-02-01 12:14:00','3960518377'),(15,'alice-custom','https://example.org/alice-api-custom','198.51.100.1','alice',10,'',0,1,1,'2020-02-01 12:15:00','2020-02-01 12:15:00','954617339'),(16,'a','https://example.org/alice-api-secret','198.51.100.1','alice',0,'a5ec',0,0,1,'2020-02-01 12:16:00','2020-02-01 12:16:00','2445325189'),(17,'b','https://example.org/bulk-1','198.51.100.1','alice',0,'',0,0,1,'2020-02-01 12:17:00','2020-02-01 12:17:00','2185161885'),(18,'bulk-custom','https://example.org/bulk-2','198.51.100.1','alice',0,'',0,1,1,'2020-02-01 12:18:00','2020-02-01 12:18:00','456637735'),(19,'c','https://example.com/anon-api','203.0.113.7','ANONIP-203.0.113.7',0,'',0,0,1,'2020-02-01 12:19:00','2020-02-01 12:19:00','2299470578');
/*!40000 ALTER TABLE `links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES ('2015_11_04_015813_create_link_table',1),('2015_11_04_015823_create_users_table',1),('2016_12_27_232934_create_clicks_table',1),('2017_02_04_025727_add_link_table_indexes',1),('2017_02_08_003907_alter_link_clicks_to_integer',1),('2017_12_21_095425_create_api_quota_index',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `ip` text COLLATE utf8_unicode_ci NOT NULL,
  `recovery_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `active` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `api_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `api_active` tinyint(1) NOT NULL DEFAULT '0',
  `api_quota` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '60',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'polradmin','$2y$10$T.dTaPNk2hy7BOXdrc1mFuaZxaEns2ut57.i6TOyG2SqOnI6Xdh9K','admin@polr.test','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000001','admin','1','0000000000000000000000000ad001',1,'60','2020-02-01 10:01:00','2020-02-01 10:01:00',NULL),(2,'alice','$2y$10$5AYusdXRPOBUD.Fh4IdNbOrfneOoJzkcgBLVATN3Zinaw5mZlUpM.','alice@example.com','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000002','','1','00000000000000000000000a11ce01',1,'60','2020-02-01 10:02:00','2020-02-01 10:02:00',NULL),(3,'bob','$2y$10$wAcoc0V0hG6hRvx6ojUhUuayJXgZ/at9FVw0zkZsBrEkx3MdxSC0O','bob@example.com','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000003','admin','1','0',0,'60','2020-02-01 10:03:00','2020-02-01 10:03:00',NULL),(4,'quota','$2y$10$s62pVl3ClaheY2hiEyTBdu3XWK691CWSkOwIk8fAfrErHbl/PiJoe','quota@example.com','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000004','','1','00000000000000000000000000ff01',1,'1','2020-02-01 10:04:00','2020-02-01 10:04:00',NULL),(5,'dave','$2y$10$mtMtn2xvMDo.72DoFThoueiTRL7kmdz07pSLWLF8WVzGweOaULX3G','dave@example.com','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000005','','0','0',0,'60','2020-02-01 10:05:00','2020-02-01 10:05:00',NULL),(6,'carol','$2y$10$O7wCbh09sacQDSj/jVnEn.ZYl83jVFLuYwA9Bm1bHBaIgNH2eiDZ6','carol@example.org','198.51.100.1','recovery00000000000000000000000000000000000000000000000000000000000000000000000000000000000000000006','','1',NULL,0,'60','2020-02-01 10:06:00','2020-02-01 10:06:00',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
