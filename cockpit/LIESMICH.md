# Warum hier keine .htaccess liegt

Der Passwortschutz für /cockpit/ wird im KAS unter „Verzeichnisschutz"
eingeschaltet. All-Inkl schreibt die Zugangszeilen dann in eine `.htaccess`
**auf dem Server**.

Diese Datei liegt bewusst **nicht** im Repository. Am 30.08.2026 hat der
FTP-Deploy die Server-Fassung überschrieben und den Schutz still entfernt —
das Protokoll zeigte `Removing old file 'cockpit/.htaccess'`. Ein Ausschluss
in der lftp-Zeile hat das nicht verhindert. Was zuverlässig hilft: Die Datei
gibt es hier nicht mehr, also kann der Deploy sie auch nicht übertragen.

Der Ablauf „Cockpit schuetzen" (.github/workflows/cockpit-schutz.yml) kann den
Schutz alternativ ohne KAS setzen — er braucht dafür das Repository-Secret
COCKPIT_PASSWORD.

Der `X-Robots-Tag`-Header, der Suchmaschinen aussperrt, steht seitdem
ebenfalls nur noch auf dem Server. Die Seite selbst trägt zusätzlich
`<meta name="robots" content="noindex, nofollow">`, das bleibt also wirksam.
