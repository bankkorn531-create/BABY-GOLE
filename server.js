require('dotenv').config();
const express = require('express');
const mongoose = require('mongoose');
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const cors = require('cors');

const app = express();
app.use(express.json());
app.use(cors()); // อนุญาตให้หน้าเว็บ (Frontend) เรียกใช้งาน API ได้

const MAX_DEVICES = 3; // จำกัดการล็อกอินสูงสุด 3 อุปกรณ์

// ==========================================
// 1. ตั้งค่าฐานข้อมูล (MongoDB Schema)
// ==========================================
mongoose.connect(process.env.MONGO_URI)
  .then(() => console.log('✅ เชื่อมต่อ MongoDB สำเร็จ'))
  .catch(err => console.error('❌ เชื่อมต่อ MongoDB ล้มเหลว:', err));

const userSchema = new mongoose.Schema({
  username: { type: String, required: true, unique: true },
  password: { type: String, required: true },
  role: { type: String, default: 'user' }, // 'user' หรือ 'admin'
  refreshTokens: [{ type: String }] // เก็บ Token ของอุปกรณ์ต่างๆ (สูงสุด 3)
});

const User = mongoose.model('User', userSchema);

// ==========================================
// 2. Middleware ตรวจสอบสิทธิ์
// ==========================================
// ตรวจสอบ Access Token
const verifyToken = (req, res, next) => {
  const token = req.headers.authorization?.split(' ')[1];
  if (!token) return res.status(401).json({ message: 'ไม่มี Access Token' });

  try {
    const decoded = jwt.verify(token, process.env.ACCESS_SECRET);
    req.user = decoded;
    next();
  } catch (error) {
    res.status(401).json({ message: 'Token หมดอายุหรือไม่ถูกต้อง' });
  }
};

// ตรวจสอบว่าเป็น Admin หรือไม่
const isAdmin = (req, res, next) => {
  if (req.user.role !== 'admin') return res.status(403).json({ message: 'สิทธิ์ไม่เพียงพอ' });
  next();
};

// ==========================================
// 3. API สำหรับผู้ใช้งานทั่วไป (Authentication)
// ==========================================

// สมัครสมาชิก (ทำไว้เพื่อทดสอบสร้าง User)
app.post('/register', async (req, res) => {
  try {
    const { username, password, role } = req.body;
    const hashedPassword = await bcrypt.hash(password, 10);
    const newUser = new User({ username, password: hashedPassword, role });
    await newUser.save();
    res.status(201).json({ message: 'สมัครสมาชิกสำเร็จ' });
  } catch (error) {
    res.status(400).json({ message: 'ชื่อผู้ใช้นี้มีในระบบแล้ว หรือข้อมูลผิดพลาด' });
  }
});

// เข้าสู่ระบบ (พร้อมระบบจำกัด 3 อุปกรณ์)
app.post('/login', async (req, res) => {
  const { username, password } = req.body;
  const user = await User.findOne({ username });

  if (!user || !(await bcrypt.compare(password, user.password))) {
    return res.status(401).json({ message: 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง' });
  }

  // สร้าง Token
  const accessToken = jwt.sign({ userId: user._id, role: user.role }, process.env.ACCESS_SECRET, { expiresIn: '15m' });
  const refreshToken = jwt.sign({ userId: user._id }, process.env.REFRESH_SECRET, { expiresIn: '7d' });

  // จัดการลิมิตอุปกรณ์
  if (user.refreshTokens.length >= MAX_DEVICES) {
    // ตัดให้เหลือ 2 อันล่าสุด เพื่อให้อันใหม่ที่จะใส่เข้าไปกลายเป็นอันที่ 3 พอดี
    user.refreshTokens = user.refreshTokens.slice(-(MAX_DEVICES - 1));
  }
  user.refreshTokens.push(refreshToken);
  
  await user.save(); // บันทึกลงฐานข้อมูลจริง

  res.json({ message: 'ล็อกอินสำเร็จ', accessToken, refreshToken });
});

// ขอ Access Token ใหม่ เมื่ออันเก่าหมดอายุ
app.post('/refresh-token', async (req, res) => {
  const { refreshToken } = req.body;
  if (!refreshToken) return res.status(401).json({ message: 'ไม่มี Refresh Token' });

  try {
    const decoded = jwt.verify(refreshToken, process.env.REFRESH_SECRET);
    const user = await User.findById(decoded.userId);

    // เช็คว่า Token นี้ยังอยู่ใน Database หรือโดนเตะออกไปแล้ว
    if (!user || !user.refreshTokens.includes(refreshToken)) {
      return res.status(403).json({ message: 'Token นี้ถูกเพิกถอนไปแล้ว' });
    }

    // ออก Access Token ใหม่
    const newAccessToken = jwt.sign({ userId: user._id, role: user.role }, process.env.ACCESS_SECRET, { expiresIn: '15m' });
    res.json({ accessToken: newAccessToken });
  } catch (error) {
    res.status(403).json({ message: 'Refresh Token หมดอายุหรือไม่ถูกต้อง' });
  }
});

// ==========================================
// 4. API สำหรับระบบหลังบ้าน (Admin)
// ==========================================

// ดึงข้อมูลผู้ใช้ทั้งหมดมาแสดงบนเว็บหลังบ้าน
app.get('/admin/users', verifyToken, isAdmin, async (req, res) => {
  try {
    // ดึงมาทุกคน แต่ไม่แสดงรหัสผ่าน
    const users = await User.find({}, '-password'); 
    res.json({ total: users.length, users });
  } catch (error) {
    res.status(500).json({ message: 'เกิดข้อผิดพลาดที่เซิร์ฟเวอร์' });
  }
});

// ==========================================
// 5. เริ่มต้นรันเซิร์ฟเวอร์
// ==========================================
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`🚀 Server รันอยู่ที่ http://localhost:${PORT}`);
});