--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS galette_lend_category;
CREATE TABLE galette_lend_category (
  category_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) COLLATE utf8_general_ci NOT NULL,
  is_active tinyint(1) NOT NULL,
  PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS galette_lend_status;
CREATE TABLE galette_lend_status (
  status_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  status_text varchar(100) NOT NULL,
  in_stock tinyint(1) NOT NULL,
  is_active tinyint(1) NOT NULL,
  rent_day_number INT NULL DEFAULT NULL,
  PRIMARY KEY (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS galette_lend_rents;
CREATE TABLE galette_lend_rents (
  rent_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  object_id int(10) unsigned NOT NULL,
  date_begin datetime NOT NULL,
  date_forecast DATETIME NULL DEFAULT NULL,
  date_end datetime DEFAULT NULL,
  status_id int(10) unsigned NOT NULL,
  adherent_id int(10) unsigned DEFAULT NULL,
  comments varchar(200) NOT NULL,
  PRIMARY KEY (rent_id),
  KEY date_begin (date_begin),
  FOREIGN KEY FK_rent_adherent_1 (adherent_id) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY FK_rent_status_1 (status_id) REFERENCES galette_lend_status (status_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY FK_rent_object_1 (object_id) REFERENCES galette_lend_objects (object_id) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS galette_lend_objects;
CREATE TABLE galette_lend_objects (
  object_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description varchar(500) NOT NULL,
  serial_number varchar(30) NOT NULL,
  price decimal(15,3) NOT NULL,
  price_per_day tinyint(1) NOT NULL DEFAULT FALSE,
  dimension varchar(100) NOT NULL,
  weight decimal(15,3) NOT NULL,
  is_active tinyint(1) NOT NULL,
  category_id INT(10) UNSIGNED NULL,
  rent_price DECIMAL(15,3) NULL,
  nb_available INT NULL,
  rent_id int(10) unsigned NULL DEFAULT NULL,
  PRIMARY KEY (object_id),
  FOREIGN KEY FK_rent_category_1 (category_id) REFERENCES galette_lend_category (category_id) ON DELETE NO ACTION ON UPDATE NO ACTION,
  FOREIGN KEY FK_object_rent_1 (rent_id) REFERENCES galette_lend_rents (rent_id) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS galette_lend_pictures;
CREATE TABLE galette_lend_pictures (
  object_id int(11) NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Garage A (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Maison B (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Bibliotheque C (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location courte durée (exemple)', 0, 1, 7);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location longue durée (exemple)', 0, 1, 30);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Reparation (exemple)', 0, 1, 14);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Vendu (exemple)', 0, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Detruit (exemple)', 0, 1);

DROP TABLE IF EXISTS galette_lend_categories_pictures;
CREATE TABLE IF NOT EXISTS galette_lend_categories_pictures (
  category_id int(11) NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS=1;
