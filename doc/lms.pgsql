/* $Id$ */

BEGIN;
DROP TABLE IF EXISTS billtech_payments;
DROP TABLE IF EXISTS billtech_info;
DROP SEQUENCE IF EXISTS billtech_payments_id_seq;

CREATE SEQUENCE billtech_payments_id_seq;

CREATE TABLE billtech_payments (
  id SERIAL PRIMARY KEY, 
  ten VARCHAR(16) DEFAULT '' NOT NULL, 
  customerid INTEGER, 
  amount NUMERIC(9, 2) DEFAULT 0 NOT NULL, 
  title TEXT DEFAULT '' NOT NULL, 
  document_number VARCHAR(255) DEFAULT '', 
  reference_number VARCHAR(255) DEFAULT '', 
  cdate INTEGER DEFAULT 0 NOT NULL, 
  closed SMALLINT DEFAULT 0 NOT NULL, 
  cashid INTEGER
);

CREATE TABLE billtech_info (
  keytype VARCHAR(255) PRIMARY KEY, 
  keyvalue VARCHAR(255)
);

CREATE TABLE billtech_log (
  cdate INTEGER DEFAULT 0 NOT NULL, 
  type VARCHAR(255) DEFAULT '' NOT NULL, 
  description TEXT NOT NULL
);

INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'isp_id', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'payment_url', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_url', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_key', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_secret', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'payment_expiration', 'never');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'private_key', 'plugins/BillTech/lms.pem');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'cashimport_enabled', true);
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'manage_cutoff', true);
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'append_customer_info', true);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('last_sync', 0);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('current_sync', 0);

CREATE TABLE billtech_payment_links (
  id serial PRIMARY KEY, 
  customer_id integer not null references customers(id) on delete cascade, 
  src_cash_id integer references cash(id) on delete 
  set 
    null, 
    src_document_id integer references documents(id) on delete 
  set 
    null, 
    type varchar(255) not null, 
    link varchar(2000) not null, 
    short_link varchar(160), 
    token varchar(1000) not null, 
    amount numeric(9, 2) not null
);

CREATE INDEX billtech_payment_links__customer_id ON billtech_payment_links (customer_id);
CREATE INDEX billtech_payment_links__src_cash_id ON billtech_payment_links (src_cash_id);
CREATE INDEX billtech_payment_links__token ON billtech_payment_links (token);

ALTER TABLE billtech_payments ADD COLUMN token varchar(1000);

CREATE INDEX billtech_payments__reference_number ON billtech_payments(reference_number);
CREATE INDEX billtech_payments__closed_cdate ON billtech_payments(closed, cdate);
CREATE INDEX billtech_payments__token ON billtech_payments(token);
CREATE TABLE billtech_customer_info (customer_id integer PRIMARY KEY, last_cash_id integer);
CREATE INDEX billtech_customer_info__customer_id on billtech_customer_info (customer_id);

INSERT INTO dbinfo (keytype, keyvalue) VALUES ('dbversion_BillTech', '2022012000');

COMMIT;
