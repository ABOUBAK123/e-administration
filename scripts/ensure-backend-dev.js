const net = require('net');
const { spawn } = require('child_process');
const { execSync } = require('child_process');
const path = require('path');

const host = '127.0.0.1';
const port = 3000;

function isPortOpen(hostname, portNumber, timeoutMs = 800) {
  return new Promise((resolve) => {
    const socket = new net.Socket();
    let settled = false;

    const finish = (open) => {
      if (settled) return;
      settled = true;
      socket.destroy();
      resolve(open);
    };

    socket.setTimeout(timeoutMs);
    socket.once('connect', () => finish(true));
    socket.once('timeout', () => finish(false));
    socket.once('error', () => finish(false));
    socket.connect(portNumber, hostname);
  });
}

function isPortListeningWithNetstat(portNumber) {
  try {
    const output = execSync('netstat -ano', { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
    const lines = output.split(/\r?\n/);
    return lines.some((line) => {
      const lower = line.toLowerCase();
      return lower.includes(`:${portNumber}`) && lower.includes('listening');
    });
  } catch {
    return false;
  }
}

async function main() {
  const byNetstat = isPortListeningWithNetstat(port);
  const bySocket =
    (await isPortOpen('127.0.0.1', port)) ||
    (await isPortOpen('::1', port)) ||
    (await isPortOpen('localhost', port));
  const alreadyRunning = byNetstat || bySocket;

  if (alreadyRunning) {
    console.log(`[dev] Backend already running on http://localhost:${port}, skipping duplicate start.`);
    return;
  }

  console.log('[dev] Starting backend (apps/backend npm run start:dev)...');

  const child = spawn('npm', ['run', 'start:dev'], {
    cwd: path.resolve(__dirname, '..', 'apps', 'backend'),
    stdio: 'inherit',
    shell: true,
  });

  child.on('exit', (code) => {
    process.exit(code ?? 0);
  });
}

main().catch((error) => {
  console.error('[dev] Failed to ensure backend dev server:', error);
  process.exit(1);
});
