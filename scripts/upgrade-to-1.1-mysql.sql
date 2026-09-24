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
    'GENERATED_CONTRIB_INFO_TEXT', 'THUMB_MAX_WIDTH', 'THUMB_MAX_HEIGHT', 'VIEW_THUMBNAIL', 'VIEW_FULLSIZE',
    'VIEW_CATEGORY', 'VIEW_DATE_FORECAST', 'VIEW_DESCRIPTION', 'VIEW_DIMENSION', 'VIEW_LEND_PRICE',
    'VIEW_LIST_PRICE_SUM', 'VIEW_PRICE', 'VIEW_SERIAL', 'VIEW_WEIGHT'
);

DROP TABLE galette_lend_parameters;
