const { execSync } = require('child_process');

function getListeningPidsForPort(port) {
  try {
    const output = execSync('netstat -ano -p tcp', { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
    const lines = output.split(/\r?\n/);
    const pids = new Set();

    for (const line of lines) {
      const normalized = line.trim().replace(/\s+/g, ' ');
      if (!normalized) continue;
      if (!normalized.toUpperCase().includes('LISTENING')) continue;
      if (!normalized.includes(`:${port} `) && !normalized.includes(`:${port}\t`) && !normalized.includes(`:${port}`)) continue;

      const parts = normalized.split(' ');
      const pid = parts[parts.length - 1];
      if (/^\d+$/.test(pid)) {
        pids.add(pid);
      }
    }

    return Array.from(pids);
  } catch {
    return [];
  }
}

function killPid(pid) {
  try {
    execSync(`taskkill /PID ${pid} /F`, { stdio: ['ignore', 'ignore', 'ignore'] });
    return true;
  } catch {
    return false;
  }
}

function main() {
  const ports = process.argv.slice(2).map((value) => Number(value)).filter((value) => Number.isInteger(value) && value > 0);
  if (ports.length === 0) {
    console.log('[dev] No ports provided to kill-ports script.');
    return;
  }

  const visitedPids = new Set();

  for (const port of ports) {
    const pids = getListeningPidsForPort(port);
    if (pids.length === 0) {
      console.log(`[dev] Port ${port} is already free.`);
      continue;
    }

    for (const pid of pids) {
      if (visitedPids.has(pid)) continue;
      visitedPids.add(pid);
      const killed = killPid(pid);
      if (killed) {
        console.log(`[dev] Killed PID ${pid} using port ${port}.`);
      } else {
        console.log(`[dev] Could not kill PID ${pid} (port ${port}).`);
      }
    }
  }
}

main();
