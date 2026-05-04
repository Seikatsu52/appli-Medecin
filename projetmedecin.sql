-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : lun. 04 mai 2026 à 14:16
-- Version du serveur : 8.0.27
-- Version de PHP : 7.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `projetmedecin`
--

-- --------------------------------------------------------

--
-- Structure de la table `authentification`
--

DROP TABLE IF EXISTS `authentification`;
CREATE TABLE IF NOT EXISTS `authentification` (
  `token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idPatient` bigint UNSIGNED NOT NULL,
  `ipAppareil` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `authentification_idpatient_foreign` (`idPatient`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `authentification`
--

INSERT INTO `authentification` (`token`, `idPatient`, `ipAppareil`, `created_at`, `updated_at`) VALUES
('15d91a50731b7eb0464939f0d5efec9a26f73a5a82fb1c58fef4a2e2c609d973', 1, '127.0.0.1', '2026-03-26 09:44:39', '2026-03-26 09:44:39'),
('a6327d201226d20efeb44720df6893cb0e630fe84c6d00db3e6326b9db960c9b', 2, '127.0.0.1', '2026-04-27 12:21:34', '2026-04-27 12:21:34'),
('f9bdfb4c7a6b5db29d50cf8a809520bfa0bbf8e4c666fb9aee3cca7207742900', 3, '127.0.0.1', '2026-04-09 10:45:20', '2026-04-09 10:45:20');

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2026_03_12_131248_create_patients_table', 1),
(2, '2026_03_12_131554_create_rdv_table', 1),
(3, '2026_03_12_131611_create_authentifications_table', 1);

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

DROP TABLE IF EXISTS `patients`;
CREATE TABLE IF NOT EXISTS `patients` (
  `idPatient` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomPatient` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenomPatient` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruePatient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpPatient` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `villePatient` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telPatient` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `loginPatient` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mdpPatient` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idPatient`),
  UNIQUE KEY `patients_loginpatient_unique` (`loginPatient`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`idPatient`, `nomPatient`, `prenomPatient`, `ruePatient`, `cpPatient`, `villePatient`, `telPatient`, `loginPatient`, `mdpPatient`, `created_at`, `updated_at`) VALUES
(1, 'Arens', 'Amélia', 'Rue test', '92040', 'Issy-les-Moulineaux', '0615789645', 'AmelA', '$2y$10$cfc.hsR70BxilhgB1r6GC.uowv70IXP24H.HVtnU.6TxxOYsPCix2', '2026-03-26 09:43:58', '2026-03-26 09:43:58'),
(2, 'W', 'Cloe', 'Rue CloW', '92040', 'Issy-les-Moulineaux', '0625789642', 'CloeW', '$2y$10$7wfeMulr6UAq1V.4UG5ZbOh5Sy8M7R5Uxxnjtmda/X8gbQlarITxe', '2026-03-26 12:47:38', '2026-03-26 12:47:38'),
(3, 'A', 'Amelia', '24', '55100', 'Issy-les-Moulineaux', '0658741258', 'Amelia', '$2y$10$bMITmd/Me5neKGhw9spNkuk/FwPH8iNMZ7WugUQ2NmEKeBD.PjH2K', '2026-04-09 10:44:16', '2026-04-09 10:44:16');

-- --------------------------------------------------------

--
-- Structure de la table `rdv`
--

DROP TABLE IF EXISTS `rdv`;
CREATE TABLE IF NOT EXISTS `rdv` (
  `idRdv` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `dateHeureRdv` datetime NOT NULL,
  `idPatient` bigint UNSIGNED NOT NULL,
  `nomMedecin` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenomMedecin` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `idMedecin` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`idRdv`),
  KEY `rdv_idpatient_foreign` (`idPatient`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `rdv`
--

INSERT INTO `rdv` (`idRdv`, `dateHeureRdv`, `idPatient`, `nomMedecin`, `prenomMedecin`, `idMedecin`, `created_at`, `updated_at`) VALUES
(1, '2026-03-27 16:00:00', 1, 'SAYRIN', 'JEAN', 'SAYRIN-JEAN', '2026-03-26 12:38:55', '2026-03-26 12:38:55'),
(2, '2026-03-27 08:00:00', 2, 'SAYRIN', 'JEAN', 'SAYRIN-JEAN', '2026-03-26 12:47:47', '2026-03-26 12:47:47'),
(3, '2026-03-27 08:00:00', 2, 'LAZAR', 'VIOLETA', 'LAZAR-VIOLETA', '2026-03-26 12:53:49', '2026-03-26 12:53:49'),
(4, '2026-03-28 15:00:00', 2, 'QUEMIN', 'RAPHAEL', 'QUEMIN-RAPHAEL', '2026-03-27 08:27:48', '2026-03-27 08:27:48'),
(5, '2026-04-24 12:00:00', 2, 'MURATYAN', 'EDOUARD', 'MURATYAN-EDOUARD', '2026-03-27 08:28:42', '2026-03-27 08:28:42'),
(6, '2026-04-10 09:00:00', 3, 'KNIAZEFF', 'ALEXIS', 'KNIAZEFF-ALEXIS', '2026-04-09 10:45:46', '2026-04-09 10:45:58'),
(7, '2026-04-28 08:20:00', 2, 'BRIERE', 'ISABELLE', 'BRIERE-ISABELLE', '2026-04-27 12:22:28', '2026-04-27 12:22:28'),
(8, '2026-05-21 17:00:00', 2, 'PELLETIER', 'MARIE', 'PELLETIER-MARIE', '2026-04-27 12:23:21', '2026-04-27 12:23:21');

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `authentification`
--
ALTER TABLE `authentification`
  ADD CONSTRAINT `authentification_idpatient_foreign` FOREIGN KEY (`idPatient`) REFERENCES `patients` (`idPatient`) ON DELETE CASCADE;

--
-- Contraintes pour la table `rdv`
--
ALTER TABLE `rdv`
  ADD CONSTRAINT `rdv_idpatient_foreign` FOREIGN KEY (`idPatient`) REFERENCES `patients` (`idPatient`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
