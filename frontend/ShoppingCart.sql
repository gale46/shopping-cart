CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    role TINYINT DEFAULT 0 COMMENT '0:User, 1:Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
insert into users(username, password)values('allen', '202603');




CREATE TABLE IF NOT EXISTS product(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
	price INT NOT NULL DEFAULT 0,
	stock INT NOT NULL DEFAULT 0,
	description  TEXT,
	image_url VARCHAR(255) ,
	
	
	
	seller_id INT NOT NULL,
	CONSTRAINT fk_product_seller
	FOREIGN KEY(seller_id) REFERENCES seller(id) 
	ON DELETE CASCADE,
	
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
insert into product(name, price,seller_id) value("apple", 50, 1);
DELETE FROM seller WHERE id = 1;


CREATE TABLE IF NOT EXISTS seller(
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL UNIQUE,
	email VARCHAR(100) UNIQUE,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
	
);

insert into seller(name) value("seller1");


CREATE TABLE IF NOT EXISTS cart_item(
    id INT PRIMARY KEY AUTO_INCREMENT,
	user_id INT NOT NULL,
    product_id INT NOT NULL,
    -- 產品id

    -- quantity
    quantity INT NOT NULL DEFAULT 1, 
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(product_id) REFERENCES product(id)
	
);
insert into cart_item(user_id, product_id) value (1, 5);

