const { createProxyMiddleware } = require('http-proxy-middleware');
const express = require('express');
const { spawn } = require('child_process');
const path = require('path');

const PORT = process.env.PORT || 3000;
const PHP_PORT = process.env.PHP_PORT || 8000;
const ROOT = path.resolve(__dirname, '..');

function startPhpServer() {
  const args = ['-S', `127.0.0.1:${PHP_PORT}`, '-t', ROOT];
  const php = spawn('php', args, { cwd: ROOT, stdio: 'inherit' });

  php.on('exit', (code) => {
    console.error(`PHP server exited with code ${code}`);
    process.exit(code || 1);
  });

  return php;
}

function startNodeServer() {
  const app = express();

  app.use(
    '/',
    createProxyMiddleware({
      target: `http://127.0.0.1:${PHP_PORT}`,
      changeOrigin: true,
      ws: true
    })
  );

  app.listen(PORT, () => {
    console.log(`Node proxy running at http://localhost:${PORT}`);
  });
}

startPhpServer();
startNodeServer();






