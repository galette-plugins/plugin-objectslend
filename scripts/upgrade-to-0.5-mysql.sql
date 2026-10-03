--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

CREATE TABLE IF NOT EXISTS galette_lend_categories_pictures (
  category_id int(11) NOT NULL,
  picture mediumblob NOT NULL,
  format varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ;

-- Add fullsize parameter
INSERT INTO galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
VALUES
('VIEW_FULLSIZE', 0, 0, 1, 0, 1, NOW(), NOW());

DELETE FROM galette_lend_parameters WHERE code='VIEW_CATEGORY_THUMB';
DELETE FROM galette_lend_parameters WHERE code='VIEW_OBJECT_THUMB';
DELETE FROM galette_lend_parameters WHERE code='OBJECTS_PER_PAGE_NUMBER_LIST';
DELETE FROM galette_lend_parameters WHERE code='OBJECTS_PER_PAGE_DEFAULT';
DELETE FROM galette_lend_parameters WHERE code='VIEW_NAME';

ALTER TABLE galette_lend_rents DROP FOREIGN KEY FK_rent_adherent_1;
ALTER TABLE galette_lend_rents ADD CONSTRAINT FK_rent_adherent_1 FOREIGN KEY (adherent_id) REFERENCES galette_adherents (id_adh) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE galette_lend_objects ADD rent_id INT(10) UNSIGNED NULL DEFAULT NULL;
ALTER TABLE galette_lend_objects ADD CONSTRAINT FK_object_rent_1 FOREIGN KEY (rent_id) REFERENCES galette_lend_rents (rent_id) ON DELETE NO ACTION ON UPDATE NO ACTION;

UPDATE galette_lend_objects SET galette_lend_objects.rent_id = (SELECT MAX(rent_id) FROM galette_lend_rents WHERE galette_lend_rents.object_id=galette_lend_objects.object_id);

ALTER TABLE galette_lend_status CHANGE is_home_location in_stock tinyint(1) NOT NULL;
