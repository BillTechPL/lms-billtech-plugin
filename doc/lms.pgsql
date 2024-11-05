/* $Id$ */

BEGIN;
DROP TABLE IF EXISTS billtech_payments;
DROP TABLE IF EXISTS billtech_info;
DROP TABLE IF EXISTS billtech_log;
DROP TABLE IF EXISTS billtech_payment_links;
DROP TABLE IF EXISTS billtech_customer_info;

DROP SEQUENCE IF EXISTS billtech_payments_id_seq;
DROP SEQUENCE IF EXISTS billtech_payment_links_id_seq;

CREATE TABLE billtech_payments (
  id serial PRIMARY KEY,
  ten varchar(16) DEFAULT '' NOT NULL,
  customerid integer,
  amount numeric(9, 2) DEFAULT 0 NOT NULL,
  title text DEFAULT '' NOT NULL,
  document_number varchar(255) DEFAULT '',
  reference_number varchar(255) DEFAULT '',
  cdate integer DEFAULT 0 NOT NULL,
  closed smallint DEFAULT 0 NOT NULL,
  cashid integer
      CONSTRAINT billtech_payment__cashid_fkey REFERENCES cash(id) ON DELETE SET NULL,
  token varchar(1000)
);

CREATE TABLE billtech_info (
  keytype varchar(255) PRIMARY KEY,
  keyvalue varchar(255)
);

CREATE TABLE billtech_log (
  cdate integer DEFAULT 0 NOT NULL,
  type varchar(255) DEFAULT '' NOT NULL,
  description TEXT NOT NULL
);

CREATE TABLE billtech_payment_links (
  id serial PRIMARY KEY,
  customer_id integer NOT NULL
      CONSTRAINT billtech_payment_links__customer_id_fkey REFERENCES customers(id) ON DELETE CASCADE,
  src_cash_id integer
      CONSTRAINT billtech_payment_links__src_cash_id_fkey REFERENCES cash(id) ON DELETE SET NULL,
  src_document_id integer
      CONSTRAINT billtech_payment_links__src_document_id_fkey REFERENCES documents(id) ON DELETE SET NULL,
  type varchar(255) NOT NULL,
  link varchar(2000) NOT NULL,
  short_link varchar(160),
  token varchar(1000) NOT NULL,
  amount numeric(9, 2) NOT NULL
);

CREATE TABLE billtech_customer_info (
    customer_id integer PRIMARY KEY,
    last_cash_id integer
);

CREATE INDEX billtech_payment_links__customer_id ON billtech_payment_links (customer_id);
CREATE INDEX billtech_payment_links__src_cash_id ON billtech_payment_links (src_cash_id);
CREATE INDEX billtech_payment_links__token ON billtech_payment_links (token);

CREATE INDEX billtech_payments__reference_number ON billtech_payments(reference_number);
CREATE INDEX billtech_payments__closed_cdate ON billtech_payments(closed, cdate);
CREATE INDEX billtech_payments__token ON billtech_payments(token);

CREATE INDEX billtech_customer_info__customer_id ON billtech_customer_info (customer_id);

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
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'log_retention_days', 7);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('last_sync', 0);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('current_sync', 0);

INSERT INTO dbinfo (keytype, keyvalue) VALUES ('dbversion_BillTech', '2023091200');

COMMIT;
