# ext_tables.sql contient les directives permettant d ajouter des tables et champs à la DB

#
# Table structure for table 'pages'
#
CREATE TABLE pages (
	ot_website_uid INT UNSIGNED DEFAULT NULL,

    KEY website (ot_website_uid)
);

#
# Table structure for table 'ot_websites'
#
CREATE TABLE ot_websites
(
    uid INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    locale VARCHAR(10) DEFAULT 'fr-FR',
    config_identifier VARCHAR(255),
    deleted tinyint(1) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY domain (domain),
    KEY config_identifier (config_identifier)
);
