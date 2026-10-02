-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: buildnexus_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `bids`
--

DROP TABLE IF EXISTS `bids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bid_no` varchar(50) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `project_name` varchar(100) NOT NULL,
  `subcontractor` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bids`
--

LOCK TABLES `bids` WRITE;
/*!40000 ALTER TABLE `bids` DISABLE KEYS */;
INSERT INTO `bids` VALUES (1,'BID-2024-001',NULL,'Luxury Villa in Kandy','Lanka Construction',5000000.00,'Submitted','2026-09-25 10:59:29'),(2,'BID-2024-002',NULL,'Colombo Office Complex','Metro Builders',12500000.00,'Submitted','2026-09-25 10:59:29'),(3,'BID-2024-003',NULL,'Galle Boutique Hotel','Coastal Contractors',8200000.00,'Submitted','2026-09-25 10:59:29'),(4,'BID-2024-004',NULL,'Luxury Villa in Kandy','Kandy Homes',4800000.00,'Submitted','2026-09-25 10:59:29'),(5,'BID-2024-005',NULL,'Highway Expansion E01','Mega Engineering',250000000.00,'Submitted','2026-09-25 10:59:29');
/*!40000 ALTER TABLE `bids` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `bill_date` date DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `category` varchar(50) DEFAULT 'Materials',
  `status` enum('Paid','Unpaid','Overdue') DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills`
--

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
INSERT INTO `bills` VALUES (1,1,'BuildEasy Hardware','BILL-101','2025-12-26',450000.00,'Materials','Paid','2025-12-26 18:01:22'),(2,1,'Nippon Paint','BILL-102','2025-12-26',150000.00,'Materials','Paid','2025-12-26 18:01:22'),(3,2,'Global Electric','BILL-201','2025-12-26',5000000.00,'Materials','Paid','2025-12-26 18:01:22'),(4,4,'Nippon Paint Lanka','BILL-0045','2026-10-01',120000.00,'Materials','Paid','2026-10-01 17:08:03'),(5,4,'Kandy Masonry & Finishing Works','BILL-5501','2026-10-01',11108750.00,'Subcontractors','Paid','2026-10-01 17:08:03'),(6,1,'Structural Steel Overrun','BILL-OVER-01','2026-10-01',348200.00,'Materials','Paid','2026-10-01 17:45:18'),(7,2,'MEP Variation Subcontract','BILL-OVER-02','2026-10-01',3800000.00,'Subcontractors','Paid','2026-10-01 17:45:18');
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `allocated_amount` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
INSERT INTO `budgets` VALUES (1,1,862000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(2,2,8000000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(3,3,12000000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(4,4,25145000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(5,25,1500000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(6,26,1500000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(7,29,45000000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(8,30,60000000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(9,31,120000000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22'),(10,34,250000.00,'Initial ERP Project Budget Allocation','2026-10-01 17:17:22');
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_logs`
--

DROP TABLE IF EXISTS `campaign_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `recipient_email` varchar(150) NOT NULL,
  `recipient_name` varchar(150) DEFAULT NULL,
  `status` enum('Sent','Bounced','Opened','Clicked') DEFAULT 'Sent',
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `recipient_email` (`recipient_email`),
  CONSTRAINT `fk_camplog_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_logs`
--

LOCK TABLES `campaign_logs` WRITE;
/*!40000 ALTER TABLE `campaign_logs` DISABLE KEYS */;
INSERT INTO `campaign_logs` VALUES (1,1,'client1@example.com','Sampath Perera','Clicked','2024-10-15 09:00:00','2024-10-15 09:00:00'),(2,1,'client2@example.com','Nimal Silva','Opened','2024-10-15 09:00:00',NULL),(3,1,'client3@example.com','Sunil Fernando','Sent',NULL,NULL),(4,2,'client1@example.com','Sampath Perera','Clicked','2024-10-01 10:30:00','2024-10-01 10:30:00'),(5,2,'client2@example.com','Nimal Silva','Opened','2024-10-01 10:30:00',NULL),(6,2,'client3@example.com','Sunil Fernando','Sent',NULL,NULL),(7,4,'client1@example.com','Sampath Perera','Clicked','2024-09-20 11:15:00','2024-09-20 11:15:00'),(8,4,'client2@example.com','Nimal Silva','Opened','2024-09-20 11:15:00',NULL),(9,4,'client3@example.com','Sunil Fernando','Sent',NULL,NULL),(11,8,'john.doe@buildnexus.com','John Doe','Sent',NULL,NULL),(12,8,'sunil.p@fieldcrew.com','Sunil Perera','Sent',NULL,NULL),(13,8,'sales@lankatiles.com','Lanka Tiles','Sent',NULL,NULL),(14,8,'client.silva@email.com','Mr. Silva','Sent',NULL,NULL),(15,8,'k.weera@architects.lk','K. Weerasinghe','Sent',NULL,NULL),(16,8,'contact@metrobuilders.lk','Metro Builders','Sent',NULL,NULL),(17,8,'e198130@esoft.academy','Bemasha Prashangi','Sent',NULL,NULL),(18,8,'pm@buildnexus.com','Bemasha Prashangi','Sent',NULL,NULL),(19,8,'user@demo.com','Bemasha Prashangi','Sent',NULL,NULL),(20,8,'bema.s@email.com','Bemasha Prashangi','Sent',NULL,NULL),(21,8,'pts@gmail.com','Praveen Trevel ','Sent',NULL,NULL),(22,8,'ajith@gmail.com','Ajith','Sent',NULL,NULL),(23,8,'bimal@gmail.com','Bimal','Sent',NULL,NULL),(24,8,'saman.j@gmail.com','Saman Jayawardena','Sent',NULL,NULL),(25,8,'procure@cascade.lk','Cascade Leisure Resorts','Sent',NULL,NULL),(26,8,'naveen.s@horizon.lk','Naveen Senaratne','Sent',NULL,NULL),(27,8,'client@buildnexus.com','Mrs. Silva','Sent',NULL,NULL),(28,8,'test@buildnexus.com','Bema','Sent',NULL,NULL),(29,8,'john.doe@email.com','John Doe','Sent',NULL,NULL);
/*!40000 ALTER TABLE `campaign_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `recipient_group` varchar(50) NOT NULL,
  `schedule_date` date DEFAULT NULL,
  `status` enum('Draft','Scheduled','Sent') DEFAULT 'Draft',
  `content` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
INSERT INTO `campaigns` VALUES (1,'Year end project ','SEE','clients','2025-12-30','','xxcxxxcccccc',1,'2025-12-26 21:10:18'),(2,'Year end project ','SEE','clients','2025-12-30','Scheduled','xxcxxxcccccc',1,'2025-12-26 21:15:52'),(3,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 21:45:31'),(4,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 21:45:38'),(5,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 21:47:18'),(6,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 21:51:24'),(7,'Newseller ','SEE','leads','2025-12-27','Scheduled','saadsds',1,'2025-12-26 21:57:49'),(8,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 21:58:02'),(9,'Newseller ','SEE','leads','2025-12-27','Draft','saadsds',1,'2025-12-26 21:58:06'),(10,'fdfd','gdd','leads','2025-12-27','Scheduled','gdvvcv',1,'2025-12-26 22:02:38'),(11,'Copy of Year end project ','SEE','clients',NULL,'Draft','xxcxxxcccccc',1,'2025-12-26 22:02:43');
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `change_orders`
--

DROP TABLE IF EXISTS `change_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `change_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `co_number` varchar(50) NOT NULL,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cost_impact` decimal(15,2) NOT NULL,
  `schedule_impact_days` int(11) DEFAULT 0,
  `time_impact_days` int(11) DEFAULT 0,
  `status` enum('Draft','Pending','Approved','Declined','Rejected') NOT NULL DEFAULT 'Pending',
  `client_feedback` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `co_number` (`co_number`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `change_orders_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `change_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `change_orders`
--

LOCK TABLES `change_orders` WRITE;
/*!40000 ALTER TABLE `change_orders` DISABLE KEYS */;
INSERT INTO `change_orders` VALUES (1,'CO-1',26,6,'Add Under-cabinet LED Lighting','Install 15ft of dimmable LED strip lighting below wall cabinets.',45000.00,0,0,'Pending',NULL,NULL,NULL,'2026-01-04 05:28:37',NULL,NULL),(2,'CO-2',2,NULL,'sinck ','sadsddsffgf',50000.00,0,0,'Pending',NULL,NULL,1,'2026-01-04 06:12:43',NULL,NULL),(3,'CO-2024-001',4,4,'CO-2024-001','Upgrade master bathroom tiles to premium Italian marble.',150000.00,5,0,'Pending',NULL,NULL,NULL,'2026-01-04 13:00:34',NULL,NULL),(4,'CO-2024-002',4,4,'CO-2024-002','Additional outdoor lighting for the garden and pool area.',80000.00,2,0,'Approved',NULL,NULL,NULL,'2026-01-04 13:00:34','2026-01-04 18:30:34',NULL),(5,'CO-5',26,6,'CO-2026-001','Under-cabinet LED strip lighting installation.',45000.00,0,0,'Pending',NULL,NULL,NULL,'2026-01-04 13:00:34',NULL,NULL),(6,'CO-6',1,5,'Light update','cdffdcd',10000.00,0,0,'Pending',NULL,NULL,1,'2026-01-04 13:15:18',NULL,NULL),(7,'CO-7',3,NULL,'sofa','ssddf',200000.00,0,0,'Pending',NULL,NULL,1,'2026-01-04 17:26:21',NULL,NULL),(9,'CO-0012',4,4,'Additional Pool Deck Teak Slatting','Upgrade poolside decking to weather-treated teak',75000.00,3,3,'Approved',NULL,NULL,NULL,'2026-10-01 17:08:03','2026-01-04 18:30:34',NULL);
/*!40000 ALTER TABLE `change_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company` varchar(255) DEFAULT 'N/A',
  `project_id` int(11) DEFAULT NULL,
  `status` enum('Active','On Hold','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (4,'Mrs. Silva','client@buildnexus.com','N/A',4,'Active','2025-12-27 05:55:04'),(5,'Bema','test@buildnexus.com','N/A',1,'Active','2025-12-27 05:55:04'),(6,'John Doe','john.doe@email.com','N/A',26,'Active','2026-01-04 05:27:39');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_project_assignments`
--

DROP TABLE IF EXISTS `contact_project_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_project_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assignment_role` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `contact_project_assignments_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_project_assignments_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_project_assignments`
--

LOCK TABLES `contact_project_assignments` WRITE;
/*!40000 ALTER TABLE `contact_project_assignments` DISABLE KEYS */;
INSERT INTO `contact_project_assignments` VALUES (1,1,1,'Project Manager'),(2,1,29,'Project Manager'),(3,2,4,'Foreman'),(4,3,30,'Vendor'),(5,4,4,'Client'),(6,5,29,'Architect'),(7,6,29,'Subcontractor');
/*!40000 ALTER TABLE `contact_project_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `role_type` enum('Project Manager','Foreman','Vendor','Client','Architect','Subcontractor','Engineer','Inspector') NOT NULL,
  `linked_user_id` int(11) DEFAULT NULL,
  `default_project_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('Active','Archived') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `linked_user_id` (`linked_user_id`),
  KEY `default_project_id` (`default_project_id`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_ibfk_2` FOREIGN KEY (`default_project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'John Doe','john.doe@buildnexus.com','+94 77 123 4567','BuildNexus Corporate','Project Manager',2,1,'Level 12, World Trade Center, Colombo 01','Active','2026-10-01 15:51:54'),(2,'Sunil Perera','sunil.p@fieldcrew.com','+94 71 987 6543','Nexus Field Operations','Foreman',12,4,'Site Office, Peradeniya Road, Kandy','Active','2026-10-01 15:51:54'),(3,'Lanka Tiles','sales@lankatiles.com','+94 11 476 5600','Lanka Walltiles PLC','Vendor',NULL,30,'212 Nawala Road, Rajagiriya, Sri Lanka','Active','2026-10-01 15:51:54'),(4,'Mr. Silva','client.silva@email.com','+94 77 555 8899','Silva Holdings Ltd','Client',4,4,'45 Kandy Road, Katugastota','Active','2026-10-01 15:51:54'),(5,'K. Weerasinghe','k.weera@architects.lk','+94 11 258 9632','Weerasinghe & Associates Architects','Architect',NULL,29,'14 Alfred House Gardens, Colombo 03','Active','2026-10-01 15:51:54'),(6,'Metro Builders','contact@metrobuilders.lk','+94 11 741 2589','Metro Builders & Civil Contractors','Subcontractor',NULL,29,'88 Galle Road, Dehiwala','Active','2026-10-01 15:51:54'),(8,'John Doe','john.doe@email.com','+94 70 555 3456',NULL,'Client',10,26,NULL,'Active','2026-10-02 10:46:34');
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_report_photos`
--

DROP TABLE IF EXISTS `daily_report_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_report_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `photo_url` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_drp_report` (`report_id`),
  CONSTRAINT `fk_drp_report` FOREIGN KEY (`report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_report_photos`
--

LOCK TABLES `daily_report_photos` WRITE;
/*!40000 ALTER TABLE `daily_report_photos` DISABLE KEYS */;
INSERT INTO `daily_report_photos` VALUES (1,7,'https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=600&h=400&fit=crop','Site activity verification','2026-10-01 15:23:35'),(2,8,'https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=600&h=400&fit=crop','Site activity verification','2026-10-01 15:23:35'),(3,9,'https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=600&h=400&fit=crop','Site activity verification','2026-10-01 15:23:35'),(4,10,'https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=600&h=400&fit=crop','Site activity verification','2026-10-01 15:23:35'),(5,11,'uploads/daily_logs/report_1790878185_1fa5dc.jpg','Foreman site upload','2026-10-01 18:09:45');
/*!40000 ALTER TABLE `daily_report_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_reports`
--

DROP TABLE IF EXISTS `daily_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `daily_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `foreman_id` int(11) DEFAULT NULL,
  `report_date` date DEFAULT curdate(),
  `weather_condition` varchar(100) DEFAULT 'Sunny, 30°C',
  `work_summary` text DEFAULT NULL,
  `crew_count` int(11) DEFAULT 0,
  `subcontractor_notes` text DEFAULT NULL,
  `status` enum('Draft','Submitted','Reviewed') DEFAULT 'Submitted',
  `site_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dr_project` (`project_id`),
  KEY `fk_dr_foreman` (`foreman_id`),
  CONSTRAINT `daily_reports_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `daily_reports_ibfk_2` FOREIGN KEY (`foreman_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_dr_foreman` FOREIGN KEY (`foreman_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_reports`
--

LOCK TABLES `daily_reports` WRITE;
/*!40000 ALTER TABLE `daily_reports` DISABLE KEYS */;
INSERT INTO `daily_reports` VALUES (1,2,3,'2025-12-21','Sunny, 30°C','bla bla',0,NULL,'Submitted',NULL,'2025-12-21 09:52:38'),(2,1,3,'2025-12-21','Sunny, 30°C','mY BE ',0,NULL,'Submitted',NULL,'2025-12-21 10:50:17'),(3,26,3,'2026-01-11','Sunny, 30°C','Demolition complete. Disposed of old cabinetry. Awaiting plumbing inspection.',0,NULL,'Submitted',NULL,'2026-01-04 05:28:37'),(4,2,3,'2026-01-04','Sunny, 30°C','dfghjklasderftggghg',0,NULL,'Submitted','uploads/site_photos/report_1767535857.png','2026-01-04 14:10:57'),(5,3,3,'2026-01-04','Sunny, 30°C','rrrrrrrrrrrrrrrrrrrrrrrrrrrr',0,NULL,'Submitted','uploads/site_photos/report_1767536772.png','2026-01-04 14:26:12'),(6,5,3,'2026-01-04','Sunny, 30°C','demolition complete',0,NULL,'Submitted','uploads/site_photos/report_1767546855.png','2026-01-04 17:14:15'),(7,4,12,'2024-10-28','Sunny, 32°C','Foundation reinforcement and concrete pour inspection completed for Grid A-C. Curing blankets placed and site cleared.',14,'Structural subcontractor on-site with 8 steel fixers and 6 concrete masons.','Submitted',NULL,'2026-10-01 15:23:35'),(8,29,13,'2024-10-28','Partly Cloudy, 30°C','HVAC main ducting installed on 4th floor; structural drywall framing in progress for meeting suites.',22,'MEP engineering team completed pressure testing for riser ducts.','Submitted',NULL,'2026-10-01 15:23:35'),(9,30,14,'2024-10-27','Rainy, 28°C','Interior plastering and tile layout in progress in Lobby & Suite 101-105 despite exterior rain showers.',10,'Tiling subcontractor started bathroom waterproofing seal.','Submitted',NULL,'2026-10-01 15:23:35'),(10,4,12,'2024-10-27','Sunny, 31°C','Site excavation and perimeter footing trenching completed. Soil density testing report received and approved.',12,'Earthmoving backhoe operator on site 8.0 hrs.','Submitted',NULL,'2026-10-01 15:23:35'),(11,2,2,'2026-10-01','Sunny, 30°C','kjjjjjjjjjjjjdddd',0,'','Submitted','uploads/daily_logs/report_1790878185_1fa5dc.jpg','2026-10-01 18:09:45');
/*!40000 ALTER TABLE `daily_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `plate_number` varchar(50) DEFAULT NULL,
  `type` enum('Vehicle','Machinery','Small Tool') NOT NULL DEFAULT 'Machinery',
  `current_project_id` int(11) DEFAULT NULL,
  `status` enum('Available','In Use','Maintenance','Decommissioned') DEFAULT 'Available',
  `next_service_date` date DEFAULT NULL,
  `hourly_operating_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (1,'EQ-01','JCB Excavator','WP-1234','Machinery',NULL,'Available',NULL,0.00,'2026-10-01 15:57:27'),(2,'EQ-02','Tata Tipper','CP-5678','Vehicle',NULL,'Available',NULL,0.00,'2026-10-01 15:57:27'),(3,'EQ-001','Caterpillar Excavator 320','WP-EX-3201','Machinery',1,'In Use','2025-03-15',125.00,'2026-10-01 15:57:27'),(4,'VH-012','Toyota Hilux','CP-CAB-4590','Vehicle',4,'In Use','2025-01-10',45.00,'2026-10-01 15:57:27'),(5,'EQ-005','Concrete Mixer','SER-CM-8820','Machinery',NULL,'Available','2025-02-01',35.00,'2026-10-01 15:57:27'),(6,'EQ-003','JCB Backhoe Loader','WP-LB-9904','Machinery',29,'Maintenance','2024-11-05',95.00,'2026-10-01 15:57:27'),(7,'EQ-002','Komatsu D65 Bulldozer','WP-DZ-1102','Machinery',31,'In Use','2025-04-20',140.00,'2026-10-01 15:57:27'),(8,'VH-008','Isuzu Elf Tipper Truck','SP-TR-7721','Vehicle',30,'Available','2025-01-25',60.00,'2026-10-01 15:57:27');
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_logs`
--

DROP TABLE IF EXISTS `equipment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `foreman_id` int(11) DEFAULT NULL,
  `hours_used` decimal(6,2) NOT NULL DEFAULT 0.00,
  `fuel_liters` decimal(6,2) DEFAULT 0.00,
  `log_date` date DEFAULT curdate(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `project_id` (`project_id`),
  KEY `foreman_id` (`foreman_id`),
  CONSTRAINT `equipment_logs_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  CONSTRAINT `equipment_logs_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `equipment_logs_ibfk_3` FOREIGN KEY (`foreman_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_logs`
--

LOCK TABLES `equipment_logs` WRITE;
/*!40000 ALTER TABLE `equipment_logs` DISABLE KEYS */;
INSERT INTO `equipment_logs` VALUES (1,2,2,3,3.00,1.00,'2025-12-21','may be ','2026-10-01 15:57:27'),(2,3,31,1,7.50,65.00,'2024-10-28','Earthwork grading and slope cutting on Section B. Engine oil pressure normal.','2026-10-01 15:57:27'),(3,3,31,1,6.00,50.00,'2024-10-27','Trench excavation for culvert drains. Replaced bucket tooth pin.','2026-10-01 15:57:27'),(4,3,31,1,8.00,72.00,'2024-10-25','Clearing subgrade rock formations. Hydraulic lines inspected.','2026-10-01 15:57:27'),(5,4,4,1,3.50,25.00,'2024-10-28','Transporting structural tie rods and site supervisor transit between sites.','2026-10-01 15:57:27'),(6,4,4,1,4.00,30.00,'2024-10-26','Delivered electrical distribution boxes and cable conduits from local depot.','2026-10-01 15:57:27'),(7,5,1,1,5.00,15.00,'2024-10-20','Batching test pours for retaining wall footings. Drum washed and greased.','2026-10-01 15:57:27'),(8,6,29,1,2.00,18.00,'2024-10-22','Hydraulic boom hose weeping oil. Scheduled for full hydraulic pack rebuild.','2026-10-01 15:57:27');
/*!40000 ALTER TABLE `equipment_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimate_items`
--

DROP TABLE IF EXISTS `estimate_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estimate_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estimate_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 7.25,
  PRIMARY KEY (`id`),
  KEY `estimate_id` (`estimate_id`),
  CONSTRAINT `estimate_items_ibfk_1` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimate_items`
--

LOCK TABLES `estimate_items` WRITE;
/*!40000 ALTER TABLE `estimate_items` DISABLE KEYS */;
INSERT INTO `estimate_items` VALUES (43,16,'Removal and disposal of existing kitchen cabinets, countertops, sink, faucet, and backsplash.','Demolition',30000.00,7.25),(44,16,'Supply and installation of mid-grade shaker style kitchen base cabinets (includes hardware).','Cabinetry',12000.00,7.25),(45,16,'Supply and installation of mid-grade shaker style kitchen wall cabinets (includes hardware).','Cabinetry',10000.00,7.25),(46,16,'Supply and installation of 3cm mid-grade quartz countertops with standard edge profile and sink cutout.','Countertops',11000.00,7.25),(47,16,'Supply and installation of new stainless steel undermount kitchen sink (single bowl).','Plumbing',20000.00,7.25),(48,16,'Supply and installation of mid-grade ceramic or porcelain tile backsplash (including labor).','Tiling',1300.00,7.25),(49,16,'Prepare and paint kitchen walls and ceiling (two coats of mid-grade washable paint).','Finishing',150.00,7.25),(50,17,'Removal and disposal of existing kitchen cabinets, countertops, sink, faucet, and backsplash.','Demolition',30000.00,7.25),(51,17,'Supply and installation of mid-grade shaker style kitchen base cabinets (includes hardware).','Cabinetry',12000.00,7.25),(52,17,'Supply and installation of mid-grade shaker style kitchen wall cabinets (includes hardware).','Cabinetry',10000.00,7.25),(53,17,'Supply and installation of 3cm mid-grade quartz countertops with standard edge profile and sink cutout.','Countertops',11000.00,7.25),(54,17,'Supply and installation of new stainless steel undermount kitchen sink (single bowl).','Plumbing',20000.00,7.25),(55,17,'Supply and installation of mid-grade ceramic or porcelain tile backsplash (including labor).','Tiling',1300.00,7.25),(56,17,'Prepare and paint kitchen walls and ceiling (two coats of mid-grade washable paint).','Finishing',150.00,7.25),(57,18,'Removal and disposal of existing kitchen cabinets, countertops, sink, faucet, and backsplash.','Demolition',30000.00,7.25),(58,18,'Supply and installation of mid-grade shaker style kitchen base cabinets (includes hardware).','Cabinetry',12000.00,7.25),(59,18,'Supply and installation of mid-grade shaker style kitchen wall cabinets (includes hardware).','Cabinetry',10000.00,7.25),(60,18,'Supply and installation of 3cm mid-grade quartz countertops with standard edge profile and sink cutout.','Countertops',11000.00,7.25),(61,18,'Supply and installation of new stainless steel undermount kitchen sink (single bowl).','Plumbing',20000.00,7.25),(62,18,'Supply and installation of mid-grade ceramic or porcelain tile backsplash (including labor).','Tiling',1300.00,7.25),(63,18,'Prepare and paint kitchen walls and ceiling (two coats of mid-grade washable paint).','Finishing',150.00,7.25);
/*!40000 ALTER TABLE `estimate_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimate_selections`
--

DROP TABLE IF EXISTS `estimate_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estimate_selections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `img_url` varchar(255) DEFAULT NULL,
  `included` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimate_selections`
--

LOCK TABLES `estimate_selections` WRITE;
/*!40000 ALTER TABLE `estimate_selections` DISABLE KEYS */;
INSERT INTO `estimate_selections` VALUES (1,'Heritage Hex Porcelain',4560.00,40,'Approved','https://images.unsplash.com/photo-1620626011761-996317b8d101?w=400&h=300&fit=crop',1,'2026-09-25 11:43:57'),(2,'Mountain View Stone',1824.00,4,'Declined','https://images.unsplash.com/photo-1542385151-efd9000785a0?w=400&h=300&fit=crop',1,'2026-09-25 11:43:57'),(3,'STYLISH Single Handle Sink Mixer',310.80,1,'Approved','https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=400&h=300&fit=crop',1,'2026-09-25 11:43:57'),(4,'Oak Vanity Cabinet',1062.50,1,'Approved','https://images.unsplash.com/photo-1595515106969-1ce29566ff1c?w=400&h=300&fit=crop',1,'2026-09-25 11:43:57');
/*!40000 ALTER TABLE `estimate_selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estimates`
--

DROP TABLE IF EXISTS `estimates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estimates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `estimate_number` varchar(50) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('Draft','Sent','Approved','Rejected') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `estimate_number` (`estimate_number`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `estimates_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estimates`
--

LOCK TABLES `estimates` WRITE;
/*!40000 ALTER TABLE `estimates` DISABLE KEYS */;
INSERT INTO `estimates` VALUES (1,1,'EST-1001',4200000.00,'Approved','2025-12-21 08:22:02'),(2,1,'EST-1766308690',500000.00,'Draft','2025-12-21 09:18:10'),(3,1,'EST-1766765987',0.00,'Draft','2025-12-26 16:19:47'),(8,1,'EST-1767466500',0.00,'Draft','2026-01-03 18:55:00'),(16,1,'EST-1767531269',862000.00,'Approved','2026-01-04 12:54:29'),(17,8,'EST-1767546092',862000.00,'Sent','2026-01-04 17:01:32'),(18,8,'EST-1767546114',862000.00,'Sent','2026-01-04 17:01:54');
/*!40000 ALTER TABLE `estimates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floor_plan_pins`
--

DROP TABLE IF EXISTS `floor_plan_pins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `floor_plan_pins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `floor_plan_id` int(11) NOT NULL,
  `pin_type` enum('Punchlist','RFI','Inspection','Note') DEFAULT 'Note',
  `reference_id` int(11) DEFAULT NULL,
  `x_percent` decimal(6,2) NOT NULL,
  `y_percent` decimal(6,2) NOT NULL,
  `comment` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `floor_plan_id` (`floor_plan_id`),
  KEY `pin_type` (`pin_type`),
  KEY `fk_fppin_creator` (`created_by`),
  CONSTRAINT `fk_fppin_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fppin_plan` FOREIGN KEY (`floor_plan_id`) REFERENCES `project_floor_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floor_plan_pins`
--

LOCK TABLES `floor_plan_pins` WRITE;
/*!40000 ALTER TABLE `floor_plan_pins` DISABLE KEYS */;
INSERT INTO `floor_plan_pins` VALUES (1,1,'Punchlist',1,34.50,42.80,'Rough-in conduit exposed along east drywall partition. Patch and skim required.',1,'2026-10-01 16:25:24'),(2,1,'RFI',1,68.20,28.40,'RFI #004: Confirmation requested on beam clearance above executive doorway header.',1,'2026-10-01 16:25:24'),(3,1,'Inspection',NULL,52.10,74.60,'Passed electrical rough-in code check. Signed off by Municipal Inspector.',1,'2026-10-01 16:25:24'),(4,1,'Note',NULL,21.00,65.00,'Client requested double-hung acoustic glazing for conference room perimeter.',1,'2026-10-01 16:25:24');
/*!40000 ALTER TABLE `floor_plan_pins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `floor_plans`
--

DROP TABLE IF EXISTS `floor_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `floor_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `file_2d` varchar(255) DEFAULT NULL,
  `file_3d` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Processing','Completed') DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `floor_plans_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `floor_plans_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floor_plans`
--

LOCK TABLES `floor_plans` WRITE;
/*!40000 ALTER TABLE `floor_plans` DISABLE KEYS */;
INSERT INTO `floor_plans` VALUES (1,1,'villa ','1766754413_2d_medium.jpg','sample_3d.jpg',1,'2025-12-26 13:06:53','Completed'),(2,1,'villa ','1766754730_2d_medium.jpg','sample_3d.jpg',1,'2025-12-26 13:12:10','Completed'),(3,1,'house ','1766755002_2d_59f7d43b756c8419fd5472214684b12e.jpg','sample_3d.jpg',1,'2025-12-26 13:16:42','Completed'),(4,1,'villa ','1766755099_2d_59f7d43b756c8419fd5472214684b12e.jpg','1766755099_2d_59f7d43b756c8419fd5472214684b12e.jpg',1,'2025-12-26 13:18:19','Completed'),(5,1,'villa ','1766755382_2d_download (1).png','sample_3d.jpg',1,'2025-12-26 13:23:02','Completed'),(6,1,'villa ','1766756040_2d_sample_3d.jpg.jpg','sample_3d.jpg',1,'2025-12-26 13:34:00','Completed'),(7,17,'villa ','1767458372_screencapture-localhost-buildnexus-features-3d-floor-plans-php-2025-12-21-15_06_51.png','mesh_4aa45eb69fc7f88d.glb',1,'2026-01-03 16:39:32','Completed'),(8,1,'house ','1767458512_59f7d43b756c8419fd5472214684b12e.jpg','mesh_acde048b6ff7e0a6.glb',1,'2026-01-03 16:41:52','Completed'),(9,2,'villa ','1767460738_d67a804d.jpg','mesh_1767460738.glb',5,'2026-01-03 17:18:58','Completed'),(10,2,'villa ','1767461708_9ea5bfca.jpg','spatial_mesh_10.glb',1,'2026-01-03 17:35:08','Completed'),(11,1,'villa ','1767462201_ce1e0c8e.jpg','spatial_mesh_11.glb',1,'2026-01-03 17:43:21','Completed'),(12,2,'house ','1789894295_3a5dbb17.png','spatial_mesh_12.glb',1,'2026-09-20 08:51:35','Completed'),(13,4,'villa ','1790323855_60d62100.jpg','spatial_mesh_13.glb',1,'2026-09-25 08:10:55','Completed'),(14,5,'house ','1790325440_8a08b0f0.jpg','spatial_mesh_14.glb',1,'2026-09-25 08:37:20','Completed');
/*!40000 ALTER TABLE `floor_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grn`
--

DROP TABLE IF EXISTS `grn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grn` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grn_number` varchar(50) NOT NULL,
  `po_id` int(11) NOT NULL,
  `received_by` int(11) DEFAULT NULL,
  `received_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Pending Approval','Approved','Rejected') DEFAULT 'Pending Approval',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grn`
--

LOCK TABLES `grn` WRITE;
/*!40000 ALTER TABLE `grn` DISABLE KEYS */;
INSERT INTO `grn` VALUES (1,'GRN-2026-001',3,1,'2026-10-01','Delivery verified by site security, awaiting PM sign-off | PM Sign-off: Approved on 2026-10-01 19:53 (Quality verified)','Pending Approval','2026-10-01 17:45:18');
/*!40000 ALTER TABLE `grn` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inspection_defects`
--

DROP TABLE IF EXISTS `inspection_defects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inspection_defects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_id` int(11) NOT NULL,
  `defect_description` text NOT NULL,
  `remedy_status` enum('Pending','Rectified','Verified') DEFAULT 'Pending',
  `photo_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_id_inspection` (`inspection_id`),
  CONSTRAINT `fk_id_inspection` FOREIGN KEY (`inspection_id`) REFERENCES `project_inspections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_defects`
--

LOCK TABLES `inspection_defects` WRITE;
/*!40000 ALTER TABLE `inspection_defects` DISABLE KEYS */;
INSERT INTO `inspection_defects` VALUES (1,3,'Secondary water riser pressure drop below 50 PSI. Repair and re-test.','Pending',NULL);
/*!40000 ALTER TABLE `inspection_defects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit_price` decimal(15,2) NOT NULL,
  `line_total` decimal(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,3,'Variation: CO-4 - CO-2024-002',1.00,80000.00,80000.00),(2,4,'Variation: CO-4 - CO-2024-002',1.00,80000.00,80000.00),(3,5,'Variation: CO-4 - CO-2024-002',1.00,80000.00,80000.00),(4,6,'Variation: CO-4 - CO-2024-002',1.00,80000.00,80000.00);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_payments`
--

DROP TABLE IF EXISTS `invoice_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `payment_method` enum('Card','Bank Transfer','Cheque','Cash') NOT NULL DEFAULT 'Bank Transfer',
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `receipt_slip_url` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inv_pay_invoice` (`invoice_id`),
  KEY `idx_inv_pay_client` (`client_id`),
  KEY `idx_inv_pay_status` (`status`),
  CONSTRAINT `fk_inv_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_payments`
--

LOCK TABLES `invoice_payments` WRITE;
/*!40000 ALTER TABLE `invoice_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` enum('Draft','Sent','Pending Verification','Paid','Partially Paid','Overdue','Void') NOT NULL DEFAULT 'Sent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `issue_date` date DEFAULT curdate(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,NULL,'INV-2025-001',250000.00,0.00,'2025-01-15','',NULL,'2025-12-21 11:19:35',0.00,'2026-10-01'),(2,26,10,'INV-2026-001',400000.00,0.00,'2026-10-20','Sent',NULL,'2026-01-04 05:28:37',862000.00,'2026-10-01'),(3,4,4,'INV-2026-002',80000.00,0.00,'2026-10-15','Sent','Generated from Change Order: CO-4','2026-10-01 07:36:57',80000.00,'2026-10-01'),(4,4,4,'INV-2026-003',80000.00,0.00,'2026-10-15','Sent','Generated from Change Order: CO-4','2026-10-01 07:41:27',80000.00,'2026-10-01'),(5,4,4,'INV-2026-004',80000.00,0.00,'2026-10-15','Sent','Generated from Change Order: CO-4','2026-10-01 07:41:59',80000.00,'2026-10-01'),(6,4,4,'INV-2026-005',80000.00,0.00,'2026-10-15','Sent','Generated from Change Order: CO-4','2026-10-01 07:46:48',80000.00,'2026-10-01'),(7,4,4,'INV-0128',2500000.00,0.00,'2026-10-15','Sent','Milestone 3 Progress Payment','2026-10-01 17:08:03',2500000.00,'2026-10-01'),(8,26,10,'INV-2026-006',462000.00,0.00,'2026-10-25','Sent','Phase 2 Milestone Payment','2026-10-02 13:37:09',462000.00,'2026-10-02');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(150) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `project_type` varchar(100) NOT NULL,
  `estimated_budget` decimal(15,2) DEFAULT 0.00,
  `site_location` varchar(255) DEFAULT NULL,
  `status` enum('New','Follow-up','Quoted','Converted','Lost') DEFAULT 'New',
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ai_response_draft` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (1,'Bemasha Prashangi','e198130@esoft.academy','0712345678','Residential',25000000.00,'Greater Colombo Area','Converted',1,NULL,NULL,'2025-12-21 09:46:56'),(2,'Bemasha Prashangi','pm@buildnexus.com',NULL,'xxx',25000000.00,'Greater Colombo Area','Converted',NULL,NULL,NULL,'2025-12-26 22:38:56'),(3,'Bemasha Prashangi','pm@buildnexus.com',NULL,'ddsss',25000000.00,'Greater Colombo Area','Converted',NULL,NULL,NULL,'2025-12-26 22:39:33'),(4,'Bemasha Prashangi','user@demo.com',NULL,'Office Innovation',28000000.00,'Greater Colombo Area','Converted',NULL,'Source: Website | Assigned to: Sunil',NULL,'2026-01-03 13:22:02'),(5,'Bemasha Prashangi','e198130@esoft.academy',NULL,'Office Innovation',32000000.00,'Greater Colombo Area','Converted',NULL,'Source: Website',NULL,'2026-01-03 13:27:26'),(6,'Bemasha Prashangi','bema.s@email.com',NULL,'Office Innovation',32000000.00,'Nawala Road, Rajagiriya','Converted',NULL,'Source: Website',NULL,'2026-01-03 13:29:24'),(7,'Praveen Trevel ','pts@gmail.com','0712345678','commercial ',60000000.00,'Havelock Town, Colombo 05','Converted',2,'Source: Referral | Assigned to: Sunil | Assigned to: Sunil',NULL,'2026-01-03 13:35:53'),(8,'Ajith','ajith@gmail.com',NULL,'comercial',85000000.00,'Union Place, Colombo 02','Converted',3,'Source: Referral',NULL,'2026-01-04 16:47:27'),(9,'Bimal','bimal@gmail.com',NULL,'residetail',45000000.00,'Kandy Road, Katugastota','Converted',7,'Source: Website',NULL,'2026-01-04 16:56:29'),(10,'Saman Jayawardena','saman.j@gmail.com','+94 77 443 2190','Residential Luxury Villa',55000000.00,'Victoria Golf Range, Digana, Kandy','New',NULL,'Client wants a 4-bedroom eco-luxury villa with cantilevered swimming pool.',NULL,'2026-10-01 16:03:27'),(11,'Cascade Leisure Resorts','procure@cascade.lk','+94 11 889 0012','Commercial Hotel Expansion',120000000.00,'Lighthouse Road, Galle Fort','Follow-up',2,'Phase 2 boutique suite expansion with heritage conservation compliance requirements.',NULL,'2026-10-01 16:03:27'),(12,'Naveen Senaratne','naveen.s@horizon.lk','+94 71 556 7788','Office Renovation',18500000.00,'Duplication Road, Colombo 03','Quoted',7,'3-floor corporate office interior redesign with open collaboration zones and acoustic partitions.','Ayubowan Naveen,\n\nThank you for contacting Tharaka Construction & BuildNexus regarding your upcoming Office Renovation project at Duplication Road, Colombo 03.\n\nWe have carefully reviewed your initial requirements (\"3-floor corporate office interior redesign with open collaboration zones and acoustic partitions.\"). With over 15 years of industry-leading commercial and residential engineering experience across Sri Lanka, our multidisciplinary team specializes in high-quality structural execution, modern architectural finishes, and transparent turnkey project management.\n\nTo provide you with a comprehensive Bill of Quantities (BOQ) and preliminary project timeline, we would welcome the opportunity to conduct an initial on-site consultation or technical coordination call at your convenience.\n\nPlease let us know your preferred availability this week for an exploratory discussion.\n\nWarm regards,\n\nLead Estimator & Pre-Construction Team\nTharaka Construction | Powered by BuildNexus\nDirect: +94 11 234 5678 | Email: inquiries@buildnexus.com','2026-10-01 16:03:27'),(18,'Dr. Rohan De Silva','rohan@hospital.lk','+94 77 123 4567','Commercial Medical Center',65000000.00,'Colombo, Sri Lanka','New',NULL,NULL,NULL,'2026-10-01 17:45:18'),(19,'Kavinda Wickramasinghe','kavinda@resorts.lk','+94 77 123 4567','Luxury Beach Chalets',42000000.00,'Colombo, Sri Lanka','New',NULL,NULL,NULL,'2026-10-01 17:45:18'),(20,'Sanjaya Ratnayake','sanjaya@ratnayake.lk','+94 77 123 4567','Residential 3-Story Residence',28000000.00,'Colombo, Sri Lanka','New',NULL,NULL,NULL,'2026-10-01 17:45:18'),(21,'Anoma Gunasekara','anoma@textiles.lk','+94 77 123 4567','Factory Warehouse Renovation',35000000.00,'Colombo, Sri Lanka','New',NULL,NULL,'Ayubowan Anoma,\n\nThank you for contacting Tharaka Construction & BuildNexus regarding your upcoming Factory Warehouse Renovation project at Colombo, Sri Lanka.\n\nWe have carefully reviewed your initial requirements. With over 15 years of industry-leading commercial and residential engineering experience across Sri Lanka, our multidisciplinary team specializes in high-quality structural execution, modern architectural finishes, and transparent turnkey project management.\n\nTo provide you with a comprehensive Bill of Quantities (BOQ) and preliminary project timeline, we would welcome the opportunity to conduct an initial on-site consultation or technical coordination call at your convenience.\n\nPlease let us know your preferred availability this week for an exploratory discussion.\n\nWarm regards,\n\nLead Estimator & Pre-Construction Team\nTharaka Construction | Powered by BuildNexus\nDirect: +94 11 234 5678 | Email: inquiries@buildnexus.com','2026-10-01 17:45:18');
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketing_campaigns`
--

DROP TABLE IF EXISTS `marketing_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marketing_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(150) NOT NULL,
  `subject_line` varchar(255) NOT NULL,
  `target_audience` varchar(100) DEFAULT 'All Contacts',
  `recipient_count` int(11) DEFAULT 0,
  `status` enum('Draft','Scheduled','Sending','Sent','Archived') DEFAULT 'Draft',
  `send_date` datetime DEFAULT NULL,
  `content_html` longtext NOT NULL,
  `total_sent` int(11) DEFAULT 0,
  `total_opened` int(11) DEFAULT 0,
  `total_clicked` int(11) DEFAULT 0,
  `open_rate` decimal(5,2) GENERATED ALWAYS AS (if(`total_sent` > 0,`total_opened` / `total_sent` * 100,0)) STORED,
  `click_rate` decimal(5,2) GENERATED ALWAYS AS (if(`total_sent` > 0,`total_clicked` / `total_sent` * 100,0)) STORED,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `send_date` (`send_date`),
  KEY `fk_mcamp_creator` (`created_by`),
  CONSTRAINT `fk_mcamp_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketing_campaigns`
--

LOCK TABLES `marketing_campaigns` WRITE;
/*!40000 ALTER TABLE `marketing_campaigns` DISABLE KEYS */;
INSERT INTO `marketing_campaigns` VALUES (1,'October Newsletter','BuildNexus Monthly Digest: Commercial Innovations & Site Highlights','All Contacts',1250,'Sent','2024-10-15 09:00:00','<h2>BuildNexus October Newsletter</h2><p>Explore our recent engineering milestones, safety benchmarks, and upcoming tender announcements across Colombo and Kandy.</p>',1250,356,53,28.48,4.24,1,'2024-10-15 03:30:00'),(2,'New Project Showcase: Kandy Villa','Architectural Showcase: Luxury Eco-Villa in Kandy Hills','Past Clients',850,'Sent','2024-10-01 10:30:00','<h2>Exclusive Project Showcase: Kandy Villa</h2><p>We are thrilled to unveil our completed turnkey residential project blending sustainable timber framing with panoramic hill views.</p>',850,298,76,35.06,8.94,1,'2024-10-01 05:00:00'),(3,'Holiday Promotion','Special Pre-Construction Architectural & Permitting Discounts','Leads',0,'Draft','2024-11-05 08:00:00','<h2>Early Bird Holiday Pre-Construction Package</h2><p>Plan your Q1 residential and industrial renovations with zero estimation fees this holiday season.</p>',0,0,0,0.00,0.00,1,'2024-11-05 02:30:00'),(4,'Q3 Project Updates','BuildNexus Q3 Engineering Milestones & Supply Chain Highlights','All Contacts',2500,'Sent','2024-09-20 11:15:00','<h2>Quarterly Engineering Report - Q3</h2><p>Review our portfolio metrics, concrete pour records, and on-time completion rates for commercial developments.</p>',2500,550,78,22.00,3.12,1,'2024-09-20 05:45:00'),(5,'Follow-up for Open Bids','Pending Subcontractor Bid Packages & Material Requests','Subcontractors',34,'Scheduled','2024-11-01 14:00:00','<h2>Subcontractor Bid Package Follow-up</h2><p>Friendly reminder that tender submission deadlines for Colombo Commercial Complex rough-in packages close next Friday.</p>',0,0,0,0.00,0.00,1,'2024-11-01 08:30:00'),(8,'October Newsletter (Copy)','BuildNexus Monthly Digest: Commercial Innovations & Site Highlights','All Contacts',19,'Sent','2026-10-01 21:44:37','<h2>BuildNexus October Newsletter</h2><p>Explore our recent engineering milestones, safety benchmarks, and upcoming tender announcements across Colombo and Kandy.</p>',19,0,0,0.00,0.00,1,'2026-10-01 16:14:28');
/*!40000 ALTER TABLE `marketing_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mood_board_items`
--

DROP TABLE IF EXISTS `mood_board_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mood_board_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mood_board_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `item_type` enum('Inspiration','Material Sample','Color Palette','Lighting') DEFAULT 'Inspiration',
  `pos_x` int(11) DEFAULT 0,
  `pos_y` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `linked_selection_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `mood_board_id` (`mood_board_id`),
  KEY `item_type` (`item_type`),
  KEY `fk_mbi_selection` (`linked_selection_id`),
  CONSTRAINT `fk_mbi_board` FOREIGN KEY (`mood_board_id`) REFERENCES `project_mood_boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mbi_selection` FOREIGN KEY (`linked_selection_id`) REFERENCES `project_selections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mood_board_items`
--

LOCK TABLES `mood_board_items` WRITE;
/*!40000 ALTER TABLE `mood_board_items` DISABLE KEYS */;
INSERT INTO `mood_board_items` VALUES (1,1,'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&auto=format&fit=crop','Freestanding Stone Composite Soaking Tub','Inspiration',0,0,0,NULL,'2026-10-01 16:32:24'),(2,1,'https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=600&auto=format&fit=crop','Fluted White Oak Double Vanity','Material Sample',220,0,1,NULL,'2026-10-01 16:32:24'),(3,1,'https://images.unsplash.com/photo-1584622781564-1d987f7333c1?w=600&auto=format&fit=crop','Wall-Mount Matte Black Fixture Trim','Material Sample',440,0,2,NULL,'2026-10-01 16:32:24'),(4,1,'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=600&auto=format&fit=crop','Warm Sand & Beige Color Swatches (#E8DFD8)','Color Palette',0,220,3,NULL,'2026-10-01 16:32:24'),(5,2,'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=600&auto=format&fit=crop','Calacatta Gold Waterfall Island Countertop','Inspiration',0,0,0,NULL,'2026-10-01 16:32:24'),(6,2,'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop','Brushed Brass Linear Pendant Luminaire','Lighting',220,0,1,NULL,'2026-10-01 16:32:24'),(7,2,'https://images.unsplash.com/photo-1507089947368-19c1da9775ae?w=600&auto=format&fit=crop','Smoked Oak Base Cabinetry Finish','Material Sample',440,0,2,NULL,'2026-10-01 16:32:24'),(8,3,'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=600&auto=format&fit=crop','Polished Concrete Slab with Teak Louvers','Inspiration',0,0,0,NULL,'2026-10-01 16:32:24'),(9,3,'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=600&auto=format&fit=crop','Earthy Forest & Clay Palette (#2C3E2D, #C47A53)','Color Palette',220,0,1,NULL,'2026-10-01 16:32:24'),(10,4,'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop','Basalt Stone Pavers & Reflecting Pond','Inspiration',0,0,0,NULL,'2026-10-01 16:32:24'),(11,5,'https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?auto=format&fit=crop&w=600&q=80','Boucle Curved Lounge Sofa','Inspiration',10,10,1,NULL,'2026-10-01 16:35:57'),(12,5,'https://images.unsplash.com/photo-1595428774223-ef52624120d2?auto=format&fit=crop&w=600&q=80','Honed Roman Travertine Plinth','Material Sample',250,10,2,NULL,'2026-10-01 16:35:57'),(13,5,'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=600&q=80','Warm Opal Globe Pendant Light','Lighting',10,250,3,NULL,'2026-10-01 16:35:57');
/*!40000 ALTER TABLE `mood_board_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mood_boards`
--

DROP TABLE IF EXISTS `mood_boards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mood_boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `mood_boards_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mood_boards`
--

LOCK TABLES `mood_boards` WRITE;
/*!40000 ALTER TABLE `mood_boards` DISABLE KEYS */;
/*!40000 ALTER TABLE `mood_boards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'note',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (2,2,4,'note','New Reply on Luxury Villa in Kandy','Mrs. Silva: \"Automated Test Reply: Landscaping will commence on Monday. (6abfbb39009ac)\"',0,'2026-10-02 14:10:01'),(3,2,4,'note','New Project Note on Luxury Villa in Kandy','Mrs. Silva: \"what\'s project status\"',0,'2026-10-02 14:12:59'),(4,2,4,'note','New Project Note on Luxury Villa in Kandy','Mrs. Silva: \"hi\"',0,'2026-10-02 14:25:34');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(100) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT NULL,
  `fee_deducted` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `gateway_response` text DEFAULT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('Credit Card','Bank Transfer','Direct Deposit','Cash','Cheque') DEFAULT 'Credit Card',
  `status` enum('Completed','Pending','Failed','Refunded') DEFAULT 'Completed',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_files`
--

DROP TABLE IF EXISTS `project_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` varchar(50) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_files`
--

LOCK TABLES `project_files` WRITE;
/*!40000 ALTER TABLE `project_files` DISABLE KEYS */;
INSERT INTO `project_files` VALUES (1,1,'workflow-1.jpg.jpg','uploads/1766818141_workflow_1.jpg.jpg','74 KB',5,'2025-12-27 06:49:01');
/*!40000 ALTER TABLE `project_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_floor_plans`
--

DROP TABLE IF EXISTS `project_floor_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_floor_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `plan_code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `discipline` enum('Architectural','Structural','Plumbing','Electrical','HVAC','Civil') DEFAULT 'Architectural',
  `file_url` varchar(255) NOT NULL,
  `version_tag` varchar(20) DEFAULT 'Rev 1.0',
  `status` enum('Under Review','Approved for Construction','Superseded') DEFAULT 'Approved for Construction',
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `discipline` (`discipline`),
  KEY `status` (`status`),
  KEY `fk_pfp_uploader` (`uploaded_by`),
  CONSTRAINT `fk_pfp_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pfp_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_floor_plans`
--

LOCK TABLES `project_floor_plans` WRITE;
/*!40000 ALTER TABLE `project_floor_plans` DISABLE KEYS */;
INSERT INTO `project_floor_plans` VALUES (1,1,'A-101','Ground Floor Architectural & Space Layout','Architectural','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1600&auto=format&fit=crop','Rev 2.0','Approved for Construction',1,'2026-10-01 16:25:23','2026-10-01 16:25:23'),(2,1,'S-201','Foundation & Reinforced Concrete Column Grid','Structural','https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=1600&auto=format&fit=crop','Rev 1.2','Approved for Construction',1,'2026-10-01 16:25:23','2026-10-01 16:25:23'),(3,1,'E-002','Main Distribution Board & Lighting Circuit Plan','Electrical','https://images.unsplash.com/photo-1581094794329-c8112a89af12?w=1600&auto=format&fit=crop','Rev 1.0','Under Review',1,'2026-10-01 16:25:23','2026-10-01 16:25:23'),(4,2,'P-301','Sanitary Plumbing & Drainage Runout Plan','Plumbing','https://images.unsplash.com/photo-1541888946425-d0fbb186c5f7?w=1600&auto=format&fit=crop','Rev 1.1','Approved for Construction',1,'2026-10-01 16:25:23','2026-10-01 16:25:23'),(5,1,'M-401','HVAC Ductwork & Chilled Water Piping Scheme','HVAC','https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=1600&auto=format&fit=crop','Rev 0.9','Under Review',1,'2026-10-01 16:25:24','2026-10-01 16:25:24'),(6,1,'A-100','Schematic Preliminary Concept Floor Plan','Architectural','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=1600&auto=format&fit=crop','Rev 1.0','Superseded',1,'2026-10-01 16:25:24','2026-10-01 16:25:24');
/*!40000 ALTER TABLE `project_floor_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_inspections`
--

DROP TABLE IF EXISTS `project_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inspection_code` varchar(50) NOT NULL,
  `project_id` int(11) NOT NULL,
  `inspection_type` varchar(100) NOT NULL,
  `inspector_name` varchar(150) NOT NULL,
  `inspector_agency` varchar(150) DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('Scheduled','Passed','Failed','Cancelled') DEFAULT 'Scheduled',
  `result_notes` text DEFAULT NULL,
  `report_file_url` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `inspection_code` (`inspection_code`),
  KEY `fk_pi_project` (`project_id`),
  KEY `fk_pi_user` (`created_by`),
  CONSTRAINT `fk_pi_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pi_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_inspections`
--

LOCK TABLES `project_inspections` WRITE;
/*!40000 ALTER TABLE `project_inspections` DISABLE KEYS */;
INSERT INTO `project_inspections` VALUES (1,'INSP-2024-001',4,'Framing','Municipal Inspector','City Council Building Dept','2024-11-05','2024-11-05','Passed','Framing anchors, shear walls, and joist hangers verified according to structural drawings.',NULL,1,'2026-10-01 15:39:41'),(2,'INSP-2024-002',29,'Electrical Rough-in','John Doe','Urban Development Authority (UDA)','2024-11-08',NULL,'Scheduled','Pre-drywall electrical inspection for 4th floor conduits, junction boxes, and panel grounding.',NULL,1,'2026-10-01 15:39:41'),(3,'INSP-2024-003',30,'Final Plumbing','Municipal Inspector','Southern Provincial Council','2024-11-01','2024-11-01','Failed','Water pressure drop detected in secondary riser line. Backflow preventer certificate required before re-inspection.',NULL,1,'2026-10-01 15:39:41'),(4,'INSP-2024-004',4,'Foundation','Municipal Inspector','City Council Building Dept','2024-10-15','2024-10-15','Passed','Foundation rebar, depth of excavation, and soil compaction confirmed up to national building code.',NULL,1,'2026-10-01 15:39:41');
/*!40000 ALTER TABLE `project_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_milestones`
--

DROP TABLE IF EXISTS `project_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `event_type` enum('Milestone','Phase Start','Phase End','Inspection','Delivery') DEFAULT 'Milestone',
  `phase_name` varchar(255) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `color_hex` varchar(10) DEFAULT NULL,
  `status` enum('Upcoming','In Progress','Completed','Delayed') DEFAULT 'Upcoming',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `fk_milestones_created_by` (`created_by`),
  CONSTRAINT `fk_milestones_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_milestones_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_milestones`
--

LOCK TABLES `project_milestones` WRITE;
/*!40000 ALTER TABLE `project_milestones` DISABLE KEYS */;
INSERT INTO `project_milestones` VALUES (1,5,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(2,6,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(3,7,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(4,8,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(5,9,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(6,10,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(7,11,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(8,12,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(9,13,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(10,14,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(11,15,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(12,16,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(13,17,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(14,18,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(15,19,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(16,20,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(17,21,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(18,22,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(19,23,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(20,24,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-03','2026-01-10',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(21,26,'Phase 1: Demolition & Site Prep','Milestone','Phase 1: Demolition & Site Prep','2026-01-10','2026-01-15',NULL,NULL,'Completed',NULL,'2026-10-01 08:02:19'),(22,26,'Phase 2: Rough-in Plumbing & Electrical','Milestone','Phase 2: Rough-in Plumbing & Electrical','2026-01-16','2026-01-22',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(23,26,'Phase 3: Cabinetry & Countertop Install','Milestone','Phase 3: Cabinetry & Countertop Install','2026-01-23','2026-02-05',NULL,NULL,'Upcoming',NULL,'2026-10-01 08:02:19'),(24,26,'Phase 4: Tiling & Backsplash','Milestone','Phase 4: Tiling & Backsplash','2026-02-06','2026-02-12',NULL,NULL,'Upcoming',NULL,'2026-10-01 08:02:19'),(25,1,'Mobilization & Site Prep','Milestone','Mobilization & Site Prep','2026-01-04','2026-01-11',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(26,27,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-04','2026-01-11',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(27,28,'Initial Project Kickoff & Planning','Milestone','Initial Project Kickoff & Planning','2026-01-04','2026-01-11',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(28,1,'Mobilization & Site Prep','Milestone','Mobilization & Site Prep','2026-01-04','2026-01-11',NULL,NULL,'In Progress',NULL,'2026-10-01 08:02:19'),(29,31,'Earthwork Start','Phase Start','Earthwork Start','2026-10-01',NULL,'Heavy machinery mobilized for initial site earthwork.','#f97316','In Progress',NULL,'2026-10-01 08:03:29'),(30,4,'Foundation Complete','Milestone','Foundation Complete','2026-10-18',NULL,'Reinforced concrete foundation curing inspection and signoff.','#3b82f6','Upcoming',NULL,'2026-10-01 08:03:29'),(31,30,'Finishing Touches','Phase End','Finishing Touches','2026-10-25',NULL,'Interior joinery and architectural fixtures completion.','#a855f7','Upcoming',NULL,'2026-10-01 08:03:29');
/*!40000 ALTER TABLE `project_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_mood_boards`
--

DROP TABLE IF EXISTS `project_mood_boards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_mood_boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `room_space` varchar(100) DEFAULT 'General',
  `description` text DEFAULT NULL,
  `status` enum('Draft','Shared with Client','Approved','Revisions Requested') DEFAULT 'Draft',
  `client_feedback` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `room_space` (`room_space`),
  KEY `status` (`status`),
  KEY `fk_pmb_creator` (`created_by`),
  CONSTRAINT `fk_pmb_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pmb_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_mood_boards`
--

LOCK TABLES `project_mood_boards` WRITE;
/*!40000 ALTER TABLE `project_mood_boards` DISABLE KEYS */;
INSERT INTO `project_mood_boards` VALUES (1,1,'Modern Japandi Master Bath','Master Bath','Earthy minimalist aesthetic combining organic fluted oak millwork, warm travertine tiles, matte black plumbing accents, and soft recessed cove lighting.','Approved',NULL,'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=800&auto=format&fit=crop',1,'2026-10-01 16:32:24','2026-10-01 16:32:24'),(2,1,'Executive Chef Kitchen & Butler Pantry','Kitchen','Dramatic high-contrast culinary space featuring waterfall Calacatta marble island, fluted glass upper cabinetry, and integrated brushed brass task pendants.','Shared with Client',NULL,'https://images.unsplash.com/photo-1556911220-e15b29be8c8f?w=800&auto=format&fit=crop',1,'2026-10-01 16:32:24','2026-10-01 16:32:24'),(3,2,'Tropical Modern Living & Terrace Transition','Living Room','Biophilic indoor-outdoor entertaining lounge with polished concrete floors, louvered teak screens, and oversized modular linen sectionals.','Draft',NULL,'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop',1,'2026-10-01 16:32:24','2026-10-01 16:32:24'),(4,1,'Exterior Courtyard & Water Feature Concept','Exterior Facade','Cantilevered exposed aggregate pergolas framing a tranquil basalt reflecting pond with landscape wash downlights.','Revisions Requested',NULL,'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&auto=format&fit=crop',1,'2026-10-01 16:32:24','2026-10-01 16:32:24'),(5,1,'Test Luxury Penthouse Lounge','Living Room','Warm minimalism with travertine, fluted oak, and brass accents','Approved','Looks stunning! Approved by homeowner on 2026-10-01','https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=800&q=80',1,'2026-10-01 16:35:57','2026-10-01 16:35:57');
/*!40000 ALTER TABLE `project_mood_boards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_notes`
--

DROP TABLE IF EXISTS `project_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `is_internal_only` tinyint(1) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `project_notes_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_notes`
--

LOCK TABLES `project_notes` WRITE;
/*!40000 ALTER TABLE `project_notes` DISABLE KEYS */;
INSERT INTO `project_notes` VALUES (1,1,5,'hi',0,NULL,'2025-12-27 06:28:07','2026-10-02 14:05:42'),(2,1,5,'Add labor and material cost for a wooden kitchen cabinet',0,NULL,'2026-01-03 08:56:22','2026-10-02 14:05:42'),(3,1,5,'hi there ',0,NULL,'2026-01-03 21:54:26','2026-10-02 14:05:42'),(4,26,8,'Can we ensure the quartz slab matches the sample provided last week?',0,NULL,'2026-01-04 05:28:37','2026-10-02 14:05:42'),(5,4,4,'hi',0,NULL,'2026-10-01 06:49:01','2026-10-02 14:05:42'),(6,4,4,'price',0,NULL,'2026-10-01 06:49:11','2026-10-02 14:05:42'),(7,4,10,'Client confirmed the selection for the kitchen backsplash tiles (Heritage Hex Porcelain). Please proceed with ordering.',0,NULL,'2026-09-30 10:35:42','2026-10-02 14:05:42'),(8,4,16,'That is correct. Looking forward to seeing them installed!',0,7,'2026-09-30 11:35:42','2026-10-02 14:05:42'),(9,4,16,'Can we get an update on the expected delivery date for the bathroom vanity?',0,NULL,'2026-10-01 10:35:42','2026-10-02 14:05:42'),(10,26,10,'Client confirmed the selection for the kitchen backsplash tiles (Heritage Hex Porcelain). Please proceed with ordering.',0,NULL,'2026-09-30 10:35:42','2026-10-02 14:05:42'),(11,26,16,'That is correct. Looking forward to seeing them installed!',0,10,'2026-09-30 11:35:42','2026-10-02 14:05:42'),(12,26,16,'Can we get an update on the expected delivery date for the bathroom vanity?',0,NULL,'2026-10-01 10:35:42','2026-10-02 14:05:42'),(16,4,4,'what\'s project status',0,NULL,'2026-10-02 14:12:59','2026-10-02 14:12:59'),(17,4,4,'hi',0,NULL,'2026-10-02 14:25:34','2026-10-02 14:25:34'),(18,4,2,'we start pantry side',0,16,'2026-10-02 14:26:30','2026-10-02 14:26:30');
/*!40000 ALTER TABLE `project_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_phases`
--

DROP TABLE IF EXISTS `project_phases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_phases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `phase_number` int(11) NOT NULL DEFAULT 1,
  `title` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('UPCOMING','IN PROGRESS','COMPLETED') NOT NULL DEFAULT 'UPCOMING',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phases_project` (`project_id`),
  CONSTRAINT `fk_project_phases_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_phases`
--

LOCK TABLES `project_phases` WRITE;
/*!40000 ALTER TABLE `project_phases` DISABLE KEYS */;
INSERT INTO `project_phases` VALUES (1,26,1,'Phase 1: Demolition & Site Prep','2026-01-10','2026-01-15','COMPLETED','Full teardown of existing cabinetry, countertops, and appliances with site protection.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(2,26,2,'Phase 2: Rough-in Plumbing & Electrical','2026-01-16','2026-01-22','IN PROGRESS','Installation of rough-in plumbing lines, electrical junction boxes, and new dedicated circuits.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(3,26,3,'Phase 3: Cabinetry & Countertop Install','2026-01-23','2026-02-05','UPCOMING','Precision mounting of custom shaker cabinets, quartz countertops, and island waterfall edge.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(4,26,4,'Phase 4: Tiling & Backsplash','2026-02-06','2026-02-12','UPCOMING','Subway tile backsplash installation, grouting, sealing, and final trim work.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(5,4,1,'Phase 1: Foundation & Earthwork','2026-01-05','2026-01-25','COMPLETED','Excavation and foundation concrete pouring.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(6,4,2,'Phase 2: Structural Framing & Columns','2026-01-26','2026-02-20','IN PROGRESS','Reinforced column construction and framing.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(7,4,3,'Phase 3: MEP Rough-in & Insulation','2026-02-21','2026-03-15','UPCOMING','Mechanical, electrical, plumbing installation.','2026-10-02 10:46:34','2026-10-02 10:46:34'),(8,4,4,'Phase 4: Interior Finishes & Handover','2026-03-16','2026-04-10','UPCOMING','Floor finishes, painting, fixture installation and final inspection.','2026-10-02 10:46:34','2026-10-02 10:46:34');
/*!40000 ALTER TABLE `project_phases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_rfis`
--

DROP TABLE IF EXISTS `project_rfis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_rfis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfi_number` varchar(50) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `assigned_to_name` varchar(150) DEFAULT NULL,
  `assigned_to_role` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `question_details` text DEFAULT NULL,
  `suggested_solution` text DEFAULT NULL,
  `status` enum('Open','Answered','Overdue','Closed') DEFAULT 'Open',
  `due_date` date DEFAULT NULL,
  `date_sent` date DEFAULT curdate(),
  `attachment_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rfi_number` (`rfi_number`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `project_rfis_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_rfis_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_rfis`
--

LOCK TABLES `project_rfis` WRITE;
/*!40000 ALTER TABLE `project_rfis` DISABLE KEYS */;
INSERT INTO `project_rfis` VALUES (1,'RFI-2026-001',1,1,'CLIRA','Subcontractor','WOOD NEED TO WATER BASED','ddss',NULL,'Open','2026-10-02','2026-10-01',NULL,'2026-10-01 07:10:56');
/*!40000 ALTER TABLE `project_rfis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_selection_rooms`
--

DROP TABLE IF EXISTS `project_selection_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_selection_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `budget_allowance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `fk_selroom_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_selection_rooms`
--

LOCK TABLES `project_selection_rooms` WRITE;
/*!40000 ALTER TABLE `project_selection_rooms` DISABLE KEYS */;
INSERT INTO `project_selection_rooms` VALUES (1,34,'Kitchen',250000.00,'2026-10-01 16:17:04'),(2,34,'Master Bathroom',150000.00,'2026-10-01 16:17:04'),(3,1,'Living Room - Test Lounge',750000.00,'2026-10-01 16:35:57');
/*!40000 ALTER TABLE `project_selection_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_selections`
--

DROP TABLE IF EXISTS `project_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_selections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT 'Fixtures',
  `photo_url` varchar(255) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `total_price` decimal(15,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `include_in_budget` tinyint(1) DEFAULT 1,
  `approval_status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `client_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `project_id` (`project_id`),
  KEY `approval_status` (`approval_status`),
  CONSTRAINT `fk_psel_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_psel_room` FOREIGN KEY (`room_id`) REFERENCES `project_selection_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_selections`
--

LOCK TABLES `project_selections` WRITE;
/*!40000 ALTER TABLE `project_selections` DISABLE KEYS */;
INSERT INTO `project_selections` VALUES (1,1,34,'Heritage Hex Porcelain','Flooring','https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&h=450&fit=crop',4560.00,40.00,182400.00,1,'Approved',NULL,'2026-10-01 16:17:04'),(2,1,34,'Mountain View Stone','Wall Finishes','https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=450&fit=crop',1824.00,4.00,7296.00,1,'Approved',NULL,'2026-10-01 16:17:04'),(3,1,34,'STYLISH Single Handle Sink Mixer','Plumbing','https://images.unsplash.com/photo-1584622781564-1d987f7333c1?w=600&h=450&fit=crop',310.80,1.00,310.80,1,'Approved',NULL,'2026-10-01 16:17:04'),(4,1,34,'Oak Vanity Cabinet','Cabinetry','https://images.unsplash.com/photo-1556910103-1c02745aae4d?w=600&h=450&fit=crop',1082.50,1.00,1082.50,1,'Declined',NULL,'2026-10-01 16:17:04');
/*!40000 ALTER TABLE `project_selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_submittals`
--

DROP TABLE IF EXISTS `project_submittals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_submittals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submittal_number` varchar(50) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `spec_section` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `submitted_to_name` varchar(150) DEFAULT NULL,
  `submitted_to_role` varchar(100) DEFAULT NULL,
  `submittal_type` enum('Product Data','Shop Drawing','Sample','Test Report') DEFAULT 'Product Data',
  `status` enum('Pending','Approved','Approved as Noted','Revise & Resubmit','Rejected') DEFAULT 'Pending',
  `date_sent` date DEFAULT curdate(),
  `review_due_date` date DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `submittal_number` (`submittal_number`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `project_submittals_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_submittals_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_submittals`
--

LOCK TABLES `project_submittals` WRITE;
/*!40000 ALTER TABLE `project_submittals` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_submittals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(150) NOT NULL,
  `project_code` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `pm_id` int(11) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT 0.00,
  `location` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `stage` enum('Planning','Earthwork','Framing','Roofing','Finishing','Handover') DEFAULT 'Planning',
  `progress_percent` int(11) DEFAULT 0,
  `status` enum('Active','Completed','On Hold','Archived') DEFAULT 'Active',
  `thumbnail_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_code` (`project_code`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,'Skyline Residence','PRJ-0001',5,'Mr. Perera',2,862000.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=140&h=90&fit=crop','2025-12-21 06:02:04','2026-10-02 10:56:36'),(2,'Coastal Villa','PRJ-0002',NULL,'Ms. Fernando',2,8000000.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=140&h=90&fit=crop','2025-12-21 06:02:04','2026-10-02 10:56:36'),(3,'Modern Apartment - Colombo','PRJ-0003',NULL,NULL,2,12000000.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1503387762-592dea58ef21?w=140&h=90&fit=crop','2025-12-21 08:30:12','2026-10-02 10:56:36'),(4,'Luxury Villa in Kandy','PRJ-0004',4,NULL,2,24845000.00,NULL,'2026-10-04','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop','2025-12-21 08:30:12','2026-10-02 14:35:39'),(5,'Residential Project','PRJ-0005',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=140&h=90&fit=crop','2026-01-03 10:39:11','2026-10-02 10:56:36'),(6,'xxx Project','PRJ-0006',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=140&h=90&fit=crop','2026-01-03 10:57:48','2026-10-02 10:56:36'),(7,'xxx Project','PRJ-0007',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=140&h=90&fit=crop','2026-01-03 11:11:33','2026-10-02 10:56:36'),(8,'Residential Project','PRJ-0008',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=140&h=90&fit=crop','2026-01-03 13:07:51','2026-10-02 10:56:36'),(9,'Residential Project','PRJ-0009',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1503387762-592dea58ef21?w=140&h=90&fit=crop','2026-01-03 13:07:59','2026-10-02 10:56:36'),(10,'Residential Project','PRJ-0010',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop','2026-01-03 13:09:43','2026-10-02 10:56:36'),(11,'Office Innovation Project','PRJ-0011',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=140&h=90&fit=crop','2026-01-03 13:34:40','2026-10-02 10:56:36'),(12,'Office Innovation Project','PRJ-0012',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=140&h=90&fit=crop','2026-01-03 14:07:01','2026-10-02 10:56:36'),(13,'commercial  Project','PRJ-0013',NULL,'Praveen',2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=140&h=90&fit=crop','2026-01-03 14:07:34','2026-10-02 10:56:36'),(14,'Office Innovation Project','PRJ-0014',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=140&h=90&fit=crop','2026-01-03 14:10:27','2026-10-02 10:56:36'),(15,'commercial  Project','PRJ-0015',NULL,'Praveen',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1503387762-592dea58ef21?w=140&h=90&fit=crop','2026-01-03 14:13:08','2026-10-02 10:56:36'),(16,'commercial  Project','PRJ-0016',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop','2026-01-03 14:15:04','2026-10-02 10:56:36'),(17,'commercial  Project','PRJ-0017',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=140&h=90&fit=crop','2026-01-03 14:15:34','2026-10-02 10:56:36'),(18,'commercial  Project','PRJ-0018',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=140&h=90&fit=crop','2026-01-03 14:15:58','2026-10-02 10:56:36'),(19,'commercial  Project','PRJ-0019',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=140&h=90&fit=crop','2026-01-03 14:34:43','2026-10-02 10:56:36'),(20,'commercial  Project','PRJ-0020',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=140&h=90&fit=crop','2026-01-03 14:36:07','2026-10-02 10:56:36'),(21,'Office Innovation Project','PRJ-0021',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1503387762-592dea58ef21?w=140&h=90&fit=crop','2026-01-03 14:36:10','2026-10-02 10:56:36'),(22,'commercial  Project','PRJ-0022',NULL,'Praveen Trevel ',2,0.00,NULL,'2026-03-01','2026-11-30','Planning',0,'Active','https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop','2026-01-03 16:11:36','2026-10-02 10:56:36'),(23,'Office Innovation Project','PRJ-0023',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=140&h=90&fit=crop','2026-01-03 16:12:15','2026-10-02 10:56:36'),(24,'ddsss Project','PRJ-0024',NULL,'Bemasha Prashangi',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=140&h=90&fit=crop','2026-01-03 16:20:59','2026-10-02 10:56:36'),(25,'Office modify ','PRJ-0025',NULL,'Mr. Zasel',2,1500000.00,NULL,'2026-01-03','2026-11-30','Framing',0,'Active','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=140&h=90&fit=crop','2026-01-03 17:25:03','2026-10-02 10:56:36'),(26,'Modern Kitchen Remodel','PRJ-0026',6,'John Doe',2,1500000.00,NULL,'2026-01-10','2026-11-30','Planning',50,'Active','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=140&h=90&fit=crop','2026-01-04 05:27:39','2026-10-02 10:56:36'),(27,'comercial Project','PRJ-0027',NULL,'Ajith',2,0.00,NULL,'2026-03-01','2026-11-30','Finishing',0,'Active','https://images.unsplash.com/photo-1503387762-592dea58ef21?w=140&h=90&fit=crop','2026-01-04 16:49:44','2026-10-02 10:56:36'),(28,'residetail Project','PRJ-0028',NULL,'Bimal',2,0.00,NULL,'2026-03-01','2026-11-30','Earthwork',0,'Active','https://images.unsplash.com/photo-1541888946425-d81bb19480c5?w=140&h=90&fit=crop','2026-01-04 16:57:39','2026-10-02 10:56:36'),(29,'Colombo Office Complex','PRJ-2026-011',NULL,NULL,2,45000000.00,NULL,'2026-10-11','2026-11-30','Planning',0,'Active',NULL,'2026-10-01 08:03:29','2026-10-02 10:56:36'),(30,'Galle Boutique Hotel','PRJ-2026-012',NULL,NULL,2,60000000.00,NULL,'2026-09-01','2026-11-30','Finishing',0,'Active',NULL,'2026-10-01 08:03:29','2026-10-02 10:56:36'),(31,'Highway Expansion E01','PRJ-2026-013',NULL,NULL,2,120000000.00,NULL,'2026-10-01','2026-11-30','Earthwork',100,'Active',NULL,'2026-10-01 08:03:29','2026-10-02 10:56:36'),(34,'Davis Kitchen Remodel','PRJ-2026-DKR',NULL,'Arthur Davis',2,250000.00,'Colombo, Sri Lanka','2026-10-01','2026-11-30','Planning',0,'Active',NULL,'2026-10-01 16:17:04','2026-10-02 10:56:36'),(35,'Project Alpha','PRJ-PRO-01',NULL,NULL,2,0.00,NULL,'2026-03-01','2026-11-30','Framing',100,'Active',NULL,'2026-10-01 16:57:24','2026-10-02 10:56:36'),(36,'Luxury Villa','PRJ-LUX-01',NULL,NULL,2,0.00,NULL,'2026-03-01','2026-11-30','Framing',0,'Active',NULL,'2026-10-01 16:57:24','2026-10-02 10:56:36'),(37,'City Center Mall','PRJ-2026-005',NULL,NULL,2,45000000.00,NULL,NULL,NULL,'Planning',0,'Active',NULL,'2026-10-01 17:45:18','2026-10-02 10:56:36');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) DEFAULT NULL,
  `item_description` varchar(255) DEFAULT NULL,
  `quantity_ordered` decimal(10,2) DEFAULT NULL,
  `quantity_received` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `line_total` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `vendor_name` varchar(255) DEFAULT NULL,
  `po_date` date DEFAULT curdate(),
  `expected_delivery_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `category` varchar(50) DEFAULT 'Materials',
  `status` enum('Draft','Pending Delivery','Goods Received','Converted to Bill','Paid','Cancelled') DEFAULT 'Draft',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `project_id` (`project_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
INSERT INTO `purchase_orders` VALUES (2,'PO-0098',4,NULL,'Lanka Steel Suppliers','2026-10-01',NULL,450000.00,0.00,450000.00,'Materials','','Roofing beams & standing seam fasteners',NULL,'2026-10-01 17:08:03'),(3,'PO #123',35,NULL,'Holcim Concrete Lanka','2026-10-01',NULL,650000.00,0.00,650000.00,'Materials','Pending Delivery','Ready mix concrete delivery',NULL,'2026-10-01 17:45:18');
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_code` varchar(50) NOT NULL,
  `project_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('Draft','Sent','Approved','Rejected') DEFAULT 'Sent',
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `status` (`status`),
  KEY `is_approved` (`is_approved`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotes`
--

LOCK TABLES `quotes` WRITE;
/*!40000 ALTER TABLE `quotes` DISABLE KEYS */;
INSERT INTO `quotes` VALUES (1,'Q-0012',37,1,12500000.00,'Sent',0,'2026-10-01 17:45:18'),(2,'Q-0015',35,1,3800000.00,'Sent',0,'2026-10-01 17:45:18'),(3,'Q-0018',4,1,5400000.00,'Sent',0,'2026-10-01 17:45:18');
/*!40000 ALTER TABLE `quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rfi_responses`
--

DROP TABLE IF EXISTS `rfi_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rfi_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rfi_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `response_text` text DEFAULT NULL,
  `official_attachment` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rfi_id` (`rfi_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `rfi_responses_ibfk_1` FOREIGN KEY (`rfi_id`) REFERENCES `project_rfis` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfi_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rfi_responses`
--

LOCK TABLES `rfi_responses` WRITE;
/*!40000 ALTER TABLE `rfi_responses` DISABLE KEYS */;
/*!40000 ALTER TABLE `rfi_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `safety_meeting_attendees`
--

DROP TABLE IF EXISTS `safety_meeting_attendees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `safety_meeting_attendees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meeting_id` int(11) NOT NULL,
  `worker_name` varchar(150) NOT NULL,
  `trade_role` varchar(100) DEFAULT 'Laborer',
  `signature_status` enum('Signed','Present','Absent') DEFAULT 'Signed',
  PRIMARY KEY (`id`),
  KEY `meeting_id` (`meeting_id`),
  CONSTRAINT `safety_meeting_attendees_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `safety_meeting_logs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `safety_meeting_attendees`
--

LOCK TABLES `safety_meeting_attendees` WRITE;
/*!40000 ALTER TABLE `safety_meeting_attendees` DISABLE KEYS */;
INSERT INTO `safety_meeting_attendees` VALUES (1,2,'Nimal Jayawardena','Excavator Operator','Signed'),(2,2,'Ruwan Bandara','Shoring Carpenter','Signed'),(3,2,'Kasun Dissanayake','Laborer','Signed'),(4,2,'Chaminda Silva','Laborer','Signed'),(5,2,'Anura Senanayake','Pipelayer','Signed'),(6,2,'Suresh Kumar','Mason','Signed'),(7,2,'Mohamed Rizwan','Laborer','Signed'),(8,2,'Pradeep Kumara','Steel Fixer','Signed'),(9,2,'Dinesh Gunasekara','Site Assistant','Signed'),(10,2,'Janaka Wickramasinghe','Carpenter','Signed'),(11,2,'Tharaka Mendis','Laborer','Signed'),(12,2,'Lalith Gamage','Surveyor Aide','Signed'),(13,3,'Sarath Kumara','Lead Electrician','Signed'),(14,3,'Ajith Premasiri','Electrician','Signed'),(15,3,'Gayan Madushanka','Electrician','Signed'),(16,3,'Mahesh Weerasinghe','Apprentice Electrician','Signed'),(17,3,'Nuwan Fernando','HVAC Tech','Signed'),(18,3,'Asanka Jayasinghe','Plumber','Signed'),(19,3,'Buddhika Rathnayake','Drywaller','Signed'),(20,3,'Manjula Wijesinghe','Drywaller','Signed'),(21,3,'Kelum Pathirana','Painter','Signed'),(22,3,'Chandana Perera','Laborer','Signed'),(23,3,'Roshan Abeysekara','Laborer','Signed'),(24,3,'Dhammika Alwis','Scaffolder','Signed'),(25,3,'Vipula Saman','Safety Assistant','Signed'),(26,3,'Indika Ranasinghe','Welder','Signed'),(27,3,'Saman Jayasuriya','Foreman Assistant','Signed'),(28,4,'Kusal Mendis','Scaffolder Lead','Signed'),(29,4,'Amila Pushpakumara','Roofer','Signed'),(30,4,'Sunil Ranatunga','Carpenter','Signed'),(31,4,'Lasantha De Silva','Glazier','Signed'),(32,4,'Jagath Kumara','Mason','Signed'),(33,4,'Sanjaya Liyanage','Laborer','Signed'),(34,4,'Priyashantha Silva','Painter','Signed'),(35,4,'Nalin Wickramasinghe','Laborer','Signed');
/*!40000 ALTER TABLE `safety_meeting_attendees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `safety_meeting_logs`
--

DROP TABLE IF EXISTS `safety_meeting_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `safety_meeting_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topic_id` int(11) DEFAULT NULL,
  `custom_topic` varchar(255) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `foreman_id` int(11) DEFAULT NULL,
  `attendees_count` int(11) DEFAULT 0,
  `meeting_date` date DEFAULT curdate(),
  `notes` text DEFAULT NULL,
  `signed_roster_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `topic_id` (`topic_id`),
  KEY `project_id` (`project_id`),
  KEY `foreman_id` (`foreman_id`),
  CONSTRAINT `safety_meeting_logs_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `safety_topics` (`id`),
  CONSTRAINT `safety_meeting_logs_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `safety_meeting_logs_ibfk_3` FOREIGN KEY (`foreman_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `safety_meeting_logs`
--

LOCK TABLES `safety_meeting_logs` WRITE;
/*!40000 ALTER TABLE `safety_meeting_logs` DISABLE KEYS */;
INSERT INTO `safety_meeting_logs` VALUES (1,1,NULL,1,3,11,'2025-12-21','compulsory',NULL,'2026-10-01 15:45:29'),(2,4,NULL,4,12,12,'2024-10-28','Discussed shoring box placement for hillside retaining wall excavation. Verified soil classification Type B. Spoil piles relocated 3 feet back from excavation edge. Ladder egress positioned at north and south ends.',NULL,'2026-10-01 15:45:29'),(3,3,NULL,29,13,15,'2024-10-27','Reviewed temporary switchboard grounding and GFCI daily testing. Tagged out two frayed 3-phase extension cables. Re-emphasized lockout/tagout (LOTO) protocols for 4th floor riser panels.',NULL,'2026-10-01 15:45:29'),(4,2,NULL,30,14,8,'2024-10-26','Inspected exterior bamboo and steel tubular scaffolding. Fall arrest harnesses checked for ANSI compliance and date tags. Confirmed lifelines secured to certified structural anchor points on the roof deck.',NULL,'2026-10-01 15:45:29');
/*!40000 ALTER TABLE `safety_meeting_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `safety_topics`
--

DROP TABLE IF EXISTS `safety_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `safety_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(100) DEFAULT 'General Safety',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `safety_topics`
--

LOCK TABLES `safety_topics` WRITE;
/*!40000 ALTER TABLE `safety_topics` DISABLE KEYS */;
INSERT INTO `safety_topics` VALUES (1,'Personal Protective Equipment (PPE)','Mandatory PPE on site includes hard hats (ANSI Z89.1 certified), high-visibility reflective vests, steel-toed boots (ASTM F2413), and protective eye goggles. Ensure all equipment is inspected daily for cracks, dents, tears, or degraded straps before entering the active work zones. Replace damaged helmets immediately.','General Safety','2026-10-01 15:45:29'),(2,'Working at Heights','Fall protection is required for work elevated at 6 feet or higher. Inspect harnesses, lanyards, and anchor points daily. Double-check scaffolding cross-braces, guardrails, toe boards, and outriggers. Never climb scaffolding ladders while carrying heavy tools; use mechanical hoists or tool belts. Keep 100% tie-off compliance at all edge perimeters.','Fall Protection','2026-10-01 15:45:29'),(3,'Electrical Safety','Inspect all extension cords and power tool casings for exposed copper, cuts, or damaged insulation. Ground Fault Circuit Interrupters (GFCI) must be utilized on all temporary site distribution panels and power drops. Maintain a minimum safe clearance of 10 feet from overhead power lines. Lockout/Tagout (LOTO) protocols strictly apply to all live distribution boxes.','Hazard Prevention','2026-10-01 15:45:29'),(4,'Trenching and Shoring Safety','All excavations 5 feet or deeper require engineered protective systems: sloping, benching, shielding (trench boxes), or shoring. Keep excavated spoil piles and heavy machinery at least 2 feet away from the trench crest. Safe egress ladders must be spaced within 25 feet of any worker inside the trench. A designated Competent Person must conduct atmospheric and wall stability tests daily before entry.','Excavation Safety','2026-10-01 15:45:29'),(5,'Scaffolding Erection & Inspection','Scaffolds must be erected on sound footings with mudsills and screw jacks. Platforms must be fully planked with no gaps exceeding 1 inch. Green inspection tags must be signed by the scaffold competent person daily. Red tags indicate out-of-service scaffolding. Never exceed manufacturer maximum rated load capacity.','Fall Protection','2026-10-01 15:45:29'),(6,'Hot Work & Fire Prevention','Hot work permits are required prior to any welding, cutting, grinding, or open-flame operations. Combustible materials within 35 feet must be cleared or shielded with fire-retardant blankets. Maintain a dedicated Fire Watch with a charged, inspected multi-purpose ABC extinguisher during hot work and for at least 30 minutes following completion.','Fire Safety','2026-10-01 15:45:29'),(7,'Crane, Rigging & Heavy Equipment','Never stand or walk under suspended loads. Riggers must verify sling capacity, shackle pins, and hook safety latches before hoisting. Crane operators and signal persons must confirm standard hand or radio signals. Maintain barricades around the crane swing radius to prevent pinch-point and crush hazards.','Equipment Safety','2026-10-01 15:45:29'),(8,'Heat Stress & Hydration Protocols','High ambient heat and humidity pose severe risks of heat exhaustion and heat stroke. Workers must consume at least 1 cup of cool water or electrolyte drink every 15 to 20 minutes. Utilize shaded rest areas during scheduled breaks. Immediately report symptoms including dizziness, nausea, confusion, or lack of sweating to site first aiders.','Occupational Health','2026-10-01 15:45:29');
/*!40000 ALTER TABLE `safety_topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `selections`
--

DROP TABLE IF EXISTS `selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `selections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `price_impact` decimal(15,2) DEFAULT 0.00,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `selections_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `selections`
--

LOCK TABLES `selections` WRITE;
/*!40000 ALTER TABLE `selections` DISABLE KEYS */;
/*!40000 ALTER TABLE `selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submittal_reviews`
--

DROP TABLE IF EXISTS `submittal_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submittal_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submittal_id` int(11) DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `review_decision` enum('Approved','Approved as Noted','Revise & Resubmit','Rejected') DEFAULT NULL,
  `review_comments` text DEFAULT NULL,
  `annotated_file_path` varchar(255) DEFAULT NULL,
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `submittal_id` (`submittal_id`),
  KEY `reviewer_id` (`reviewer_id`),
  CONSTRAINT `submittal_reviews_ibfk_1` FOREIGN KEY (`submittal_id`) REFERENCES `project_submittals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `submittal_reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submittal_reviews`
--

LOCK TABLES `submittal_reviews` WRITE;
/*!40000 ALTER TABLE `submittal_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `submittal_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'Lanka Cement','Kamal',NULL,NULL),(2,'City Hardware','Nimal',NULL,NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `takeoff_items`
--

DROP TABLE IF EXISTS `takeoff_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `takeoff_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'Materials',
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `unit` varchar(50) DEFAULT 'units',
  `unit_cost` decimal(15,2) DEFAULT 0.00,
  `total_cost` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `takeoff_items`
--

LOCK TABLES `takeoff_items` WRITE;
/*!40000 ALTER TABLE `takeoff_items` DISABLE KEYS */;
INSERT INTO `takeoff_items` VALUES (1,4,'Materials','Cement, rebar, masonry blocks & roof shingles',1.00,'lot',8000000.00,8000000.00,'2026-10-01 17:08:03'),(2,4,'Labor','Site engineers, foremen & trade crew labor',1.00,'lot',5000000.00,5000000.00,'2026-10-01 17:08:03'),(3,4,'Subcontractors','HVAC, plumbing rough-in, electrical wiring',1.00,'lot',7000000.00,7000000.00,'2026-10-01 17:08:03'),(4,4,'Permits','Planning authority approval & utility connection fees',1.00,'lot',800000.00,800000.00,'2026-10-01 17:08:03'),(5,4,'Contingency','Design variance & unforeseen geotechnical buffer',1.00,'lot',2500000.00,2500000.00,'2026-10-01 17:08:03');
/*!40000 ALTER TABLE `takeoff_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `takeoffs`
--

DROP TABLE IF EXISTS `takeoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `takeoffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `takeoff_no` varchar(50) NOT NULL,
  `project_name` varchar(100) NOT NULL,
  `creator` varchar(100) NOT NULL,
  `status` varchar(50) DEFAULT 'In Progress',
  `img_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `takeoffs`
--

LOCK TABLES `takeoffs` WRITE;
/*!40000 ALTER TABLE `takeoffs` DISABLE KEYS */;
INSERT INTO `takeoffs` VALUES (1,'T-001','Luxury Villa in Kandy','John Doe','Archived','https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=100&h=100&fit=crop','2026-09-25 11:18:21'),(2,'T-002','Colombo Office Complex','Jane Smith','In Progress','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=100&h=100&fit=crop','2026-09-25 11:18:21'),(3,'T-003','Galle Boutique Hotel','John Doe','Completed','https://images.unsplash.com/photo-1566073771259-6a8506099945?w=100&h=100&fit=crop','2026-09-25 11:18:21');
/*!40000 ALTER TABLE `takeoffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `due_date` date NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('To Do','In Progress','Done','Blocked') DEFAULT 'To Do',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_assigned` (`assigned_to`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_due` (`due_date`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,2,NULL,'Grand Residencies',NULL,'Medium','2025-12-28',NULL,NULL,'To Do','2025-12-27 08:56:22'),(2,4,NULL,'Grand Residencies',NULL,'Medium','2025-12-14',NULL,NULL,'To Do','2025-12-27 08:56:48'),(3,26,NULL,'Disconnect existing plumbing lines',NULL,'Medium','2026-01-10',NULL,NULL,'Done','2026-01-04 05:28:20'),(4,26,NULL,'Install new base cabinets',NULL,'Medium','2026-01-24',NULL,NULL,'To Do','2026-01-04 05:28:20'),(5,35,1,'Review and Approve Quote #Q-0012','Verify supplier quotes for electrical switchgear, main cables, and breakers against approved MEP bill of quantities.','High','2026-10-01','2026-10-01 19:03:18',1,'Done','2026-10-01 16:57:24'),(6,30,1,'Finalize selections with client for \'Galle Boutique Hotel\'','Present teak timber slats, honed limestone tiles, and matte brass hardware options to the client design committee.','Medium','2026-09-30',NULL,1,'To Do','2026-10-01 16:57:24'),(7,36,1,'Submit PO for roofing materials - \'Luxury Villa\'','Transmit purchase order to Lanka Steel for zinc-aluminum sheets, insulation foil, and standing seam clips.','Urgent','2026-10-03',NULL,1,'To Do','2026-10-01 16:57:24'),(8,31,1,'Onboard new subcontractor for \'Highway Expansion E01\'','Verify sub-contractor safety manual compliance, contractor insurance, and workers compensation.','Medium','2026-10-01','2026-10-01 18:57:24',1,'Done','2026-10-01 16:57:24'),(9,1,1,'Pour concrete foundation for Block B - \'Skyline Residence\'','Coordinate with ready-mix concrete plant and QA team for cylinder compression test batching.','High','2026-10-05',NULL,1,'To Do','2026-10-01 16:57:24'),(11,35,1,'Submit HVAC ductwork shop drawings','Urgent overdue construction milestone','High','2026-09-28',NULL,NULL,'In Progress','2026-10-01 17:45:18'),(12,4,1,'Install reinforcement mesh on second floor slab','Urgent overdue construction milestone','High','2026-09-28',NULL,NULL,'In Progress','2026-10-01 17:45:18'),(13,37,1,'Execute waterproofing membrane flood test','Urgent overdue construction milestone','High','2026-09-28',NULL,NULL,'In Progress','2026-10-01 17:45:18'),(14,1,1,'Deliver structural steel connection certifications','Urgent overdue construction milestone','High','2026-09-28',NULL,NULL,'In Progress','2026-10-01 17:45:18'),(15,4,1,'Foundation Pouring','Inspect and pour foundation footings','Urgent','2026-10-01','2026-10-01 21:15:18',NULL,'Done','2026-09-30 17:45:18');
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `time_cards`
--

DROP TABLE IF EXISTS `time_cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `time_cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `work_date` date NOT NULL DEFAULT curdate(),
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `break_minutes` int(11) DEFAULT 0,
  `total_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hourly_rate` decimal(10,2) DEFAULT 0.00,
  `work_notes` text DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `status` enum('On-Site','Completed') DEFAULT 'On-Site',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tc_user` (`user_id`),
  KEY `fk_tc_project` (`project_id`),
  KEY `fk_tc_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_tc_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `time_cards_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `time_cards_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `time_cards`
--

LOCK TABLES `time_cards` WRITE;
/*!40000 ALTER TABLE `time_cards` DISABLE KEYS */;
INSERT INTO `time_cards` VALUES (1,1,3,'2026-10-01','2025-12-21 15:21:51','2025-12-21 15:41:43',0,0.32,0.00,NULL,'Approved','Completed',1,'2026-10-01 15:34:10','2026-10-01 15:31:38'),(3,1,3,'2026-10-01','2025-12-21 15:22:52','2025-12-21 15:41:47',0,0.19,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(5,1,3,'2026-10-01','2025-12-21 15:41:56','2026-01-04 19:19:43',0,7.00,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(6,1,1,'2026-10-01','2025-12-26 20:05:03','2025-12-26 20:05:10',0,0.00,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(7,11,3,'2026-10-01','2026-01-04 19:21:25','2026-01-04 19:33:10',0,0.00,0.00,NULL,'Approved','Completed',1,'2026-10-01 15:36:16','2026-10-01 15:31:38'),(8,16,3,'2026-10-01','2026-01-04 19:27:37','2026-01-04 19:33:14',0,0.00,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(9,12,3,'2026-10-01','2026-01-04 19:33:24','2026-01-04 19:33:28',0,0.00,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(10,4,3,'2026-10-01','2026-01-04 22:42:55','2026-01-04 22:43:00',0,0.00,0.00,NULL,'Pending','Completed',NULL,NULL,'2026-10-01 15:31:38'),(11,4,12,'2024-10-28','2024-10-28 08:00:00','2024-10-28 17:00:00',30,8.50,2500.00,'Supervised concrete pump pouring on basement slab. Conducted morning safety toolbox talk.','Approved','Completed',1,'2026-10-01 15:31:39','2026-10-01 15:31:39'),(12,29,13,'2024-10-28','2024-10-28 08:00:00','2024-10-28 16:30:00',30,8.00,2400.00,'MEP duct rough-in inspection and electrical conduit routing on 4th floor.','Pending','Completed',1,'2026-10-01 15:31:39','2026-10-01 15:31:39'),(13,30,14,'2024-10-28','2024-10-28 07:30:00','2024-10-28 17:00:00',30,9.00,2200.00,'Extended overtime shift for waterproofing application before evening rain.','Rejected','Completed',1,'2026-10-01 15:31:39','2026-10-01 15:31:39'),(14,31,15,'2024-10-27','2024-10-27 08:00:00','2024-10-27 16:30:00',30,8.00,2600.00,'Earthwork grading and subbase compaction density check for Chainage 12+400.','Approved','Completed',1,'2026-10-01 15:31:39','2026-10-01 15:31:39'),(17,31,2,'2026-10-01','2026-10-01 23:39:21','2026-10-01 23:42:03',0,0.03,55.00,'','Pending','Completed',NULL,NULL,'2026-10-01 18:09:21');
/*!40000 ALTER TABLE `time_cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `time_off_requests`
--

DROP TABLE IF EXISTS `time_off_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `time_off_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `time_off_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `time_off_requests`
--

LOCK TABLES `time_off_requests` WRITE;
/*!40000 ALTER TABLE `time_off_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `time_off_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `role` enum('Admin','Project Manager','Foreman','Client') NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT 35.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive','Invited') DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Admin','admin@buildnexus.com','+94 77 123 4567','password123','123 Main Street, Colombo 03','Admin',65.00,'2025-12-20 06:01:58','Active'),(2,'Dilshan Perera','pm@buildnexus.com','+94 71 234 5678','password123','45 Galle Road, Mount Lavinia','Project Manager',55.00,'2025-12-20 06:01:58','Active'),(3,'Sunil Gamage','foreman@buildnexus.com','+94 76 345 6789','$2y$10$JPVppMh28pZOZpb4871Dx.0jaycWGeMW40HnzOI0h7Fb7u5Sggvta','88 Kandy Road, Kelaniya','Foreman',45.00,'2025-12-20 06:01:58','Active'),(4,'Mrs. Silva','client@buildnexus.com','+94 70 456 7890','$2y$10$622URQzQU96X7rDvbPavFuUs3lWPjtzfj8yEHbryy33RlWZ7aRQgy','12 Flower Road, Colombo 07','Client',35.00,'2025-12-20 06:01:58','Active'),(5,'Bema','test@buildnexus.com','0718916633','$2y$10$0/docveO4gkxMclZI.gZ2OoBLOehf7eZ2FPeQFgPxyEbbgK5XJ30q','409/3,jaela road, Gampaha','Client',35.00,'2025-12-20 06:25:42','Inactive'),(6,'test1','test1@gmail.com','0712345678','$2y$10$7LwJ2tRf/rPBb3FdDI2aWe4y8j2k6UHQcdhMF2lZFNyTcwiC2Wkq6','No1, Kirillawala, Gampaha','Foreman',45.00,'2025-12-23 07:01:45','Active'),(7,'Aruna Perera','aruna@buildnexus.com','+94 77 555 1234','$2y$10$5gVFqYuJbnA2f3Ocy50XdOZYuNFTa7RDkDNPs0jsv2NSH9xjwibRW','45 Galle Rd, Colombo 03','Admin',65.00,'2026-01-04 05:27:38','Active'),(8,'Kaveen Silva','kaveen@buildnexus.com','+94 71 555 5678','$2y$10$5gVFqYuJbnA2f3Ocy50XdOZYuNFTa7RDkDNPs0jsv2NSH9xjwibRW','88 Kandy Rd, Kelaniya','Project Manager',55.00,'2026-01-04 05:27:38','Active'),(9,'Mahesh Gamage','mahesh@buildnexus.com','+94 76 555 9012','$2y$10$5gVFqYuJbnA2f3Ocy50XdOZYuNFTa7RDkDNPs0jsv2NSH9xjwibRW','12 Flower Rd, Colombo 07','Foreman',45.00,'2026-01-04 05:27:38','Active'),(10,'John Doe','john.doe@email.com','+94 70 555 3456','$2y$10$HuWRRzzyJxkRAX4e5rSBBensfZdQZJHwr4WPKgoJyATzf5fuKMwBW','Highland Villa, Kandy','Client',35.00,'2026-01-04 05:27:38','Active'),(11,'Prashangi','bemashaprashangi@gmail.com',NULL,'$2y$10$8yk6expRVzY8R8PyPHDItORjJ8sWTChLfyn4JAoPglQyUqzb2MTqK',NULL,'Project Manager',55.00,'2026-09-25 10:49:45','Active'),(12,'Sunil Perera','sunil.perera@buildnexus.com',NULL,'$2y$10$ty3M9el2f4iY0Ut/N0yJl.3x.2ScxyUxNcBlBA/MVAxVs7J6fOx/a',NULL,'Foreman',45.00,'2026-10-01 15:23:35','Active'),(13,'Kamal Dias','kamal.dias@buildnexus.com',NULL,'$2y$10$wjgJSnfpwLeqVTZlsHOexul.7fhZiy51GYMWKk/j4TG9Ci/2dCEZq',NULL,'Foreman',45.00,'2026-10-01 15:23:35','Active'),(14,'Ravi Fernando','ravi.fernando@buildnexus.com',NULL,'$2y$10$UJ2TP3WVaZ60YR2jAJkFLOzKFEDCpaNvt3ZcjftFXx1MQLHHU6dU6',NULL,'Foreman',45.00,'2026-10-01 15:23:35','Active'),(15,'Anusha Kumari','anusha.kumari@buildnexus.com',NULL,'$2y$10$J.UoNFVQmhPEpE/rXbsHKu4G07o760g6hb9Sn.QpMQDddDK0X.yfu',NULL,'Foreman',45.00,'2026-10-01 15:31:39','Active'),(16,'Mr. Silva','mr.silva@buildnexus.com',NULL,'$2y$10$PR/RbLDzknCVfTZzlyYZwe6H6eGBhy7/0AY24U1SoHB/gRbP4ruQK',NULL,'Client',35.00,'2026-10-02 14:05:42','Active');
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

-- Dump completed on 2026-10-02 20:33:34
