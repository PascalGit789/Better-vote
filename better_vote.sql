-- phpMyAdmin SQL Dump
-- version 4.0.4
-- http://www.phpmyadmin.net
--
-- Client: localhost
-- Généré le: Sam 27 Mai 2017 à 03:41
-- Version du serveur: 5.6.12-log
-- Version de PHP: 5.4.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données: `better_vote`
--
CREATE DATABASE IF NOT EXISTS `better_vote` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `better_vote`;

-- --------------------------------------------------------

--
-- Structure de la table `poll`
--

CREATE TABLE IF NOT EXISTS `poll` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(100) NOT NULL,
  `public` tinyint(1) NOT NULL DEFAULT '0',
  `one_vote_ip` tinyint(1) NOT NULL DEFAULT '0',
  `url` varchar(10) NOT NULL,
  `address_ip` varchar(35) NOT NULL,
  `datetime_creation` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Structure de la table `poll_option`
--

CREATE TABLE IF NOT EXISTS `poll_option` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_poll` int(11) unsigned NOT NULL,
  `texte` varchar(75) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `poll_vote`
--

CREATE TABLE IF NOT EXISTS `poll_vote` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_poll_option` int(11) unsigned NOT NULL,
  `id_voter` int(11) unsigned NOT NULL,
  `vote_order` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

-- --------------------------------------------------------

--
-- Structure de la table `voter`
--

CREATE TABLE IF NOT EXISTS `voter` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `address_ip` varchar(35) NOT NULL,
  `id_poll` int(11) NOT NULL,
  `datetime_vote` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
