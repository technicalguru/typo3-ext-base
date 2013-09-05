#
# Table structure for table 'tx_rsextbase_scheduler'
#
CREATE TABLE tx_rsextbase_scheduler (
  uid int(11) not null auto_increment,
  pid int(11) default 0 not null,
  crdate int(11) default 0 not null,
  tstamp int(11) default 0 not null,
  application varchar(60) default '' not null,
  task varchar(60) default '' not null,
  schedule_type varchar(20) default '' not null,
  schedule_data varchar(300) default '' not null,
  default_runtime int(11) default 0 not null,
  current_run int(11) default 0 not null,
  last_run int(11) default 0 not null,
  
  PRIMARY KEY (uid)
);
