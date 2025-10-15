-- --------------------------------------------------------
-- Database: bank_db
-- Bank Management System
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS bank_db;
USE bank_db;

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- --------------------------------------------------------
-- Table structure for `accounts`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
  account_no INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  balance DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

-- --------------------------------------------------------
-- Table structure for `transactions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_no INT NOT NULL,
  type ENUM('deposit', 'withdraw', 'transfer') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  remark VARCHAR(255),
  FOREIGN KEY (account_no) REFERENCES accounts(account_no)
    ON DELETE CASCADE
    ON UPDATE CASCADE
);

-- --------------------------------------------------------
-- Sample Data (Optional)
-- --------------------------------------------------------
INSERT INTO users (name, email, password)
VALUES
('Aditya Borhade', 'aditya@example.com', '12345'),
('Rohit Sharma', 'rohit@example.com', '12345');

INSERT INTO accounts (user_id, balance)
VALUES
(1, 10000.00),
(2, 5000.00);

INSERT INTO transactions (account_no, type, amount, remark)
VALUES
(1, 'deposit', 10000.00, 'Initial Deposit'),
(2, 'deposit', 5000.00, 'Initial Deposit');
