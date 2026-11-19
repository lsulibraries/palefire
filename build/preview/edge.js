/**
 * @file
 * Headless Edge that cannot outlive the script that started it.
 *
 * Every script that needs headless Edge (screenshots, CDP checks, traces)
 * starts it through here, never with its own spawn():
 * - Edge relaunches itself at start unless told not to
 *   (--edge-skip-compat-layer-relaunch), so the spawned PID can be a stub
 *   that is already gone: proc.kill() then missed the real browser, which
 *   is how dozens were left running. Close stops every process whose
 *   command line holds this launch's own profile folder, and the tree.
 * - A watchdog in its own process closes Edge when the script dies in any
 *   way (error, Ctrl+C, a session stopped, taskkill) or after maxMs.
 * - It refuses to start when MAX_BROWSERS headless Edges already run.
 * - Each launch gets a fresh profile in the temp folder, deleted on close,
 *   so it never joins the user's own Edge.
 *
 * const { launch, screenshot } = require('./edge');
 * const edge = await launch({ port: 9333 });
 * const page = await edge.page();       // { send, ws }
 * await page.send('Page.navigate', { url });
 * edge.close();
 *
 * await screenshot({ url, file, width: 1440, height: 900 });
 */

'use strict';

const { spawn, execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const MAX_BROWSERS = Number(process.env.PF_EDGE_MAX) || 4;
const DEFAULT_MAX_MS = 5 * 60 * 1000;
const BASE_ARGS = [
  '--headless=new',
  '--disable-gpu',
  '--no-first-run',
  '--no-default-browser-check',
  '--disable-extensions',
  '--disable-sync',
  '--disable-background-networking',
  '--disable-component-update',
  '--edge-skip-compat-layer-relaunch',
];

// Run by the watchdog process: args are owner PID, Edge PID, profile, deadline.
const WATCHDOG = `
const { execFileSync } = require('child_process');
const fs = require('fs');
const [owner, edge, dir, deadline] = process.argv.slice(1);
const t = setInterval(() => {
  let alive = Date.now() < Number(deadline);
  try { process.kill(Number(owner), 0); } catch (e) { alive = false; }
  if (alive) return;
  clearInterval(t);
  try { execFileSync('taskkill', ['/T', '/F', '/PID', edge], { stdio: 'ignore', windowsHide: true }); } catch (e) {}
  for (let i = 0; i < 20; i++) {
    try { fs.rmSync(dir, { recursive: true, force: true }); break; }
    catch (e) { Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 500); }
  }
}, 500);
`;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sleepSync = (ms) => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);

/**
 * Counts headless Edge browsers already running (main processes only).
 */
function runningBrowsers() {
  const ps = "@(Get-CimInstance Win32_Process -Filter \"Name='msedge.exe'\" | Where-Object { $_.CommandLine -match '--headless' -and $_.CommandLine -notmatch '--type=' }).Count";
  try {
    return Number(execFileSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', ps], { encoding: 'utf8', windowsHide: true, timeout: 60000 }).trim()) || 0;
  }
  catch (e) {
    return 0;
  }
}

function killTree(pid) {
  try {
    execFileSync('taskkill', ['/T', '/F', '/PID', String(pid)], { stdio: 'ignore', windowsHide: true });
  }
  catch (e) {
    // Already gone.
  }
}

/**
 * Stops every Edge process started with this profile folder, and its tree.
 */
function killByDir(dir, pid) {
  killTree(pid);
  const ps = "Get-CimInstance Win32_Process -Filter \"Name='msedge.exe'\" | Where-Object { $_.CommandLine -like '*" + dir + "*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }";
  try {
    execFileSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', ps], { stdio: 'ignore', windowsHide: true, timeout: 60000 });
  }
  catch (e) {
    // Nothing left to stop.
  }
}

function removeDir(dir) {
  for (let i = 0; i < 10; i++) {
    try {
      fs.rmSync(dir, { recursive: true, force: true });
      return;
    }
    catch (e) {
      sleepSync(300);
    }
  }
}

/**
 * Starts Edge with a fresh profile, a watchdog and exit handlers.
 */
function start(args, maxMs) {
  const running = runningBrowsers();
  if (running >= MAX_BROWSERS) {
    throw new Error(`${running} headless Edge browsers are already running (limit ${MAX_BROWSERS}). Let them finish, or stop leftovers with ~/.claude/tools/reap-headless-edge.ps1 -MaxMinutes 0.`);
  }
  const dir = path.join(os.tmpdir(), 'pf-edge', `${process.pid}-${Date.now()}`);
  fs.mkdirSync(dir, { recursive: true });
  const proc = spawn(EDGE, [...BASE_ARGS, '--user-data-dir=' + dir, ...args], { stdio: 'ignore', windowsHide: true });

  // The watchdog, a detached Node process, checks every half second that
  // this script is alive; when it is gone (or after maxMs) it kills Edge's
  // tree and deletes the profile. Node, not PowerShell: a detached process
  // has no console, and PowerShell without one exits doing nothing.
  const dog = spawn(process.execPath, ['-e', WATCHDOG, String(process.pid), String(proc.pid), dir, String(Date.now() + maxMs)], { detached: true, stdio: 'ignore', windowsHide: true });
  dog.unref();

  let closed = false;
  const close = () => {
    if (closed) {
      return;
    }
    closed = true;
    clearTimeout(timer);
    // Watchdog first, so it can never act on a reused PID later.
    killTree(dog.pid);
    killByDir(dir, proc.pid);
    removeDir(dir);
  };
  const timer = setTimeout(() => {
    console.error(`edge.js: closing headless Edge after ${maxMs / 1000}s (maxMs).`);
    close();
  }, maxMs);
  timer.unref();

  process.on('exit', close);
  for (const sig of ['SIGINT', 'SIGTERM', 'SIGHUP', 'SIGBREAK']) {
    process.once(sig, () => {
      close();
      process.exit(130);
    });
  }
  return { proc, close };
}

/**
 * Launches headless Edge with a CDP port and waits until it answers.
 */
async function launch({ port = 9333, args = [], maxMs = DEFAULT_MAX_MS } = {}) {
  const base = `http://127.0.0.1:${port}`;
  try {
    await fetch(base + '/json/version');
    throw new Error(`Port ${port} already has a browser on it; pick another port.`);
  }
  catch (e) {
    if (e.message.startsWith('Port')) {
      throw e;
    }
  }
  const { proc, close } = start(['--remote-debugging-port=' + port, ...args, 'about:blank'], maxMs);
  for (let i = 0; ; i++) {
    if (proc.exitCode !== null) {
      close();
      throw new Error('Headless Edge exited before it was ready.');
    }
    try {
      await (await fetch(base + '/json/version')).json();
      break;
    }
    catch (e) {
      if (i > 75) {
        close();
        throw new Error('Headless Edge did not answer on port ' + port);
      }
      await sleep(400);
    }
  }

  // Connects to the first page target and returns a small CDP client.
  const page = async () => {
    const list = await (await fetch(base + '/json')).json();
    const target = list.find((t) => t.type === 'page');
    const ws = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((r, j) => {
      ws.addEventListener('open', r);
      ws.addEventListener('error', j);
    });
    let id = 0;
    const wait = {};
    ws.addEventListener('message', (m) => {
      const d = JSON.parse(m.data);
      if (d.id && wait[d.id]) {
        wait[d.id](d);
        delete wait[d.id];
      }
    });
    const send = (method, params = {}) => new Promise((r) => {
      const i = ++id;
      wait[i] = r;
      ws.send(JSON.stringify({ id: i, method, params }));
    });
    return { ws, send };
  };

  return { port, proc, page, close };
}

/**
 * One-shot --screenshot run; the tree is killed if it hangs.
 */
function screenshot({ url, file, width = 1440, height = 900, args = [], maxMs = 60000 }) {
  return new Promise((resolve, reject) => {
    const { proc, close } = start([`--screenshot=${file}`, `--window-size=${width},${height}`, '--hide-scrollbars', ...args, url], maxMs);
    proc.on('exit', () => {
      close();
      fs.existsSync(file) ? resolve(file) : reject(new Error('No screenshot written: ' + url));
    });
  });
}

module.exports = { launch, screenshot, runningBrowsers };
