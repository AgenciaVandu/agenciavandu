-- MySQL dump 10.13  Distrib 9.7.1, for macos14.8 (x86_64)
--
-- Host: 127.0.0.1    Database: agenciavandu
-- ------------------------------------------------------
-- Server version	9.7.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '8a60dcec-9527-11f1-bcfb-2207bfaa1773:1-5789';

--
-- Table structure for table `cliente_constancias`
--

DROP TABLE IF EXISTS `cliente_constancias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_constancias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `dropbox_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` bigint unsigned NOT NULL DEFAULT '0',
  `emitida_el` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_constancias_cliente_id_foreign` (`cliente_id`),
  KEY `cliente_constancias_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `cliente_constancias_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cliente_constancias_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente_constancias`
--

LOCK TABLES `cliente_constancias` WRITE;
/*!40000 ALTER TABLE `cliente_constancias` DISABLE KEYS */;
/*!40000 ALTER TABLE `cliente_constancias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cliente_mensajes`
--

DROP TABLE IF EXISTS `cliente_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_mensajes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `servicio` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datos` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_mensajes_cliente_id_foreign` (`cliente_id`),
  KEY `cliente_mensajes_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `cliente_mensajes_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cliente_mensajes_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente_mensajes`
--

LOCK TABLES `cliente_mensajes` WRITE;
/*!40000 ALTER TABLE `cliente_mensajes` DISABLE KEYS */;
/*!40000 ALTER TABLE `cliente_mensajes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cliente_razones`
--

DROP TABLE IF EXISTS `cliente_razones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cliente_razones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `razon_social` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen_fiscal` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cp_fiscal` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uso_cfdi` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_factura` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_razones_cuenta_id_foreign` (`cuenta_id`),
  KEY `cliente_razones_cliente_id_foreign` (`cliente_id`),
  CONSTRAINT `cliente_razones_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cliente_razones_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente_razones`
--

LOCK TABLES `cliente_razones` WRITE;
/*!40000 ALTER TABLE `cliente_razones` DISABLE KEYS */;
/*!40000 ALTER TABLE `cliente_razones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empresa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `razon_social` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regimen_fiscal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cp_fiscal` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uso_cfdi` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_pago` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dias_credito` smallint unsigned DEFAULT NULL,
  `email_factura` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `nuevo` tinyint(1) NOT NULL DEFAULT '0',
  `interes` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_at` timestamp NULL DEFAULT NULL,
  `redes_token` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_token` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_visto_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ejemplo` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `clientes_redes_token_unique` (`redes_token`),
  UNIQUE KEY `clientes_portal_token_unique` (`portal_token`),
  KEY `clientes_nombre_index` (`nombre`),
  KEY `clientes_empresa_index` (`empresa`),
  KEY `clientes_nuevo_index` (`nuevo`),
  KEY `clientes_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `clientes_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (1,1,'Jessica Estefani','Grupo C & S',NULL,'999 222 6934',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'manual',0,NULL,NULL,NULL,NULL,NULL,'2026-10-08 22:37:39','2026-10-08 22:37:39',0);
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `correo_plantillas`
--

DROP TABLE IF EXISTS `correo_plantillas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `correo_plantillas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `clave` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `propia` tinyint(1) NOT NULL DEFAULT '0',
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `para` json DEFAULT NULL,
  `asunto` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuerpo` text COLLATE utf8mb4_unicode_ci,
  `boton` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resumen` tinyint(1) NOT NULL DEFAULT '0',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo_plantillas_cuenta_id_clave_unique` (`cuenta_id`,`clave`),
  KEY `correo_plantillas_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `correo_plantillas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `correo_plantillas`
--

LOCK TABLES `correo_plantillas` WRITE;
/*!40000 ALTER TABLE `correo_plantillas` DISABLE KEYS */;
/*!40000 ALTER TABLE `correo_plantillas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `correos`
--

DROP TABLE IF EXISTS `correos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `correos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned DEFAULT NULL,
  `plantilla` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `para` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cc` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asunto` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuerpo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjuntos` json DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enviado',
  `error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `correos_presupuesto_id_foreign` (`presupuesto_id`),
  KEY `correos_proyecto_id_foreign` (`proyecto_id`),
  KEY `correos_cliente_id_created_at_index` (`cliente_id`,`created_at`),
  KEY `correos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `correos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `correos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `correos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `correos_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `correos`
--

LOCK TABLES `correos` WRITE;
/*!40000 ALTER TABLE `correos` DISABLE KEYS */;
/*!40000 ALTER TABLE `correos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizacions`
--

DROP TABLE IF EXISTS `cotizacions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizacions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cotizacions_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `cotizacions_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizacions`
--

LOCK TABLES `cotizacions` WRITE;
/*!40000 ALTER TABLE `cotizacions` DISABLE KEYS */;
/*!40000 ALTER TABLE `cotizacions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credito_letras`
--

DROP TABLE IF EXISTS `credito_letras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credito_letras` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `credito_id` bigint unsigned NOT NULL,
  `numero` smallint unsigned NOT NULL,
  `vence_el` date NOT NULL,
  `capital` decimal(12,2) NOT NULL,
  `interes` decimal(12,2) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `pago_id` bigint unsigned DEFAULT NULL,
  `pagada_el` date DEFAULT NULL,
  `pagado` decimal(12,2) DEFAULT NULL,
  `moratorio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `descuento` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adelantada` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `credito_letras_credito_id_numero_unique` (`credito_id`,`numero`),
  KEY `credito_letras_pago_id_foreign` (`pago_id`),
  KEY `credito_letras_credito_id_pagada_el_index` (`credito_id`,`pagada_el`),
  CONSTRAINT `credito_letras_credito_id_foreign` FOREIGN KEY (`credito_id`) REFERENCES `creditos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credito_letras_pago_id_foreign` FOREIGN KEY (`pago_id`) REFERENCES `credito_pagos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credito_letras`
--

LOCK TABLES `credito_letras` WRITE;
/*!40000 ALTER TABLE `credito_letras` DISABLE KEYS */;
/*!40000 ALTER TABLE `credito_letras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credito_pagos`
--

DROP TABLE IF EXISTS `credito_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credito_pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `credito_id` bigint unsigned NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credito_pagos_credito_id_foreign` (`credito_id`),
  KEY `credito_pagos_user_id_foreign` (`user_id`),
  CONSTRAINT `credito_pagos_credito_id_foreign` FOREIGN KEY (`credito_id`) REFERENCES `creditos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credito_pagos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credito_pagos`
--

LOCK TABLES `credito_pagos` WRITE;
/*!40000 ALTER TABLE `credito_pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `credito_pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `creditos`
--

DROP TABLE IF EXISTS `creditos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `creditos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `unidad_id` bigint unsigned NOT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `vendedor_id` bigint unsigned DEFAULT NULL,
  `sucursal_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `precio` decimal(12,2) NOT NULL,
  `enganche` decimal(12,2) NOT NULL DEFAULT '0.00',
  `financiado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tasa` decimal(6,3) NOT NULL DEFAULT '0.000',
  `plazo` smallint unsigned NOT NULL DEFAULT '0',
  `mensualidad` decimal(12,2) NOT NULL DEFAULT '0.00',
  `moratorio_modo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'porcentaje',
  `moratorio_valor` decimal(10,2) NOT NULL DEFAULT '0.00',
  `dias_gracia` smallint unsigned NOT NULL DEFAULT '0',
  `descuento_adelanto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `financiera` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creditos_unidad_id_foreign` (`unidad_id`),
  KEY `creditos_cliente_id_foreign` (`cliente_id`),
  KEY `creditos_vendedor_id_foreign` (`vendedor_id`),
  KEY `creditos_sucursal_id_foreign` (`sucursal_id`),
  KEY `creditos_cuenta_id_estado_index` (`cuenta_id`,`estado`),
  CONSTRAINT `creditos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `creditos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `creditos_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `creditos_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `creditos_vendedor_id_foreign` FOREIGN KEY (`vendedor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `creditos`
--

LOCK TABLES `creditos` WRITE;
/*!40000 ALTER TABLE `creditos` DISABLE KEYS */;
/*!40000 ALTER TABLE `creditos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuentas`
--

DROP TABLE IF EXISTS `cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `giro` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agencia',
  `dominio` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `ajustes` json DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuentas_dominio_unique` (`dominio`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuentas`
--

LOCK TABLES `cuentas` WRITE;
/*!40000 ALTER TABLE `cuentas` DISABLE KEYS */;
INSERT INTO `cuentas` VALUES (1,'Agencia Vandu','agencia',NULL,1,NULL,NULL,'2026-10-19 06:03:47','2026-10-19 06:03:47'),(2,'Test venta','comercio',NULL,1,'{\"emisor\": {\"email\": \"vandumx@gmail.com\", \"nombre\": \"alvar\", \"telefono\": \"\"}, \"modulos\": [\"pos\"], \"sucursales_max\": 1}',NULL,'2026-10-19 06:19:01','2026-10-19 06:19:16');
/*!40000 ALTER TABLE `cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `embudo_etapas`
--

DROP TABLE IF EXISTS `embudo_etapas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `embudo_etapas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `probabilidad` tinyint unsigned NOT NULL DEFAULT '0',
  `tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `embudo_etapas_cuenta_id_foreign` (`cuenta_id`),
  CONSTRAINT `embudo_etapas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `embudo_etapas`
--

LOCK TABLES `embudo_etapas` WRITE;
/*!40000 ALTER TABLE `embudo_etapas` DISABLE KEYS */;
/*!40000 ALTER TABLE `embudo_etapas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `galeria_secciones`
--

DROP TABLE IF EXISTS `galeria_secciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `galeria_secciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `carpeta` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int unsigned NOT NULL DEFAULT '0',
  `zip_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `galeria_secciones_cuenta_id_foreign` (`cuenta_id`),
  KEY `galeria_secciones_proyecto_id_orden_index` (`proyecto_id`,`orden`),
  CONSTRAINT `galeria_secciones_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `galeria_secciones_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `galeria_secciones`
--

LOCK TABLES `galeria_secciones` WRITE;
/*!40000 ALTER TABLE `galeria_secciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `galeria_secciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `giros`
--

DROP TABLE IF EXISTS `giros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `giros` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bi-briefcase',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modulos` json DEFAULT NULL,
  `roles` json DEFAULT NULL,
  `proyectos` json DEFAULT NULL,
  `costeo` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `giros_clave_unique` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `giros`
--

LOCK TABLES `giros` WRITE;
/*!40000 ALTER TABLE `giros` DISABLE KEYS */;
/*!40000 ALTER TABLE `giros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `integraciones`
--

DROP TABLE IF EXISTS `integraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `integraciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proveedor` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuenta` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datos` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `integraciones_cuenta_id_proveedor_unique` (`cuenta_id`,`proveedor`),
  KEY `integraciones_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `integraciones_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integraciones`
--

LOCK TABLES `integraciones` WRITE;
/*!40000 ALTER TABLE `integraciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `integraciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_users_table',1),(2,'2014_10_12_100000_create_password_reset_tokens_table',1),(3,'2019_08_19_000000_create_failed_jobs_table',1),(4,'2019_12_14_000001_create_personal_access_tokens_table',1),(5,'2026_03_13_012458_create_cotizacions_table',1),(6,'2026_10_08_000001_create_clientes_table',2),(7,'2026_10_08_000002_create_presupuestos_table',2),(8,'2026_10_09_000001_create_proyectos_tables',3),(9,'2026_10_09_000002_add_aceptada_el_to_presupuestos',3),(10,'2026_10_09_000003_add_titulo_to_presupuesto_conceptos',4),(11,'2026_10_10_000001_add_costeo_to_presupuestos',5),(12,'2026_10_10_000002_add_observaciones_to_presupuestos',5),(13,'2026_10_11_000001_add_facturacion_to_clientes',5),(14,'2026_10_11_000002_add_metodo_pago',5),(15,'2026_10_11_000003_add_credito_to_proyectos',5),(16,'2026_10_12_000001_create_correos_table',5),(17,'2026_10_12_000002_dropbox',5),(18,'2026_10_13_000001_create_push_suscripciones',5),(19,'2026_10_13_000002_add_contactos_sitio',5),(20,'2026_10_13_000003_create_correo_plantillas',5),(21,'2026_10_13_000004_aceptacion_en_linea',5),(22,'2026_10_14_000001_create_redes',5),(23,'2026_10_14_000002_create_proyecto_tipos',5),(24,'2026_10_14_000003_dias_en_etapas',5),(25,'2026_10_15_000001_create_equipo_y_tareas',5),(26,'2026_10_16_000001_create_cuentas',6),(27,'2026_10_17_000001_aprobar_tareas',6),(28,'2026_10_17_000002_galeria_secciones',6),(29,'2026_10_17_000003_portal_cliente',6),(30,'2026_10_18_000001_create_proveedores',7),(31,'2026_10_19_000001_costeo_por_componentes',7),(32,'2026_10_20_000001_create_ventas',7),(33,'2026_10_21_000001_cotizaciones_para_prospectos',7),(34,'2026_10_22_000001_create_presupuesto_facturas',7),(35,'2026_10_23_000001_complementos_de_pago',7),(36,'2026_10_24_000001_nota_de_venta',7),(37,'2026_10_25_000001_razones_sociales',7),(38,'2026_10_25_000002_solicitudes_archivos',7),(39,'2026_10_25_000003_cancelaciones',7),(40,'2026_10_26_000001_create_inventario',7),(41,'2026_10_27_000001_giros_y_dominios',7),(42,'2026_10_27_000002_productos_ejemplo',7),(43,'2026_10_28_000001_autos_y_cobranza',7),(44,'2026_10_28_000002_autos_ejemplo',7),(45,'2026_10_29_000001_create_pos_tables',7);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `evento` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cuerpo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entregadas` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notificaciones_created_at_index` (`created_at`),
  KEY `notificaciones_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `notificaciones_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orden_compra_archivos`
--

DROP TABLE IF EXISTS `orden_compra_archivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_compra_archivos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `orden_compra_id` bigint unsigned NOT NULL,
  `tipo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(600) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `dropbox_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_compra_archivos_cuenta_id_foreign` (`cuenta_id`),
  KEY `orden_compra_archivos_orden_compra_id_foreign` (`orden_compra_id`),
  KEY `orden_compra_archivos_user_id_foreign` (`user_id`),
  CONSTRAINT `orden_compra_archivos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orden_compra_archivos_orden_compra_id_foreign` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orden_compra_archivos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orden_compra_archivos`
--

LOCK TABLES `orden_compra_archivos` WRITE;
/*!40000 ALTER TABLE `orden_compra_archivos` DISABLE KEYS */;
/*!40000 ALTER TABLE `orden_compra_archivos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orden_compra_comentarios`
--

DROP TABLE IF EXISTS `orden_compra_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_compra_comentarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `orden_compra_id` bigint unsigned NOT NULL,
  `autor` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orden_compra_comentarios_cuenta_id_foreign` (`cuenta_id`),
  KEY `orden_compra_comentarios_orden_compra_id_foreign` (`orden_compra_id`),
  KEY `orden_compra_comentarios_user_id_foreign` (`user_id`),
  CONSTRAINT `orden_compra_comentarios_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orden_compra_comentarios_orden_compra_id_foreign` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `orden_compra_comentarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orden_compra_comentarios`
--

LOCK TABLES `orden_compra_comentarios` WRITE;
/*!40000 ALTER TABLE `orden_compra_comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `orden_compra_comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orden_compra_conceptos`
--

DROP TABLE IF EXISTS `orden_compra_conceptos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orden_compra_conceptos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `orden_compra_id` bigint unsigned NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL DEFAULT '1.00',
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `orden_compra_conceptos_orden_compra_id_foreign` (`orden_compra_id`),
  CONSTRAINT `orden_compra_conceptos_orden_compra_id_foreign` FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orden_compra_conceptos`
--

LOCK TABLES `orden_compra_conceptos` WRITE;
/*!40000 ALTER TABLE `orden_compra_conceptos` DISABLE KEYS */;
/*!40000 ALTER TABLE `orden_compra_conceptos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_compra`
--

DROP TABLE IF EXISTS `ordenes_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordenes_compra` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proveedor_id` bigint unsigned NOT NULL,
  `proyecto_id` bigint unsigned DEFAULT NULL,
  `creada_por` bigint unsigned DEFAULT NULL,
  `folio` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicaciones` text COLLATE utf8mb4_unicode_ci,
  `fecha` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `lugar_entrega` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `con_iva` tinyint(1) NOT NULL DEFAULT '1',
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT '16.00',
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `cancelada_at` timestamp NULL DEFAULT NULL,
  `motivo_cancelacion` text COLLATE utf8mb4_unicode_ci,
  `enviada_at` timestamp NULL DEFAULT NULL,
  `vista_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ordenes_compra_cuenta_id_folio_unique` (`cuenta_id`,`folio`),
  KEY `ordenes_compra_proveedor_id_foreign` (`proveedor_id`),
  KEY `ordenes_compra_proyecto_id_foreign` (`proyecto_id`),
  KEY `ordenes_compra_creada_por_foreign` (`creada_por`),
  CONSTRAINT `ordenes_compra_creada_por_foreign` FOREIGN KEY (`creada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ordenes_compra_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ordenes_compra_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ordenes_compra_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_compra`
--

LOCK TABLES `ordenes_compra` WRITE;
/*!40000 ALTER TABLE `ordenes_compra` DISABLE KEYS */;
/*!40000 ALTER TABLE `ordenes_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_venta_detalles`
--

DROP TABLE IF EXISTS `pos_venta_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_venta_detalles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned NOT NULL,
  `pos_venta_id` bigint unsigned NOT NULL,
  `producto_id` bigint unsigned NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `precio` decimal(14,2) NOT NULL,
  `importe` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pos_venta_detalles_cuenta_id_foreign` (`cuenta_id`),
  KEY `pos_venta_detalles_pos_venta_id_foreign` (`pos_venta_id`),
  KEY `pos_venta_detalles_producto_id_foreign` (`producto_id`),
  CONSTRAINT `pos_venta_detalles_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`),
  CONSTRAINT `pos_venta_detalles_pos_venta_id_foreign` FOREIGN KEY (`pos_venta_id`) REFERENCES `pos_ventas` (`id`),
  CONSTRAINT `pos_venta_detalles_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_venta_detalles`
--

LOCK TABLES `pos_venta_detalles` WRITE;
/*!40000 ALTER TABLE `pos_venta_detalles` DISABLE KEYS */;
/*!40000 ALTER TABLE `pos_venta_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_ventas`
--

DROP TABLE IF EXISTS `pos_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_ventas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `folio` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `solicitud_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pagada',
  `subtotal` decimal(14,2) NOT NULL,
  `descuento` decimal(14,2) NOT NULL DEFAULT '0.00',
  `impuestos` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total` decimal(14,2) NOT NULL,
  `forma_pago` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recibido` decimal(14,2) DEFAULT NULL,
  `cambio` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cancelada_at` timestamp NULL DEFAULT NULL,
  `cancelada_por` bigint unsigned DEFAULT NULL,
  `motivo_cancelacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_ventas_cuenta_id_folio_unique` (`cuenta_id`,`folio`),
  UNIQUE KEY `pos_ventas_cuenta_id_solicitud_id_unique` (`cuenta_id`,`solicitud_id`),
  KEY `pos_ventas_user_id_foreign` (`user_id`),
  KEY `pos_ventas_cancelada_por_foreign` (`cancelada_por`),
  CONSTRAINT `pos_ventas_cancelada_por_foreign` FOREIGN KEY (`cancelada_por`) REFERENCES `users` (`id`),
  CONSTRAINT `pos_ventas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`),
  CONSTRAINT `pos_ventas_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_ventas`
--

LOCK TABLES `pos_ventas` WRITE;
/*!40000 ALTER TABLE `pos_ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `pos_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_codigos`
--

DROP TABLE IF EXISTS `presupuesto_codigos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_codigos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned NOT NULL,
  `codigo_hash` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_at` timestamp NOT NULL,
  `usado_at` timestamp NULL DEFAULT NULL,
  `intentos` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_codigos_presupuesto_id_expira_at_index` (`presupuesto_id`,`expira_at`),
  KEY `presupuesto_codigos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `presupuesto_codigos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_codigos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_codigos`
--

LOCK TABLES `presupuesto_codigos` WRITE;
/*!40000 ALTER TABLE `presupuesto_codigos` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuesto_codigos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_conceptos`
--

DROP TABLE IF EXISTS `presupuesto_conceptos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_conceptos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned NOT NULL,
  `producto_id` bigint unsigned DEFAULT NULL,
  `titulo` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costos` json DEFAULT NULL,
  `costo_proveedor` decimal(12,2) DEFAULT NULL,
  `gasolina` decimal(12,2) DEFAULT NULL,
  `utilidad` decimal(12,2) DEFAULT NULL,
  `utilidad_modo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pct',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_conceptos_presupuesto_id_foreign` (`presupuesto_id`),
  KEY `presupuesto_conceptos_cuenta_id_index` (`cuenta_id`),
  KEY `presupuesto_conceptos_producto_id_foreign` (`producto_id`),
  CONSTRAINT `presupuesto_conceptos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_conceptos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_conceptos_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_conceptos`
--

LOCK TABLES `presupuesto_conceptos` WRITE;
/*!40000 ALTER TABLE `presupuesto_conceptos` DISABLE KEYS */;
INSERT INTO `presupuesto_conceptos` VALUES (5,1,1,NULL,NULL,'Bardado en Camisas Logotipo C&S: Icono amarillo, letras negras',15.00,147.50,NULL,NULL,NULL,NULL,'pct',0,'2026-10-09 02:19:02','2026-10-09 02:19:02'),(6,1,1,NULL,NULL,'Bordado en playeras Logotipo C&S: Icono amarilo, letras blancas',9.00,147.50,NULL,NULL,NULL,NULL,'pct',1,'2026-10-09 02:19:02','2026-10-09 02:19:02');
/*!40000 ALTER TABLE `presupuesto_conceptos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_eventos`
--

DROP TABLE IF EXISTS `presupuesto_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detalle` text COLLATE utf8mb4_unicode_ci,
  `datos` json DEFAULT NULL,
  `visible_cliente` tinyint(1) NOT NULL DEFAULT '1',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_eventos_presupuesto_id_id_index` (`presupuesto_id`,`id`),
  KEY `presupuesto_eventos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `presupuesto_eventos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_eventos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_eventos`
--

LOCK TABLES `presupuesto_eventos` WRITE;
/*!40000 ALTER TABLE `presupuesto_eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuesto_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_facturas`
--

DROP TABLE IF EXISTS `presupuesto_facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuesto_facturas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'factura',
  `lote` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pago_id` bigint unsigned DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` bigint unsigned NOT NULL DEFAULT '0',
  `enviada_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `presupuesto_facturas_cuenta_id_foreign` (`cuenta_id`),
  KEY `presupuesto_facturas_pago_id_foreign` (`pago_id`),
  KEY `presupuesto_facturas_presupuesto_id_tipo_index` (`presupuesto_id`,`tipo`),
  CONSTRAINT `presupuesto_facturas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuesto_facturas_pago_id_foreign` FOREIGN KEY (`pago_id`) REFERENCES `proyecto_pagos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `presupuesto_facturas_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_facturas`
--

LOCK TABLES `presupuesto_facturas` WRITE;
/*!40000 ALTER TABLE `presupuesto_facturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `presupuesto_facturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuestos`
--

DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `presupuestos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `razon_id` bigint unsigned DEFAULT NULL,
  `prospecto_id` bigint unsigned DEFAULT NULL,
  `folio` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_empresa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha` date NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Presupuesto de servicios',
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emisor_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emisor_telefono` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emisor_sitio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emisor_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modo_iva` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mas_iva',
  `metodo_sat` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprobante` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'factura',
  `nota_folio` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT '16.00',
  `consideraciones` json DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `mostrar_pago` tinyint(1) NOT NULL DEFAULT '1',
  `pago_intro` text COLLATE utf8mb4_unicode_ci,
  `banco` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clabe` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `beneficiario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nota_comprobante` text COLLATE utf8mb4_unicode_ci,
  `nota_factura` text COLLATE utf8mb4_unicode_ci,
  `vigente_hasta` datetime NOT NULL,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `cancelada_at` timestamp NULL DEFAULT NULL,
  `motivo_cancelacion` text COLLATE utf8mb4_unicode_ci,
  `aceptada_el` date DEFAULT NULL,
  `vistas` int unsigned NOT NULL DEFAULT '0',
  `ultima_vista_at` timestamp NULL DEFAULT NULL,
  `notas_internas` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `presupuestos_token_unique` (`token`),
  UNIQUE KEY `presupuestos_cuenta_id_folio_unique` (`cuenta_id`,`folio`),
  KEY `presupuestos_cliente_id_foreign` (`cliente_id`),
  KEY `presupuestos_estado_vigente_hasta_index` (`estado`,`vigente_hasta`),
  KEY `presupuestos_cuenta_id_index` (`cuenta_id`),
  KEY `presupuestos_prospecto_id_foreign` (`prospecto_id`),
  KEY `presupuestos_razon_id_foreign` (`razon_id`),
  CONSTRAINT `presupuestos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuestos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `presupuestos_prospecto_id_foreign` FOREIGN KEY (`prospecto_id`) REFERENCES `prospectos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `presupuestos_razon_id_foreign` FOREIGN KEY (`razon_id`) REFERENCES `cliente_razones` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuestos`
--

LOCK TABLES `presupuestos` WRITE;
/*!40000 ALTER TABLE `presupuestos` DISABLE KEYS */;
INSERT INTO `presupuestos` VALUES (1,1,1,NULL,NULL,'CT-0001','L5mYh4P54AvQhUBSK36icuOLkPuCh3a7','Jessica Estefani','Grupo C & S','2026-10-08','Presupuesto de servicios',NULL,'Alvar Buenfil','(999) 146 0310','agenciavandu.com','ab@agenciavandu.com','mas_iva',NULL,'factura',NULL,16.00,'[{\"items\": [\"5 días hábiles después de la recepción del pago.\"], \"titulo\": \"Tiempos de entrega\"}, {\"items\": [\"Pago por adelantado.\"], \"titulo\": \"Condiciones de pago\"}]',NULL,1,'Para realizar el pago correspondiente, favor de utilizar los siguientes datos:','Santander','014910605738096757','Alvar Buenfil Vadillo','Una vez realizado el pago, favor de enviar el comprobante a proyectos@agenciavandu.com o por WhatsApp al 999 146 0310 para su confirmación.','Este servicio incluye factura. Favor de proporcionar sus datos fiscales (RFC, razón social y uso de CFDI) para su emisión.','2026-10-16 05:59:00','enviada',NULL,NULL,NULL,0,NULL,NULL,'2026-10-08 22:46:31','2026-10-09 02:19:02');
/*!40000 ALTER TABLE `presupuestos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_categorias`
--

DROP TABLE IF EXISTS `producto_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_categorias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_categorias_cuenta_id_nombre_unique` (`cuenta_id`,`nombre`),
  CONSTRAINT `producto_categorias_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_categorias`
--

LOCK TABLES `producto_categorias` WRITE;
/*!40000 ALTER TABLE `producto_categorias` DISABLE KEYS */;
INSERT INTO `producto_categorias` VALUES (1,2,'Mobiliario',1,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(2,2,'Iluminación',2,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(3,2,'Materiales',3,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(4,2,'Textiles',4,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(5,2,'Herramientas',5,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(6,2,'Servicios',6,'2026-10-19 06:19:01','2026-10-19 06:19:01');
/*!40000 ALTER TABLE `producto_categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_movimientos`
--

DROP TABLE IF EXISTS `producto_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_movimientos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `producto_id` bigint unsigned NOT NULL,
  `tipo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `existencia` decimal(12,2) NOT NULL,
  `presupuesto_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nota` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_movimientos_cuenta_id_foreign` (`cuenta_id`),
  KEY `producto_movimientos_user_id_foreign` (`user_id`),
  KEY `producto_movimientos_producto_id_id_index` (`producto_id`,`id`),
  KEY `producto_movimientos_presupuesto_id_index` (`presupuesto_id`),
  CONSTRAINT `producto_movimientos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `producto_movimientos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `producto_movimientos_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `producto_movimientos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_movimientos`
--

LOCK TABLES `producto_movimientos` WRITE;
/*!40000 ALTER TABLE `producto_movimientos` DISABLE KEYS */;
INSERT INTO `producto_movimientos` VALUES (1,2,1,'entrada',12.00,12.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(2,2,2,'entrada',3.00,3.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(3,2,4,'entrada',25.00,25.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(4,2,5,'entrada',18.00,18.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(5,2,6,'entrada',9.00,9.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(6,2,7,'entrada',150.00,150.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01'),(7,2,8,'entrada',6.00,6.00,NULL,2,'Existencia de ejemplo','2026-10-19 06:19:01');
/*!40000 ALTER TABLE `producto_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_slugs`
--

DROP TABLE IF EXISTS `producto_slugs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `producto_slugs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `producto_id` bigint unsigned NOT NULL,
  `slug` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `producto_slugs_slug_unique` (`slug`),
  KEY `producto_slugs_producto_id_foreign` (`producto_id`),
  CONSTRAINT `producto_slugs_producto_id_foreign` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_slugs`
--

LOCK TABLES `producto_slugs` WRITE;
/*!40000 ALTER TABLE `producto_slugs` DISABLE KEYS */;
/*!40000 ALTER TABLE `producto_slugs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `proveedor_id` bigint unsigned DEFAULT NULL,
  `clave` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pieza',
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo` decimal(12,2) DEFAULT NULL,
  `controla_existencia` tinyint(1) NOT NULL DEFAULT '1',
  `existencia` decimal(12,2) NOT NULL DEFAULT '0.00',
  `minimo` decimal(12,2) DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publico` tinyint(1) NOT NULL DEFAULT '1',
  `mostrar_precio` tinyint(1) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `ejemplo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `codigo_barras` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `productos_slug_unique` (`slug`),
  UNIQUE KEY `productos_cuenta_id_clave_unique` (`cuenta_id`,`clave`),
  UNIQUE KEY `productos_cuenta_id_codigo_barras_unique` (`cuenta_id`,`codigo_barras`),
  KEY `productos_categoria_id_foreign` (`categoria_id`),
  KEY `productos_proveedor_id_foreign` (`proveedor_id`),
  KEY `productos_cuenta_id_activo_index` (`cuenta_id`,`activo`),
  CONSTRAINT `productos_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `producto_categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `productos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `productos_proveedor_id_foreign` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,2,1,NULL,'EJ-001','Silla ejecutiva ergonómica','silla-ejecutiva-ergonomica','Respaldo de malla, soporte lumbar y ajuste de altura.\nGarantía de 1 año.','pieza',3490.00,2100.00,1,12.00,4.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(2,2,1,NULL,'EJ-002','Escritorio de 120 cm','escritorio-de-120-cm','Cubierta de melamina de 25 mm con pasacables.','pieza',4890.00,3100.00,1,3.00,4.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(3,2,1,NULL,'EJ-003','Archivero metálico de 3 gavetas','archivero-metalico-de-3-gavetas','Lámina de acero con cerradura central.','pieza',3290.00,2200.00,1,0.00,2.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(4,2,2,NULL,'EJ-004','Lámpara LED de escritorio','lampara-led-de-escritorio','Luz cálida y fría, brazo ajustable y puerto USB.','pieza',590.00,320.00,1,25.00,8.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(5,2,3,NULL,'EJ-005','Pintura vinílica blanca 19 L','pintura-vinilica-blanca-19-l','Acabado mate, lavable, rinde hasta 90 m².','cubeta',1890.00,1250.00,1,18.00,5.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(6,2,3,NULL,'EJ-006','Cable eléctrico calibre 12, 100 m','cable-electrico-calibre-12-100-m','Cobre THW-LS, 90 °C.','rollo',1650.00,1100.00,1,9.00,3.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(7,2,4,NULL,'EJ-007','Playera de algodón personalizada','playera-de-algodon-personalizada','Algodón 100 %, estampado a una tinta al frente.','pieza',189.00,95.00,1,150.00,50.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(8,2,5,NULL,'EJ-008','Kit de herramientas de 40 piezas','kit-de-herramientas-de-40-piezas','Desarmadores, llaves, pinzas y estuche rígido.','kit',1290.00,780.00,1,6.00,2.00,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(9,2,6,NULL,'EJ-009','Instalación a domicilio','instalacion-a-domicilio','Entrega, armado e instalación dentro de la ciudad.','servicio',650.00,NULL,0,0.00,NULL,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL),(10,2,6,NULL,'EJ-010','Mantenimiento preventivo','mantenimiento-preventivo','Revisión, limpieza y ajuste en sitio.','servicio',980.00,NULL,0,0.00,NULL,NULL,1,NULL,1,1,'2026-10-19 06:19:01','2026-10-19 06:19:01',NULL);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prospecto_eventos`
--

DROP TABLE IF EXISTS `prospecto_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospecto_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `prospecto_id` bigint unsigned NOT NULL,
  `tipo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `de_etapa_id` bigint unsigned DEFAULT NULL,
  `a_etapa_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci,
  `datos` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospecto_eventos_cuenta_id_foreign` (`cuenta_id`),
  KEY `prospecto_eventos_prospecto_id_foreign` (`prospecto_id`),
  KEY `prospecto_eventos_de_etapa_id_foreign` (`de_etapa_id`),
  KEY `prospecto_eventos_a_etapa_id_foreign` (`a_etapa_id`),
  KEY `prospecto_eventos_user_id_foreign` (`user_id`),
  CONSTRAINT `prospecto_eventos_a_etapa_id_foreign` FOREIGN KEY (`a_etapa_id`) REFERENCES `embudo_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospecto_eventos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospecto_eventos_de_etapa_id_foreign` FOREIGN KEY (`de_etapa_id`) REFERENCES `embudo_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospecto_eventos_prospecto_id_foreign` FOREIGN KEY (`prospecto_id`) REFERENCES `prospectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospecto_eventos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospecto_eventos`
--

LOCK TABLES `prospecto_eventos` WRITE;
/*!40000 ALTER TABLE `prospecto_eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prospecto_eventos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prospectos`
--

DROP TABLE IF EXISTS `prospectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospectos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `empresa` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `interes` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(12,2) DEFAULT NULL,
  `etapa_id` bigint unsigned DEFAULT NULL,
  `responsable_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `presupuesto_id` bigint unsigned DEFAULT NULL,
  `nuevo` tinyint(1) NOT NULL DEFAULT '0',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `motivo_perdida` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_at` timestamp NULL DEFAULT NULL,
  `cerrado_at` timestamp NULL DEFAULT NULL,
  `contacto_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prospectos_etapa_id_foreign` (`etapa_id`),
  KEY `prospectos_responsable_id_foreign` (`responsable_id`),
  KEY `prospectos_cliente_id_foreign` (`cliente_id`),
  KEY `prospectos_presupuesto_id_foreign` (`presupuesto_id`),
  KEY `prospectos_cuenta_id_etapa_id_index` (`cuenta_id`,`etapa_id`),
  CONSTRAINT `prospectos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospectos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prospectos_etapa_id_foreign` FOREIGN KEY (`etapa_id`) REFERENCES `embudo_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospectos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prospectos_responsable_id_foreign` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prospectos`
--

LOCK TABLES `prospectos` WRITE;
/*!40000 ALTER TABLE `prospectos` DISABLE KEYS */;
/*!40000 ALTER TABLE `prospectos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `empresa` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rubro` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `razon_social` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `portal_token` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portal_visto_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proveedores_portal_token_unique` (`portal_token`),
  KEY `proveedores_cuenta_id_empresa_index` (`cuenta_id`,`empresa`),
  CONSTRAINT `proveedores_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyecto_archivos`
--

DROP TABLE IF EXISTS `proyecto_archivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyecto_archivos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `etapa_id` bigint unsigned DEFAULT NULL,
  `seccion_id` bigint unsigned DEFAULT NULL,
  `solicitud_id` bigint unsigned DEFAULT NULL,
  `grupo` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'documento',
  `origen` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `dropbox_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso` bigint unsigned NOT NULL DEFAULT '0',
  `vista` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `miniatura` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ancho` int unsigned DEFAULT NULL,
  `alto` int unsigned DEFAULT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `orden` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_archivos_etapa_id_foreign` (`etapa_id`),
  KEY `proyecto_archivos_proyecto_id_grupo_index` (`proyecto_id`,`grupo`),
  KEY `proyecto_archivos_dropbox_id_index` (`dropbox_id`),
  KEY `proyecto_archivos_cuenta_id_index` (`cuenta_id`),
  KEY `proyecto_archivos_seccion_id_foreign` (`seccion_id`),
  KEY `proyecto_archivos_solicitud_id_foreign` (`solicitud_id`),
  CONSTRAINT `proyecto_archivos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyecto_archivos_etapa_id_foreign` FOREIGN KEY (`etapa_id`) REFERENCES `proyecto_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proyecto_archivos_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyecto_archivos_seccion_id_foreign` FOREIGN KEY (`seccion_id`) REFERENCES `galeria_secciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proyecto_archivos_solicitud_id_foreign` FOREIGN KEY (`solicitud_id`) REFERENCES `proyecto_solicitudes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyecto_archivos`
--

LOCK TABLES `proyecto_archivos` WRITE;
/*!40000 ALTER TABLE `proyecto_archivos` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyecto_archivos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyecto_etapas`
--

DROP TABLE IF EXISTS `proyecto_etapas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyecto_etapas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `es_fecha` tinyint(1) NOT NULL DEFAULT '0',
  `dias` smallint unsigned DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `completada_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_etapas_proyecto_id_foreign` (`proyecto_id`),
  KEY `proyecto_etapas_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `proyecto_etapas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyecto_etapas_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyecto_etapas`
--

LOCK TABLES `proyecto_etapas` WRITE;
/*!40000 ALTER TABLE `proyecto_etapas` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyecto_etapas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyecto_pagos`
--

DROP TABLE IF EXISTS `proyecto_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyecto_pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL DEFAULT '0.00',
  `monto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `antes_de` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vence_el` date DEFAULT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `pagado_el` date DEFAULT NULL,
  `metodo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_pagos_proyecto_id_foreign` (`proyecto_id`),
  KEY `proyecto_pagos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `proyecto_pagos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyecto_pagos_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyecto_pagos`
--

LOCK TABLES `proyecto_pagos` WRITE;
/*!40000 ALTER TABLE `proyecto_pagos` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyecto_pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyecto_solicitudes`
--

DROP TABLE IF EXISTS `proyecto_solicitudes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyecto_solicitudes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned NOT NULL,
  `titulo` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instrucciones` text COLLATE utf8mb4_unicode_ci,
  `fecha_limite` date DEFAULT NULL,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `recibida_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_solicitudes_cuenta_id_foreign` (`cuenta_id`),
  KEY `proyecto_solicitudes_proyecto_id_foreign` (`proyecto_id`),
  CONSTRAINT `proyecto_solicitudes_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyecto_solicitudes_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyecto_solicitudes`
--

LOCK TABLES `proyecto_solicitudes` WRITE;
/*!40000 ALTER TABLE `proyecto_solicitudes` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyecto_solicitudes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyecto_tipos`
--

DROP TABLE IF EXISTS `proyecto_tipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyecto_tipos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `propio` tinyint(1) NOT NULL DEFAULT '0',
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapas` json NOT NULL,
  `pagos` json NOT NULL,
  `opciones` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proyecto_tipos_cuenta_id_clave_unique` (`cuenta_id`,`clave`),
  KEY `proyecto_tipos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `proyecto_tipos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyecto_tipos`
--

LOCK TABLES `proyecto_tipos` WRITE;
/*!40000 ALTER TABLE `proyecto_tipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyecto_tipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proyectos`
--

DROP TABLE IF EXISTS `proyectos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proyectos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `presupuesto_id` bigint unsigned DEFAULT NULL,
  `token` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dropbox_carpeta` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dropbox_zip_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `cancelado_at` timestamp NULL DEFAULT NULL,
  `motivo_cancelacion` text COLLATE utf8mb4_unicode_ci,
  `monto_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `forma_pago` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contado',
  `dias_credito` smallint unsigned DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `mensaje_cliente` text COLLATE utf8mb4_unicode_ci,
  `notas_internas` text COLLATE utf8mb4_unicode_ci,
  `vistas` int unsigned NOT NULL DEFAULT '0',
  `ultima_vista_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proyectos_token_unique` (`token`),
  UNIQUE KEY `proyectos_presupuesto_id_unique` (`presupuesto_id`),
  KEY `proyectos_cliente_id_foreign` (`cliente_id`),
  KEY `proyectos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `proyectos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyectos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `proyectos_presupuesto_id_foreign` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proyectos`
--

LOCK TABLES `proyectos` WRITE;
/*!40000 ALTER TABLE `proyectos` DISABLE KEYS */;
/*!40000 ALTER TABLE `proyectos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `push_suscripciones`
--

DROP TABLE IF EXISTS `push_suscripciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_suscripciones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `endpoint_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dh` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dispositivo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eventos` json DEFAULT NULL,
  `ultimo_envio_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_suscripciones_endpoint_hash_unique` (`endpoint_hash`),
  KEY `push_suscripciones_user_id_foreign` (`user_id`),
  KEY `push_suscripciones_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `push_suscripciones_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `push_suscripciones_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `push_suscripciones`
--

LOCK TABLES `push_suscripciones` WRITE;
/*!40000 ALTER TABLE `push_suscripciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `push_suscripciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redes_codigos`
--

DROP TABLE IF EXISTS `redes_codigos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redes_codigos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `codigo_hash` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `canal` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expira_at` timestamp NOT NULL,
  `intentos` tinyint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `redes_codigos_cliente_id_foreign` (`cliente_id`),
  KEY `redes_codigos_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `redes_codigos_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redes_codigos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redes_codigos`
--

LOCK TABLES `redes_codigos` WRITE;
/*!40000 ALTER TABLE `redes_codigos` DISABLE KEYS */;
/*!40000 ALTER TABLE `redes_codigos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redes_comentarios`
--

DROP TABLE IF EXISTS `redes_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redes_comentarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `post_id` bigint unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'comentario',
  `actor` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `autor` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `redes_comentarios_post_id_foreign` (`post_id`),
  KEY `redes_comentarios_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `redes_comentarios_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redes_comentarios_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `redes_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redes_comentarios`
--

LOCK TABLES `redes_comentarios` WRITE;
/*!40000 ALTER TABLE `redes_comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `redes_comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redes_medios`
--

DROP TABLE IF EXISTS `redes_medios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redes_medios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `post_id` bigint unsigned NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(600) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ancho` int unsigned DEFAULT NULL,
  `alto` int unsigned DEFAULT NULL,
  `bytes` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `redes_medios_post_id_foreign` (`post_id`),
  KEY `redes_medios_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `redes_medios_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redes_medios_post_id_foreign` FOREIGN KEY (`post_id`) REFERENCES `redes_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redes_medios`
--

LOCK TABLES `redes_medios` WRITE;
/*!40000 ALTER TABLE `redes_medios` DISABLE KEYS */;
/*!40000 ALTER TABLE `redes_medios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redes_perfiles`
--

DROP TABLE IF EXISTS `redes_perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redes_perfiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `red` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enlace` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seguidores` int unsigned DEFAULT NULL,
  `seguidos` int unsigned DEFAULT NULL,
  `avatar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_origen` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `redes_perfiles_cliente_id_red_unique` (`cliente_id`,`red`),
  KEY `redes_perfiles_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `redes_perfiles_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redes_perfiles_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redes_perfiles`
--

LOCK TABLES `redes_perfiles` WRITE;
/*!40000 ALTER TABLE `redes_perfiles` DISABLE KEYS */;
/*!40000 ALTER TABLE `redes_perfiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `redes_posts`
--

DROP TABLE IF EXISTS `redes_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `redes_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned NOT NULL,
  `titulo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redes` json NOT NULL,
  `formato` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post',
  `fecha` datetime DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `aprobado_at` timestamp NULL DEFAULT NULL,
  `aprobado_por` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `redes_posts_cliente_id_fecha_index` (`cliente_id`,`fecha`),
  KEY `redes_posts_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `redes_posts_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redes_posts_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `redes_posts`
--

LOCK TABLES `redes_posts` WRITE;
/*!40000 ALTER TABLE `redes_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `redes_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permisos` json DEFAULT NULL,
  `todo` tinyint(1) NOT NULL DEFAULT '0',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `roles_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `roles_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,1,'Super admin','Puede hacer todo, incluido invitar personas y cambiar permisos.','null',1,0,'2026-10-11 01:56:05','2026-10-11 01:56:05'),(2,1,'Project','Gestiona las tareas del equipo, los proyectos y el contenido de redes.','[\"proyectos\", \"tareas\", \"clientes\", \"redes\", \"archivos\", \"ventas\"]',0,1,'2026-10-11 01:56:05','2026-10-11 01:56:05'),(3,1,'Fotógrafo','Ve sus tareas y sube fotos y videos a la carpeta de cada una.','[]',0,2,'2026-10-11 01:56:05','2026-10-11 01:56:05'),(4,1,'Diseñador','Ve sus tareas y sube sus diseños a la carpeta que le asignen.','[]',0,3,'2026-10-11 01:56:05','2026-10-11 01:56:05'),(5,2,'Super admin','Puede hacer todo, incluido invitar personas y cambiar permisos.',NULL,1,0,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(6,2,'Vendedor','Atiende prospectos y clientes, y cotiza eligiendo productos del catálogo. No ve costos ni márgenes.','[\"ventas\", \"cotizaciones\", \"clientes\"]',0,1,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(7,2,'Almacén','Da de alta productos, registra entradas y salidas, e importa el catálogo.','[\"productos\", \"proveedores\"]',0,2,'2026-10-19 06:19:01','2026-10-19 06:19:01'),(8,2,'Administración','Cobros, finanzas, clientes, cotizaciones y costos del catálogo.','[\"finanzas\", \"clientes\", \"cotizaciones\", \"productos\"]',0,3,'2026-10-19 06:19:01','2026-10-19 06:19:01');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sucursales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sucursales_cuenta_id_foreign` (`cuenta_id`),
  CONSTRAINT `sucursales_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarea_archivos`
--

DROP TABLE IF EXISTS `tarea_archivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarea_archivos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `tarea_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `nombre` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta` varchar(600) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dropbox_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamano` bigint unsigned NOT NULL DEFAULT '0',
  `ronda` smallint unsigned NOT NULL DEFAULT '1',
  `proyecto_archivo_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tarea_archivos_tarea_id_foreign` (`tarea_id`),
  KEY `tarea_archivos_user_id_foreign` (`user_id`),
  KEY `tarea_archivos_cuenta_id_index` (`cuenta_id`),
  KEY `tarea_archivos_proyecto_archivo_id_foreign` (`proyecto_archivo_id`),
  CONSTRAINT `tarea_archivos_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarea_archivos_proyecto_archivo_id_foreign` FOREIGN KEY (`proyecto_archivo_id`) REFERENCES `proyecto_archivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tarea_archivos_tarea_id_foreign` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarea_archivos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarea_archivos`
--

LOCK TABLES `tarea_archivos` WRITE;
/*!40000 ALTER TABLE `tarea_archivos` DISABLE KEYS */;
/*!40000 ALTER TABLE `tarea_archivos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tarea_comentarios`
--

DROP TABLE IF EXISTS `tarea_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarea_comentarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `tarea_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tarea_comentarios_tarea_id_foreign` (`tarea_id`),
  KEY `tarea_comentarios_user_id_foreign` (`user_id`),
  KEY `tarea_comentarios_cuenta_id_index` (`cuenta_id`),
  CONSTRAINT `tarea_comentarios_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarea_comentarios_tarea_id_foreign` FOREIGN KEY (`tarea_id`) REFERENCES `tareas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tarea_comentarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tarea_comentarios`
--

LOCK TABLES `tarea_comentarios` WRITE;
/*!40000 ALTER TABLE `tarea_comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `tarea_comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tareas`
--

DROP TABLE IF EXISTS `tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tareas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `titulo` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `proyecto_id` bigint unsigned DEFAULT NULL,
  `prospecto_id` bigint unsigned DEFAULT NULL,
  `asignada_a` bigint unsigned DEFAULT NULL,
  `creada_por` bigint unsigned DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `urgente` tinyint(1) NOT NULL DEFAULT '0',
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `carpeta` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destino` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etapa_id` bigint unsigned DEFAULT NULL,
  `completar_etapa` tinyint(1) NOT NULL DEFAULT '0',
  `ronda` smallint unsigned NOT NULL DEFAULT '1',
  `entregada_at` timestamp NULL DEFAULT NULL,
  `terminada_at` timestamp NULL DEFAULT NULL,
  `aprobada_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tareas_cliente_id_foreign` (`cliente_id`),
  KEY `tareas_proyecto_id_foreign` (`proyecto_id`),
  KEY `tareas_creada_por_foreign` (`creada_por`),
  KEY `tareas_asignada_a_estado_index` (`asignada_a`,`estado`),
  KEY `tareas_cuenta_id_index` (`cuenta_id`),
  KEY `tareas_etapa_id_foreign` (`etapa_id`),
  KEY `tareas_aprobada_por_foreign` (`aprobada_por`),
  KEY `tareas_prospecto_id_foreign` (`prospecto_id`),
  CONSTRAINT `tareas_aprobada_por_foreign` FOREIGN KEY (`aprobada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_asignada_a_foreign` FOREIGN KEY (`asignada_a`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_creada_por_foreign` FOREIGN KEY (`creada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tareas_etapa_id_foreign` FOREIGN KEY (`etapa_id`) REFERENCES `proyecto_etapas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_prospecto_id_foreign` FOREIGN KEY (`prospecto_id`) REFERENCES `prospectos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tareas_proyecto_id_foreign` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tareas`
--

LOCK TABLES `tareas` WRITE;
/*!40000 ALTER TABLE `tareas` DISABLE KEYS */;
/*!40000 ALTER TABLE `tareas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unidad_fotos`
--

DROP TABLE IF EXISTS `unidad_fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidad_fotos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `unidad_id` bigint unsigned NOT NULL,
  `archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `unidad_fotos_unidad_id_foreign` (`unidad_id`),
  CONSTRAINT `unidad_fotos_unidad_id_foreign` FOREIGN KEY (`unidad_id`) REFERENCES `unidades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unidad_fotos`
--

LOCK TABLES `unidad_fotos` WRITE;
/*!40000 ALTER TABLE `unidad_fotos` DISABLE KEYS */;
/*!40000 ALTER TABLE `unidad_fotos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `unidades`
--

DROP TABLE IF EXISTS `unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `sucursal_id` bigint unsigned DEFAULT NULL,
  `vendedor_id` bigint unsigned DEFAULT NULL,
  `marca` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anio` smallint unsigned DEFAULT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kilometraje` int unsigned DEFAULT NULL,
  `transmision` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `combustible` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placas` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT '0.00',
  `costo` decimal(12,2) DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `ficha` text COLLATE utf8mb4_unicode_ci,
  `estado` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disponible',
  `slug` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publico` tinyint(1) NOT NULL DEFAULT '1',
  `ejemplo` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unidades_slug_unique` (`slug`),
  KEY `unidades_sucursal_id_foreign` (`sucursal_id`),
  KEY `unidades_vendedor_id_foreign` (`vendedor_id`),
  KEY `unidades_cuenta_id_estado_index` (`cuenta_id`,`estado`),
  CONSTRAINT `unidades_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `unidades_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `unidades_vendedor_id_foreign` FOREIGN KEY (`vendedor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `unidades`
--

LOCK TABLES `unidades` WRITE;
/*!40000 ALTER TABLE `unidades` DISABLE KEYS */;
/*!40000 ALTER TABLE `unidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cuenta_id` bigint unsigned DEFAULT NULL,
  `sucursal_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_id` bigint unsigned DEFAULT NULL,
  `puesto` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `plataforma` tinyint(1) NOT NULL DEFAULT '0',
  `invitacion_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitacion_expira` timestamp NULL DEFAULT NULL,
  `ultimo_acceso_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_invitacion_hash_unique` (`invitacion_hash`),
  KEY `users_rol_id_foreign` (`rol_id`),
  KEY `users_cuenta_id_index` (`cuenta_id`),
  KEY `users_sucursal_id_foreign` (`sucursal_id`),
  CONSTRAINT `users_cuenta_id_foreign` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_rol_id_foreign` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_sucursal_id_foreign` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,1,NULL,'Alvar Buenfil','ab@agenciavandu.com',NULL,'$2y$12$iWaeC/JfF.qfMbbjU/SZR.xjGQ1LDhr.6ah3OH2zLyaSAu3xG7gXO',1,NULL,NULL,1,1,NULL,NULL,NULL,'k5DZZ6GzojXMz9nkfsl32wlBqbQXCtqHLBxbl7OJ0TyYfB8HttHy3TsPjoxL','2026-10-08 22:34:58','2026-10-08 22:34:58'),(2,1,NULL,'Administrador Nerby','admin@nerby.local',NULL,'$2y$12$NhLpMT0li7Hmn.AOnLPOU.rGy1LcsuA1ZC5sc65bui3h.QW2CVUDe',1,'Administrador de plataforma',NULL,1,1,NULL,NULL,'2026-10-19 06:18:07',NULL,'2026-10-19 06:17:37','2026-10-19 06:18:07'),(3,2,NULL,'alvar','vandumx@gmail.com',NULL,'$2y$12$PKfQbT5cdK1pevFWWaNnKeM//EI3P4ehsy5lNEyrUHADUpXIyfbli',5,'Director',NULL,1,0,'3ffd3ba1d8d6d5caab91228a5e241cffdeeb32e8c015a041eb5bd1a3d8f6e545','2026-10-26 06:19:01',NULL,NULL,'2026-10-19 06:19:01','2026-10-19 06:19:01');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'agenciavandu'
--
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-19  8:58:32
