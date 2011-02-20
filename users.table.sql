CREATE TABLE `users` (
  `username` varchar(255) NOT NULL,
  `password` char(32) CHARACTER SET ascii NOT NULL,
  `salt` char(32) CHARACTER SET ascii NOT NULL,
  `fullname` varchar(512) NOT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

