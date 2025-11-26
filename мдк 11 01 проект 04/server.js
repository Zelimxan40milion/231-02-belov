const express = require('express');
const path = require('path');
const { exec, spawn } = require('child_process');
const http = require('http');
const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.static('public'));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));

console.log('Starting PHP server...');
const phpServer = spawn('php', ['-S', 'localhost:8000', '-t', '.'], {
  stdio: 'inherit',
  shell: true
});

phpServer.on('error', (error) => {
  console.error(`PHP Server Error: ${error}`);
});

phpServer.on('exit', (code) => {
  console.log(`PHP server exited with code ${code}`);
});

setTimeout(() => {
  console.log('PHP server should be ready');
}, 1000);

app.use((req, res) => {
  let bodyData = '';
  
  if (req.method === 'POST' || req.method === 'PUT') {
    if (req.headers['content-type'] && req.headers['content-type'].includes('application/json')) {
      bodyData = JSON.stringify(req.body);
    } else {
      const params = new URLSearchParams();
      for (const key in req.body) {
        params.append(key, req.body[key]);
      }
      bodyData = params.toString();
    }
  }

  const options = {
    hostname: 'localhost',
    port: 8000,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      'host': 'localhost:8000'
    }
  };

  delete options.headers['connection'];
  delete options.headers['content-length'];

  if (bodyData) {
    options.headers['content-length'] = Buffer.byteLength(bodyData);
    if (!options.headers['content-type']) {
      options.headers['content-type'] = 'application/x-www-form-urlencoded';
    }
  }

  const proxyReq = http.request(options, (proxyRes) => {
    Object.keys(proxyRes.headers).forEach(key => {
      res.setHeader(key, proxyRes.headers[key]);
    });
    res.statusCode = proxyRes.statusCode;
    proxyRes.pipe(res);
  });

  proxyReq.on('error', (err) => {
    console.error('Proxy Error:', err.message);
    if (!res.headersSent) {
      res.status(500).send('Internal Server Error: PHP server may not be running. Make sure PHP is installed and accessible.');
    }
  });

  if (bodyData) {
    proxyReq.write(bodyData);
  }
  proxyReq.end();
});

app.listen(PORT, () => {
  console.log(`Node.js server running on http://localhost:${PORT}`);
  console.log(`All requests will be proxied to PHP server on http://localhost:8000`);
});

process.on('SIGTERM', () => {
  console.log('Shutting down...');
  phpServer.kill();
  process.exit(0);
});

process.on('SIGINT', () => {
  console.log('Shutting down...');
  phpServer.kill();
  process.exit(0);
});
