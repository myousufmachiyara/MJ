; =============================================================================
;  MJ Jewelry System — offline installer script (Inno Setup)
; =============================================================================
;  Compile this with Inno Setup (https://jrsoftware.org/isinfo.php) on your
;  own Windows PC. See README-BUILD.md in this folder for the full,
;  step-by-step process — this script expects a folder laid out like:
;
;    offline-installer\
;      app\        <- the Laravel project (composer install already run)
;      php\        <- portable PHP 8.2 (download it yourself, see README)
;      mysql\      <- portable MariaDB  (download it yourself, see README)
;      scripts\    <- start.bat, stop.bat, backup.bat, first-run.bat, .env.offline
;      mj-offline.iss  <- this file
;
;  Running Setup.exe on the client's PC copies all of that to
;  C:\MJJewelrySystem, adds Start Menu / Desktop shortcuts, and runs
;  first-run.bat once to set up the database.
; =============================================================================

#define MyAppName "MJ Jewelry System"
#define MyAppVersion "1.0"
#define MyAppPublisher "Yuneeq Fine Jewelry L.L.C"
#define MyAppExeName "start.bat"

[Setup]
AppId={{8F2E6B2E-6B61-4C9E-9B2E-7B6B8F7E6B2E}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName=C:\MJJewelrySystem
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
; This installer bundles portable PHP + MariaDB + the app, so it is large.
; Use lzma2/max for the best compression; drop to "fast" while testing to
; speed up repeated builds.
Compression=lzma2
SolidCompression=yes
OutputDir=output
OutputBaseFilename=MJ-Jewelry-System-Setup
; No admin rights needed — everything runs from inside the install folder
; and uses the built-in PHP web server, not IIS/Apache as a Windows service.
PrivilegesRequired=lowest
ArchitecturesInstallIn64BitMode=x64

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
; -- The Laravel app itself (vendor/ must already be installed — see README) --
Source: "app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs
; -- Portable PHP --
Source: "php\*"; DestDir: "{app}\php"; Flags: ignoreversion recursesubdirs createallsubdirs
; -- Portable MariaDB --
Source: "mysql\*"; DestDir: "{app}\mysql"; Flags: ignoreversion recursesubdirs createallsubdirs
; -- Launcher / backup / setup scripts --
Source: "scripts\start.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion
Source: "scripts\stop.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion
Source: "scripts\backup.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion
Source: "scripts\first-run.bat"; DestDir: "{app}\scripts"; Flags: ignoreversion
Source: "scripts\.env.offline"; DestDir: "{app}\scripts"; Flags: ignoreversion

[Dirs]
; Writable folders the app and database need, kept outside "app\" so an
; app update (replacing the app\ folder) never touches data or backups.
Name: "{app}\data"
Name: "{app}\data\mysql-data"
Name: "{app}\backups"

[Icons]
Name: "{group}\Start {#MyAppName}"; Filename: "{app}\scripts\start.bat"; WorkingDir: "{app}\scripts"; IconFilename: "{app}\app\public\favicon.ico"
Name: "{group}\Stop {#MyAppName}"; Filename: "{app}\scripts\stop.bat"; WorkingDir: "{app}\scripts"
Name: "{group}\Back Up Data Now"; Filename: "{app}\scripts\backup.bat"; WorkingDir: "{app}\scripts"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{commondesktop}\{#MyAppName}"; Filename: "{app}\scripts\start.bat"; WorkingDir: "{app}\scripts"; IconFilename: "{app}\app\public\favicon.ico"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "Create a desktop icon"; GroupDescription: "Additional icons:"

[Run]
; Runs once, right after files are copied: initializes the database,
; migrates, seeds default data and a random admin password.
Filename: "{app}\scripts\first-run.bat"; Description: "Set up the database (first run — takes a minute)"; Flags: postinstall runascurrentuser shellexec waituntilterminated
Filename: "{app}\scripts\start.bat"; Description: "Launch {#MyAppName} now"; Flags: postinstall nowait skipifsilent shellexec

[UninstallDelete]
; Stop any running server before removing files (best-effort; ignores errors).
Type: files; Name: "{app}\app\.env"
