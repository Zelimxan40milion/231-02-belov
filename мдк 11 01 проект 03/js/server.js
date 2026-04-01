const { createProxyMiddleware } = require('http-proxy-middleware');
const express = require('express');
const { spawn } = require('child_process');
const net = require('net');
const path = require('path');

const PORT = Number(process.env.PORT || 3000);
const PHP_PORT = Number(process.env.PHP_PORT || 8000);
const ROOT = path.resolve(__dirname, '..');

function isPortFree(port) {
  return new Promise((resolve) => {
    const server = net
      .createServer()
      .once('error', () => resolve(false))
      .once('listening', () => {
        server.close(() => resolve(true));
      })
      .listen({ port, exclusive: true });
  });
}

async function findFreePort(startPort, tries = 30) {
  for (let p = startPort; p < startPort + tries; p += 1) {
    // eslint-disable-next-line no-await-in-loop
    if (await isPortFree(p)) return p;
  }
  return null;
}

function startPhpServer() {
  const args = ['-S', `127.0.0.1:${PHP_PORT}`, '-t', ROOT];
  const php = spawn('php', args, { cwd: ROOT, stdio: 'inherit' });

  php.on('exit', (code) => {
    console.error(`PHP server exited with code ${code}`);
    process.exit(code || 1);
  });

  return php;
}

function startNodeServer(port) {
  const app = express();

  app.use(
    '/',
    createProxyMiddleware({
      target: `http://127.0.0.1:${PHP_PORT}`,
      changeOrigin: true,
      ws: true
    })
  );

  const server = app.listen(port, () => {
    console.log(`Node proxy running at http://localhost:${port}`);
  });

  server.on('error', (err) => {
    if (err && err.code === 'EADDRINUSE') {
      console.error(`Port ${port} is already in use. Set PORT env var or free the port.`);
      process.exit(1);
    }
    console.error(err);
    process.exit(1);
  });
}

async function main() {
  const php = startPhpServer();

  const free = await findFreePort(PORT, 30);
  if (!free) {
    console.error(`No free port found starting from ${PORT}. Set PORT env var.`);
    process.exit(1);
  }

  const shutdown = () => {
    if (php && !php.killed) php.kill();
  };
  process.on('SIGINT', () => {
    shutdown();
    process.exit(0);
  });
  process.on('SIGTERM', () => {
    shutdown();
    process.exit(0);
  });
  process.on('exit', shutdown);

  startNodeServer(free);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
