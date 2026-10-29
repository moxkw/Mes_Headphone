-- =====================================================
-- FILE: database/mes_headphone.sql
-- Tạo database + bảng + dữ liệu mẫu cho hệ thống MES tai nghe
-- Cách dùng: phpMyAdmin -> tab Import -> chọn file này -> Thực hiện
-- Mật khẩu đã được hash bằng bcrypt (tương thích password_verify của PHP)
-- =====================================================
DROP DATABASE IF EXISTS mes_headphone;
CREATE DATABASE mes_headphone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mes_headphone;

-- 1. Người dùng (role: admin hoặc worker)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  role ENUM('admin','worker') NOT NULL DEFAULT 'worker',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Sản phẩm
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  type VARCHAR(50) NOT NULL,
  color VARCHAR(30) NOT NULL,
  price DECIMAL(12,0) NOT NULL DEFAULT 0,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB;

-- 3. Nguyên vật liệu
CREATE TABLE materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  unit VARCHAR(20) NOT NULL,
  stock_qty INT NOT NULL DEFAULT 0,
  min_qty INT NOT NULL DEFAULT 0,
  supplier VARCHAR(100)
) ENGINE=InnoDB;

-- 4. BOM: mỗi sản phẩm cần bao nhiêu nguyên vật liệu
CREATE TABLE bom (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  material_id INT NOT NULL,
  quantity INT NOT NULL,
  UNIQUE KEY uq_bom (product_id, material_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Lệnh sản xuất
CREATE TABLE production_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(20) NOT NULL UNIQUE,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  produced_qty INT NOT NULL DEFAULT 0,
  defect_qty INT NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  expected_end_date DATE NOT NULL,
  manager VARCHAR(100) NOT NULL,
  status ENUM('Planned','In Production','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 6. Nhật ký sản xuất (mỗi lần nhập sản lượng)
CREATE TABLE production_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  produced_qty INT NOT NULL,
  good_qty INT NOT NULL,
  defect_qty INT NOT NULL,
  log_date DATE NOT NULL,
  user_id INT NULL,
  note VARCHAR(255),
  FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Kiểm tra chất lượng
CREATE TABLE quality_checks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  check_code VARCHAR(20) NOT NULL UNIQUE,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  checked_qty INT NOT NULL,
  good_qty INT NOT NULL,
  defect_qty INT NOT NULL,
  defect_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  defect_type VARCHAR(100),
  inspector VARCHAR(100) NOT NULL,
  check_date DATE NOT NULL,
  note VARCHAR(255),
  FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 8. Kho thành phẩm
CREATE TABLE warehouse (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  order_id INT NULL,
  quantity INT NOT NULL,
  import_date DATE NOT NULL,
  location VARCHAR(50) NOT NULL,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  FOREIGN KEY (order_id) REFERENCES production_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ================== DỮ LIỆU MẪU ==================
INSERT INTO users (username, password, full_name, role) VALUES
('admin',  '$2y$12$4Cw9UEJt2AbFdWRb7jJhQOXF6DujzoYR5/QyMmHob.pySKXNjdD5i', 'Quản trị viên', 'admin'),
('worker', '$2y$12$BhMlaToCjBiNy8nNyYny8u/9G3E6I31mHxG8SBoZOsCOhgEQH7Tyu', 'Nguyễn Văn Công', 'worker');

INSERT INTO products (code, name, type, color, price, status) VALUES
('SP001','Tai nghe Bluetooth A1','Bluetooth','Đen',850000,'Active'),
('SP002','Tai nghe Bluetooth A2','Bluetooth','Trắng',950000,'Active'),
('SP003','Tai nghe Gaming G1','Gaming','Đen',1250000,'Active'),
('SP004','Tai nghe không dây Pro X','Không dây','Bạc',2450000,'Active');

INSERT INTO materials (code, name, unit, stock_qty, min_qty, supplier) VALUES
('MAT001','Vỏ tai nghe trái','cái',3000,500,'Công ty Nhựa Việt'),
('MAT002','Vỏ tai nghe phải','cái',3000,500,'Công ty Nhựa Việt'),
('MAT003','Pin Li-ion 300mAh','cái',1800,400,'Pin Năng Lượng Xanh'),
('MAT004','Loa 40mm','cái',5000,1000,'Âm Thanh Sài Gòn'),
('MAT005','Bo mạch PCB','cái',2200,500,'Điện Tử Hà Nội'),
('MAT006','Micro','cái',300,600,'Âm Thanh Sài Gòn'),
('MAT007','Cáp sạc USB-C','sợi',2500,500,'Dây Cáp Đại Phát'),
('MAT008','Đệm tai','cái',4000,800,'Cao Su Kỹ Thuật'),
('MAT009','Hộp sản phẩm','cái',2000,300,'Bao Bì Minh Anh'),
('MAT010','Cáp âm thanh 3.5mm','sợi',1500,300,'Dây Cáp Đại Phát');

-- BOM (product_id, material_id, quantity)
INSERT INTO bom (product_id, material_id, quantity) VALUES
(1,1,1),(1,2,1),(1,3,1),(1,4,2),(1,5,1),(1,6,1),(1,7,1),(1,9,1),
(2,1,1),(2,2,1),(2,3,1),(2,4,2),(2,5,1),(2,6,1),(2,7,1),(2,8,2),(2,9,1),
(3,1,1),(3,2,1),(3,4,2),(3,5,1),(3,6,1),(3,8,2),(3,9,1),(3,10,1),
(4,1,1),(4,2,1),(4,3,2),(4,4,2),(4,5,1),(4,6,2),(4,7,1),(4,8,2),(4,9,1);

INSERT INTO production_orders (order_code, product_id, quantity, produced_qty, defect_qty, start_date, expected_end_date, manager, status) VALUES
('MO001',1,1000,650,20,'2026-10-20','2026-11-05','Nguyễn Văn An','In Production'),
('MO002',2,500,500,15,'2026-10-10','2026-10-20','Trần Thị Bình','Completed'),
('MO003',3,800,300,10,'2026-10-24','2026-11-10','Lê Minh Cường','In Production'),
('MO004',4,300,0,0,'2026-11-01','2026-11-15','Phạm Thu Dung','Planned'),
('MO005',1,200,200,5,'2026-10-01','2026-10-08','Nguyễn Văn An','Completed');

INSERT INTO production_logs (order_id, produced_qty, good_qty, defect_qty, log_date, user_id, note) VALUES
(1,250,245,5,'2026-10-21',2,'Ca sáng'),
(1,200,192,8,'2026-10-22',2,'Ca chiều'),
(1,200,193,7,'2026-10-23',2,'Ca sáng'),
(2,300,290,10,'2026-10-12',2,''),
(2,200,195,5,'2026-10-15',2,''),
(3,150,146,4,'2026-10-25',2,''),
(3,150,144,6,'2026-10-26',2,''),
(5,200,195,5,'2026-10-03',2,'');

INSERT INTO quality_checks (check_code, order_id, product_id, checked_qty, good_qty, defect_qty, defect_rate, defect_type, inspector, check_date, note) VALUES
('QC001',1,1,100,96,4,4.00,'Không kết nối Bluetooth','Nguyễn Văn Công','2026-10-22','Kiểm tra lô đầu'),
('QC002',2,2,200,194,6,3.00,'Âm thanh bị rè','Nguyễn Văn Công','2026-10-16',''),
('QC003',3,3,100,97,3,3.00,'Vỏ bị trầy','Nguyễn Văn Công','2026-10-26',''),
('QC004',5,1,150,148,2,1.33,'Pin lỗi','Nguyễn Văn Công','2026-10-04','');

INSERT INTO warehouse (product_id, order_id, quantity, import_date, location) VALUES
(2,2,485,'2026-10-21','Kệ A1'),
(1,5,100,'2026-10-09','Kệ B2');
