-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Client :  localhost
-- Généré le :  Ven 21 Juillet 2017 à 04:12
-- Version du serveur :  5.7.18-0ubuntu0.16.04.1
-- Version de PHP :  7.0.15-0ubuntu0.16.04.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `better_vote`
--
CREATE DATABASE IF NOT EXISTS `better_vote` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `better_vote`;
-- --------------------------------------------------------

--
-- Structure de la table `poll`
--

CREATE TABLE `poll` (
  `id` int(11) UNSIGNED NOT NULL,
  `question` varchar(100) NOT NULL,
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `one_vote_ip` tinyint(1) NOT NULL DEFAULT '0',
  `url` varchar(10) NOT NULL,
  `address_ip` varchar(35) NOT NULL,
  `datetime_creation` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `poll_option`
--

CREATE TABLE `poll_option` (
  `id` int(11) UNSIGNED NOT NULL,
  `id_poll` int(11) UNSIGNED NOT NULL,
  `texte` varchar(75) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `poll_vote`
--

CREATE TABLE `poll_vote` (
  `id` int(11) UNSIGNED NOT NULL,
  `id_poll_option` int(11) UNSIGNED NOT NULL,
  `id_voter` int(11) UNSIGNED NOT NULL,
  `vote_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `voter`
--

CREATE TABLE `voter` (
  `id` int(11) UNSIGNED NOT NULL,
  `address_ip` varchar(35) NOT NULL,
  `id_poll` int(11) NOT NULL,
  `datetime_vote` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Index pour les tables exportées
--

--
-- Index pour la table `poll`
--
ALTER TABLE `poll`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `poll_option`
--
ALTER TABLE `poll_option`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `poll_vote`
--
ALTER TABLE `poll_vote`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `voter`
--
ALTER TABLE `voter`
  ADD PRIMARY KEY (`id`);
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
