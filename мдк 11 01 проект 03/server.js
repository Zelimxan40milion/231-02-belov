const fs = require('fs');
const path = require('path');
const express = require('express');

const app = express();
const host = '127.0.0.1';
const port = process.env.PORT || 8000;
const publicDir = path.join(__dirname, 'public');
const contactLog = path.join(__dirname, 'contact-messages.log');

app.use(express.json({ limit: '1mb' }));

// Serve static assets (HTML/CSS/JS/PNG/PHP as static files)
app.use(
  express.static(publicDir, {
    extensions: ['html', 'htm', 'php'],
    maxAge: '1d',
  })
);

// Simple contact endpoint (demo). Stores messages to a local log file.
app.post('/api/contact', (req, res) => {
  const { name, email, message } = req.body || {};

  if (!name || !email || !message) {
    return res.status(400).json({
      success: false,
      message: 'Заполните имя, email и сообщение',
    });
  }

  const entry = {
    name,
    email,
    message,
    ip: req.ip,
    createdAt: new Date().toISOString(),
  };

  fs.appendFile(contactLog, JSON.stringify(entry) + '\n', (err) => {
    if (err) {
      console.error('Не удалось записать сообщение', err);
      return res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }

    res.json({ success: true, message: 'Сообщение принято, спасибо!' });
  });
});

// SPA-style fallback to the main page
app.get('*', (_req, res) => {
  res.sendFile(path.join(publicDir, 'index.html'));
});

app.listen(port, host, () => {
  console.log(`Server running at http://${host}:${port}`);
});
