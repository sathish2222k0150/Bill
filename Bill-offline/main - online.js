// main.js
const { app, BrowserWindow, Menu } = require('electron'); // Import Menu
const path = require('path');

function createWindow() {
  const win = new BrowserWindow({
    width: 1200, // Make it a bit bigger for the dashboard
    height: 800,
    icon: path.join(__dirname, 'assets/icon.ico')
  });

  // This loads your initial login page
  win.loadFile('index.html');

  // Open developer tools (optional, you can remove this later)
  win.webContents.openDevTools();

  // --- THIS IS THE NEW PART: THE CUSTOM MENU ---
  const menuTemplate = [
    {
      label: 'File',
      submenu: [
        { role: 'quit' } // Standard "Quit" option
      ]
    },
    {
      label: 'Developer',
      submenu: [
        {
          label: 'Reload',
          accelerator: 'CmdOrCtrl+R', // Keyboard shortcut
          click: () => {
            win.webContents.reload(); // Simple reload
          }
        },
        {
          label: 'Force Reload (Clear Cache)',
          accelerator: 'CmdOrCtrl+Shift+R', // Keyboard shortcut
          click: () => {
            win.webContents.reloadIgnoringCache(); // Hard reload
          }
        },
        { role: 'toggleDevTools' } // Option to open/close dev tools
      ]
    }
  ];

  const menu = Menu.buildFromTemplate(menuTemplate);
  Menu.setApplicationMenu(menu);
}

app.whenReady().then(createWindow);