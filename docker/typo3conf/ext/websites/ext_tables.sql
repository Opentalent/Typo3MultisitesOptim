# ext_tables.sql contient les directives permettant d ajouter des tables et champs à la DB

#
# Table structure for table 'pages'
#
CREATE TABLE pages (
	website_uid INT UNSIGNED DEFAULT NULL,

    KEY website (website_uid)
);

#
# Table structure for table 'websites'
#
CREATE TABLE websites
(
    uid INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(255),
    locale VARCHAR(10) DEFAULT 'en-EN',
    config_identifier VARCHAR(255),
    deleted tinyint(1) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY subdomain (subdomain),
    KEY config_identifier (config_identifier)
);
