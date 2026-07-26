<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI ผู้ช่วยปรึกษาพัฒนาการเด็ก</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8fafc;
        }
        .ai-screen {
            width: 100%;
            max-width: 800px;
            height: 100vh;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .ai-header {
            background: #1e3a8a;
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
        }
        .ai-chat-box {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            padding: 12px 18px;
            border-radius: 12px;
            max-width: 75%;
            line-height: 1.5;
            font-size: 14px;
        }
        .message.user {
            background: #3b82f6;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 2px;
        }
        .message.ai {
            background: white;
            color: #1e293b;
            align-self: flex-start;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-bottom-left-radius: 2px;
        }
        .ai-input-area {
            background: white;
            padding: 15px;
            display: flex;
            gap: 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .ai-input-area input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }
        .ai-input-area button {
            padding: 12px 24px;
            background: #1e3a8a;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
        }
        .ai-input-area button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

    <div class="ai-screen">
        <div class="ai-header">
            🤖 AI ผู้ช่วยให้คำปรึกษาพัฒนาการเด็กตามความเป็นจริง
        </div>
        
        <div class="ai-chat-box" id="chatBox">
            <div class="message ai">สวัสดีครับ ผมคือผู้ช่วย AI ด้านพัฒนาการเด็ก สามารถพิมพ์สอบถามปัญหาได้ตามจริง เช่น "เด็ก 1 ขวบไม่พูด", "ลูกกินยาก" หรืออาการอื่นๆ ได้เลยครับ</div>
        </div>

        <div class="ai-input-area">
            <input type="text" id="userInput" placeholder="พิมพ์คำถามของคุณที่นี่..." onkeypress="if(event.key === 'Enter') sendAiMessage()">
            <button onclick="sendAiMessage()">ส่งข้อความ</button>
        </div>
    </div>

    <script>
        function sendAiMessage() {
            const inputField = document.getElementById('userInput');
            const text = inputField.value.trim();
            if(!text) return;

            const chatBox = document.getElementById('chatBox');

            // แสดงข้อความผู้ใช้
            chatBox.innerHTML += `<div class="message user">${text}</div>`;
            inputField.value = '';
            chatBox.scrollTop = chatBox.scrollHeight;

            // จำลองการตอบของ AI ตามความเป็นจริง
            setTimeout(() => {
                let reply = "ขออภัยครับ สำหรับคำถามนี้ แนะนำให้สังเกตอาการเบื้องต้นหรือปรึกษากุมารแพทย์เพื่อความปลอดภัยและแม่นยำยิ่งขึ้นครับ";
                
                const lowerText = text.toLowerCase();
                if(lowerText.includes('1 ขวบ') || lowerText.includes('ไม่พูด') || lowerText.includes('ขวบ')) {
                    reply = "ตามเกณฑ์พัฒนาการ เด็กอายุ 1 ขวบควรเริ่มส่งเสียงอ้อแอ้ เลียนแบบเสียง และเริ่มพูดคำที่มีความหมายได้ 1-2 คำ หากน้องยังไม่พูดแต่ยังเข้าใจภาษา สบตา และใช้ท่าทางสื่อสาร (เช่น ชี้ โบกมือ) อาจยังพอรอสังเกตได้ แต่หากน้องไม่หันตามเสียงเรียก ไม่สบตา หรือไม่ส่งเสียงอ้อแอ้เลย แนะนำให้พาไปพบกุมารแพทย์ด้านพัฒนาการครับ";
                } else if(lowerText.includes('กินยาก') || lowerText.includes('ไม่ยอมกินข้าว')) {
                    reply = "เด็กวัยเตาะแตะมักมีความอยากอาหารลดลงเพราะโตช้าลงครับ ตามความเป็นจริงควรจัดเวลาอาหารให้เป็นเวลา ไม่บังคับหรือดุว่าตอนทานข้าว และให้เด็กได้ลองหยิบจับอาหารเองเพื่อสร้างทัศนคติที่ดีต่อมื้ออาหารครับ";
                }

                chatBox.innerHTML += `<div class="message ai">${reply}</div>`;
                chatBox.scrollTop = chatBox.scrollHeight;
            }, 500);
        }
    </script>
</body>
</html>