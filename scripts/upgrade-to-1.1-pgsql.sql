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
  'pref_objectslend_' || LOWER(code),
  CASE WHEN is_text THEN COALESCE(value_text, '')
    ELSE CAST(CAST(ROUND(COALESCE(value_numeric, 0)) AS integer) AS text) END
FROM galette_lend_parameters
WHERE code IN (
    'ENABLE_MEMBER_RENT_OBJECT', 'AUTO_GENERATE_CONTRIBUTION', 'GENERATED_CONTRIBUTION_TYPE_ID',
    'GENERATED_CONTRIB_INFO_TEXT', 'THUMB_MAX_WIDTH', 'THUMB_MAX_HEIGHT', 'VIEW_THUMBNAIL',
    'VIEW_CATEGORY', 'VIEW_DATE_FORECAST', 'VIEW_DESCRIPTION', 'VIEW_DIMENSION', 'VIEW_LEND_PRICE',
    'VIEW_LIST_PRICE_SUM', 'VIEW_PRICE', 'VIEW_SERIAL', 'VIEW_WEIGHT'
);

DROP TABLE galette_lend_parameters;
DROP SEQUENCE IF EXISTS galette_lend_parameters_id_seq;

-- Amounts and weight were stored as floating point numbers
ALTER TABLE galette_lend_objects
  ALTER COLUMN price TYPE numeric(15,3) USING ROUND(CAST(price AS numeric), 3),
  ALTER COLUMN weight TYPE numeric(15,3) USING ROUND(CAST(weight AS numeric), 3),
  ALTER COLUMN rent_price TYPE numeric(15,3) USING ROUND(CAST(rent_price AS numeric), 3);

-- A rent always has an object and a status
UPDATE galette_lend_objects SET rent_id = NULL WHERE rent_id IN (
  SELECT rent_id FROM galette_lend_rents WHERE object_id IS NULL OR status_id IS NULL
);
DELETE FROM galette_lend_rents WHERE object_id IS NULL OR status_id IS NULL;
ALTER TABLE galette_lend_rents
  ALTER COLUMN object_id SET NOT NULL,
  ALTER COLUMN status_id SET NOT NULL;

-- Same foreign keys as MySQL
ALTER TABLE galette_lend_rents
  DROP CONSTRAINT IF EXISTS galette_lend_rents_object_fkey,
  DROP CONSTRAINT IF EXISTS galette_lend_rents_status_id_fkey,
  DROP CONSTRAINT IF EXISTS galette_lend_rents_adherent_id_fkey,
  ADD CONSTRAINT galette_lend_rents_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT galette_lend_rents_status_id_fkey FOREIGN KEY (status_id)
    REFERENCES galette_lend_status (status_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT galette_lend_rents_adherent_id_fkey FOREIGN KEY (adherent_id)
    REFERENCES galette_adherents (id_adh) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE galette_lend_objects
  DROP CONSTRAINT IF EXISTS galette_lend_objects_category_id_fkey,
  DROP CONSTRAINT IF EXISTS galette_lend_objects_rent_id_fkey,
  ADD CONSTRAINT galette_lend_objects_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT galette_lend_objects_rent_id_fkey FOREIGN KEY (rent_id)
    REFERENCES galette_lend_rents (rent_id) ON DELETE RESTRICT ON UPDATE CASCADE;

CREATE INDEX IF NOT EXISTS galette_lend_rents_date_begin_idx ON galette_lend_rents (date_begin);

-- Pictures had no foreign key: those of removed objects or categories are dropped
DELETE FROM galette_lend_pictures
  WHERE object_id NOT IN (SELECT object_id FROM galette_lend_objects);
ALTER TABLE galette_lend_pictures
  ALTER COLUMN object_id DROP DEFAULT,
  ADD CONSTRAINT galette_lend_pictures_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE CASCADE ON UPDATE CASCADE;

DELETE FROM galette_lend_categories_pictures
  WHERE category_id NOT IN (SELECT category_id FROM galette_lend_category);
ALTER TABLE galette_lend_categories_pictures
  ALTER COLUMN category_id DROP DEFAULT,
  ADD CONSTRAINT galette_lend_categories_pictures_category_id_fkey FOREIGN KEY (category_id)
    REFERENCES galette_lend_category (category_id) ON DELETE CASCADE ON UPDATE CASCADE;
