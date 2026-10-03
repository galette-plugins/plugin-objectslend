--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

CREATE TABLE IF NOT EXISTS `galette_lend_parameters` (
  `parameter_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8_general_ci NOT NULL,
  `is_date` tinyint(1) NOT NULL,
  `value_date` date DEFAULT NULL,
  `is_text` tinyint(1) NOT NULL,
  `value_text` varchar(300) COLLATE utf8_general_ci DEFAULT NULL,
  `is_numeric` tinyint(1) NOT NULL,
  `nb_digits` int(11) DEFAULT NULL,
  `value_numeric` double DEFAULT NULL,
  `date_creation` datetime NOT NULL,
  `date_modification` datetime NOT NULL,
  PRIMARY KEY (`parameter_id`)
) ;

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_CATEGORY', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_SERIAL', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_THUMBNAIL', 0, 0, 1, 0, 1, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_NAME', 0, 0, 1, 0, 1, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_DESCRIPTION', 0, 0, 1, 0, 1, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_PRICE', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_DIMENSION', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_WEIGHT', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_LEND_PRICE', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_CATEGORY_THUMB', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_OBJECT_THUMB', 0, 0, 1, 0, 0, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('THUMB_MAX_WIDTH', 0, 0, 1, 0, 128, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('THUMB_MAX_HEIGHT', 0, 0, 1, 0, 128, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('AUTO_GENERATE_CONTRIBUTION', 0, 0, 1, 0, 1, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('GENERATED_CONTRIBUTION_TYPE_ID', 0, 0, 1, 0, 5, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, value_text, date_creation, date_modification)
values
('GENERATED_CONTRIB_INFO_TEXT', 0, 1, 0, 'Location de {NAME} {DESCRIPTION} {SERIAL_NUMBER}', NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('ENABLE_MEMBER_RENT_OBJECT', 0, 0, 1, 0, 1, NOW(), NOW());

--
-- MIse à jour de la table `galette_lend_objects`
--

ALTER TABLE `galette_lend_objects` ADD `category_id` INT( 10 ) UNSIGNED NULL ,
ADD `rent_price` DECIMAL( 15, 3 ) NULL ,
ADD `nb_available` INT NULL ;

--
-- Structure de la table `vm_lend_category`
--

CREATE TABLE IF NOT EXISTS `galette_lend_category` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  PRIMARY KEY (`category_id`)
) ;


--
-- Contraintes pour la table `galette_lend_rents`
--

ALTER TABLE galette_lend_objects
  ADD CONSTRAINT FK_rent_category_1 FOREIGN KEY (category_id) REFERENCES galette_lend_category (category_id) ON DELETE NO ACTION ON UPDATE NO ACTION;

