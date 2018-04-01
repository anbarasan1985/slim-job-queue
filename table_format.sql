//

CREATE TABLE `job_queue` (
 `job_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Job ID',
 `submitter_id` varchar(56) NOT NULL COMMENT 'Job submitter id',
 `processor_id` varchar(56) NOT NULL COMMENT 'Job processor id',
 `priority` int(4) NOT NULL COMMENT 'Job priority based on int',
 `script` text NOT NULL,
 `vars` text NOT NULL,
 `last_run` timestamp NULL DEFAULT NULL,
 `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`job_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='Job queue list'


