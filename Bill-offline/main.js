// main.js - FINAL PORTABLE VERSION
const { app, BrowserWindow, Menu } = require('electron');
const path = require('path');
const { spawn } = require('child_process');

// This variable will hold our background PHP server process
let phpServer;

function createWindow() {
  // --- This block starts the PHP server in the background ---
  
  // Path to your portable PHP executable inside the app folder
  const phpPath = path.join(__dirname, 'php', 'php.exe');
  

  const phpProjectFolder = path.join(__dirname, 'www/');
  
  // A free port to run the server on
  const port = 8000;

  console.log(`Starting PHP server at ${phpProjectFolder} on port ${port}...`);

  // Start the PHP server process
  phpServer = spawn(phpPath, ['-S', `localhost:${port}`, '-t', phpProjectFolder]);

  // Log any output from the PHP server for debugging
  phpServer.stdout.on('data', (data) => console.log(`PHP Server: ${data.toString()}`));
  phpServer.stderr.on('data', (data) => console.error(`PHP Server Error: ${data.toString()}`));
  // --- End of PHP server block ---


  // Create the application window
  const win = new BrowserWindow({
    width: 1200,
    height: 800,
    // The icon path is correct and preserved
    icon: path.join(__dirname, 'assets/icon.ico')
  });

  // Load the URL from the LOCAL PHP server we just started.
  // We add a small delay to give the server time to start up.
  setTimeout(() => {
    win.loadURL(`http://localhost:${port}/index.php`);
  }, 1000);

  // Keep the developer tools open (I corrected the typo from openDevToos)
  win.webContents.openDevTools();

  // Your custom menu is preserved and will work correctly
  const menuTemplate = [
    {
      label: 'File',
      submenu: [ { role: 'quit' } ]
    },
    {
      label: 'Developer',
      submenu: [
        { label: 'Reload', accelerator: 'CmdOrCtrl+R', click: () => { win.webContents.reload(); } },
        { label: 'Force Reload (Clear Cache)', accelerator: 'CmdOrCtrl+Shift+R', click: () => { win.webContents.reloadIgnoringCache(); } },
        { role: 'toggleDevTools' }
      ]
    }
  ];
  const menu = Menu.buildFromTemplate(menuTemplate);
  Menu.setApplicationMenu(menu);
}

app.whenReady().then(createWindow);

// This function is crucial to shut down the PHP server when the app closes
app.on('window-all-closed', () => {
  console.log('App is closing, shutting down PHP server...');
  if (phpServer) {
    phpServer.kill(); // Kill the background process
  }
  if (process.platform !== 'darwin') {
    app.quit();
  }
});