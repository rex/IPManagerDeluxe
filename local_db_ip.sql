-- phpMyAdmin SQL Dump
-- version 3.2.3
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 10, 2010 at 12:01 AM
-- Server version: 5.0.77
-- PHP Version: 5.2.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Table structure for table `allocations`
--

DROP TABLE IF EXISTS `allocations`;
CREATE TABLE IF NOT EXISTS `allocations` (
  `allocation_id` int(20) NOT NULL auto_increment,
  `allocation_size` int(50) unsigned NOT NULL,
  `starting_ip` varchar(50) NOT NULL,
  `ending_ip` varchar(50) NOT NULL,
  `hr_start` varchar(60) NOT NULL,
  `hr_end` varchar(60) NOT NULL,
  `ip_type` enum('4','6') NOT NULL,
  `server_id` varchar(10) NOT NULL,
  `created_on` datetime NOT NULL,
  PRIMARY KEY  (`allocation_id`),
  UNIQUE KEY `starting_ip` (`starting_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Contains all small-scale allocated IP blocks.' AUTO_INCREMENT=1 ;

--
-- Dumping data for table `allocations`
--


-- --------------------------------------------------------

--
-- Table structure for table `ip_blocks`
--

DROP TABLE IF EXISTS `ip_blocks`;
CREATE TABLE IF NOT EXISTS `ip_blocks` (
  `block_id` int(20) NOT NULL auto_increment,
  `starting_ip` varchar(50) NOT NULL,
  `ending_ip` varchar(50) NOT NULL,
  `hr_start` varchar(60) NOT NULL,
  `hr_end` varchar(60) NOT NULL,
  `ip_type` enum('4','6') NOT NULL,
  `block_size` int(50) NOT NULL,
  `created_on` datetime NOT NULL,
  PRIMARY KEY  (`block_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COMMENT='Contains all large-scale IP blocks owned, to sub-allocate.' AUTO_INCREMENT=22 ;


