CREATE TABLE IF NOT EXISTS categorias(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marcas(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  marca_id INT NOT NULL,
  categoria_id INT NOT NULL,
  unidad VARCHAR(32) DEFAULT NULL,
  precio DECIMAL(12,2) NOT NULL DEFAULT 0,
  sin_tacc TINYINT(1) NOT NULL DEFAULT 0,
  congelado TINYINT(1) NOT NULL DEFAULT 0,
  veganlife TINYINT(1) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  imagen VARCHAR(500) DEFAULT NULL,
  FOREIGN KEY (marca_id) REFERENCES marcas(id),
  FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  INDEX (nombre), INDEX (unidad), INDEX (precio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  direccion VARCHAR(255) DEFAULT NULL,
  telefono VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_usuarios(
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedidos(
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  estado VARCHAR(50) NOT NULL DEFAULT 'nuevo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedido_detalles(
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  producto_id INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- demo data
INSERT INTO categorias (nombre, slug) VALUES
('Alfajores','alfajores'),('Galletitas','galletitas'),('Bebidas','bebidas')
ON DUPLICATE KEY UPDATE slug=VALUES(slug);

INSERT INTO marcas (nombre, slug) VALUES
('Un Rincón Vegano','un-rincon-vegano'),('Felices las Vacas','felices-las-vacas'),('Nutri','nutri')
ON DUPLICATE KEY UPDATE slug=VALUES(slug);

SET @cat_alf = (SELECT id FROM categorias WHERE slug='alfajores');
SET @cat_gal = (SELECT id FROM categorias WHERE slug='galletitas');
SET @cat_beb = (SELECT id FROM categorias WHERE slug='bebidas');
SET @m_rincon = (SELECT id FROM marcas WHERE slug='un-rincon-vegano');
SET @m_felices = (SELECT id FROM marcas WHERE slug='felices-las-vacas');
SET @m_nutri = (SELECT id FROM marcas WHERE slug='nutri');

INSERT INTO productos (nombre, slug, marca_id, categoria_id, unidad, precio, sin_tacc, congelado, veganlife, stock, imagen) VALUES
('Alfajor Ddl Clásico', 'alfajor-ddl-clasico', @m_rincon, @cat_alf, '1u', 2200, 1, 0, 0, 100, NULL),
('Alfajor Ddl Clásico x12', 'alfajor-ddl-clasico-12', @m_rincon, @cat_alf, '12u', 24000, 1, 0, 0, 20, NULL),
('Bebida de Almendras', 'bebida-almendras', @m_felices, @cat_beb, '1lt', 5300, 1, 0, 0, 50, NULL),
('Galletitas Avena', 'galletitas-avena', @m_nutri, @cat_gal, '500g', 3100, 1, 0, 0, 80, NULL);

INSERT INTO admin_usuarios (nombre, email, password_hash) VALUES
('Admin','admin@veganlife','$2y$10$rUYlNw37tOv3jyiUJTQS7uN7g8l8H10jTPlbcim05KnxjzQYtghK2')
ON DUPLICATE KEY UPDATE email=email;
