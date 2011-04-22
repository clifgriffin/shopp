<?php $meta = DatabaseObject::tablename('meta'); ?>
DROP TABLE IF EXISTS <?php echo $meta; ?>;
CREATE TABLE <?php echo $meta; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	parent bigint(20) unsigned NOT NULL default '0',
	context varchar(16) NOT NULL default 'product',
	type varchar(16) NOT NULL default 'meta',
	name varchar(255) NOT NULL default '',
	value longtext NOT NULL,
	numeral decimal(16,6) NOT NULL default '0.0000',
	sortorder int(10) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY name (name),
	KEY lookup (parent,context,type)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $product = DatabaseObject::tablename('product'); ?>
DROP TABLE IF EXISTS <?php echo $product; ?>;
CREATE TABLE <?php echo $product; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	post bigint(20) unsigned NOT NULL default '0',
	featured enum('off','on') NOT NULL,
	variations enum('off','on') NOT NULL,
	addons enum('off','on') NOT NULL,
	inventory enum('off','on') NOT NULL,
	sale enum('off','on') NOT NULL,
	maxprice decimal(16,6) NOT NULL default '0.00',
	minprice decimal(16,6) NOT NULL default '0.00',
	stock int(10) NOT NULL default '0',
	sold bigint(20) NOT NULL default '0',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY featured (featured,post),
	KEY lowprice (minprice,post),
	KEY oldest (publish,post),
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $price = DatabaseObject::tablename('price'); ?>
DROP TABLE IF EXISTS <?php echo $price; ?>;
CREATE TABLE <?php echo $price; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	options text NOT NULL,
	optionkey bigint(20) unsigned NOT NULL default '0',
	label varchar(100) NOT NULL default '',
	context enum('product','variant','addon') NOT NULL,
	type enum('Shipped','Virtual','Download','Donation','Subscription','Membership','N/A') NOT NULL,
	sku varchar(100) NOT NULL default '',
	price decimal(16,6) NOT NULL default '0.00',
	saleprice decimal(16,6) NOT NULL default '0.00',
	promoprice decimal(16,6) NOT NULL default '0.00',
	weight decimal(12,6) NOT NULL default '0',
	dimensions varchar(255) NOT NULL default '0',
	shipfee decimal(12,6) NOT NULL default '0',
	stock int(10) NOT NULL default '0',
	inventory enum('off','on') NOT NULL,
	sale enum('off','on') NOT NULL,
	shipping enum('on','off') NOT NULL,
	tax enum('on','off') NOT NULL,
	donation varchar(255) NOT NULL default '',
	settings text NOT NULL,
	discounts varchar(255) NOT NULL default '',
	sortorder int(10) unsigned NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY product (product),
	KEY catalog (product,context,type,inventory,stock),
	KEY context (context)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $index = DatabaseObject::tablename('index'); ?>
DROP TABLE IF EXISTS <?php echo $index; ?>;
CREATE TABLE <?php echo $index; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	terms longtext NOT NULL,
	factor tinyint(3) unsigned NOT NULL default '0',
	type varchar(16) NOT NULL default 'description',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	FULLTEXT search (terms)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $category = DatabaseObject::tablename('category'); ?>
DROP TABLE IF EXISTS <?php echo $category; ?>;
CREATE TABLE <?php echo $category; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	parent bigint(20) unsigned NOT NULL default '0',
	name varchar(255) NOT NULL default '',
	slug varchar(64) NOT NULL default '',
	uri varchar(255) NOT NULL default '',
	description text NOT NULL,
	spectemplate enum('off','on') NOT NULL,
	facetedmenus enum('off','on') NOT NULL,
	variations enum('off','on') NOT NULL,
	pricerange enum('disabled','auto','custom') NOT NULL,
	priceranges text NOT NULL,
	specs text NOT NULL,
	options text NOT NULL,
	prices text NOT NULL,
	priority int(10) NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY parent (parent)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

<?php $catalog = DatabaseObject::tablename('catalog'); ?>
DROP TABLE IF EXISTS <?php echo $catalog; ?>;
CREATE TABLE <?php echo $catalog; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	product bigint(20) unsigned NOT NULL default '0',
	parent bigint(20) unsigned NOT NULL default '0',
	taxonomy bigint(20) unsigned NOT NULL default '0',
	priority int(10) NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY product (product),
	KEY assignment (parent,taxonomy)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $asset = DatabaseObject::tablename('asset'); ?>
DROP TABLE IF EXISTS <?php echo $asset; ?>;
CREATE TABLE <?php echo $asset; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	data longblob NOT NULL,
	PRIMARY KEY id (id)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $shopping = DatabaseObject::tablename('shopping'); ?>
DROP TABLE IF EXISTS <?php echo $shopping; ?>;
CREATE TABLE <?php echo $shopping; ?> (
	session varchar(32) NOT NULL,
	customer bigint(20) unsigned NOT NULL default '0',
	ip varchar(15) NOT NULL default '0.0.0.0',
	data longtext NOT NULL,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY session (session),
	KEY customer (customer)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $customer = DatabaseObject::tablename('customer'); ?>
DROP TABLE IF EXISTS <?php echo $customer; ?>;
CREATE TABLE <?php echo $customer; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	wpuser bigint(20) unsigned NOT NULL default '0',
	password varchar(64) NOT NULL default '',
	firstname varchar(32) NOT NULL default '',
	lastname varchar(32) NOT NULL default '',
	email varchar(96) NOT NULL default '',
	phone varchar(24) NOT NULL default '',
	company varchar(100) NOT NULL default '',
	marketing enum('yes','no') NOT NULL default 'no',
	activation varchar(20) NOT NULL default '',
	type varchar(100) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY wordpress (wpuser),
	KEY type (type)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $address = DatabaseObject::tablename('address'); ?>
DROP TABLE IF EXISTS <?php echo $address; ?>;
CREATE TABLE <?php echo $address; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	customer bigint(20) unsigned NOT NULL default '0',
	type enum('billing','shipping') NOT NULL default 'billing',
	name varchar(100) NOT NULL default '',
	address varchar(100) NOT NULL default '',
	xaddress varchar(100) NOT NULL default '',
	city varchar(100) NOT NULL default '',
	state varchar(100) NOT NULL default '',
	country varchar(2) NOT NULL default '',
	postcode varchar(10) NOT NULL default '',
	geocode varchar(16) NOT NULL default '',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY ref (customer,type)	
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $purchase = DatabaseObject::tablename('purchase'); ?>
DROP TABLE IF EXISTS <?php echo $purchase; ?>;
CREATE TABLE <?php echo $purchase; ?> (
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
	company varchar(100) NOT NULL default '',
	card varchar(4) NOT NULL default '',
	cardtype varchar(32) NOT NULL default '',
	cardexpires date NOT NULL default '0000-00-00',
	cardholder varchar(96) NOT NULL default '',
	address varchar(100) NOT NULL default '',
	xaddress varchar(100) NOT NULL default '',
	city varchar(100) NOT NULL default '',
	state varchar(100) NOT NULL default '',
	country varchar(2) NOT NULL default '',
	postcode varchar(10) NOT NULL default '',
	shipaddress varchar(100) NOT NULL default '',
	shipxaddress varchar(100) NOT NULL default '',
	shipcity varchar(100) NOT NULL default '',
	shipstate varchar(100) NOT NULL default '',
	shipcountry varchar(2) NOT NULL default '',
	shippostcode varchar(10) NOT NULL default '',
	geocode varchar(16) NOT NULL default '',
	promos varchar(255) NOT NULL default '',
	subtotal decimal(16,6) NOT NULL default '0.00',
	freight decimal(16,6) NOT NULL default '0.00',
	tax decimal(16,6) NOT NULL default '0.00',
	total decimal(16,6) NOT NULL default '0.00',
	discount decimal(16,6) NOT NULL default '0.00',
	fees decimal(16,6) NOT NULL default '0.00',
	taxing enum('exclusive','inclusive') default 'exclusive',
	txnid varchar(64) NOT NULL default '',
	txnstatus varchar(64) NOT NULL default '',
	gateway varchar(64) NOT NULL default '',
	carrier varchar(100) NOT NULL default '',
	shipmethod varchar(100) NOT NULL default '',
	shiptrack varchar(100) NOT NULL default '',
	status tinyint(3) unsigned NOT NULL default '0',
	data longtext NOT NULL,
	secured text NOT NULL,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY customer (customer)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $purchased = DatabaseObject::tablename('purchased'); ?>
DROP TABLE IF EXISTS <?php echo $purchased; ?>;
CREATE TABLE <?php echo $purchased; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	purchase bigint(20) unsigned NOT NULL default '0',
	product bigint(20) unsigned NOT NULL default '0',
	price bigint(20) unsigned NOT NULL default '0',
	download bigint(20) unsigned NOT NULL default '0',
	dkey varchar(255) NOT NULL default '',
	name varchar(255) NOT NULL default '',
	description text NOT NULL,
	optionlabel varchar(255) NOT NULL default '',
	sku varchar(100) NOT NULL default '',
	quantity int(10) unsigned NOT NULL default '0',
	downloads int(10) unsigned NOT NULL default '0',
	unitprice decimal(16,6) NOT NULL default '0.00',
	unittax decimal(16,6) NOT NULL default '0.00',
	shipping decimal(16,6) NOT NULL default '0.00',
	total decimal(16,6) NOT NULL default '0.00',
	addons enum('yes','no') NOT NULL default 'no',
	variation text NOT NULL,
	data longtext NOT NULL,
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY purchase (purchase),
	KEY product (product),
	KEY dkey (dkey(8))
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;

<?php $promo = DatabaseObject::tablename('promo'); ?>
DROP TABLE IF EXISTS <?php echo $promo; ?>;
CREATE TABLE <?php echo $promo; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	name varchar(255) NOT NULL default '',
	status enum('disabled','enabled') default 'disabled',
	type enum('Percentage Off','Amount Off','Free Shipping','Buy X Get Y Free') default 'Percentage Off',
	target enum('Catalog','Cart','Cart Item') default 'Catalog',
	discount decimal(16,6) NOT NULL default '0.00',
	buyqty int(10) NOT NULL default '0',
	getqty int(10) NOT NULL default '0',
	uses int(10) NOT NULL default '0',
	search enum('all','any') default 'all',
	code varchar(255) NOT NULL default '',
	rules text NOT NULL,
	starts datetime NOT NULL default '0000-00-00 00:00:00',
	ends datetime NOT NULL default '0000-00-00 00:00:00',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY catalog (status,target)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

<?php $ownership = DatabaseObject::tablename('ownership'); ?>
DROP TABLE IF EXISTS <?php echo $ownership; ?>;
CREATE TABLE <?php echo $ownership; ?> (
	id bigint(20) unsigned NOT NULL auto_increment,
	customer bigint(20) unsigned NOT NULL default '0',
	purchase bigint(20) unsigned NOT NULL default '0',
	parent bigint(20) unsigned NOT NULL default '0',
	taxonomy int(10) unsigned NOT NULL default '0',
	priority int(10) NOT NULL default '0',
	created datetime NOT NULL default '0000-00-00 00:00:00',
	modified datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY id (id),
	KEY type (customer,taxonomy),
	KEY purchased (customer,purchase)
) ENGINE=MyIsAM DEFAULT CHARSET=utf8;