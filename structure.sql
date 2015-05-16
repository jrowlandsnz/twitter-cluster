-- phpMyAdmin SQL Dump
-- version 3.5.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 16, 2015 at 10:33 AM
-- Server version: 5.6.17
-- PHP Version: 5.3.28

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `twitter_cluster`
--

-- --------------------------------------------------------

--
-- Table structure for table `cluster`
--

CREATE TABLE IF NOT EXISTS `cluster` (
  `cluster_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cluster_size` smallint(5) unsigned NOT NULL,
  `cluster_key` varchar(5000) NOT NULL,
  `is_deleted` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `name` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`cluster_id`),
  KEY `cluster_size` (`cluster_size`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=72 ;

-- --------------------------------------------------------

--
-- Table structure for table `cluster_member`
--

CREATE TABLE IF NOT EXISTS `cluster_member` (
  `cluster_id` int(10) unsigned NOT NULL,
  `member` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`cluster_id`,`member`),
  KEY `cluster_id` (`cluster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `following`
--

CREATE TABLE IF NOT EXISTS `following` (
  `user_id` bigint(20) unsigned NOT NULL,
  `follows_user_id` bigint(20) unsigned NOT NULL,
  `when_checked` datetime NOT NULL,
  UNIQUE KEY `user_id` (`user_id`,`follows_user_id`),
  KEY `user_id_2` (`user_id`),
  KEY `follows_user_id` (`follows_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `user_id` bigint(20) unsigned NOT NULL,
  `screen_name` varchar(250) CHARACTER SET latin1 COLLATE latin1_general_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `screen_name` (`screen_name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
