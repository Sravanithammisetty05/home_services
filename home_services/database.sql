-- Home Services Booking platform - database.sql
-- Import with phpMyAdmin (Import tab). WARNING: this drops and recreates the tables,
-- so re-importing wipes existing data.

CREATE DATABASE IF NOT EXISTS home_services CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE home_services;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, bookings, worker_profiles, services, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('customer','worker','admin') NOT NULL DEFAULT 'customer',
  address TEXT NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_name VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(60) NOT NULL DEFAULT 'bi-tools',   -- Bootstrap Icons class used as the service icon
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_service_name (service_name)
) ENGINE=InnoDB;

CREATE TABLE worker_profiles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  specialization VARCHAR(100) NOT NULL,            -- matches services.service_name
  experience TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- years
  approval_status ENUM('pending','approved','deactivated') NOT NULL DEFAULT 'pending',
  availability_status ENUM('available','busy') NOT NULL DEFAULT 'available',
  UNIQUE KEY uq_worker_user (user_id),
  KEY idx_worker_spec (specialization),
  CONSTRAINT fk_wp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  worker_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  booking_time TIME NOT NULL,
  address TEXT NOT NULL,
  problem_description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Accepted','In Progress','Completed','Cancelled','Rejected') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_b_customer (customer_id),
  KEY idx_b_worker (worker_id),
  KEY idx_b_service (service_id),
  KEY idx_b_status (status),
  KEY idx_b_date (booking_date),
  CONSTRAINT fk_b_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_b_worker FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_b_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method ENUM('Cash','UPI','Card','Bank Transfer') NOT NULL DEFAULT 'Cash',
  payment_status ENUM('Pending','Paid','Refunded') NOT NULL DEFAULT 'Pending',
  transaction_reference VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_booking (booking_id),
  KEY idx_pay_status (payment_status),
  CONSTRAINT fk_pay_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sample services
INSERT INTO services (service_name, description, price, image) VALUES
('Plumbing', 'Leak repairs, pipe fitting, tap and drain fixing, bathroom and kitchen plumbing.', 299.00, 'bi-droplet-fill'),
('Electrical Repairs', 'Wiring, switches, fans, lighting, circuit breaker and power fault repairs.', 249.00, 'bi-lightning-charge-fill'),
('House Cleaning', 'Deep cleaning for rooms, kitchens and bathrooms by trained cleaners.', 799.00, 'bi-stars'),
('AC Repair and Maintenance', 'AC servicing, gas refilling, cooling problems, installation and repair.', 499.00, 'bi-snow'),
('Painting', 'Interior and exterior wall painting with clean finishing.', 1499.00, 'bi-paint-bucket'),
('Carpenter Services', 'Furniture repair, door and window fitting, custom woodwork.', 399.00, 'bi-hammer'),
('Appliance Repair', 'Washing machine, refrigerator, microwave and other appliance repairs.', 349.00, 'bi-gear-wide-connected'),
('Pest Control', 'Safe treatment for cockroaches, termites, ants, mosquitoes and rodents.', 999.00, 'bi-bug-fill');

-- Sample DEMO accounts (all use the password  Demo@1234 ). Delete them before real use.
INSERT INTO users (name, email, phone, password, role, address) VALUES
('Demo Customer', 'customer@example.com', '9000000001', '$2y$10$w9Ihl9dLExKo4VBr/VhNHuE17nVM5JcXef1ZMuMo2BZ7XYoCmNQUK', 'customer', '12 Lake View Road, Sample City'),
('Ravi Kumar', 'ravi.plumber@example.com', '9000000002', '$2y$10$w9Ihl9dLExKo4VBr/VhNHuE17nVM5JcXef1ZMuMo2BZ7XYoCmNQUK', 'worker', '4 Market Street, Sample City'),
('Anita Sharma', 'anita.electric@example.com', '9000000003', '$2y$10$w9Ihl9dLExKo4VBr/VhNHuE17nVM5JcXef1ZMuMo2BZ7XYoCmNQUK', 'worker', '9 Park Avenue, Sample City'),
('Suresh Babu', 'suresh.ac@example.com', '9000000004', '$2y$10$w9Ihl9dLExKo4VBr/VhNHuE17nVM5JcXef1ZMuMo2BZ7XYoCmNQUK', 'worker', '21 Station Road, Sample City'),
('Meena Devi', 'meena.clean@example.com', '9000000005', '$2y$10$w9Ihl9dLExKo4VBr/VhNHuE17nVM5JcXef1ZMuMo2BZ7XYoCmNQUK', 'worker', '7 Garden Lane, Sample City');

INSERT INTO worker_profiles (user_id, specialization, experience, approval_status) VALUES
(2, 'Plumbing', 8, 'approved'),
(3, 'Electrical Repairs', 6, 'approved'),
(4, 'AC Repair and Maintenance', 10, 'approved'),
(5, 'House Cleaning', 4, 'pending');

-- NOTE: no administrator account is stored here on purpose. Create the first admin
-- with:  C:\xampp\php\php.exe C:\xampp\htdocs\home_services\create_admin.php
