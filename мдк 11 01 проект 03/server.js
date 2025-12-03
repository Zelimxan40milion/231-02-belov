const { spawn } = require('child_process');
const path = require('path');

const host = '127.0.0.1';
const port = process.env.PORT || 8000;
const publicDir = path.join(__dirname, 'public');

console.log(`Starting PHP built-in server on http://${host}:${port}`);

const phpServer = spawn(
  'php',
  ['-S', `${host}:${port}`, '-t', publicDir],
  {
    stdio: 'inherit',
  }
);

phpServer.on('error', (err) => {
  console.error('Failed to launch PHP server. Is PHP installed and in PATH?', err);
  process.exit(1);
});

const shutdown = () => {
  console.log('\nStopping PHP server…');
  phpServer.kill();
  process.exit(0);
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);



