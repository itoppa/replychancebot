CREATE TABLE `twitter_accounts` (
  `id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `screen_name` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `twitter_follows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_twitter_account_id` bigint(20) unsigned DEFAULT '0',
  `to_twitter_account_id` bigint(20) unsigned DEFAULT '0',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `reply_chances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `twitter_account_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `term` int(10) unsigned DEFAULT '0',
  `count` int(10) unsigned DEFAULT '0',
  `latest_datetime` datetime DEFAULT '1970-01-01 00:00:00',
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `reply_chance_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `twitter_account_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `count` int(10) unsigned DEFAULT '0',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `twitter_account_id_idx` (`twitter_account_id`),
  KEY `start_datetime_idx` (`start_datetime`),
  KEY `end_datetime_idx` (`end_datetime`),
  KEY `count_idx` (`count`),
  KEY `twitter_account_id_count_idx` (`twitter_account_id`,`count`),
  KEY `count_twitter_account_id_idx` (`count`,`twitter_account_id`),
  KEY `start_datetime_end_datetime_idx` (`start_datetime`,`end_datetime`),
  KEY `start_datetime_end_datetime_count_idx` (`start_datetime`,`end_datetime`,`count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
