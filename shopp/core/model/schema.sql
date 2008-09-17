DROP TABLE IF EXISTS shopp_setting;
CREATE TABLE shopp_setting (
	id bigint(20) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	value longtext NOT NULL default '',
	autoload enum('on','off') NOT NULL,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_product;
CREATE TABLE shopp_product (
	id bigint(20) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	slug varchar(255) NOT NULL default '',
	summary text NOT NULL default '',
	description longtext NOT NULL default '',
	featured enum('off','on') NOT NULL default 'off',
	variations enum('off','on') NOT NULL default 'off',
	options text NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY slug (slug),
	FULLTEXT search (name,summary,description)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_price;
CREATE TABLE shopp_price (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	options text NOT NULL default '',
	optionkey bigint(20) unsigned NOT NULL default '0',
	label varchar(100) NOT NULL default '',
	context enum('product','variation','addon') NOT NULL default 'product',
	type enum('Shipped','Download','Donation','N/A') NOT NULL default 'Shipped',
	sku varchar(100) NOT NULL default '',
	price float(20,2) NOT NULL default '0.00',
	saleprice float(20,2) NOT NULL default '0.00',
	weight int(10) NOT NULL default '0',
	shipfee int(10) NOT NULL default '0',
	stock int(10) NOT NULL default '0',
	inventory enum('off','on') NOT NULL default 'off',
	sale enum('off','on') NOT NULL default 'off',
	shipping enum('off','on') NOT NULL default 'on',
	tax enum('off','on') NOT NULL default 'on',
	sortorder int(10) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY product (product),
	KEY context (context)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_spec;
CREATE TABLE shopp_spec (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	name varchar(255) NOT NULL default '',
	content text NOT NULL default '',
	sortorder int(10) unsigned NOT NULL default '0',
	PRIMARY KEY id (id),
	KEY product (product,name),
	FULLTEXT name (name,content)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_category;
CREATE TABLE shopp_category (
	id bigint(20) unsigned NOT NULL auto_increment,
	parent bigint(20) unsigned NOT NULL default '0',
	name varchar(255) NOT NULL default '',
	slug varchar(64) NOT NULL default '',
	uri varchar(255) NOT NULL default '',
	description text NOT NULL,
	specs text NOT NULL,
	options text NOT NULL,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY parent (parent)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_tag;
CREATE TABLE shopp_tag (
	id bigint(20) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_catalog;
CREATE TABLE shopp_catalog (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	category bigint(20) unsigned NOT NULL default '0',
	tag bigint(20) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY category (category),
	KEY tag (tag)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_asset;
CREATE TABLE shopp_asset (
	id bigint(20) unsigned NOT NULL auto_increment,
	parent bigint(20) unsigned NOT NULL default '0',
	context enum('product','price','category') NOT NULL default 'product',
	src bigint(20) unsigned NOT NULL default '0',
	name varchar(255) NOT NULL default '',
	value varchar(255) NOT NULL default '',
	properties text NOT NULL default '',
	size bigint(20) unsigned NOT NULL default '0',
	data longblob NOT NULL default '',
	datatype enum('metadata','image','small','thumbnail','download') NOT NULL default 'metadata',
	sortorder int(10) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY parent (parent,context),
	KEY src (src)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_cart;
CREATE TABLE shopp_cart (
	session varchar(32) NOT NULL,
	customer bigint(20) unsigned NOT NULL default '0',
	ip varchar(15) NOT NULL default '0.0.0.0',
	data longtext NOT NULL default '',
	contents longtext NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY session (session),
	KEY customer (customer)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_customer;
CREATE TABLE shopp_customer (
	id bigint(20) unsigned NOT NULL auto_increment,
	firstname varchar(32) NOT NULL default '',
	lastname varchar(32) NOT NULL default '',
	email varchar(96) NOT NULL default '',
	phone varchar(24) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_shipping;
CREATE TABLE shopp_shipping (
	id bigint(20) unsigned NOT NULL auto_increment,
	customer bigint(20) unsigned NOT NULL default '0',
	address varchar(100) NOT NULL default '',
	xaddress varchar(100) NOT NULL default '',
	city varchar(100) NOT NULL default '',
	state varchar(2) NOT NULL default '',
	country varchar(2) NOT NULL default '',
	postcode varchar(10) NOT NULL default '',
	geocode varchar(16) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_billing;
CREATE TABLE shopp_billing (
	id bigint(20) unsigned NOT NULL auto_increment,
	customer bigint(20) unsigned NOT NULL default '0',
	card varchar(4) NOT NULL default '',
	cardtype varchar(32) NOT NULL default '',
	cardexpires date NOT NULL default '0000-00-00',
	cardholder varchar(96) NOT NULL default '',
	address varchar(100) NOT NULL default '',
	xaddress varchar(100) NOT NULL default '',
	city varchar(100) NOT NULL default '',
	state varchar(2) NOT NULL default '',
	country varchar(2) NOT NULL default '',
	postcode varchar(10) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_purchase;
CREATE TABLE shopp_purchase (
	id bigint(20) unsigned NOT NULL auto_increment,
	customer bigint(20) unsigned NOT NULL default '0',
	shipping bigint(20) unsigned NOT NULL default '0',
	billing bigint(20) unsigned NOT NULL default '0',
	currency bigint(20) unsigned NOT NULL default '0',
	ip varchar(15) NOT NULL default '0.0.0.0',
	firstname varchar(32) NOT NULL default '',
	lastname varchar(32) NOT NULL default '',
	email varchar(96) NOT NULL default '',
	phone varchar(24) NOT NULL default '',
	card varchar(4) NOT NULL default '',
	cardtype varchar(32) NOT NULL default '',
	cardexpires date NOT NULL default '0000-00-00',
	cardholder varchar(96) NOT NULL default '',
	address varchar(100) NOT NULL default '',
	xaddress varchar(100) NOT NULL default '',
	city varchar(100) NOT NULL default '',
	state varchar(2) NOT NULL default '',
	country varchar(2) NOT NULL default '',
	postcode varchar(10) NOT NULL default '',
	shipaddress varchar(100) NOT NULL default '',
	shipxaddress varchar(100) NOT NULL default '',
	shipcity varchar(100) NOT NULL default '',
	shipstate varchar(2) NOT NULL default '',
	shipcountry varchar(2) NOT NULL default '',
	shippostcode varchar(10) NOT NULL default '',
	geocode varchar(16) NOT NULL default '',
	subtotal float(20,2) NOT NULL default '0.00',
	freight float(20,2) NOT NULL default '0.00',
	tax float(20,2) NOT NULL default '0.00',
	total float(20,2) NOT NULL default '0.00',
	discount float(20,2) NOT NULL default '0.00',
	fees float(20,2) NOT NULL default '0.00',
	transactionid varchar(64) NOT NULL default '',
	transtatus varchar(64) NOT NULL default '',
	gateway varchar(64) NOT NULL default '',
	shipmethod varchar(100) NOT NULL default '',
	shiptrack varchar(100) NOT NULL default '',
	status tinyint(3) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_purchased;
CREATE TABLE shopp_purchased (
	id bigint(20) unsigned NOT NULL auto_increment,
	purchase bigint(20) unsigned NOT NULL default '0',
	product bigint(20) unsigned NOT NULL default '0',
	price bigint(20) unsigned NOT NULL default '0',
	download bigint(20) unsigned NOT NULL default '0',
	dkey varchar(255) NOT NULL default '',
	name varchar(255) NOT NULL default '',
	description text NOT NULL default '',
	optionlabel varchar(255) NOT NULL default '',
	sku varchar(100) NOT NULL default '',
	quantity int(10) unsigned NOT NULL default '0',
	downloads int(10) unsigned NOT NULL default '0',
	unitprice float(20,2) NOT NULL default '0.00',
	shipping float(20,2) NOT NULL default '0.00',
	total float(20,2) NOT NULL default '0.00',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY purchase (purchase),
	KEY dkey (dkey(8))
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_promo;
CREATE TABLE shopp_promo (
	id bigint(20) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	status enum('disabled','enabled') default 'disabled',
	type enum('Percentage Off','Amount Off','Free Shipping','Buy X Get Y Free') default 'Percentage Off',
	scope enum('Item','Order') default 'Item',
	discount float(20,2) NOT NULL default '0.00',
	buyqty int(10) NOT NULL default '0',
	getqty int(10) NOT NULL default '0',
	search enum('all','any') default 'all',
	code varchar(255) NOT NULL default '',
	rules text NOT NULL,
	starts datetime NOT NULL default '0000-00-00 00:00:00',
	ends datetime NOT NULL default '0000-00-00 00:00:00',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS shopp_discount;
CREATE TABLE shopp_discount (
	id bigint(20) unsigned NOT NULL auto_increment,
	promo bigint(20) unsigned NOT NULL default '0',
	product bigint(20) unsigned NOT NULL default '0',
	price bigint(20) unsigned NOT NULL default '0',
	PRIMARY KEY id (id),
	KEY lookup (product,price)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

