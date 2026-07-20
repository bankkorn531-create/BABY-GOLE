-- =========================================================
-- babyGole Database Schema
-- นำเข้าไฟล์นี้ผ่าน phpMyAdmin (Import) หรือคำสั่ง mysql -u root -p < database.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS babygole CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE babygole;

-- ผู้ใช้งาน
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    email VARCHAR(255) DEFAULT NULL,
    alt_emails TEXT DEFAULT NULL,        -- เก็บเป็น JSON array เช่น ["a@x.com","b@x.com"]
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    profile_pic LONGTEXT DEFAULT NULL,   -- เก็บรูปแบบ base64 data URL
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- กระทู้ในชุมชน
CREATE TABLE IF NOT EXISTS forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    detail TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- คอมเมนต์/คำตอบในกระทู้
CREATE TABLE IF NOT EXISTS forum_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    author VARCHAR(255) NOT NULL,
    text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- สมุดพกบันทึกการเจริญเติบโต
CREATE TABLE IF NOT EXISTS tracker_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) DEFAULT NULL,
    record_date VARCHAR(20) NOT NULL,
    age_text VARCHAR(100) NOT NULL,
    weight VARCHAR(20) NOT NULL,
    height VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- การแจ้งเตือนชุมชน
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ข้อมูลเริ่มต้น (เหมือนของเดิมในเว็บ)
INSERT INTO users (name, username, password, phone, email, alt_emails, is_admin) VALUES
('ผู้ดูแลระบบ', 'admin', 'admin123', '0888888888', 'admin@babygole.com', NULL, 1),
('คุณแม่ สมปอง', 'user', '1234', '0991234567', 'sompong@email.com', NULL, 0);

INSERT INTO forum_posts (id, author, title, detail) VALUES
(1, 'แม่น้องติน', 'น้อง 8 เดือน ไม่ยอมกินข้าวบด ทำยังไงดีคะ?', 'พ่นข้าวออกตลอดเลยค่ะ'),
(2, 'พ่อแม่น้องเหนือ', 'ลูกเริ่มหัดเดินแล้วกลัวเดินเอง', 'เวลาปล่อยเดินจะกลัวและร้องไห้ ไม่กล้าปล่อยมือ'),
(3, 'คุณแม่เจิน', 'ผื่นแพ้ผ้าอ้อมขึ้นบ่อยควรทำอย่างไร', 'เวลาทำความสะอาดแล้วก็ยังแดงและแสบ'),
(4, 'นายเปิ้ล', 'ลูกมีปัญหาการนอนยาวช่วงกลางคืน', 'นอนหลับไม่ต่อเนื่อง ตื่นกลางดึกบ่อยมาก'),
(5, 'แม่จันทร์', 'ลูกน้อยชอบหยิบของเข้าปากตลอด', 'กังวลเรื่องของเล็กและความสะอาด');

INSERT INTO forum_replies (post_id, author, text) VALUES
(1, 'ผู้ดูแลระบบ', 'ลองเปลี่ยนเป็นวิธี BLW ให้หยิบจับเองดูนะครับ'),
(2, 'ผู้ดูแลระบบ', 'ให้วางของเล่นใกล้ ๆ และค่อย ๆ ปล่อยมือทีละนิด พร้อมให้กำลังใจ'),
(3, 'แม่ชาวบ้าน', 'ลองเปลี่ยนผ้าอ้อมบ่อย ๆ และใช้ครีมบำรุงผิวเด็ก'),
(4, 'ผู้เชี่ยวชาญ', 'จัดตารางนอนให้เป็นเวลา ลดการเล่นก่อนนอน และตรวจสอบสิ่งแวดล้อมในห้อง'),
(5, 'คุณหมอ', 'เตรียมของเล่นปลอดภัยให้หยิบจับ และคอยดูแลอย่างใกล้ชิด');
