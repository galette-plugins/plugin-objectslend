--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, value_text, date_creation, date_modification)
values
('OBJECTS_PER_PAGE_NUMBER_LIST', 0, 1, 0, '10;20;30;40;50;100;150;200;300;500', NOW(), NOW());

insert into galette_lend_parameters
(code, is_date, is_text, is_numeric, nb_digits, value_numeric, date_creation, date_modification)
values
('OBJECTS_PER_PAGE_DEFAULT', 0, 0, 1, 0, 30, NOW(), NOW());
