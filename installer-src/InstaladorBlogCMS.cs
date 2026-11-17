using System;
using System.Collections.Generic;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net.Sockets;
using System.Security.Cryptography;
using System.ServiceProcess;
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
        public string Host;
        public int Port;
        public string DefaultUser;
        public string DefaultPass;
        public string BinaryPath;
        public bool Installed;
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
            Text = "Instalador Ejecutable de BlogCMS (WordPress) — UNSM Ingeniería Web";
            Size = new Size(880, 760);
            StartPosition = FormStartPosition.CenterScreen;
            BackColor = Color.FromArgb(241, 245, 249);
            Font = new Font("Segoe UI", 9.5F, FontStyle.Regular);
            FormBorderStyle = FormBorderStyle.FixedSingle;
            MaximizeBox = false;

            // Header Banner
            Panel header = new Panel
            {
                Dock = DockStyle.Top,
                Height = 82,
                BackColor = Color.FromArgb(11, 59, 96)
            };
            Label lblTitle = new Label
            {
                Text = "Instalador de BlogCMS — Tipo: WordPress",
                Font = new Font("Segoe UI", 16F, FontStyle.Bold),
                ForeColor = Color.White,
                Location = new Point(22, 14),
                AutoSize = true
            };
            Label lblSub = new Label
            {
                Text = "UNSM · Ingeniería Web · Detección automática de MySQL Local, MySQL XAMPP y PostgreSQL",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                ForeColor = Color.FromArgb(186, 230, 253),
                Location = new Point(24, 48),
                AutoSize = true
            };
            header.Controls.Add(lblTitle);
            header.Controls.Add(lblSub);
            Controls.Add(header);

            // Group 1: Auto-detection of DB Engine & Apache
            GroupBox grpDetect = new GroupBox
            {
                Text = "1. Detección Automática de Motor de Base de Datos y Servidor Web",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                BackColor = Color.White,
                Location = new Point(18, 96),
                Size = new Size(828, 126)
            };

            Label lblSelectEngine = new Label
            {
                Text = "Motor detectado a utilizar:",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                Location = new Point(16, 28),
                AutoSize = true
            };
            cmbEngines = new ComboBox
            {
                Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                DropDownStyle = ComboBoxStyle.DropDownList,
                Location = new Point(16, 50),
                Size = new Size(510, 28)
            };
            cmbEngines.SelectedIndexChanged += OnEngineSelected;

            btnRescan = new Button
            {
                Text = "Re-escanear",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                BackColor = Color.FromArgb(226, 232, 240),
                FlatStyle = FlatStyle.Flat,
                Location = new Point(536, 48),
                Size = new Size(120, 30)
            };
            btnRescan.Click += (s, e) => ScanEnvironment();

            btnStartDb = new Button
            {
                Text = "Iniciar BD XAMPP",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Bold),
                BackColor = Color.FromArgb(2, 132, 199),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(664, 48),
                Size = new Size(146, 30)
            };
            btnStartDb.Click += OnStartDbClicked;

            lblEngineBadge = new Label
            {
                Text = "Escaneando motores...",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Regular),
                ForeColor = Color.FromArgb(71, 85, 105),
                Location = new Point(16, 88),
                Size = new Size(430, 24)
            };

            lblApacheStatus = new Label
            {
                Text = "Apache XAMPP: verificando...",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                Location = new Point(455, 88),
                Size = new Size(205, 24)
            };

            btnStartApache = new Button
            {
                Text = "Iniciar Apache",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Bold),
                BackColor = Color.FromArgb(5, 150, 105),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(664, 84),
                Size = new Size(146, 30)
            };
            btnStartApache.Click += OnStartApacheClicked;

            grpDetect.Controls.AddRange(new Control[] {
                lblSelectEngine, cmbEngines, btnRescan, btnStartDb, lblEngineBadge, lblApacheStatus, btnStartApache
            });
            Controls.Add(grpDetect);

            // Group 2: Database Parameters
            GroupBox grpDb = new GroupBox
            {
                Text = "2. Parámetros de Conexión a la Base de Datos",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                BackColor = Color.White,
                Location = new Point(18, 232),
                Size = new Size(828, 136)
            };

            txtHost = AddLabeledTextBox(grpDb, "Host / Servidor:", 16, 26, 170, "127.0.0.1");
            txtPort = AddLabeledTextBox(grpDb, "Puerto:", 200, 26, 90, "3306");
            txtUser = AddLabeledTextBox(grpDb, "Usuario BD:", 304, 26, 150, "root");
            txtPass = AddLabeledTextBox(grpDb, "Contraseña BD:", 468, 26, 165, "71490956", true);
            txtDbName = AddLabeledTextBox(grpDb, "Nombre Base de Datos:", 647, 26, 163, "blogcms_wp");

            Label lblMode = new Label
            {
                Text = "Inicialización SQL:",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                Location = new Point(16, 88),
                AutoSize = true
            };
            cmbDbMode = new ComboBox
            {
                Font = new Font("Segoe UI", 9F, FontStyle.Regular),
                DropDownStyle = ComboBoxStyle.DropDownList,
                Location = new Point(145, 84),
                Size = new Size(488, 26)
            };
            cmbDbMode.Items.Add("Importar database\\blogcms_wp.sql (6 módulos, 6 plugins, 4 temas y datos)");
            cmbDbMode.Items.Add("Conservar tablas existentes (solo actualizar wp-config.php y opciones)");
            cmbDbMode.SelectedIndex = 0;

            btnTestConn = new Button
            {
                Text = "Probar Conexión BD",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                BackColor = Color.FromArgb(11, 59, 96),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(647, 82),
                Size = new Size(163, 32)
            };
            btnTestConn.Click += OnTestConnectionClicked;

            grpDb.Controls.Add(lblMode);
            grpDb.Controls.Add(cmbDbMode);
            grpDb.Controls.Add(btnTestConn);
            Controls.Add(grpDb);

            // Group 3: Site & Theme Configuration
            GroupBox grpSite = new GroupBox
            {
                Text = "3. Configuración del Sitio y Tema Inicial (Desarrollados Desde Cero)",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                BackColor = Color.White,
                Location = new Point(18, 378),
                Size = new Size(828, 122)
            };

            txtSiteTitle = AddLabeledTextBox(grpSite, "Título del Sitio:", 16, 26, 195, "Bloc-FISI");
            txtSiteUrl = AddLabeledTextBox(grpSite, "URL del Sitio:", 225, 26, 255, "http://blogcms.localhost");

            Label lblTheme = new Label
            {
                Text = "Tema Activo Inicial:",
                Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                Location = new Point(494, 26),
                AutoSize = true
            };
            cmbTheme = new ComboBox
            {
                Font = new Font("Segoe UI", 9F, FontStyle.Regular),
                DropDownStyle = ComboBoxStyle.DropDownList,
                Location = new Point(494, 48),
                Size = new Size(316, 26)
            };
            cmbTheme.Items.Add("magazine — 3 columnas con portadas grandes");
            cmbTheme.Items.Add("business — Corporativo con Hero Banner");
            cmbTheme.Items.Add("portfolio — Grilla de proyectos con hover");
            cmbTheme.Items.Add("minimal-blog — Clean y tipográfico");
            cmbTheme.SelectedIndex = 0;

            txtAdminUser = AddLabeledTextBox(grpSite, "Usuario Admin:", 16, 76, 195, "BrayamAF", false, true);
            txtAdminPass = AddLabeledTextBox(grpSite, "Nueva Clave Admin (opcional):", 225, 76, 255, "", true, true);

            grpSite.Controls.Add(lblTheme);
            grpSite.Controls.Add(cmbTheme);
            Controls.Add(grpSite);

            // Group 4: Execution & Log
            GroupBox grpRun = new GroupBox
            {
                Text = "4. Progreso de Instalación y Accesos Rápidos",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                ForeColor = Color.FromArgb(15, 23, 42),
                BackColor = Color.White,
                Location = new Point(18, 510),
                Size = new Size(828, 196)
            };

            progressBar = new ProgressBar
            {
                Location = new Point(16, 26),
                Size = new Size(465, 22),
                Minimum = 0,
                Maximum = 100,
                Value = 0
            };

            btnInstall = new Button
            {
                Text = "Instalar BlogCMS Ahora",
                Font = new Font("Segoe UI", 9.5F, FontStyle.Bold),
                BackColor = Color.FromArgb(5, 150, 105),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(494, 20),
                Size = new Size(165, 34)
            };
            btnInstall.Click += OnInstallClicked;

            btnOpenSite = new Button
            {
                Text = "Abrir Web",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Bold),
                BackColor = Color.FromArgb(2, 132, 199),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(666, 20),
                Size = new Size(72, 34)
            };
            btnOpenSite.Click += (s, e) => Process.Start(txtSiteUrl.Text.Trim());

            btnOpenAdmin = new Button
            {
                Text = "Admin",
                Font = new Font("Segoe UI", 8.8F, FontStyle.Bold),
                BackColor = Color.FromArgb(11, 59, 96),
                ForeColor = Color.White,
                FlatStyle = FlatStyle.Flat,
                Location = new Point(742, 20),
                Size = new Size(68, 34)
            };
            btnOpenAdmin.Click += (s, e) => Process.Start(txtSiteUrl.Text.Trim().TrimEnd('/') + "/wp-admin/");

            txtLog = new TextBox
            {
                Multiline = true,
                ReadOnly = true,
                ScrollBars = ScrollBars.Vertical,
                BackColor = Color.FromArgb(15, 23, 42),
                ForeColor = Color.FromArgb(226, 232, 240),
                Font = new Font("Consolas", 9F, FontStyle.Regular),
                Location = new Point(16, 60),
                Size = new Size(794, 122)
            };

            grpRun.Controls.AddRange(new Control[] {
                progressBar, btnInstall, btnOpenSite, btnOpenAdmin, txtLog
            });
            Controls.Add(grpRun);
        }

        private TextBox AddLabeledTextBox(Control parent, string label, int x, int y, int width, string defaultVal, bool isPassword = false, bool inline = false)
        {
            if (inline)
            {
                Label lbl = new Label
                {
                    Text = label,
                    Font = new Font("Segoe UI", 8.5F, FontStyle.Bold),
                    Location = new Point(x, y),
                    AutoSize = true
                };
                TextBox tb = new TextBox
                {
                    Text = defaultVal,
                    Font = new Font("Segoe UI", 9F, FontStyle.Regular),
                    Location = new Point(x, y + 18),
                    Size = new Size(width, 24),
                    UseSystemPasswordChar = isPassword
                };
                parent.Controls.Add(lbl);
                parent.Controls.Add(tb);
                return tb;
            }
            else
            {
                Label lbl = new Label
                {
                    Text = label,
                    Font = new Font("Segoe UI", 9F, FontStyle.Bold),
                    Location = new Point(x, y),
                    AutoSize = true
                };
                TextBox tb = new TextBox
                {
                    Text = defaultVal,
                    Font = new Font("Segoe UI", 9.5F, FontStyle.Regular),
                    Location = new Point(x, y + 22),
                    Size = new Size(width, 26),
                    UseSystemPasswordChar = isPassword
                };
                parent.Controls.Add(lbl);
                parent.Controls.Add(tb);
                return tb;
            }
        }

        private void Log(string message)
        {
            if (InvokeRequired)
            {
                Invoke(new Action<string>(Log), message);
                return;
            }
            string line = string.Format("[{0:HH:mm:ss}] {1}{2}", DateTime.Now, message, Environment.NewLine);
            txtLog.AppendText(line);
        }

        private bool IsTcpPortOpen(string host, int port, int timeoutMs = 350)
        {
            try
            {
                using (TcpClient client = new TcpClient())
                {
                    IAsyncResult res = client.BeginConnect(host, port, null, null);
                    bool success = res.AsyncWaitHandle.WaitOne(timeoutMs);
                    if (success && client.Connected)
                    {
                        client.EndConnect(res);
                        return true;
                    }
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
                try
                {
                    foreach (string line in File.ReadAllLines(iniPath))
                    {
                        Match m = Regex.Match(line, @"^\s*port\s*=\s*(\d+)");
                        if (m.Success)
                        {
                            int p;
                            if (int.TryParse(m.Groups[1].Value, out p))
                                return p;
                        }
                    }
                }
                catch { }
            }
            return 3307;
        }

        private string FindLocalMysqlBinary()
        {
            string baseDir = @"C:\Program Files\MySQL";
            if (Directory.Exists(baseDir))
            {
                string[] files = Directory.GetFiles(baseDir, "mysql.exe", SearchOption.AllDirectories);
                if (files.Length > 0) return files[0];
            }
            return "";
        }

        private string FindPostgresBinary()
        {
            string baseDir = @"C:\Program Files\PostgreSQL";
            if (Directory.Exists(baseDir))
            {
                string[] files = Directory.GetFiles(baseDir, "psql.exe", SearchOption.AllDirectories);
                if (files.Length > 0) return files[0];
            }
            return "";
        }

        private void ScanEnvironment()
        {
            engines.Clear();
            cmbEngines.Items.Clear();

            // 1. MySQL Local (3306)
            string localMysqlBin = FindLocalMysqlBinary();
            bool localPortOpen = IsTcpPortOpen("127.0.0.1", 3306);
            bool localInstalled = !string.IsNullOrEmpty(localMysqlBin) || localPortOpen;
            var optLocal = new DbEngineOption
            {
                Id = "mysql_local",
                Name = "MySQL Local (MySQL Server 8.x)",
                EngineType = "mysql_local",
                Host = "127.0.0.1",
                Port = 3306,
                DefaultUser = "root",
                DefaultPass = "71490956",
                BinaryPath = localMysqlBin,
                Installed = localInstalled,
                PortOpen = localPortOpen,
                StatusText = localPortOpen ? "ACTIVO (Puerto 3306 escuchando)" : (localInstalled ? "INSTALADO (Detenido)" : "No detectado (Manual)")
            };
            engines.Add(optLocal);

            // 2. MySQL / MariaDB de XAMPP
            int xamppPort = DetectXamppMysqlPort();
            string xamppMysqlBin = @"C:\xampp\mysql\bin\mysql.exe";
            bool xamppInstalled = File.Exists(xamppMysqlBin);
            bool xamppPortOpen = IsTcpPortOpen("127.0.0.1", xamppPort);
            var optXampp = new DbEngineOption
            {
                Id = "mysql_xampp",
                Name = "MySQL / MariaDB de XAMPP",
                EngineType = "mysql_xampp",
                Host = "127.0.0.1",
                Port = xamppPort,
                DefaultUser = "root",
                DefaultPass = "",
                BinaryPath = xamppInstalled ? xamppMysqlBin : "",
                Installed = xamppInstalled,
                PortOpen = xamppPortOpen,
                StatusText = xamppPortOpen ? string.Format("ACTIVO (Puerto {0} escuchando)", xamppPort) : (xamppInstalled ? string.Format("INSTALADO en C:\\xampp (Puerto {0} detenido)", xamppPort) : "No detectado")
            };
            engines.Add(optXampp);

            // 3. PostgreSQL (5432)
            string pgBin = FindPostgresBinary();
            bool pgPortOpen = IsTcpPortOpen("127.0.0.1", 5432);
            bool pgInstalled = !string.IsNullOrEmpty(pgBin) || pgPortOpen;
            var optPg = new DbEngineOption
            {
                Id = "pgsql",
                Name = "PostgreSQL (Servidor Local / Remoto)",
                EngineType = "pgsql",
                Host = "127.0.0.1",
                Port = 5432,
                DefaultUser = "postgres",
                DefaultPass = "",
                BinaryPath = pgBin,
                Installed = pgInstalled,
                PortOpen = pgPortOpen,
                StatusText = pgPortOpen ? "ACTIVO (Puerto 5432 escuchando)" : (pgInstalled ? "INSTALADO (Puerto 5432 detenido)" : "Disponible por conexión TCP 5432")
            };
            engines.Add(optPg);

            int preferredIndex = 0;
            for (int i = 0; i < engines.Count; i++)
            {
                cmbEngines.Items.Add(engines[i]);
                if (engines[i].PortOpen && preferredIndex == 0 && i > 0 && !engines[0].PortOpen)
                {
                    preferredIndex = i;
                }
            }
            cmbEngines.SelectedIndex = preferredIndex;

            // Check Apache XAMPP
            bool apacheOpen = IsTcpPortOpen("127.0.0.1", 80);
            lblApacheStatus.Text = apacheOpen ? "Apache XAMPP (80): ACTIVO" : "Apache XAMPP (80): DETENIDO";
            lblApacheStatus.ForeColor = apacheOpen ? Color.FromArgb(5, 150, 105) : Color.FromArgb(180, 83, 9);

            Log(string.Format("Escaneo completado -> MySQL Local(3306): {0} | XAMPP({1}): {2} | PostgreSQL(5432): {3} | Apache(80): {4}",
                localPortOpen ? "ON" : "OFF",
                xamppPort, xamppPortOpen ? "ON" : "OFF",
                pgPortOpen ? "ON" : "OFF",
                apacheOpen ? "ON" : "OFF"));
        }

        private void OnEngineSelected(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            if (sel == null) return;

            txtHost.Text = sel.Host;
            txtPort.Text = sel.Port.ToString();
            txtUser.Text = sel.DefaultUser;
            txtPass.Text = sel.DefaultPass;

            lblEngineBadge.Text = string.Format("Seleccionado: {0} | Binario: {1}",
                sel.Name,
                string.IsNullOrEmpty(sel.BinaryPath) ? "Cliente PHP / TCP" : sel.BinaryPath);
        }

        private void OnStartDbClicked(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            if (sel != null && sel.EngineType == "mysql_xampp")
            {
                string mysqld = @"C:\xampp\mysql\bin\mysqld.exe";
                string myini = @"C:\xampp\mysql\bin\my.ini";
                if (File.Exists(mysqld))
                {
                    Log("Iniciando MySQL de XAMPP en segundo plano...");
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = mysqld,
                        Arguments = string.Format("--defaults-file=\"{0}\" --standalone", myini),
                        WorkingDirectory = @"C:\xampp\mysql\bin",
                        CreateNoWindow = true,
                        UseShellExecute = false
                    });
                    Task.Delay(1800).ContinueWith(_ => Invoke(new Action(ScanEnvironment)));
                    return;
                }
            }
            MessageBox.Show("Si deseas iniciar MySQL de XAMPP, selecciona 'MySQL / MariaDB de XAMPP' o verifica que C:\\xampp\\mysql exista.", "Iniciar BD", MessageBoxButtons.OK, MessageBoxIcon.Information);
        }

        private void OnStartApacheClicked(object sender, EventArgs e)
        {
            string httpd = @"C:\xampp\apache\bin\httpd.exe";
            if (!File.Exists(httpd))
            {
                MessageBox.Show("No se encontró C:\\xampp\\apache\\bin\\httpd.exe", "Apache XAMPP", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }
            Log("Iniciando Apache de XAMPP (puerto 80)...");
            Process.Start(new ProcessStartInfo
            {
                FileName = httpd,
                Arguments = "-d C:/xampp/apache",
                WorkingDirectory = @"C:\xampp\apache",
                CreateNoWindow = true,
                UseShellExecute = false
            });
            Task.Delay(1500).ContinueWith(_ => Invoke(new Action(ScanEnvironment)));
        }

        private string RunPhpScript(string phpCode)
        {
            string phpExe = @"C:\xampp\php\php.exe";
            if (!File.Exists(phpExe))
            {
                return "ERROR: No se encontró C:\\xampp\\php\\php.exe";
            }
            string tempFile = Path.Combine(Path.GetTempPath(), "blogcms_inst_" + Guid.NewGuid().ToString("N") + ".php");
            try
            {
                File.WriteAllText(tempFile, phpCode, new UTF8Encoding(false));
                ProcessStartInfo psi = new ProcessStartInfo
                {
                    FileName = phpExe,
                    Arguments = "\"" + tempFile + "\"",
                    RedirectStandardOutput = true,
                    RedirectStandardError = true,
                    UseShellExecute = false,
                    CreateNoWindow = true,
                    StandardOutputEncoding = Encoding.UTF8,
                    StandardErrorEncoding = Encoding.UTF8
                };
                using (Process p = Process.Start(psi))
                {
                    string stdout = p.StandardOutput.ReadToEnd();
                    string stderr = p.StandardError.ReadToEnd();
                    p.WaitForExit(30000);
                    return (stdout + "\n" + stderr).Trim();
                }
            }
            finally
            {
                try { if (File.Exists(tempFile)) File.Delete(tempFile); } catch { }
            }
        }

        private void OnTestConnectionClicked(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            string engineType = sel != null ? sel.EngineType : "mysql_local";
            string host = txtHost.Text.Trim();
            int port;
            int.TryParse(txtPort.Text.Trim(), out port);
            string user = txtUser.Text.Trim();
            string pass = txtPass.Text;

            Log(string.Format("Probando conexión a {0} ({1}:{2}) con usuario '{3}'...", engineType, host, port, user));

            if (!IsTcpPortOpen(host, port, 600))
            {
                Log(string.Format("ERROR: El puerto TCP {0}:{1} no está respondiendo. Inicia el servicio de base de datos primero.", host, port));
                MessageBox.Show(string.Format("El servidor {0}:{1} no responde.\nVerifica que el motor seleccionado esté en ejecución.", host, port), "Puerto cerrado", MessageBoxButtons.OK, MessageBoxIcon.Warning);
                return;
            }

            string code;
            if (engineType == "pgsql")
            {
                code = string.Format(@"<?php
if (!extension_loaded('pdo_pgsql') && !extension_loaded('pgsql')) {{
    echo 'TCP_OK: El puerto PostgreSQL {0}:{1} está abierto (Habilita extension=pdo_pgsql en C:\\xampp\\php\\php.ini para consultas directas).';
    exit;
}}
try {{
    $dsn = 'pgsql:host={0};port={1};dbname=postgres';
    $pdo = new PDO($dsn, '{2}', '{3}', [PDO::ATTR_TIMEOUT => 4]);
    echo 'OK: Conectado exitosamente a PostgreSQL en {0}:{1}';
}} catch (Exception $e) {{
    echo 'ERROR: ' . $e->getMessage();
}}", host.Replace("'", ""), port, user.Replace("'", ""), pass.Replace("'", "\\'"));
            }
            else
            {
                code = string.Format(@"<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli('{0}', '{2}', '{3}', '', {1});
if ($m->connect_errno) {{
    echo 'ERROR (' . $m->connect_errno . '): ' . $m->connect_error;
}} else {{
    echo 'OK: Conectado a MySQL/MariaDB v' . $m->server_info . ' en {0}:{1}';
    $m->close();
}}", host.Replace("'", ""), port, user.Replace("'", ""), pass.Replace("'", "\\'"));
            }

            string res = RunPhpScript(code);
            Log(res);
            MessageBox.Show(res, "Resultado de Conexión", MessageBoxButtons.OK,
                res.StartsWith("OK") || res.StartsWith("TCP_OK") ? MessageBoxIcon.Information : MessageBoxIcon.Error);
        }

        private async void OnInstallClicked(object sender, EventArgs e)
        {
            var sel = cmbEngines.SelectedItem as DbEngineOption;
            string engineType = sel != null ? sel.EngineType : "mysql_local";
            string host = txtHost.Text.Trim();
            int port;
            int.TryParse(txtPort.Text.Trim(), out port);
            string user = txtUser.Text.Trim();
            string pass = txtPass.Text;
            string dbName = Regex.Replace(txtDbName.Text.Trim(), @"[^a-zA-Z0-9_]", "");
            bool importSql = cmbDbMode.SelectedIndex == 0;
            string siteTitle = txtSiteTitle.Text.Trim();
            string siteUrl = txtSiteUrl.Text.Trim().TrimEnd('/');
            string themeChoice = cmbTheme.SelectedItem.ToString().Split(' ')[0].Trim();
            string adminUser = txtAdminUser.Text.Trim();
            string adminPass = txtAdminPass.Text;

            btnInstall.Enabled = false;
            progressBar.Value = 10;
            Log("=== INICIANDO INSTALACIÓN DE BLOGCMS ===");

            await Task.Run(() =>
            {
                try
                {
                    // 1. Auto-configure XAMPP php.ini (zip, gd, intl) and VirtualHost blogcms.localhost for any PC
                    EnsureXamppPhpExtensions();
                    EnsureXamppVirtualHost();

                    // 2. Ensure Apache is running with updated config
                    RestartOrStartApache();

                    Invoke(new Action(() => progressBar.Value = 30));

                    string sqlFile = Path.Combine(projectRoot, "database", "blogcms_wp.sql").Replace("\\", "/");
                    string rootDir = projectRoot.Replace("\\", "/");

                    if (engineType == "pgsql")
                    {
                        Log("Configurando modo PostgreSQL...");
                        WriteWpConfig(dbName, user, pass, host + ":" + port, "pgsql");
                        WriteHtaccess(siteUrl);
                        Log("wp-config.php y .htaccess generados con DB_DRIVER='pgsql'.");
                    }
                    else
                    {
                        Log(string.Format("Conectando a {0} en {1}:{2} y preparando BD '{3}'...", sel.Name, host, port, dbName));
                        string phpInstaller = string.Format(@"<?php
mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli('{0}', '{2}', '{3}', '', {1});
if ($m->connect_errno) {{
    echo 'FATAL: ' . $m->connect_error;
    exit(1);
}}
$m->set_charset('utf8mb4');
if (!$m->query('CREATE DATABASE IF NOT EXISTS `{4}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {{
    echo 'FATAL DB CREATE: ' . $m->error;
    exit(1);
}}
$m->select_db('{4}');
echo ""BD '{4}' lista en {0}:{1}.\n"";

if ({5}) {{
    $sqlFile = '{6}';
    if (!is_readable($sqlFile)) {{
        echo ""FATAL: No se encontro $sqlFile\n"";
        exit(1);
    }}
    $raw = file_get_contents($sqlFile);
    $clean = preg_replace('/^CREATE DATABASE .*?;\s*$/m', '', $raw);
    $clean = preg_replace('/^USE `[^`]+`;\s*$/m', '', $clean);
    if ($m->multi_query($clean)) {{
        do {{
            if ($r = $m->store_result()) {{ $r->free(); }}
        }} while ($m->more_results() && $m->next_result());
        if ($m->errno) {{
            echo 'FATAL SQL IMPORT: ' . $m->error;
            exit(1);
        }}
        echo ""Respaldo SQL importado exitosamente.\n"";
    }}
}}

$stmt = $m->prepare('UPDATE wp_options SET option_value = ? WHERE option_name = ?');
if ($stmt) {{
    $opts = [
        'siteurl' => '{7}',
        'home' => '{7}',
        'blogname' => '{8}',
        'template' => '{9}',
        'stylesheet' => '{9}'
    ];
    foreach ($opts as $k => $v) {{
        $stmt->bind_param('ss', $v, $k);
        $stmt->execute();
    }}
    $stmt->close();
    echo ""Opciones del sitio y tema activo ('{9}') actualizadas.\n"";
}}

if ('{7}' !== 'http://blogcms.localhost') {{
    $u_esc_url = $m->real_escape_string('{7}');
    $m->query(""UPDATE wp_posts SET post_content = REPLACE(post_content, 'http://blogcms.localhost', '$u_esc_url'), guid = REPLACE(guid, 'http://blogcms.localhost', '$u_esc_url')"");
}}

$u = $m->real_escape_string('{10}');
if ($u !== '') {{
    $m->query(""UPDATE wp_users SET user_login = '$u' WHERE ID = 1"");
}}
$p = '{11}';
if ($p !== '') {{
    $h = md5($p);
    $m->query(""UPDATE wp_users SET user_pass = '$h' WHERE ID = 1"");
    echo ""Clave de administrador actualizada.\n"";
}}
$m->close();
echo ""SUCCESS_ALL\n"";
",
                            host.Replace("'", ""),
                            port,
                            user.Replace("'", ""),
                            pass.Replace("\\", "\\\\").Replace("'", "\\'"),
                            dbName,
                            importSql ? "true" : "false",
                            sqlFile,
                            siteUrl.Replace("'", ""),
                            siteTitle.Replace("'", "\\'"),
                            themeChoice,
                            adminUser.Replace("'", ""),
                            adminPass.Replace("\\", "\\\\").Replace("'", "\\'")
                        );

                        string result = RunPhpScript(phpInstaller);
                        Log(result);
                        if (result.Contains("FATAL:"))
                        {
                            Invoke(new Action(() =>
                            {
                                btnInstall.Enabled = true;
                                MessageBox.Show(result, "Error durante la instalación", MessageBoxButtons.OK, MessageBoxIcon.Error);
                            }));
                            return;
                        }

                        Invoke(new Action(() => progressBar.Value = 80));
                        string hostPort = port == 3306 ? host : (host + ":" + port);
                        WriteWpConfig(dbName, user, pass, hostPort, "mysql");
                        WriteHtaccess(siteUrl);
                        Log("Archivos wp-config.php y .htaccess generados correctamente.");
                    }

                    Invoke(new Action(() =>
                    {
                        progressBar.Value = 100;
                        btnInstall.Enabled = true;
                        ScanEnvironment();
                        Log("=== INSTALACIÓN FINALIZADA CON ÉXITO ===");
                        MessageBox.Show(
                            "¡BlogCMS se ha configurado e instalado correctamente!\n\n" +
                            "Motor BD: " + sel.Name + " (" + host + ":" + port + ")\n" +
                            "Base de datos: " + dbName + "\n" +
                            "Tema activo: " + themeChoice + "\n" +
                            "URL: " + siteUrl,
                            "Instalación Completada",
                            MessageBoxButtons.OK,
                            MessageBoxIcon.Information);
                    }));
                }
                catch (Exception ex)
                {
                    Invoke(new Action(() =>
                    {
                        btnInstall.Enabled = true;
                        Log("Excepción: " + ex.Message);
                    }));
                }
            });
        }

        private void EnsureXamppPhpExtensions()
        {
            string phpIni = @"C:\xampp\php\php.ini";
            if (!File.Exists(phpIni)) return;
            try
            {
                string raw = File.ReadAllText(phpIni);
                string updated = raw
                    .Replace(";extension=zip", "extension=zip")
                    .Replace(";extension=gd", "extension=gd")
                    .Replace(";extension=intl", "extension=intl");
                if (updated != raw)
                {
                    File.WriteAllText(phpIni, updated);
                    Log("Extensiones zip, gd e intl habilitadas automáticamente en C:\\xampp\\php\\php.ini.");
                }
            }
            catch (Exception ex)
            {
                Log("Aviso php.ini: " + ex.Message);
            }
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
                    string block = "\r\n<VirtualHost *:80>\r\n" +
                        "    DocumentRoot \"C:/xampp/htdocs\"\r\n" +
                        "    ServerName localhost\r\n" +
                        "    <Directory \"C:/xampp/htdocs\">\r\n" +
                        "        Options Indexes FollowSymLinks Includes ExecCGI\r\n" +
                        "        AllowOverride All\r\n" +
                        "        Require all granted\r\n" +
                        "    </Directory>\r\n" +
                        "</VirtualHost>\r\n\r\n" +
                        "<VirtualHost *:80>\r\n" +
                        "    DocumentRoot \"" + normRoot + "\"\r\n" +
                        "    ServerName blogcms.localhost\r\n" +
                        "    ServerAlias blogcms.local\r\n" +
                        "    <Directory \"" + normRoot + "\">\r\n" +
                        "        Options Indexes FollowSymLinks Includes ExecCGI\r\n" +
                        "        AllowOverride All\r\n" +
                        "        Require all granted\r\n" +
                        "    </Directory>\r\n" +
                        "</VirtualHost>\r\n";
                    File.AppendAllText(vhostsPath, block);
                    Log("VirtualHost blogcms.localhost configurado en Apache apuntando a: " + normRoot);
                }
            }
            catch (Exception ex)
            {
                Log("Aviso VirtualHost: " + ex.Message);
            }
        }

        private void RestartOrStartApache()
        {
            string httpd = @"C:\xampp\apache\bin\httpd.exe";
            if (!File.Exists(httpd)) return;
            try
            {
                if (!IsTcpPortOpen("127.0.0.1", 80, 350))
                {
                    Log("Iniciando Apache de XAMPP...");
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = httpd,
                        Arguments = "-d C:/xampp/apache",
                        WorkingDirectory = @"C:\xampp\apache",
                        CreateNoWindow = true,
                        UseShellExecute = false
                    });
                }
            }
            catch { }
        }

        private string GenerateRandomHex(int bytes)
        {
            byte[] data = new byte[bytes];
            using (var rng = RandomNumberGenerator.Create())
            {
                rng.GetBytes(data);
            }
            StringBuilder sb = new StringBuilder(bytes * 2);
            foreach (byte b in data) sb.Append(b.ToString("x2"));
            return sb.ToString();
        }

        private void WriteWpConfig(string dbName, string dbUser, string dbPass, string dbHost, string dbDriver)
        {
            string[] keys = {
                "AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY",
                "AUTH_SALT", "SECURE_AUTH_SALT", "LOGGED_IN_SALT", "NONCE_SALT", "WP_SECRETS_KEY"
            };
            StringBuilder salts = new StringBuilder();
            foreach (string k in keys)
            {
                salts.AppendFormat("define( '{0}', '{1}' );\r\n", k, GenerateRandomHex(32));
            }

            string escPass = dbPass.Replace("\\", "\\\\").Replace("'", "\\'");
            string content = "<?php\r\n" +
                "/**\r\n * Configuracion base de BlogCMS (WordPress)\r\n * Generado por Instalador_BlogCMS.exe\r\n */\r\n\r\n" +
                string.Format("define( 'DB_NAME', '{0}' );\r\n", dbName) +
                string.Format("define( 'DB_USER', '{0}' );\r\n", dbUser) +
                string.Format("define( 'DB_PASSWORD', '{0}' );\r\n", escPass) +
                string.Format("define( 'DB_HOST', '{0}' );\r\n", dbHost) +
                string.Format("define( 'DB_DRIVER', '{0}' );\r\n", dbDriver) +
                "define( 'DB_CHARSET', 'utf8mb4' );\r\n" +
                "define( 'DB_COLLATE', '' );\r\n\r\n" +
                salts.ToString() + "\r\n" +
                "$table_prefix = 'wp_';\r\n\r\n" +
                "define( 'WP_DEBUG', false );\r\n" +
                "define( 'FS_METHOD', 'direct' );\r\n\r\n" +
                "if ( ! defined( 'ABSPATH' ) ) {\r\n\tdefine( 'ABSPATH', __DIR__ . '/' );\r\n}\r\n\r\n" +
                "require_once ABSPATH . 'wp-settings.php';\r\n";

            File.WriteAllText(Path.Combine(projectRoot, "wp-config.php"), content, new UTF8Encoding(false));
        }

        private void WriteHtaccess(string siteUrl)
        {
            string basePath = "/";
            try
            {
                Uri u = new Uri(siteUrl);
                if (!string.IsNullOrEmpty(u.AbsolutePath) && u.AbsolutePath != "/")
                {
                    basePath = u.AbsolutePath.TrimEnd('/') + "/";
                }
            }
            catch { }

            string rules = "# BEGIN WordPress\r\n" +
                "<IfModule mod_rewrite.c>\r\n" +
                "RewriteEngine On\r\n" +
                "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\r\n" +
                "RewriteBase " + basePath + "\r\n" +
                "RewriteRule ^index\\.php$ - [L]\r\n" +
                "RewriteCond %{REQUEST_FILENAME} !-f\r\n" +
                "RewriteCond %{REQUEST_FILENAME} !-d\r\n" +
                "RewriteRule . " + basePath + "index.php [L]\r\n" +
                "</IfModule>\r\n" +
                "# END WordPress\r\n";

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
