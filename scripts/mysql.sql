--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS galette_lend_category;
CREATE TABLE galette_lend_category (
  category_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  is_active tinyint(1) NOT NULL,
  PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_lend_status;
CREATE TABLE galette_lend_status (
  status_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  status_text varchar(100) NOT NULL,
  in_stock tinyint(1) NOT NULL,
  is_active tinyint(1) NOT NULL,
  rent_day_number INT NULL DEFAULT NULL,
  PRIMARY KEY (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

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
  CONSTRAINT galette_lend_rents_adherent_id_fkey FOREIGN KEY (adherent_id)
    REFERENCES galette_adherents (id_adh) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT galette_lend_rents_status_id_fkey FOREIGN KEY (status_id)
    REFERENCES galette_lend_status (status_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT galette_lend_rents_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_lend_objects;
CREATE TABLE galette_lend_objects (
  object_id int(10) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  description text NOT NULL,
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
  CONSTRAINT galette_lend_objects_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT galette_lend_objects_rent_id_fkey FOREIGN KEY (rent_id)
    REFERENCES galette_lend_rents (rent_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_lend_pictures;
CREATE TABLE galette_lend_pictures (
  object_id int(10) unsigned NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (object_id),
  CONSTRAINT galette_lend_pictures_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

DROP TABLE IF EXISTS galette_lend_categories_pictures;
CREATE TABLE galette_lend_categories_pictures (
  category_id int(10) unsigned NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (category_id),
  CONSTRAINT galette_lend_categories_pictures_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Garage A (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Maison B (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Bibliotheque C (exemple)', 1, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location courte durée (exemple)', 0, 1, 7);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location longue durée (exemple)', 0, 1, 30);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Reparation (exemple)', 0, 1, 14);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Vendu (exemple)', 0, 1);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Detruit (exemple)', 0, 1);

SET FOREIGN_KEY_CHECKS=1;
