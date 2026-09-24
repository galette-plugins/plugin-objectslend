--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

-- Plugin preferences move to core preferences. Declaring them already
-- inserted their defaults: those give way to the values set so far.
DELETE FROM galette_preferences WHERE nom_pref LIKE 'pref_objectslend_%';

INSERT INTO galette_preferences (nom_pref, val_pref)
SELECT
  CONCAT('pref_objectslend_', LOWER(code)),
  CASE WHEN is_text = 1 THEN COALESCE(value_text, '')
    ELSE CAST(CAST(COALESCE(value_numeric, 0) AS SIGNED) AS CHAR) END
FROM galette_lend_parameters
WHERE code IN (
    'ENABLE_MEMBER_RENT_OBJECT', 'AUTO_GENERATE_CONTRIBUTION', 'GENERATED_CONTRIBUTION_TYPE_ID',
    'GENERATED_CONTRIB_INFO_TEXT', 'THUMB_MAX_WIDTH', 'THUMB_MAX_HEIGHT', 'VIEW_THUMBNAIL',
    'VIEW_CATEGORY', 'VIEW_DATE_FORECAST', 'VIEW_DESCRIPTION', 'VIEW_DIMENSION', 'VIEW_LEND_PRICE',
    'VIEW_LIST_PRICE_SUM', 'VIEW_PRICE', 'VIEW_SERIAL', 'VIEW_WEIGHT'
);

DROP TABLE galette_lend_parameters;

-- Align schema with PostgreSQL one: utf8mb4, same foreign keys on both
-- engines, pictures removed with their object or category.
-- Foreign keys names depend on the MySQL version that created them, and
-- MySQL cannot drop them conditionally: tables holding some are rebuilt.
SET FOREIGN_KEY_CHECKS=0;

ALTER TABLE galette_lend_category CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
ALTER TABLE galette_lend_status CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;

CREATE TABLE galette_lend_rents_new (
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

INSERT INTO galette_lend_rents_new
  (rent_id, object_id, date_begin, date_forecast, date_end, status_id, adherent_id, comments)
SELECT rent_id, object_id, date_begin, date_forecast, date_end, status_id, adherent_id, comments
FROM galette_lend_rents;

CREATE TABLE galette_lend_objects_new (
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
  CONSTRAINT galette_lend_objects_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT galette_lend_objects_rent_id_fkey FOREIGN KEY (rent_id)
    REFERENCES galette_lend_rents (rent_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_lend_objects_new
  (object_id, name, description, serial_number, price, price_per_day, dimension, weight,
    is_active, category_id, rent_price, nb_available, rent_id)
SELECT object_id, name, description, serial_number, price, price_per_day, dimension, weight,
    is_active, category_id, rent_price, nb_available, rent_id
FROM galette_lend_objects;

-- Pictures had no foreign key: those of removed objects or categories are dropped
CREATE TABLE galette_lend_pictures_new (
  object_id int(10) unsigned NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (object_id),
  CONSTRAINT galette_lend_pictures_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_lend_pictures_new (object_id, picture, format)
SELECT p.object_id, p.picture, p.format
FROM galette_lend_pictures p
INNER JOIN galette_lend_objects o ON o.object_id = p.object_id;

CREATE TABLE galette_lend_categories_pictures_new (
  category_id int(10) unsigned NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (category_id),
  CONSTRAINT galette_lend_categories_pictures_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

INSERT INTO galette_lend_categories_pictures_new (category_id, picture, format)
SELECT p.category_id, p.picture, p.format
FROM galette_lend_categories_pictures p
INNER JOIN galette_lend_category c ON c.category_id = p.category_id;

DROP TABLE galette_lend_rents, galette_lend_objects, galette_lend_pictures, galette_lend_categories_pictures;

RENAME TABLE galette_lend_rents_new TO galette_lend_rents,
  galette_lend_objects_new TO galette_lend_objects,
  galette_lend_pictures_new TO galette_lend_pictures,
  galette_lend_categories_pictures_new TO galette_lend_categories_pictures;

SET FOREIGN_KEY_CHECKS=1;
