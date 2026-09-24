--
-- This file is part of Galette Objects Lend plugin (https://galette.eu).
-- SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
-- SPDX-License-Identifier: GPL-3.0-or-later
--

DROP SEQUENCE IF EXISTS galette_lend_objects_id_seq;
CREATE SEQUENCE galette_lend_objects_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for lend rents
DROP SEQUENCE IF EXISTS galette_lend_rents_id_seq;
CREATE SEQUENCE galette_lend_rents_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for lend status
DROP SEQUENCE IF EXISTS galette_lend_status_id_seq;
CREATE SEQUENCE galette_lend_status_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;

-- sequence for lend cetegories
DROP SEQUENCE IF EXISTS galette_lend_category_id_seq;
CREATE SEQUENCE galette_lend_category_id_seq
    START 1
    INCREMENT 1
    MAXVALUE 2147483647
    MINVALUE 1
    CACHE 1;


-- Schema
-- REMINDER: Create order IS important, dependencies first !!
DROP TABLE IF EXISTS galette_lend_category CASCADE;
CREATE TABLE galette_lend_category (
    category_id integer DEFAULT nextval('galette_lend_category_id_seq'::text) NOT NULL,
    name character varying(100) NOT NULL,
    is_active boolean NOT NULL,
    PRIMARY KEY (category_id)
);


DROP TABLE IF EXISTS galette_lend_status CASCADE;
CREATE TABLE galette_lend_status (
    status_id integer DEFAULT nextval('galette_lend_status_id_seq'::text) NOT NULL,
    status_text character varying(100) NOT NULL,
    in_stock boolean NOT NULL,
    is_active boolean NOT NULL,
    rent_day_number integer NULL DEFAULT NULL,
    PRIMARY KEY (status_id)
);


DROP TABLE IF EXISTS galette_lend_rents CASCADE;
CREATE TABLE galette_lend_rents (
    rent_id integer DEFAULT nextval('galette_lend_rents_id_seq'::text) NOT NULL,
    object_id integer NOT NULL,
    date_begin timestamp NOT NULL,
    date_forecast timestamp NULL DEFAULT NULL,
    date_end timestamp DEFAULT NULL,
    status_id integer NOT NULL,
    adherent_id integer,
    comments character varying(200) NOT NULL,
    PRIMARY KEY (rent_id),
    CONSTRAINT galette_lend_rents_status_id_fkey FOREIGN KEY (status_id)
      REFERENCES galette_lend_status (status_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT galette_lend_rents_adherent_id_fkey FOREIGN KEY (adherent_id)
      REFERENCES galette_adherents (id_adh) ON DELETE SET NULL ON UPDATE CASCADE
);
CREATE INDEX galette_lend_rents_date_begin_idx ON galette_lend_rents (date_begin);



DROP TABLE IF EXISTS galette_lend_objects CASCADE;
CREATE TABLE galette_lend_objects (
    object_id integer DEFAULT nextval('galette_lend_objects_id_seq'::text) NOT NULL,
    name character varying(100) NOT NULL,
    description text NOT NULL,
    serial_number character varying(30) NOT NULL,
    price numeric(15,3) NOT NULL,
    price_per_day boolean NOT NULL DEFAULT FALSE,
    dimension character varying(100) NOT NULL,
    weight numeric(15,3) NOT NULL,
    is_active boolean NOT NULL,
    category_id integer,
    rent_price numeric(15,3) NULL,
    nb_available integer NULL,
    rent_id integer,
    PRIMARY KEY (object_id),
    CONSTRAINT galette_lend_objects_category_id_fkey FOREIGN KEY (category_id)
      REFERENCES galette_lend_category (category_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT galette_lend_objects_rent_id_fkey FOREIGN KEY (rent_id)
      REFERENCES galette_lend_rents (rent_id) ON DELETE RESTRICT ON UPDATE CASCADE
);

ALTER TABLE galette_lend_rents ADD CONSTRAINT galette_lend_rents_object_id_fkey FOREIGN KEY (object_id)
  REFERENCES galette_lend_objects (object_id) ON DELETE RESTRICT ON UPDATE CASCADE;

DROP TABLE IF EXISTS galette_lend_pictures;
CREATE TABLE galette_lend_pictures (
  object_id integer NOT NULL,
  picture bytea NOT NULL,
  format character varying(10) DEFAULT '' NOT NULL,
  PRIMARY KEY (object_id),
  CONSTRAINT galette_lend_pictures_object_id_fkey FOREIGN KEY (object_id)
    REFERENCES galette_lend_objects (object_id) ON DELETE CASCADE ON UPDATE CASCADE
);



DROP TABLE IF EXISTS galette_lend_categories_pictures;
CREATE TABLE galette_lend_categories_pictures (
    category_id integer NOT NULL,
    picture bytea NOT NULL,
    format character varying(10) DEFAULT '' NOT NULL,
    PRIMARY KEY (category_id),
    CONSTRAINT galette_lend_categories_pictures_category_id_fkey FOREIGN KEY (category_id)
      REFERENCES galette_lend_category (category_id) ON DELETE CASCADE ON UPDATE CASCADE
);


-- Statuses example data
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Garage A (exemple)', TRUE, TRUE);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Maison B (exemple)', TRUE, TRUE);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Bibliotheque C (exemple)', TRUE, TRUE);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location courte durée (exemple)', FALSE, TRUE, 7);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Location longue durée (exemple)', FALSE, TRUE, 30);
INSERT INTO galette_lend_status (status_text, in_stock, is_active, rent_day_number) VALUES('Reparation (exemple)', FALSE, TRUE, 14);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Vendu (exemple)', FALSE, TRUE);
INSERT INTO galette_lend_status (status_text, in_stock, is_active) VALUES('Detruit (exemple)', FALSE, TRUE);
