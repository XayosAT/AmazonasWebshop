DROP TABLE amazonas_webshop.ordered_products;
DROP TABLE amazonas_webshop.product;
DROP TABLE amazonas_webshop.receipt;
DROP TABLE amazonas_webshop.user;
DROP TABLE amazonas_webshop.person;
DROP TABLE amazonas_webshop.address;

DELETE FROM amazonas_webshop.ordered_products WHERE true;
DELETE FROM amazonas_webshop.product  WHERE true;
DELETE FROM amazonas_webshop.receipt WHERE true;
DELETE FROM amazonas_webshop.user WHERE true;
DELETE FROM amazonas_webshop.person WHERE true;
DELETE FROM amazonas_webshop.address WHERE true;