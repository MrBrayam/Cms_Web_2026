using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.Text;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using System.Windows.Forms;

namespace BlogCMSInstaller
{
    public class DbEngineOption
    {
        public string Id;
        public string Name;
        public string EngineType; // "mysql_local", "mysql_xampp", "pgsql"
        public string Driver;     // "mysql" or "pgsql"
        public string Host;
        public int Port;
        public string DefaultUser;
        public string DefaultPass;
        public string BinaryPath;
        public bool PortOpen;
        public string StatusText;

        public override string ToString()
        {
            return string.Format("{0}  [{1}:{2}] — {3}", Name, Host, Port, StatusText);
        }
    }

    public class MainForm : Form
    {
        private string projectRoot;
        private List<DbEngineOption> engines = new List<DbEngineOption>();

        private ComboBox cmbEngines;
        private Label lblEngineBadge;
        private Button btnRescan;
        private Button btnStartDb;
        private Button btnStartApache;
        private Label lblApacheStatus;

        private TextBox txtHost;
        private TextBox txtPort;
        private TextBox txtUser;
        private TextBox txtPass;
        private TextBox txtDbName;
        private ComboBox cmbDbMode;
        private Button btnTestConn;

        private TextBox txtSiteTitle;
        private TextBox txtSiteUrl;
        private ComboBox cmbTheme;
        private TextBox txtAdminUser;
        private TextBox txtAdminPass;

        private ProgressBar progressBar;
        private TextBox txtLog;
        private Button btnInstall;
        private Button btnOpenSite;
        private Button btnOpenAdmin;

        public MainForm()
        {
            projectRoot = AppDomain.CurrentDomain.BaseDirectory.TrimEnd('\\', '/');
            InitializeUi();
            ScanEnvironment();
        }

        private void InitializeUi()
        {
            Text = "Instalador de BlogCMS Propio (Sin WordPress) — UNSM Ingenieria Web";
            Size = new Size(880, 760);
            StartPosition = FormStartPosition.CenterScreen;
            BackColor = Color.FromArgb(241, 245, 249);
            Font = new Font("Segoe UI", 9.5F, FontStyle.Regular);
            FormBorderStyle = FormBorderStyle.FixedSingle;
            MaximizeBox = false;

            Panel header = new Panel { Dock = DockStyle.Top, Height = 82, BackColor = Color.FromArgb(11, 59, 96) };
            header.Controls.Add(new Label
            {
                Text = "Instalador de BlogCMS Propio — Arquitectura PHP + PDO",
                Font = new Font("Segoe UI", 16F, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(22, 14),
                AutoSize = true
            });
            header.Controls.Add(new Label
            {
                Text = "6 Modulos + 6 Plugins (plugin.json / ZipArchive) + 4 Temas Propios · MySQL Local, XAMPP y PostgreSQL",
                Font = new Font("Segoe UI", 9.2F, FontStyle.Regular),
                ForeColor = Color.FromArgb(186, 230, 253),
                Location = new Point(24, 48),
                AutoSize = true
            });
            Controls.Add(header);

            GroupBox grpDetect = new GroupBox
            {
                Text = "1. Detección Automática de Motor de Base de Datos y Servidor Web",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                BackColor = Color.White,
                Location = new Point(18, 96),
                Size = new Size(828, 126)
            };
            grpDetect.Controls.Add(new Label { Text = "Motor detectado a utilizar:", Font = new Font("Segoe UI", 9F, FontStyle.Bold), Location = new Point(16, 28), AutoSize = true });
            cmbEngines = new ComboBox { Font = new Font("Segoe UI", 9.5F), DropDownStyle = ComboBoxStyle.DropDownList, Location = new Point(16, 50), Size = new Size(510, 28) };
            cmbEngines.SelectedIndexChanged += OnEngineSelected;

            btnRescan = new Button { Text = "Re-escanear", Font = new Font("Segoe UI", 9F, FontStyle.Bold), BackColor = Color.FromArgb(226, 232, 240), FlatStyle = FlatStyle.Flat, Location = new Point(536, 48), Size = new Size(120, 30) };
            btnRescan.Click += (s, e) => ScanEnvironment();

            btnStartDb = new Button { Text = "Iniciar BD XAMPP", Font = new Font("Segoe UI", 8.8F, FontStyle.Bold), BackColor = Color.FromArgb(2, 132, 199), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(664, 48), Size = new Size(146, 30) };
            btnStartDb.Click += OnStartDbClicked;

            lblEngineBadge = new Label { Text = "Escaneando motores...", Font = new Font("Segoe UI", 8.8F), ForeColor = Color.FromArgb(71, 85, 105), Location = new Point(16, 88), Size = new Size(430, 24) };
            lblApacheStatus = new Label { Text = "Apache XAMPP: verificando...", Font = new Font("Segoe UI", 8.8F, FontStyle.Bold), Location = new Point(455, 88), Size = new Size(205, 24) };

            btnStartApache = new Button { Text = "Iniciar Apache", Font = new Font("Segoe UI", 8.8F, FontStyle.Bold), BackColor = Color.FromArgb(5, 150, 105), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(664, 84), Size = new Size(146, 30) };
            btnStartApache.Click += OnStartApacheClicked;

            grpDetect.Controls.AddRange(new Control[] { cmbEngines, btnRescan, btnStartDb, lblEngineBadge, lblApacheStatus, btnStartApache });
            Controls.Add(grpDetect);

            GroupBox grpDb = new GroupBox
            {
                Text = "2. Parámetros de Conexión (PDO MySQL / PostgreSQL)",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                BackColor = Color.White,
                Location = new Point(18, 232),
                Size = new Size(828, 136)
            };
            txtHost = AddLabeledTextBox(grpDb, "Host:", 16, 26, 170, "127.0.0.1");
            txtPort = AddLabeledTextBox(grpDb, "Puerto:", 200, 26, 90, "3306");
            txtUser = AddLabeledTextBox(grpDb, "Usuario BD:", 304, 26, 150, "root");
            txtPass = AddLabeledTextBox(grpDb, "Contraseña BD:", 468, 26, 165, "", true);
            txtDbName = AddLabeledTextBox(grpDb, "Base de Datos:", 647, 26, 163, "blogcms_wp");

            grpDb.Controls.Add(new Label { Text = "Inicialización SQL:", Font = new Font("Segoe UI", 9F, FontStyle.Bold), Location = new Point(16, 88), AutoSize = true });
            cmbDbMode = new ComboBox { Font = new Font("Segoe UI", 9F), DropDownStyle = ComboBoxStyle.DropDownList, Location = new Point(145, 84), Size = new Size(488, 26) };
            cmbDbMode.Items.Add("Importar esquema y datos iniciales (schema_mysql.sql / schema_pgsql.sql)");
            cmbDbMode.Items.Add("Conservar tablas existentes (solo generar config/config.php)");
            cmbDbMode.SelectedIndex = 0;

            btnTestConn = new Button { Text = "Probar Conexión", Font = new Font("Segoe UI", 9F, FontStyle.Bold), BackColor = Color.FromArgb(11, 59, 96), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(647, 82), Size = new Size(163, 32) };
            btnTestConn.Click += OnTestConnectionClicked;
            grpDb.Controls.Add(cmbDbMode);
            grpDb.Controls.Add(btnTestConn);
            Controls.Add(grpDb);

            GroupBox grpSite = new GroupBox
            {
                Text = "3. Configuración del Sitio y Tema Inicial",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                BackColor = Color.White,
                Location = new Point(18, 378),
                Size = new Size(828, 122)
            };
            txtSiteTitle = AddLabeledTextBox(grpSite, "Título del Sitio:", 16, 26, 195, "Bloc-FISI");
            txtSiteUrl = AddLabeledTextBox(grpSite, "URL del Sitio:", 225, 26, 255, "http://blogcms.localhost");

            grpSite.Controls.Add(new Label { Text = "Tema Activo Inicial:", Font = new Font("Segoe UI", 9F, FontStyle.Bold), Location = new Point(494, 26), AutoSize = true });
            cmbTheme = new ComboBox { Font = new Font("Segoe UI", 9F), DropDownStyle = ComboBoxStyle.DropDownList, Location = new Point(494, 48), Size = new Size(316, 26) };
            cmbTheme.Items.Add("magazine — 3 columnas con portadas grandes");
            cmbTheme.Items.Add("business — Corporativo con Hero Banner");
            cmbTheme.Items.Add("portfolio — Grilla de proyectos con hover");
            cmbTheme.Items.Add("minimal-blog — Clean y tipográfico");
            cmbTheme.SelectedIndex = 0;
            grpSite.Controls.Add(cmbTheme);

            txtAdminUser = AddLabeledTextBox(grpSite, "Usuario Admin:", 16, 76, 195, "admin", false, true);
            txtAdminPass = AddLabeledTextBox(grpSite, "Nueva Clave Admin (opcional):", 225, 76, 255, "", true, true);
            Controls.Add(grpSite);

            GroupBox grpRun = new GroupBox
            {
                Text = "4. Progreso de Instalación y Accesos Rápidos",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                BackColor = Color.White,
                Location = new Point(18, 510),
                Size = new Size(828, 196)
            };
            progressBar = new ProgressBar { Location = new Point(16, 26), Size = new Size(465, 22), Minimum = 0, Maximum = 100 };
            btnInstall = new Button { Text = "Instalar BlogCMS Ahora", Font = new Font("Segoe UI", 9.5F, FontStyle.Bold), BackColor = Color.FromArgb(5, 150, 105), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(494, 20), Size = new Size(165, 34) };
            btnInstall.Click += OnInstallClicked;

            btnOpenSite = new Button { Text = "Abrir Web", Font = new Font("Segoe UI", 8.8F, FontStyle.Bold), BackColor = Color.FromArgb(2, 132, 199), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(666, 20), Size = new Size(72, 34) };
            btnOpenSite.Click += (s, e) => Process.Start(txtSiteUrl.Text.Trim());

            btnOpenAdmin = new Button { Text = "Admin", Font = new Font("Segoe UI", 8.8F, FontStyle.Bold), BackColor = Color.FromArgb(11, 59, 96), ForeColor = Color.White, FlatStyle = FlatStyle.Flat, Location = new Point(742, 20), Size = new Size(68, 34) };
            btnOpenAdmin.Click += (s, e) => Process.Start(txtSiteUrl.Text.Trim().TrimEnd('/') + "/admin");

            txtLog = new TextBox { Multiline = true, ReadOnly = true, ScrollBars = ScrollBars.Vertical, BackColor = Color.FromArgb(15, 23, 42), ForeColor = Color.FromArgb(226, 232, 240), Font = new Font("Consolas", 9F), Location = new Point(16, 60), Size = new Size(794, 122) };
            grpRun.Controls.AddRange(new Control[] { progressBar, btnInstall, btnOpenSite, btnOpenAdmin, txtLog });
            Controls.Add(grpRun);
        }

        private TextBox AddLabeledTextBox(Control parent, string label, int x, int y, int width, string defaultVal, bool isPassword = false, bool inline = false)
        {
            parent.Controls.Add(new Label { Text = label, Font = new Font("Segoe UI", inline ? 8.5F : 9F, FontStyle.Bold), Location = new Point(x, y), AutoSize = true });
            TextBox tb = new TextBox { Text = defaultVal, Font = new Font("Segoe UI", inline ? 9F : 9.5F), Location = new Point(x, y + (inline ? 18 : 22)), Size = new Size(width, inline ? 24 : 26), UseSystemPasswordChar = isPassword };
            parent.Controls.Add(tb);
            return tb;
        }

        private void Log(string message)
        {
            if (InvokeRequired) { Invoke(new Action<string>(Log), message); return; }
            txtLog.AppendText(string.Format("[{0:HH:mm:ss}] {1}{2}", DateTime.Now, message, Environment.NewLine));
        }

        private bool IsTcpPortOpen(string host, int port, int timeoutMs = 350)
        {
            try
            {
                using (TcpClient c = new TcpClient())
                {
                    IAsyncResult ar = c.BeginConnect(host, port, null, null);
                    bool ok = ar.AsyncWaitHandle.WaitOne(timeoutMs);
                    if (ok && c.Connected) { c.EndConnect(ar); return true; }
                }
            }
            catch { }
            return false;
        }

        private int DetectXamppMysqlPort()
        {
            string iniPath = @"C:\xampp\mysql\bin\my.ini";
            if (File.Exists(iniPath))
            {
                foreach (string line in File.ReadAllLines(iniPath))
                {
                    Match m = Regex.Match(line, @"^\s*port\s*=\s*(\d+)");
                    if (m.Success) { int p; if (int.TryParse(m.Groups[1].Value, out p)) return p; }
                }
            }
            return 3307;
        }

        private string FindBinary(string rootDir, string exeName)
        {
            if (!Directory.Exists(rootDir)) return "";
            string[] files = Directory.GetFiles(rootDir, exeName, SearchOption.AllDirectories);
            return files.Length > 0 ? files[0] : "";
        }

        private void ScanEnvironment()
        {
            engines.Clear();
            cmbEngines.Items.Clear();

            string localBin = FindBinary(@"C:\Program Files\MySQL", "mysql.exe");
            bool localOpen = IsTcpPortOpen("127.0.0.1", 3306);
            engines.Add(new DbEngineOption
            {
                Id = "mysql_local", Name = "MySQL Local (MySQL Server 8.x)", EngineType = "mysql_local", Driver = "mysql",
                Host = "127.0.0.1", Port = 3306, DefaultUser = "root", DefaultPass = "71490956", BinaryPath = localBin,
                PortOpen = localOpen, StatusText = localOpen ? "ACTIVO (Puerto 3306)" : (!string.IsNullOrEmpty(localBin) ? "INSTALADO (Detenido)" : "Manual")
            });

            int xamppPort = DetectXamppMysqlPort();
            string xamppBin = @"C:\xampp\mysql\bin\mysql.exe";
            bool xamppOpen = IsTcpPortOpen("127.0.0.1", xamppPort);
            engines.Add(new DbEngineOption
            {
                Id = "mysql_xampp", Name = "MySQL / MariaDB de XAMPP", EngineType = "mysql_xampp", Driver = "mysql",
                Host = "127.0.0.1", Port = xamppPort, DefaultUser = "root", DefaultPass = "", BinaryPath = File.Exists(xamppBin) ? xamppBin : "",
                PortOpen = xamppOpen, StatusText = xamppOpen ? "ACTIVO (Puerto " + xamppPort + ")" : (File.Exists(xamppBin) ? "Detenido (Puerto " + xamppPort + ")" : "No detectado")
            });

            string pgBin = FindBinary(@"C:\Program Files\PostgreSQL", "psql.exe");
            bool pgOpen = IsTcpPortOpen("127.0.0.1", 5432);
            engines.Add(new DbEngineOption
            {
                Id = "pgsql", Name = "PostgreSQL", EngineType = "pgsql", Driver = "pgsql",
                Host = "127.0.0.1", Port = 5432, DefaultUser = "postgres", DefaultPass = "", BinaryPath = pgBin,
                PortOpen = pgOpen, StatusText = pgOpen ? "ACTIVO (Puerto 5432)" : (!string.IsNullOrEmpty(pgBin) ? "INSTALADO (Detenido)" : "Disponible TCP 5432")
            });

            int preferred = 0;
            for (int i = 0; i < engines.Count; i++)
            {
                cmbEngines.Items.Add(engines[i]);
                if (engines[i].PortOpen && !engines[preferred].PortOpen) preferred = i;
            }
            cmbEngines.SelectedIndex = preferred;

            bool apacheOpen = IsTcpPortOpen("127.0.0.1", 80);
            lblApacheStatus.Text = apacheOpen ? "Apache XAMPP (80): ACTIVO" : "Apache XAMPP (80): DETENIDO";
            lblApacheStatus.ForeColor = apacheOpen ? Color.FromArgb(5, 150, 105) : Color.FromArgb(180, 83, 9);
            Log(string.Format("Escaneo -> MySQL(3306): {0} | XAMPP({1}): {2} | PostgreSQL(5432): {3} | Apache(80): {4}",
                localOpen ? "ON" : "OFF", xamppPort, xamppOpen ? "ON" : "OFF", pgOpen ? "ON" : "OFF", apacheOpen ? "ON" : "OFF"));
        }

        private void OnEngineSelected(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            if (sel == null) return;
            txtHost.Text = sel.Host;
            txtPort.Text = sel.Port.ToString();
            txtUser.Text = sel.DefaultUser;
            txtPass.Text = sel.DefaultPass;
            lblEngineBadge.Text = "Motor: " + sel.Name + " | Driver PDO: " + sel.Driver;
        }

        private void OnStartDbClicked(object sender, EventArgs e)
        {
            string mysqld = @"C:\xampp\mysql\bin\mysqld.exe";
            if (File.Exists(mysqld))
            {
                Log("Iniciando MySQL de XAMPP...");
                Process.Start(new ProcessStartInfo { FileName = mysqld, Arguments = "--defaults-file=\"C:\\xampp\\mysql\\bin\\my.ini\" --standalone", WorkingDirectory = @"C:\xampp\mysql\bin", CreateNoWindow = true, UseShellExecute = false });
                Task.Delay(1800).ContinueWith(_ => Invoke(new Action(ScanEnvironment)));
            }
        }

        private void OnStartApacheClicked(object sender, EventArgs e)
        {
            string httpd = @"C:\xampp\apache\bin\httpd.exe";
            if (File.Exists(httpd))
            {
                Log("Iniciando Apache de XAMPP...");
                Process.Start(new ProcessStartInfo { FileName = httpd, Arguments = "-d C:/xampp/apache", WorkingDirectory = @"C:\xampp\apache", CreateNoWindow = true, UseShellExecute = false });
                Task.Delay(1500).ContinueWith(_ => Invoke(new Action(ScanEnvironment)));
            }
        }

        private string RunPhpFile(string phpCode, string[] args)
        {
            string phpExe = @"C:\xampp\php\php.exe";
            if (!File.Exists(phpExe)) return "FATAL: No se encontro C:\\xampp\\php\\php.exe";
            string tempFile = Path.Combine(Path.GetTempPath(), "blogcms_" + Guid.NewGuid().ToString("N") + ".php");
            try
            {
                File.WriteAllText(tempFile, phpCode, new UTF8Encoding(false));
                StringBuilder argBuilder = new StringBuilder("\"" + tempFile + "\"");
                foreach (string a in args) argBuilder.Append(" \"" + a.Replace("\"", "\\\"") + "\"");

                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = argBuilder.ToString(),
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    StandardOutputEncoding = Encoding.UTF8,
                    StandardErrorEncoding = Encoding.UTF8
                };
                using (Process p = Process.Start(psi))
                {
                    string outTxt = p.StandardOutput.ReadToEnd() + "\n" + p.StandardError.ReadToEnd();
                    p.WaitForExit(30000);
                    return outTxt.Trim();
                }
            }
            finally { try { if (File.Exists(tempFile)) File.Delete(tempFile); } catch { } }
        }

        private void OnTestConnectionClicked(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            string driver = sel != null ? sel.Driver : "mysql";
            EnsureXamppPhpExtensions();

            string code = @"<?php
[$driver, $host, $port, $user, $pass] = array_slice($argv, 1);
try {
    $dsn = ($driver === 'pgsql') ? ""pgsql:host=$host;port=$port;dbname=postgres"" : ""mysql:host=$host;port=$port;charset=utf8mb4"";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 4]);
    echo 'OK: Conectado a ' . $driver . ' v' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "" ($host:$port)"";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}";
            string res = RunPhpFile(code, new[] { driver, txtHost.Text.Trim(), txtPort.Text.Trim(), txtUser.Text.Trim(), txtPass.Text });
            Log(res);
            MessageBox.Show(res, "Prueba de Conexion PDO", MessageBoxButtons.OK, res.StartsWith("OK") ? MessageBoxIcon.Information : MessageBoxIcon.Error);
        }

        private async void OnInstallClicked(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            string driver = sel != null ? sel.Driver : "mysql";
            string host = txtHost.Text.Trim();
            string port = txtPort.Text.Trim();
            string user = txtUser.Text.Trim();
            string pass = txtPass.Text;
            string dbName = Regex.Replace(txtDbName.Text.Trim(), @"[^a-zA-Z0-9_]", "");
            string importFlag = cmbDbMode.SelectedIndex == 0 ? "1" : "0";
            string siteTitle = txtSiteTitle.Text.Trim();
            string siteUrl = txtSiteUrl.Text.Trim().TrimEnd('/');
            string themeSlug = cmbTheme.SelectedItem.ToString().Split(' ')[0].Trim();
            string adminUser = txtAdminUser.Text.Trim();
            string adminPass = txtAdminPass.Text;

            btnInstall.Enabled = false;
            progressBar.Value = 15;
            Log("=== INICIANDO INSTALACION DE BLOGCMS PROPIO ===");

            await Task.Run(() =>
            {
                EnsureXamppPhpExtensions();
                EnsureXamppVirtualHost();
                if (!IsTcpPortOpen("127.0.0.1", 80, 350)) OnStartApacheClicked(null, EventArgs.Empty);

                Invoke(new Action(() => progressBar.Value = 40));

                string phpCode = @"<?php
[$driver, $host, $port, $user, $pass, $db, $importFlag, $title, $url, $theme, $adminUser, $adminPass, $root] = array_slice($argv, 1);
$port = (int)$port;
try {
    $adminDsn = ($driver === 'pgsql') ? ""pgsql:host=$host;port=$port;dbname=postgres"" : ""mysql:host=$host;port=$port;charset=utf8mb4"";
    $rootPdo = new PDO($adminDsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($driver === 'pgsql') {
        $st = $rootPdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $st->execute([$db]);
        if (!$st->fetchColumn()) $rootPdo->exec('CREATE DATABASE ""' . $db . '"" ENCODING \'UTF8\'');
        $dsn = ""pgsql:host=$host;port=$port;dbname=$db"";
        $sqlFile = $root . '/database/schema_pgsql.sql';
    } else {
        $rootPdo->exec(""CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"");
        $dsn = ""mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4"";
        $sqlFile = $root . '/database/schema_mysql.sql';
    }
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo ""Base de datos '$db' ($driver) lista.\n"";

    if ($importFlag === '1') {
        $raw = preg_replace('/^\s*--.*$/m', '', file_get_contents($sqlFile));
        $stmts = [];
        $buf = ''; $inStr = false; $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch === ""'"" && ($i === 0 || $raw[$i - 1] !== '\\')) {
                if ($inStr && $i + 1 < $len && $raw[$i + 1] === ""'"") { $buf .= ""''""; $i++; continue; }
                $inStr = !$inStr;
            }
            if ($ch === ';' && !$inStr) { $t = trim($buf); if ($t !== '') $stmts[] = $t; $buf = ''; }
            else { $buf .= $ch; }
        }
        if (trim($buf) !== '') $stmts[] = trim($buf);
        foreach ($stmts as $q) $pdo->exec($q);
        echo ""Esquema y datos importados desde "" . basename($sqlFile) . "".\n"";
    }

    $up = $pdo->prepare('UPDATE options SET option_value = ? WHERE option_name = ?');
    foreach (['site_title' => $title, 'site_url' => $url, 'active_theme' => $theme] as $k => $v) $up->execute([$v, $k]);
    $pdo->exec('UPDATE themes SET is_active = 0');
    $pdo->prepare('UPDATE themes SET is_active = 1 WHERE slug = ?')->execute([$theme]);

    if ($adminUser !== '') {
        $pdo->prepare('UPDATE users SET username = ? WHERE id = 1')->execute([$adminUser]);
    }
    if ($adminPass !== '') {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 1')->execute([password_hash($adminPass, PASSWORD_BCRYPT)]);
    }

    $cfg = ""<?php\nreturn [\n    'db_driver'  => '$driver',\n    'db_host'    => '$host',\n    'db_port'    => $port,\n    'db_name'    => '$db',\n    'db_user'    => '$user',\n    'db_pass'    => '"" . addcslashes($pass, ""\\'"") . ""',\n    'app_url'    => '$url',\n    'app_secret' => '"" . bin2hex(random_bytes(32)) . ""',\n    'debug'      => false,\n];\n"";
    file_put_contents($root . '/config/config.php', $cfg);
    echo ""config/config.php generado correctamente.\nSUCCESS_OK\n"";
} catch (Throwable $ex) {
    echo 'FATAL: ' . $ex->getMessage();
}";
                string res = RunPhpFile(phpCode, new[] { driver, host, port, user, pass, dbName, importFlag, siteTitle, siteUrl, themeSlug, adminUser, adminPass, projectRoot.Replace("\\", "/") });
                Log(res);
                WriteHtaccess();

                Invoke(new Action(() =>
                {
                    progressBar.Value = res.Contains("SUCCESS_OK") ? 100 : 0;
                    btnInstall.Enabled = true;
                    ScanEnvironment();
                    MessageBox.Show(res.Contains("SUCCESS_OK") ? "BlogCMS instalado correctamente.\nURL: " + siteUrl : res,
                        "Resultado Instalacion", MessageBoxButtons.OK,
                        res.Contains("SUCCESS_OK") ? MessageBoxIcon.Information : MessageBoxIcon.Error);
                }));
            });
        }

        private void EnsureXamppPhpExtensions()
        {
            string phpIni = @"C:\xampp\php\php.ini";
            if (!File.Exists(phpIni)) return;
            try
            {
                string raw = File.ReadAllText(phpIni);
                string upd = raw
                    .Replace(";extension=zip", "extension=zip")
                    .Replace(";extension=gd", "extension=gd")
                    .Replace(";extension=intl", "extension=intl")
                    .Replace(";extension=pdo_pgsql", "extension=pdo_pgsql")
                    .Replace(";extension=pgsql", "extension=pgsql");
                if (upd != raw)
                {
                    File.WriteAllText(phpIni, upd);
                    Log("Extensiones PHP (zip, gd, intl, pdo_pgsql, pgsql) habilitadas en php.ini.");
                }
            }
            catch { }
        }

        private void EnsureXamppVirtualHost()
        {
            string vhostsPath = @"C:\xampp\apache\conf\extra\httpd-vhosts.conf";
            if (!File.Exists(vhostsPath)) return;
            try
            {
                string raw = File.ReadAllText(vhostsPath);
                string normRoot = projectRoot.Replace("\\", "/");
                if (!raw.Contains("ServerName blogcms.localhost"))
                {
                    string block = "\r\n<VirtualHost *:80>\r\n    DocumentRoot \"C:/xampp/htdocs\"\r\n    ServerName localhost\r\n    <Directory \"C:/xampp/htdocs\">\r\n        Options Indexes FollowSymLinks Includes ExecCGI\r\n        AllowOverride All\r\n        Require all granted\r\n    </Directory>\r\n</VirtualHost>\r\n\r\n<VirtualHost *:80>\r\n    DocumentRoot \"" + normRoot + "\"\r\n    ServerName blogcms.localhost\r\n    ServerAlias blogcms.local\r\n    <Directory \"" + normRoot + "\">\r\n        Options Indexes FollowSymLinks Includes ExecCGI\r\n        AllowOverride All\r\n        Require all granted\r\n    </Directory>\r\n</VirtualHost>\r\n";
                    File.AppendAllText(vhostsPath, block);
                    Log("VirtualHost blogcms.localhost configurado.");
                }
            }
            catch { }
        }

        private void WriteHtaccess()
        {
            string rules = "Options -Indexes\r\nRedirectMatch 403 ^/(config|core|database|installer-src)(/.*)?$\r\n\r\n<IfModule mod_rewrite.c>\r\n    RewriteEngine On\r\n    RewriteBase /\r\n    RewriteCond %{REQUEST_FILENAME} !-f\r\n    RewriteCond %{REQUEST_FILENAME} !-d\r\n    RewriteRule ^ index.php [L]\r\n</IfModule>\r\n";
            File.WriteAllText(Path.Combine(projectRoot, ".htaccess"), rules, new UTF8Encoding(false));
        }

        [STAThread]
        public static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());
        }
    }
}
