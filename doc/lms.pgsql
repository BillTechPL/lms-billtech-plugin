/* $Id$ */

BEGIN;
DROP TABLE IF EXISTS billtech_payments;
DROP TABLE IF EXISTS billtech_info;

DROP SEQUENCE IF EXISTS billtech_payments_id_seq;
CREATE SEQUENCE billtech_payments_id_seq;
CREATE TABLE billtech_payments
(
	id               SERIAL                                                      PRIMARY KEY,
	ten              VARCHAR(16) DEFAULT ''                                      NOT NULL,
	customerid       INTEGER      DEFAULT NULL,
	amount           NUMERIC(9, 2) DEFAULT 0                                     NOT NULL,
	title            TEXT DEFAULT ''                                             NOT NULL,
	document_number  VARCHAR(255) DEFAULT '',
	reference_number VARCHAR(255) DEFAULT '',
	cdate            INTEGER DEFAULT 0                                           NOT NULL,
	closed           SMALLINT DEFAULT 0                                          NOT NULL,
	cashid           INTEGER                                                     NULL
);

CREATE TABLE billtech_info
(
	keytype  VARCHAR(255) PRIMARY KEY,
	keyvalue VARCHAR(255)
);

CREATE TABLE billtech_log
(
	cdate       INT DEFAULT 0           NOT NULL,
	type   VARCHAR(255) DEFAULT '' NOT NULL,
	description TEXT                    NOT NULL
);

INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'isp_id', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'payment_url', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_url', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_key', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'api_secret', '');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'payment_expiration', 5);
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'private_key', 'plugins/BillTech/lms.pem');
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'cashimport_enabled', true);
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'manage_cutoff', true);
INSERT INTO uiconfig (section, var, value) VALUES ('billtech', 'append_customer_info', true);

INSERT INTO billtech_info (keytype, keyvalue) VALUES ('last_sync', 0);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('current_sync', 0);


create table billtech_payment_links (
    id serial primary key,
    customer_id integer not null references customers(id) on delete cascade,
    src_cash_id integer references cash(id) on delete cascade,
    src_document_id integer references documents(id) on delete cascade,
    type varchar(255) not null,
    link varchar(2000) not null,
    short_link varchar(160),
    token varchar(1000) not null,
    amount numeric(9,2) not null
);

create index billtech_payment_links__customer_id on billtech_payment_links (customer_id);
create index billtech_payment_links__src_cash_id on billtech_payment_links (src_cash_id);
create index billtech_payment_links__token on billtech_payment_links (token);


alter table billtech_payments add column token varchar(1000);

create index billtech_payments__reference_number on billtech_payments(reference_number);
create index billtech_payments__closed_cdate on billtech_payments(closed, cdate);
create index billtech_payments__token on billtech_payments(token);

create table billtech_customer_info
(
    customer_id     	int    primary key,
    last_cash_id	 	int
);

create index billtech_customer_info__customer_id on billtech_customer_info (customer_id);


INSERT INTO dbinfo (keytype, keyvalue) VALUES ('dbversion_BillTech', '2020091900');
COMMIT;