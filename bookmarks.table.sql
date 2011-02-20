CREATE TABLE `bookmarks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(512) NOT NULL,
  `parent_id` int(11) unsigned DEFAULT NULL,
  `description` varchar(512) NOT NULL,
  `url` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `username` (`username`(333)),
  KEY `parent_id` (`parent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

