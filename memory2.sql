-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : dim. 19 oct. 2025 à 08:07
-- Version du serveur : 8.4.3
-- Version de PHP : 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `memory2`
--

-- --------------------------------------------------------

--
-- Structure de la table `games`
--

CREATE TABLE `games` (
  `id` int NOT NULL,
  `player_id` int NOT NULL,
  `pairs_count` int NOT NULL,
  `moves_count` int DEFAULT '0',
  `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `status` enum('playing','completed','abandoned') COLLATE utf8mb4_unicode_ci DEFAULT 'playing',
  `score` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déclencheurs `games`
--
DELIMITER $$
CREATE TRIGGER `trg_games_before_insert` BEFORE INSERT ON `games` FOR EACH ROW BEGIN
    
    IF NOT (NEW.pairs_count BETWEEN 3 AND 12) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'pairs_count must be between 3 and 12 pairs';
    END IF;
    
   
    IF NEW.moves_count < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'moves_count must be >= 0';
    END IF;
    
   
    IF NEW.score < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'score must be >= 0';
    END IF;
    
   
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL THEN
        IF NEW.end_time < NEW.start_time THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'end_time cannot be before start_time';
        END IF;
    END IF;
    
    
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL AND NEW.duration_seconds IS NULL THEN
        SET NEW.duration_seconds = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_games_before_update` BEFORE UPDATE ON `games` FOR EACH ROW BEGIN
   
    IF NOT (NEW.pairs_count BETWEEN 3 AND 12) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'pairs_count must be between 3 and 12 pairs';
    END IF;
    
  
    IF NEW.moves_count < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'moves_count must be >= 0';
    END IF;
    
   
    IF NEW.score < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'score must be >= 0';
    END IF;
    
 
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL THEN
        IF NEW.end_time < NEW.start_time THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'end_time cannot be before start_time';
        END IF;
    END IF;
    
    
    IF NEW.end_time IS NOT NULL AND NEW.start_time IS NOT NULL AND 
       (NEW.end_time != OLD.end_time OR NEW.start_time != OLD.start_time) THEN
        SET NEW.duration_seconds = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
    
   
    IF NEW.player_id != OLD.player_id THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'player_id cannot be modified after game creation';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `players`
--

CREATE TABLE `players` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_player_id` (`player_id`),
  ADD KEY `idx_score` (`score`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `games`
--
ALTER TABLE `games`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `players`
--
ALTER TABLE `players`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
