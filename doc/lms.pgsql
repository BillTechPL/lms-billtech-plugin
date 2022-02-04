/* $Id$ */

BEGIN;
DROP TABLE IF EXISTS billtech_payments;
DROP TABLE IF EXISTS billtech_info;

DROP SEQUENCE IF EXISTS billtech_payments_id_seq;
CREATE SEQUENCE billtech_payments_id_seq;
CREATE TABLE billtech_payments
(
	id               INTEGER DEFAULT nextval('billtech_payments_id_seq' :: TEXT) NOT NULL,
	ten              VARCHAR(16) DEFAULT ''                                      NOT NULL,
	customerid       INTEGER      DEFAULT NULL,
	amount           NUMERIC(9, 2) DEFAULT 0                                     NOT NULL,
	title            TEXT DEFAULT ''                                             NOT NULL,
	document_number  VARCHAR(255) DEFAULT '',
	reference_number VARCHAR(255) DEFAULT '',
	cdate            INTEGER DEFAULT 0                                           NOT NULL,
	closed           SMALLINT DEFAULT 0                                          NOT NULL,
	cashid           INTEGER                                                     NULL,
	UNIQUE (reference_number, customerid)
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
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('last_sync', 0);
INSERT INTO billtech_info (keytype, keyvalue) VALUES ('current_sync', 0);
INSERT INTO dbinfo (keytype, keyvalue) VALUES ('dbversion_BillTech', '2017112400');
COMMIT;
