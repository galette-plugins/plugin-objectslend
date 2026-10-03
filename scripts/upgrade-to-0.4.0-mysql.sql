--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

ALTER TABLE `galette_lend_status` CHANGE `is_galette_location` `is_home_location` TINYINT(1) NOT NULL;
ALTER TABLE `galette_lend_status` ADD `rent_day_number` INT NULL DEFAULT NULL ;
ALTER TABLE `galette_lend_rents` ADD `date_forecast` DATETIME NULL DEFAULT NULL AFTER `date_begin`;
ALTER TABLE `galette_lend_objects` ADD `price_per_day` BOOLEAN NOT NULL DEFAULT FALSE AFTER `price`;

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_DATE_FORECAST', 0, 0, 1, 0, 1, NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('VIEW_LIST_PRICE_SUM', 0, 0, 1, 0, 0, NOW(), NOW());
