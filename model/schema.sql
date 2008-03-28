DROP TABLE IF EXISTS `shopp_setting`;
CREATE TABLE `shopp_setting` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`name` varchar(255) NOT NULL default '',
	`value` longtext NOT NULL default '',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_product`;
CREATE TABLE `shopp_product` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`name` varchar(255) NOT NULL default '',
	`description` text NOT NULL default '',
	`details` longtext NOT NULL default '',
	`brand` varchar(255) NOT NULL default '',
	`currency` bigint(20) unsigned NOT NULL default '0',
	`price` float(20,2) NOT NULL default '0.00',
	`specialprice` float(20,2) NOT NULL default '0',
	`domship` float(20,2) NOT NULL default '0',
	`intlship` float(20,2) NOT NULL default '0',
	`inventory` int(10) NOT NULL default '0',
	`category` int(10) unsigned NOT NULL default '0',
	`shipping` enum('enabled','disabled') default 'enabled',
	`tax` enum('enabled','disabled') default 'enabled',
	`donation` enum('enabled','disabled') default 'disabled',
	`special` enum('enabled','disabled') default 'disabled',
	`status` enum('enabled','disabled') default 'disabled',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_var`;
CREATE TABLE `shopp_var` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`product` bigint(20) unsigned NOT NULL default '0',
	`label` int(10) unsigned NOT NULL default '0',
	`value` varchar(255) NOT NULL default '',
	`price` float(20,2) NOT NULL default '0.00',
	`specialprice` float(20,2) NOT NULL default '0',
	`domship` float(20,2) NOT NULL default '0',
	`intlship` float(20,2) NOT NULL default '0',
	`inventory` int(10) NOT NULL default '0',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_asset`;
CREATE TABLE `shopp_asset` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`product` bigint(20) unsigned NOT NULL default '0',
	`name` varchar(255) NOT NULL default '',
	`value` varchar(255) NOT NULL default '',
	`data` longblob NOT NULL default '',
	`type` enum('metadata','image','download') default 'metadata',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_cart`;
CREATE TABLE `shopp_cart` (
	`session` varchar(32) NOT NULL default '',
	`customer` bigint(20) unsigned NOT NULL default '0',
	`ip` varchar(15) NOT NULL default '0.0.0.0',
	`data` longtext NOT NULL default '',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`session`),
	KEY `customer` (`customer`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_item`;
CREATE TABLE `shopp_item` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`cart` varchar(32) NOT NULL default '',
	`product` bigint(20) unsigned NOT NULL default '0',
	`option` bigint(20) unsigned NOT NULL default '0',
	`discount` bigint(20) unsigned NOT NULL default '0',
	`quantity` int(10) unsigned NOT NULL default '0',
	`price` float(20,2) NOT NULL default '0',
	`shipping` float(20,2) NOT NULL default '0',
	`tax` float(20,2) NOT NULL default '0',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_customer`;
CREATE TABLE `shopp_customer` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`firstname` varchar(32) NOT NULL default '',
	`lastname` varchar(32) NOT NULL default '',
	`email` varchar(96) NOT NULL default '',
	`phone` varchar(24) NOT NULL default '',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_shipping`;
CREATE TABLE `shopp_shipping` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`customer` bigint(20) unsigned NOT NULL default '0',
	`address` varchar(100) NOT NULL default '',
	`xaddress` varchar(100) NOT NULL default '',
	`city` varchar(100) NOT NULL default '',
	`state` varchar(2) NOT NULL default '',
	`country` varchar(2) NOT NULL default '',
	`postcode` varchar(10) NOT NULL default '',
	`geocode` varchar(16) NOT NULL default '',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_billing`;
CREATE TABLE `shopp_billing` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`customer` bigint(20) unsigned NOT NULL default '0',
	`card` varchar(4) NOT NULL default '',
	`cardtype` varchar(32) NOT NULL default '',
	`cardexpires` date NOT NULL default '0000-00-00',
	`cardholder` varchar(96) NOT NULL default '',
	`address` varchar(100) NOT NULL default '',
	`xaddress` varchar(100) NOT NULL default '',
	`city` varchar(100) NOT NULL default '',
	`state` varchar(2) NOT NULL default '',
	`country` varchar(2) NOT NULL default '',
	`postcode` varchar(10) NOT NULL default '',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_purchase`;
CREATE TABLE `shopp_purchase` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`customer` bigint(20) unsigned NOT NULL default '0',
	`shipping` bigint(20) unsigned NOT NULL default '0',
	`billing` bigint(20) unsigned NOT NULL default '0',
	`currency` bigint(20) unsigned NOT NULL default '0',
	`total` float(20,2) NOT NULL default '0',
	`discount` float(20,2) NOT NULL default '0',
	`transactionid` varchar(64) NOT NULL default '',
	`gateway` varchar(64) NOT NULL default '',
	`shiptrack` varchar(100) NOT NULL default '',
	`status` tinyint(3) unsigned NOT NULL default '0',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `shopp_purchased`;
CREATE TABLE `shopp_purchased` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`product` bigint(20) unsigned NOT NULL default '0',
	`option` bigint(20) unsigned NOT NULL default '0',
	`discount` bigint(20) unsigned NOT NULL default '0',
	`description` varchar(255) NOT NULL default '',
	`quantity` int(10) unsigned NOT NULL default '0',
	`price` float(20,2) NOT NULL default '0',
	`shipping` float(20,2) NOT NULL default '0',
	`tax` float(20,2) NOT NULL default '0',
	`created` datetime NOT NULL default '0000-00-00 00:00:00',
	`modified` datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY(`id`)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;
